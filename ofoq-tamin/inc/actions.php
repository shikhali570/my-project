<?php
/**
 * پردازش همه عملیات‌های تغییردهنده داده (سبد، سفارش، پنل مدیریت و پنل خریدار)
 * تمام درخواست‌ها POST و محافظت‌شده با توکن CSRF هستند.
 */

$act = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $act !== '' && $act !== 'mellat_callback') {
    csrf_guard();
}

// وضعیت‌هایی که مدیر می‌تواند برای سفارش ثبت کند
$ORDER_STATUSES = array_keys(order_statuses());
$PAYMENT_STATUSES = array_keys(payment_statuses());
$RFQ_STATUSES = array_keys(rfq_statuses());

switch ($act) {

    // callback درگاه از سمت مرورگر/بانک می‌آید و به نشست یا CSRF سایت وابسته نیست؛
    // هویت پرداخت با شناسه تلاش، RefId و استعلام سروربه‌سرور تأیید می‌شود.
    case 'mellat_callback': {
        $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($requestMethod, ['GET', 'POST'], true)) {
            http_response_code(405);
            header('Allow: GET, POST');
            exit('روش درخواست مجاز نیست.');
        }
        $saleOrderId = en_digits($_POST['SaleOrderId'] ?? $_GET['SaleOrderId'] ?? '');
        $resCode = en_digits($_POST['ResCode'] ?? $_GET['ResCode'] ?? '');
        if (!ctype_digit($saleOrderId) || (int)$saleOrderId < 1 || !preg_match('/^[0-9]{1,4}$/', $resCode)) {
            http_response_code(400);
            exit('پاسخ درگاه معتبر نیست.');
        }
        $stmt = $db->prepare("SELECT pa.*, o.order_no, o.user_id, o.total, o.payment_status, o.status AS order_status
                              FROM payment_attempts pa JOIN orders o ON o.id = pa.order_id
                              WHERE pa.provider = 'mellat' AND pa.gateway_order_id = ? LIMIT 1");
        $stmt->execute([(int)$saleOrderId]);
        $payment = $stmt->fetch();
        if (!$payment || $payment['provider'] !== 'mellat') {
            http_response_code(404);
            exit('درخواست پرداخت یافت نشد.');
        }
        $orderId = (int)$payment['order_id'];
        $orderNo = (string)$payment['order_no'];
        $_SESSION['recent_order_id'] = $orderId;

        if ($payment['status'] === 'paid' || $payment['payment_status'] === 'paid') {
            flash('پرداخت این سفارش قبلاً تأیید شده است.', 'success');
            redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
        }

        if ($resCode !== '0') {
            $decline = $db->prepare("UPDATE payment_attempts SET status = 'declined', response_code = ?, credential_enc = NULL, updated_at = ? WHERE id = ? AND status = 'redirected'");
            $decline->execute([$resCode, date('Y-m-d H:i:s'), (int)$payment['id']]);
            if ($decline->rowCount() === 1) {
                log_action('mellat_payment_declined', 'order', $orderId, 'درگاه ملت پرداخت سفارش ' . $orderNo . ' را نپذیرفت (کد ' . $resCode . ')');
            }
            flash('پرداخت در درگاه تکمیل نشد. سفارش شما ثبت شده است و می‌توانید دوباره برای پرداخت اقدام کنید.', 'info');
            redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
        }

        $refId = trim((string)($_POST['RefId'] ?? $_GET['RefId'] ?? ''));
        $saleReferenceId = en_digits($_POST['SaleReferenceId'] ?? $_GET['SaleReferenceId'] ?? '');
        $finalAmount = en_digits($_POST['FinalAmount'] ?? $_GET['FinalAmount'] ?? '');
        if ($refId === '' || strlen($refId) > 100 || !is_string($payment['ref_id'])
            || !hash_equals((string)$payment['ref_id'], $refId)
            || !preg_match('/^[0-9]{1,30}$/', $saleReferenceId)
            || ($finalAmount !== '' && (!ctype_digit($finalAmount) || (int)$finalAmount !== (int)$payment['total'] * 10))) {
            http_response_code(400);
            exit('شناسه‌های پاسخ درگاه با درخواست پرداخت تطابق ندارند.');
        }
        $credentials = mellat_attempt_credentials($payment);
        if (!$credentials) {
            $db->prepare("UPDATE payment_attempts SET status = 'verification_pending', response_code = ?, updated_at = ? WHERE id = ? AND status != 'paid'")
                ->execute([$resCode, date('Y-m-d H:i:s'), (int)$payment['id']]);
            flash('پاسخ درگاه دریافت شد اما اعتبارنامهٔ امن برای تأیید در دسترس نیست؛ با پشتیبانی تماس بگیرید.', 'info');
            redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
        }

        try {
            $db->beginTransaction();
            $claim = $db->prepare("UPDATE payment_attempts SET status = 'verifying', updated_at = ?
                                   WHERE id = ? AND status IN ('redirected', 'verification_pending', 'verified', 'settle_pending')");
            $claim->execute([date('Y-m-d H:i:s'), (int)$payment['id']]);
            $claimed = $claim->rowCount() === 1;
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            flash('بررسی پرداخت موقتاً ممکن نیست؛ لطفاً چند لحظه دیگر دوباره تلاش کنید.', 'info');
            redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
        }
        if (!$claimed) {
            $latest = mellat_latest_attempt($db, $orderId);
            if ($latest && $latest['status'] === 'paid') {
                flash('پرداخت این سفارش قبلاً تأیید شده است.', 'success');
            } else {
                flash('پاسخ درگاه در حال بررسی است؛ لطفاً از ایجاد پرداخت تازه خودداری کنید و کمی بعد وضعیت را بررسی کنید.', 'info');
            }
            redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
        }

        $verifyArgs = [
            'terminalId' => (int)$credentials['terminal_id'],
            'userName' => $credentials['username'],
            'userPassword' => $credentials['password'],
            'orderId' => (int)$payment['gateway_order_id'],
            'saleOrderId' => (int)$payment['gateway_order_id'],
            'saleReferenceId' => $saleReferenceId,
        ];
        $verify = mellat_soap_call('bpVerifyRequest', $verifyArgs);
        if (empty($verify['ok'])) {
            $db->prepare("UPDATE payment_attempts SET status = 'verification_pending', response_code = ?, verify_code = ?, updated_at = ? WHERE id = ? AND status != 'paid'")
                ->execute([$resCode, 'transport_error', date('Y-m-d H:i:s'), (int)$payment['id']]);
            flash('پاسخ بانک دریافت شد؛ تأیید نهایی موقتاً در دسترس نیست. سفارش پرداخت‌شده ثبت نشده؛ لطفاً دوباره از صفحه سفارش اقدام کنید یا با پشتیبانی تماس بگیرید.', 'info');
            redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
        }
        $verifyCode = trim((string)$verify['value']);
        if (!in_array($verifyCode, ['0', '42'], true)) {
            $db->prepare("UPDATE payment_attempts SET status = 'verify_failed', response_code = ?, verify_code = ?, sale_reference_id = ?, credential_enc = NULL, updated_at = ? WHERE id = ? AND status != 'paid'")
                ->execute([$resCode, $verifyCode, $saleReferenceId, date('Y-m-d H:i:s'), (int)$payment['id']]);
            log_action('mellat_payment_verify_failed', 'order', $orderId, 'تأیید ملت برای سفارش ' . $orderNo . ' ناموفق بود (کد ' . $verifyCode . ')');
            flash('بانک نتوانست پرداخت را تأیید کند. سفارش شما ثبت شده و می‌توانید دوباره تلاش کنید.', 'error');
            redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
        }
        $db->prepare("UPDATE payment_attempts SET status = 'verified', response_code = ?, verify_code = ?, sale_reference_id = ?, updated_at = ? WHERE id = ? AND status != 'paid'")
            ->execute([$resCode, $verifyCode, $saleReferenceId, date('Y-m-d H:i:s'), (int)$payment['id']]);

        $settle = mellat_soap_call('bpSettleRequest', $verifyArgs);
        if (empty($settle['ok'])) {
            $db->prepare("UPDATE payment_attempts SET status = 'settle_pending', settle_code = ?, updated_at = ? WHERE id = ? AND status != 'paid'")
                ->execute(['transport_error', date('Y-m-d H:i:s'), (int)$payment['id']]);
            flash('پرداخت در انتظار تأیید نهایی درگاه است. سفارش پرداخت‌شده ثبت نشده؛ صفحه را کمی بعد دوباره بررسی کنید یا با پشتیبانی تماس بگیرید.', 'info');
            redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
        }
        $settleCode = trim((string)$settle['value']);
        if (!in_array($settleCode, ['0', '45'], true)) {
            $db->prepare("UPDATE payment_attempts SET status = 'settle_pending', settle_code = ?, updated_at = ? WHERE id = ? AND status != 'paid'")
                ->execute([$settleCode, date('Y-m-d H:i:s'), (int)$payment['id']]);
            log_action('mellat_payment_settle_pending', 'order', $orderId, 'تسویه ملت برای سفارش ' . $orderNo . ' در انتظار بررسی است (کد ' . $settleCode . ')');
            flash('بانک پرداخت را گرفته اما تسویهٔ نهایی را هنوز تأیید نکرده است؛ وضعیت سفارش فعلاً پرداخت‌نشده می‌ماند. با پشتیبانی تماس بگیرید.', 'info');
            redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
        }

        $now = date('Y-m-d H:i:s');
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE payment_attempts SET status = 'paid', response_code = ?, verify_code = ?, settle_code = ?, sale_reference_id = ?, credential_enc = NULL, updated_at = ?, paid_at = ? WHERE id = ? AND status != 'paid'")
                ->execute([$resCode, $verifyCode, $settleCode, $saleReferenceId, $now, $now, (int)$payment['id']]);
            $db->prepare("UPDATE orders SET payment_status = 'paid', updated_at = ? WHERE id = ? AND payment_status != 'paid'")
                ->execute([$now, $orderId]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            $db->prepare("UPDATE payment_attempts SET status = 'settle_pending', settle_code = ?, updated_at = ? WHERE id = ? AND status != 'paid'")
                ->execute(['database_error', $now, (int)$payment['id']]);
            flash('درگاه پرداخت را تأیید کرد اما ثبت نتیجه در سامانه با خطا روبه‌رو شد؛ لطفاً با پشتیبانی تماس بگیرید و شماره پیگیری بانک را ارائه کنید.', 'info');
            redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
        }
        if (!empty($payment['user_id'])) {
            notify((int)$payment['user_id'], 'پرداخت سفارش ' . $orderNo . ' تأیید شد', 'پرداخت آنلاین سفارش شما توسط به‌پرداخت ملت تأیید شد.', 'index.php?page=panel_order&no=' . $orderNo);
        }
        notify_admins('پرداخت آنلاین سفارش ' . $orderNo, 'پرداخت سفارش با درگاه به‌پرداخت ملت تأیید شد.', 'index.php?page=admin_order&id=' . $orderId);
        log_action('mellat_payment_paid', 'order', $orderId, 'پرداخت سفارش ' . $orderNo . ' با به‌پرداخت ملت تأیید شد؛ شماره مرجع ' . $saleReferenceId);
        flash('پرداخت شما با موفقیت تأیید شد.', 'success');
        redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
    }

    case 'rfq_attachment_download': {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            die('روش درخواست مجاز نیست.');
        }
        $rfqId = (int)get('rfq_id');
        if (!is_logged_in() && !rfq_guest_session_owns($rfqId)) {
            http_response_code(403);
            die('برای دریافت پیوست باید صاحب استعلام باشید یا وارد حساب کاربری شوید.');
        }
        $token = get('attachment');
        $stmt = $db->prepare('SELECT id, user_id, attachments_json FROM rfqs WHERE id = ?');
        $stmt->execute([$rfqId]);
        $rfq = $stmt->fetch();
        if (!$rfq) {
            http_response_code(404);
            die('استعلام یافت نشد.');
        }
        $accountOwner = !empty($rfq['user_id']) && (int)$rfq['user_id'] === user_id();
        $guestSessionOwner = empty($rfq['user_id']) && rfq_guest_session_owns($rfqId);
        if (!is_admin() && !$accountOwner && !$guestSessionOwner) {
            http_response_code(403);
            die('دسترسی به پیوست این استعلام مجاز نیست.');
        }

        $attachment = null;
        foreach (rfq_attachments_decode($rfq['attachments_json'] ?? '[]') as $candidate) {
            if (hash_equals($candidate['token'], $token)) {
                $attachment = $candidate;
                break;
            }
        }
        $path = $attachment ? rfq_attachment_resolve_path($attachment) : null;
        if ($path === null || !is_file($path) || !is_readable($path)) {
            http_response_code(404);
            die('پیوست یافت نشد.');
        }

        $downloadName = rfq_attachment_safe_name($attachment['name']);
        if ($downloadName === '') {
            $downloadName = 'attachment.' . $attachment['extension'];
        }
        header('Content-Type: application/octet-stream');
        header('X-Content-Type-Options: nosniff');
        $disposition = 'Content-Disposition: attachment; filename="attachment.' . $attachment['extension'] . '"; filename*=UTF-8' . chr(39) . chr(39) . rawurlencode($downloadName);
        header($disposition);
        header('Content-Length: ' . (string)filesize($path));
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        readfile($path);
        exit;
    }

    // ================================================== سبد خرید
    case 'add_cart': {
        $pid = (int)post('id', (string)get('id'));
        $qty = max(1, (int)en_digits(post('qty', '1')));
        $stmt = $db->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
        $stmt->execute([$pid]);
        $p = $stmt->fetch();

        if (!$p) {
            flash('کالای انتخابی یافت نشد.', 'error');
            redirect('index.php?page=home');
        }
        $inCart = (int)($_SESSION['cart'][$pid] ?? 0);
        if ($p['stock'] <= 0) {
            flash('این کالا در حال حاضر موجود نیست. می‌توانید استعلام قیمت ثبت کنید.', 'error');
            redirect('index.php?page=product&id=' . $pid);
        }
        if ($inCart + $qty > (int)$p['stock']) {
            $qty = max(1, (int)$p['stock'] - $inCart);
            flash('موجودی کافی نیست؛ حداکثر ' . fa_num($p['stock']) . ' ' . $p['unit'] . ' از این کالا قابل سفارش است.', 'error');
        }
        $_SESSION['cart'][$pid] = $inCart + $qty;
        flash('«' . $p['name'] . '» به سبد سفارش اضافه شد.', 'success');
        redirect(safe_local_url(post('redirect'), 'index.php?page=cart'));
    }

    case 'cart_update': {
        $pid = (int)post('id');
        $op = post('op');
        if (isset($_SESSION['cart'][$pid])) {
            $current = (int)$_SESSION['cart'][$pid];
            $stmt = $db->prepare('SELECT name, unit, stock FROM products WHERE id = ?');
            $stmt->execute([$pid]);
            $prod = $stmt->fetch();
            $stock = $prod ? (int)$prod['stock'] : 0;
            $name = $prod ? $prod['name'] : 'این کالا';

            if ($op === 'del') {
                unset($_SESSION['cart'][$pid]);
                flash('کالا از سبد سفارش حذف شد.', 'info');
            } elseif ($op === 'dec') {
                if ($current <= 1) {
                    unset($_SESSION['cart'][$pid]);
                    flash('کالا از سبد سفارش حذف شد.', 'info');
                } else {
                    $_SESSION['cart'][$pid] = $current - 1;
                }
            } elseif ($op === 'inc' || $op === 'set') {
                $rawQty = en_digits(post('qty', ''));
                // فیلد خالی یا غیرعددی یعنی «بدون تغییر»؛ صفر یعنی حذف کالا
                $wanted = $op === 'inc' ? $current + 1 : (ctype_digit($rawQty) ? (int)$rawQty : $current);
                if ($wanted <= 0) {
                    unset($_SESSION['cart'][$pid]);
                    flash('کالا از سبد سفارش حذف شد.', 'info');
                } elseif ($stock <= 0) {
                    flash('«' . $name . '» در حال حاضر ناموجود است؛ تعداد آن تغییر نکرد.', 'error');
                } elseif ($wanted > $stock) {
                    $_SESSION['cart'][$pid] = $stock;
                    flash('موجودی کافی نیست؛ حداکثر ' . fa_num($stock) . ' ' . $prod['unit'] . ' از «' . $name . '» قابل سفارش است.', 'error');
                } else {
                    $_SESSION['cart'][$pid] = $wanted;
                }
            }
        }
        redirect('index.php?page=cart');
    }

    case 'cart_clear': {
        $_SESSION['cart'] = [];
        flash('سبد سفارش خالی شد.', 'info');
        redirect('index.php?page=cart');
    }

    case 'cart_coupon': {
        $code = post('coupon_code');
        if ($code === '') {
            unset($_SESSION['coupon']);
            redirect('index.php?page=cart');
        }
        $coupon = coupon_find($db, $code);
        $totals = cart_totals($db);
        $err = coupon_error($coupon, $totals['subtotal']);
        if ($err) {
            unset($_SESSION['coupon']);
            flash($err, 'error');
        } else {
            $_SESSION['coupon'] = $coupon['code'];
            flash('کد تخفیف «' . $coupon['code'] . '» اعمال شد.', 'success');
        }
        redirect('index.php?page=cart');
    }

    // ================================================== ثبت سفارش
    case 'checkout': {
        $totals = cart_totals($db, $_SESSION['coupon'] ?? null);
        if (!$totals['items']) {
            flash('سبد سفارش خالی است.', 'error');
            redirect('index.php?page=cart');
        }

        // ورودی‌ها را نگه می‌داریم تا پس از خطا، کاربر دوباره تایپ نکند
        $input = [
            'customer_name' => post('customer_name'),
            'company' => post('company'),
            'phone' => post('phone'),
            'tax_id' => post('tax_id'),
            'postal_code' => post('postal_code'),
            'province' => post('province'),
            'city' => post('city'),
            'address' => post('address'),
            'payment_method' => post('payment_method'),
            'note' => post('note'),
        ];
        $customer = $input['customer_name'];
        $company = $input['company'];
        $phone = en_digits($input['phone']);
        $taxId = en_digits($input['tax_id']);
        $province = $input['province'];
        $city = $input['city'];
        $address = $input['address'];
        $note = $input['note'];
        $email = post('email');
        $u = current_user();
        $entityType = $u && in_array(($u['entity_type'] ?? ''), ['individual', 'legal'], true)
            ? $u['entity_type']
            : ($u && !empty($u['company']) ? 'legal' : null);
        if ($entityType === 'individual') {
            $company = '';
        } elseif ($entityType === 'legal' && $company === '' && $u) {
            $company = (string)($u['company'] ?? '');
        }
        if ($taxId === '' && $u) {
            $taxId = ($entityType === 'legal' && !empty($u['economic_code']))
                ? en_digits($u['economic_code'])
                : en_digits($u['national_id'] ?? '');
        }
        $postalCode = en_digits($input['postal_code'] !== '' ? $input['postal_code'] : ($u['postal_code'] ?? ''));
        $buyerNationalId = $u ? en_digits($u['national_id'] ?? '') : '';
        $buyerEconomicCode = $entityType === 'legal' && $u ? en_digits($u['economic_code'] ?? '') : '';

        // فقط روش‌های شناخته‌شده پذیرفته می‌شوند؛ درگاه ملت در سمت سرور هم کنترل می‌شود.
        $paymentMethod = in_array($input['payment_method'], ['transfer', 'wallet', 'mellat'], true)
            ? $input['payment_method']
            : 'transfer';

        $errors = [];
        if (mb_strlen($customer) < 3) {
            $errors['customer_name'] = 'نام رابط خرید را کامل وارد کنید (حداقل ۳ حرف).';
        }
        if (!valid_phone($phone)) {
            $errors['phone'] = 'شماره همراه معتبر نیست؛ ۱۱ رقم و با ۰۹ شروع شود.';
        }
        if ($entityType === 'legal' && mb_strlen($company) < 2) {
            $errors['company'] = 'نام شرکت یا مؤسسه را کامل وارد کنید.';
        }
        if (mb_strlen($address) < 10) {
            $errors['address'] = 'نشانی تحویل را کامل‌تر وارد کنید (شامل خیابان و پلاک).';
        }
        $postalCodeRequired = in_array($entityType, ['individual', 'legal'], true);
        if (($postalCodeRequired && $postalCode === '') || ($postalCode !== '' && !preg_match('/^[0-9]{10}$/', $postalCode))) {
            $errors['postal_code'] = 'کد پستی باید ۱۰ رقم باشد.';
        }
        if ($paymentMethod === 'wallet') {
            if (!$u) {
                $errors['payment_method'] = 'برای پرداخت از اعتبار کارپوشه، ابتدا وارد حساب خریدار شوید.';
            } elseif ((int)$u['credit'] < $totals['total']) {
                $errors['payment_method'] = 'اعتبار کارپوشه شما (' . money($u['credit']) . ') برای پرداخت این سفارش کافی نیست.';
            }
        } elseif ($paymentMethod === 'mellat' && !mellat_gateway_status()['ready']) {
            $errors['payment_method'] = 'پرداخت آنلاین موقتاً آماده نیست؛ روش دیگری انتخاب کنید یا با پشتیبانی تماس بگیرید.';
        }

        // کنترل موجودی انبار
        foreach ($totals['items'] as $it) {
            if ($it['qty'] > (int)$it['stock']) {
                $errors['stock_' . $it['id']] = 'موجودی «' . $it['name'] . '» کافی نیست (موجودی فعلی: ' . fa_num($it['stock']) . ' ' . $it['unit'] . ').';
            }
        }

        if ($errors) {
            remember_form('checkout', $input, $errors);
            flash('ثبت سفارش انجام نشد؛ لطفاً موارد مشخص‌شده را اصلاح کنید.', 'error');
            redirect('index.php?page=cart#checkout');
        }

        $orderNo = next_order_no($db);
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare('INSERT INTO orders (order_no, user_id, entity_type, buyer_national_id, buyer_economic_code, customer_name, company, phone, email, tax_id, postal_code, province, city, address, note, subtotal, discount, tax_amount, shipping, total, coupon_code, status, payment_status, payment_method, created_at, updated_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $orderNo, $u ? (int)$u['id'] : null, $entityType, $buyerNationalId ?: null, $buyerEconomicCode ?: null,
            $customer, $company ?: null, $phone, $email ?: null, $taxId ?: null,
            $postalCode ?: null, $province ?: null, $city ?: null, $address, $note ?: null,
            $totals['subtotal'], $totals['discount'], $totals['tax'], $totals['shipping'], $totals['total'],
            $totals['coupon']['code'] ?? null, 'pending', 'unpaid', $paymentMethod, $now, $now,
        ]);
        $orderId = (int)$db->lastInsertId();

        $stmtItem = $db->prepare('INSERT INTO order_items (order_id, product_id, name, brand, tax_id, price, qty, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmtStock = $db->prepare('UPDATE products SET stock = stock - ?, sold = sold + ? WHERE id = ?');
        $itemsJson = [];
        foreach ($totals['items'] as $it) {
            $stmtItem->execute([$orderId, $it['id'], $it['name'], $it['brand'], $it['tax_id'], $it['price'], $it['qty'], $it['line_total']]);
            $stmtStock->execute([$it['qty'], $it['qty'], $it['id']]);
            $itemsJson[] = ['name' => $it['name'], 'brand' => $it['brand'], 'tax_id' => $it['tax_id'], 'unit' => (string)($it['unit'] ?? ''), 'price' => (int)$it['price'], 'qty' => (int)$it['qty'], 'total' => (int)$it['line_total']];
        }

        // مصرف کد تخفیف
        if (!empty($totals['coupon'])) {
            $db->prepare('UPDATE coupons SET used = used + 1 WHERE id = ?')->execute([$totals['coupon']['id']]);
        }

        // صدور خودکار صورتحساب الکترونیکی
        $stmt = $db->prepare('INSERT INTO invoices (invoice_no, tax_unique_id, order_id, user_id, buyer_name, buyer_phone, buyer_tax_id, subtotal, tax_amount, total_amount, items_json, created_at, status)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            next_invoice_no($db), gen_tax_unique_id(), $orderId, $u ? (int)$u['id'] : null,
            $company ?: $customer, $phone, $taxId ?: null,
            $totals['subtotal'], $totals['tax'], $totals['total'],
            json_encode($itemsJson, JSON_UNESCAPED_UNICODE), $now, 'ثبت قطعی در سامانه مؤدیان',
        ]);

        $_SESSION['cart'] = [];
        unset($_SESSION['coupon'], $_SESSION['form_state']['checkout']);

        if ($u) {
            notify($u['id'], 'سفارش ' . $orderNo . ' ثبت شد', 'سفارش شما با مبلغ ' . money($totals['total']) . ' ثبت شد و صورتحساب الکترونیکی آن صادر گردید. وضعیت آماده‌سازی را از پنل پیگیری کنید.', 'index.php?page=panel_order&no=' . $orderNo);
        }
        notify_admins('سفارش جدید ' . $orderNo, ($company ?: $customer) . ' سفارشی به مبلغ ' . money($totals['total']) . ' ثبت کرد.', 'index.php?page=admin_order&id=' . $orderId);
        log_action('order_create', 'order', $orderId, 'ثبت سفارش ' . $orderNo . ' به مبلغ ' . $totals['total'] . ' تومان');
        $_SESSION['recent_order_id'] = $orderId;

        if ($paymentMethod === 'mellat') {
            $paymentStart = mellat_start_order_payment($db, ['id' => $orderId, 'order_no' => $orderNo, 'total' => $totals['total']]);
            if (!empty($paymentStart['ok'])) {
                log_action('mellat_payment_start', 'order', $orderId, 'آغاز پرداخت آنلاین سفارش ' . $orderNo . ' در به‌پرداخت ملت');
                mellat_redirect_to_bank($paymentStart['ref_id']);
            }
            flash('سفارش ' . $orderNo . ' ثبت شد، اما شروع پرداخت آنلاین کامل نشد. ' . ($paymentStart['message'] ?? 'می‌توانید از صفحه سفارش دوباره تلاش کنید.'), 'info');
            redirect('index.php?page=order_success&no=' . rawurlencode($orderNo));
        }

        flash('سفارش شما با شماره ' . $orderNo . ' ثبت شد و صورتحساب الکترونیکی صادر گردید.', 'success');
        redirect('index.php?page=order_success&no=' . $orderNo);
    }

    case 'buyer_cancel_order': {
        require_buyer();
        $id = (int)post('id');
        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, user_id()]);
        $order = $stmt->fetch();
        if (!$order) {
            flash('سفارش یافت نشد.', 'error');
            redirect('index.php?page=panel_orders');
        }
        if (!in_array($order['status'], ['pending', 'approved'], true)) {
            flash('در این مرحله امکان لغو سفارش وجود ندارد. با پشتیبانی تماس بگیرید.', 'error');
            redirect('index.php?page=panel_order&no=' . $order['order_no']);
        }
        $db->prepare("UPDATE orders SET status = 'canceled', updated_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s'), $id]);
        notify_admins('لغو سفارش ' . $order['order_no'], 'خریدار سفارش را لغو کرد.', 'index.php?page=admin_order&id=' . $id);
        log_action('order_cancel', 'order', $id, 'لغو سفارش ' . $order['order_no'] . ' توسط خریدار');
        flash('سفارش ' . $order['order_no'] . ' لغو شد.', 'info');
        redirect('index.php?page=panel_order&no=' . $order['order_no']);
    }

    // ================================================== علاقه‌مندی‌ها
    case 'favorite_toggle': {
        require_buyer();
        $pid = (int)post('id');
        if (is_favorite($pid)) {
            $db->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?')->execute([user_id(), $pid]);
            flash('کالا از علاقه‌مندی‌ها حذف شد.', 'info');
        } else {
            $db->prepare('INSERT INTO favorites (user_id, product_id, created_at) VALUES (?, ?, ?)')->execute([user_id(), $pid, date('Y-m-d H:i:s')]);
            flash('کالا به علاقه‌مندی‌ها اضافه شد.', 'success');
        }
        redirect(safe_local_url(post('redirect'), 'index.php?page=panel_favorites'));
    }

    // ================================================== استعلام قیمت
    case 'rfq_submit': {
        $rawItems = $_POST['items'] ?? [];
        $input = [
            'company' => post('company'),
            'phone' => post('phone'),
            'email' => post('email'),
            'messenger' => post('messenger'),
            'title' => post('title'),
            'items' => rfq_form_item_rows($rawItems),
        ];
        $company = $input['company'];
        $phone = en_digits($input['phone']);
        $email = $input['email'];
        $messenger = $input['messenger'];
        $title = $input['title'];
        $returnPage = post('return_to') === 'panel_rfqs' && is_buyer() ? 'panel_rfqs' : 'rfq';
        $errors = [];

        if (mb_strlen($company, 'UTF-8') < 3 || mb_strlen($company, 'UTF-8') > 180) {
            $errors['company'] = 'نام شرکت یا پیمانکار را کامل وارد کنید (۳ تا ۱۸۰ نویسه).';
        }
        if (!valid_phone($phone)) {
            $errors['phone'] = 'شماره همراه معتبر نیست؛ ۱۱ رقم و با ۰۹ شروع شود.';
        }
        if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'یک نشانی ایمیل معتبر وارد کنید.';
        }
        if (mb_strlen($messenger, 'UTF-8') > 120) {
            $errors['messenger'] = 'اطلاعات پیام‌رسان نباید بیش از ۱۲۰ نویسه باشد.';
        }
        if (mb_strlen($title, 'UTF-8') > 180) {
            $errors['title'] = 'عنوان درخواست نباید بیش از ۱۸۰ نویسه باشد.';
        }

        $itemError = null;
        $items = rfq_validate_items($rawItems, categories(), $itemError);
        if ($itemError !== null) {
            $errors['items'] = $itemError;
        }
        $attachmentCheck = rfq_attachment_uploads_inspect($_FILES['attachments'] ?? null);
        if ($attachmentCheck['error'] !== null) {
            $errors['attachments'] = $attachmentCheck['error'];
        }

        if ($errors) {
            if ($attachmentCheck['files'] && empty($errors['attachments'])) {
                $errors['attachment_notice'] = 'پس از ارسال ناموفق، فایل‌های انتخاب‌شده حفظ نمی‌شوند؛ بعد از اصلاح خطاها دوباره آن‌ها را انتخاب کنید.';
            }
            remember_form('rfq', $input, $errors);
            flash('لطفاً موارد مشخص‌شده را اصلاح کنید.', 'error');
            redirect('index.php?page=' . $returnPage);
        }

        $attachmentResult = rfq_attachment_uploads_commit($attachmentCheck['files']);
        if ($attachmentResult['error'] !== null) {
            $errors['attachments'] = $attachmentResult['error'];
            remember_form('rfq', $input, $errors);
            flash('پیوست ذخیره نشد؛ لطفاً دوباره تلاش کنید.', 'error');
            redirect('index.php?page=' . $returnPage);
        }

        $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $attachmentsJson = json_encode($attachmentResult['files'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($itemsJson === false || $attachmentsJson === false) {
            rfq_attachments_delete($attachmentResult['files']);
            remember_form('rfq', $input, ['items' => 'ذخیره اطلاعات استعلام ناموفق بود؛ لطفاً دوباره تلاش کنید.']);
            flash('ذخیره اطلاعات استعلام ناموفق بود.', 'error');
            redirect('index.php?page=' . $returnPage);
        }

        $code = gen_rfq_code($db);
        $description = rfq_items_summary($items);
        try {
            $db->beginTransaction();
            $stmt = $db->prepare('INSERT INTO rfqs (rfq_code, user_id, company, phone, email, messenger, title, description, items_json, attachments_json, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $code,
                user_id() ?: null,
                $company,
                $phone,
                $email,
                $messenger !== '' ? $messenger : null,
                $title !== '' ? $title : null,
                $description,
                $itemsJson,
                $attachmentsJson,
                'new',
                date('Y-m-d H:i:s'),
            ]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            rfq_attachments_delete($attachmentResult['files']);
            throw $exception;
        }

        $rfqId = (int)$db->lastInsertId();
        if (!is_logged_in()) {
            rfq_guest_session_grant($rfqId);
        }
        if (is_buyer()) {
            notify(user_id(), 'استعلام ' . $code . ' ثبت شد', 'کارشناسان فروش تا حداکثر ۲۴ ساعت کاری قیمت سازمانی را اعلام می‌کنند.', 'index.php?page=panel_rfqs');
        }
        notify_admins('استعلام جدید ' . $code, $company . ' درخواست قیمت ثبت کرد.', 'index.php?page=admin_rfqs');
        log_action('rfq_create', 'rfq', $rfqId, 'ثبت استعلام ' . $code);

        flash('استعلام شما با کد پیگیری ' . $code . ' ثبت شد. پاسخ قیمت در پنل خریدار قابل مشاهده است.', 'success');
        redirect('index.php?page=' . $returnPage . '&done=' . urlencode($code));
    }

    // ================================================== اعلان‌ها
    case 'notify_read':
    case 'notify_read_all': {
        require_login();
        if ($act === 'notify_read_all') {
            $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([user_id()]);
        } else {
            $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([(int)get('id'), user_id()]);
        }
        redirect(get('back') ?: 'index.php?page=' . (is_admin() ? 'admin_notifications' : 'panel_notifications'));
    }

    // ================================================== مدیریت: کالاها
    case 'product_save': {
        require_admin();
        $id = (int)post('id');
        $existing = null;
        if ($id > 0) {
            $found = $db->prepare('SELECT * FROM products WHERE id = ?');
            $found->execute([$id]);
            $existing = $found->fetch();
            if (!$existing) {
                flash('کالا یافت نشد.', 'error');
                redirect('index.php?page=admin_products');
            }
        }
        $formUrl = 'index.php?page=admin_product_form' . ($id ? '&id=' . $id : '');
        $data = [
            'sku' => post('sku'),
            'name' => post('name'),
            'category' => post('category'),
            'brand' => post('brand'),
            'price' => (int)en_digits(post('price', '0')),
            'old_price' => (int)en_digits(post('old_price', '0')),
            'tax_id' => en_digits(post('tax_id')),
            'unit' => post('unit', 'عدد'),
            'stock' => (int)en_digits(post('stock', '0')),
            'min_stock' => (int)en_digits(post('min_stock', '5')),
            'icon' => post('icon', '📦'),
            'image' => post('image'),
            'description' => post('description'),
            'specs' => post('specs'),
            'is_active' => post('is_active') === '1' ? 1 : 0,
        ];
        $removeImage = post('remove_image') === '1';
        $inspected = product_image_inspect($_FILES['image_file'] ?? null);

        // اعتبارسنجی همه‌ی فیلدها پیش از ذخیره فایل؛ خطا = فرم با ورودی‌های قبلی برمی‌گردد
        $errors = [];
        if (mb_strlen($data['name']) < 5 || $data['price'] <= 0 || $data['tax_id'] === '') {
            $errors['form'] = 'نام کالا، قیمت و شناسه کالای مالیاتی الزامی است.';
        }
        if ($data['image'] !== '') {
            if (product_image_src($data['image']) === '') {
                $errors['image'] = 'نشانی تصویر باید با http:// یا https:// شروع شود (یا فایل را آپلود کنید).';
            } elseif (preg_match(PRODUCT_IMAGE_PATH_PATTERN, $data['image']) && !is_file(APP_ROOT . '/' . $data['image'])) {
                $errors['image'] = 'فایل تصویر با این نشانی روی سرور پیدا نشد.';
            }
        }
        if ($inspected['error'] !== null) {
            $errors['image_file'] = $inspected['error'];
        }
        if ($errors) {
            remember_form('product', $data + ['remove_image' => $removeImage ? '1' : ''], $errors);
            flash(reset($errors), 'error');
            redirect($formUrl);
        }

        // تصویر جدید > حذف تصویر > نشانی (URL) فعلی فرم
        $stored = product_image_commit($inspected);
        if ($stored['error'] !== null) {
            remember_form('product', $data + ['remove_image' => $removeImage ? '1' : ''], ['image_file' => $stored['error']]);
            flash($stored['error'], 'error');
            redirect($formUrl);
        }
        if ($stored['path'] !== null) {
            $imageValue = $stored['path'];
        } elseif ($removeImage) {
            $imageValue = null;
        } else {
            $imageValue = $data['image'] !== '' ? $data['image'] : null;
        }
        $oldImage = $existing['image'] ?? null;

        if ($id > 0) {
            $row = $data;
            $row['image'] = $imageValue;
            $row['old_price'] = $data['old_price'] ?: null;
            $sql = 'UPDATE products SET sku=?, name=?, category=?, brand=?, price=?, old_price=?, tax_id=?, unit=?, stock=?, min_stock=?, icon=?, image=?, description=?, specs=?, is_active=? WHERE id=?';
            $stmt = $db->prepare($sql);
            $stmt->execute([...array_values($row), $id]);
            if ($oldImage !== $imageValue) {
                product_image_remove($oldImage);
            }
            log_action('product_update', 'product', $id, 'ویرایش کالا: ' . $data['name'] . ($stored['path'] !== null ? ' (تصویر جدید آپلود شد)' : ''));
            flash('کالا با موفقیت به‌روزرسانی شد.', 'success');
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $stmt = $db->prepare('INSERT INTO products (sku, name, category, brand, price, old_price, tax_id, unit, stock, min_stock, icon, image, description, specs, is_active, created_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $data['sku'] = $data['sku'] ?: 'SKU-' . random_int(1000, 9999);
            $stmt->execute([$data['sku'], $data['name'], $data['category'], $data['brand'], $data['price'], $data['old_price'] ?: null, $data['tax_id'], $data['unit'], $data['stock'], $data['min_stock'], $data['icon'], $imageValue, $data['description'], $data['specs'], $data['is_active'], $data['created_at']]);
            $id = (int)$db->lastInsertId();
            log_action('product_create', 'product', $id, 'افزودن کالای جدید: ' . $data['name']);
            flash('کالای جدید با موفقیت به کاتالوگ اضافه شد.', 'success');
        }
        redirect('index.php?page=admin_products');
    }

    case 'product_delete': {
        require_admin();
        $id = (int)post('id');
        $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$p) {
            flash('کالا یافت نشد.', 'error');
            redirect('index.php?page=admin_products');
        }
        $used = $db->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?');
        $used->execute([$id]);
        if ((int)$used->fetchColumn() > 0) {
            $db->prepare('UPDATE products SET is_active = 0 WHERE id = ?')->execute([$id]);
            log_action('product_disable', 'product', $id, 'غیرفعال‌سازی کالا (به دلیل سابقه سفارش): ' . $p['name']);
            flash('این کالا در سفارش‌های ثبت‌شده استفاده شده است؛ به‌جای حذف، از کاتالوگ غیرفعال شد.', 'info');
        } else {
            $db->prepare('DELETE FROM favorites WHERE product_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            product_image_remove($p['image'] ?? null);
            log_action('product_delete', 'product', $id, 'حذف کالا: ' . $p['name']);
            flash('کالا حذف شد.', 'success');
        }
        redirect('index.php?page=admin_products');
    }

    case 'product_toggle': {
        require_admin();
        $id = (int)post('id');
        $db->prepare('UPDATE products SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$id]);
        log_action('product_toggle', 'product', $id, 'تغییر وضعیت نمایش کالا در کاتالوگ');
        flash('وضعیت نمایش کالا تغییر کرد.', 'success');
        redirect(post('redirect') ?: 'index.php?page=admin_products');
    }

    case 'stock_update': {
        require_admin();
        $id = (int)post('id');
        $delta = (int)en_digits(post('delta', '0'));
        $set = post('set');
        if ($set !== '') {
            $db->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([max(0, (int)en_digits($set)), $id]);
        } else {
            $db->prepare('UPDATE products SET stock = MAX(0, stock + ?) WHERE id = ?')->execute([$delta, $id]);
        }
        log_action('stock_update', 'product', $id, 'اصلاح موجودی انبار');
        flash('موجودی انبار به‌روزرسانی شد.', 'success');
        redirect(post('redirect') ?: 'index.php?page=admin_products');
    }

    // ================================================== مدیریت: گروه کالا
    case 'category_save': {
        require_admin();
        $id = (int)post('id');
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', post('slug')));
        $title = post('title');
        $icon = post('icon', '📦');
        $sort = (int)en_digits(post('sort_order', '0'));
        $active = post('is_active') === '1' ? 1 : 0;

        if ($slug === '' || mb_strlen($title) < 2) {
            flash('عنوان و شناسه لاتین گروه کالا الزامی است.', 'error');
            redirect('index.php?page=admin_categories');
        }
        if ($id > 0) {
            $db->prepare('UPDATE categories SET slug=?, title=?, icon=?, sort_order=?, is_active=? WHERE id=?')->execute([$slug, $title, $icon, $sort, $active, $id]);
            log_action('category_update', 'category', $id, 'ویرایش گروه کالا: ' . $title);
            flash('گروه کالا به‌روزرسانی شد.', 'success');
        } else {
            try {
                $db->prepare('INSERT INTO categories (slug, title, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?)')->execute([$slug, $title, $icon, $sort, $active]);
                log_action('category_create', 'category', (int)$db->lastInsertId(), 'افزودن گروه کالا: ' . $title);
                flash('گروه کالای جدید ایجاد شد.', 'success');
            } catch (PDOException $e) {
                flash('شناسه لاتین تکراری است؛ مقدار دیگری وارد کنید.', 'error');
            }
        }
        redirect('index.php?page=admin_categories');
    }

    case 'category_delete': {
        require_admin();
        $id = (int)post('id');
        $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $cat = $stmt->fetch();
        if ($cat) {
            $cnt = $db->prepare('SELECT COUNT(*) FROM products WHERE category = ?');
            $cnt->execute([$cat['slug']]);
            if ((int)$cnt->fetchColumn() > 0) {
                flash('این گروه دارای کالای فعال است؛ ابتدا کالاها را منتقل یا حذف کنید.', 'error');
            } else {
                $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
                log_action('category_delete', 'category', $id, 'حذف گروه کالا: ' . $cat['title']);
                flash('گروه کالا حذف شد.', 'success');
            }
        }
        redirect('index.php?page=admin_categories');
    }

    // ================================================== مدیریت: سفارش‌ها
    case 'order_update': {
        require_admin();
        $id = (int)post('id');
        $status = post('status');
        $payment = post('payment_status');
        $tracking = en_digits(post('tracking_code'));
        $adminNote = post('admin_note');

        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) {
            flash('سفارش یافت نشد.', 'error');
            redirect('index.php?page=admin_orders');
        }

        $status = in_array($status, $ORDER_STATUSES, true) ? $status : $order['status'];
        $payment = in_array($payment, $PAYMENT_STATUSES, true) ? $payment : $order['payment_status'];

        $db->prepare('UPDATE orders SET status = ?, payment_status = ?, tracking_code = ?, admin_note = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, $payment, $tracking ?: null, $adminNote ?: null, date('Y-m-d H:i:s'), $id]);

        if ($order['status'] !== $status) {
            notify($order['user_id'], 'تغییر وضعیت سفارش ' . $order['order_no'],
                'وضعیت سفارش شما به «' . order_status_label($status) . '» تغییر یافت.' . ($tracking ? ' کد رهگیری مرسوله: ' . $tracking : ''),
                'index.php?page=panel_order&no=' . $order['order_no']);
        }
        if ($order['payment_status'] !== $payment && $payment === 'paid') {
            notify($order['user_id'], 'پرداخت سفارش ' . $order['order_no'] . ' تأیید شد', 'پرداخت شما به مبلغ ' . money($order['total']) . ' تأیید و در کارپوشه ثبت شد.', 'index.php?page=panel_order&no=' . $order['order_no']);
        }
        log_action('order_update', 'order', $id, 'به‌روزرسانی سفارش ' . $order['order_no'] . ' به وضعیت ' . order_status_label($status));
        flash('سفارش به‌روزرسانی شد.', 'success');
        redirect('index.php?page=admin_order&id=' . $id);
    }

    case 'order_issue_invoice': {
        require_admin();
        $id = (int)post('id');
        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) {
            flash('سفارش یافت نشد.', 'error');
            redirect('index.php?page=admin_orders');
        }
        $exists = $db->prepare('SELECT tax_unique_id FROM invoices WHERE order_id = ?');
        $exists->execute([$id]);
        $found = $exists->fetchColumn();
        if ($found) {
            flash('صورتحساب این سفارش قبلاً صادر شده است.', 'info');
            redirect('index.php?page=invoice&id=' . $found);
        }

        $items = $db->prepare('SELECT oi.*, p.unit AS unit FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
        $items->execute([$id]);
        $rows = [];
        foreach ($items->fetchAll() as $it) {
            $rows[] = ['name' => $it['name'], 'brand' => $it['brand'], 'tax_id' => $it['tax_id'], 'unit' => (string)($it['unit'] ?? ''), 'price' => (int)$it['price'], 'qty' => (int)$it['qty'], 'total' => (int)$it['total']];
        }
        $taxUid = gen_tax_unique_id();
        $db->prepare('INSERT INTO invoices (invoice_no, tax_unique_id, order_id, user_id, buyer_name, buyer_phone, buyer_tax_id, subtotal, tax_amount, total_amount, items_json, created_at, status)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                next_invoice_no($db), $taxUid, $id, $order['user_id'],
                $order['company'] ?: $order['customer_name'], $order['phone'], $order['tax_id'],
                $order['subtotal'], $order['tax_amount'], $order['total'],
                json_encode($rows, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s'), 'ثبت قطعی در سامانه مؤدیان',
            ]);
        notify($order['user_id'], 'صورتحساب الکترونیکی صادر شد', 'صورتحساب سفارش ' . $order['order_no'] . ' با شناسه یکتای مالیاتی در کارپوشه مؤدیان ثبت شد.', 'index.php?page=panel_invoices');
        log_action('invoice_issue', 'order', $id, 'صدور صورتحساب الکترونیکی برای سفارش ' . $order['order_no']);
        flash('صورتحساب الکترونیکی صادر شد.', 'success');
        redirect('index.php?page=invoice&id=' . $taxUid);
    }

    case 'order_delete': {
        require_admin();
        $id = (int)post('id');
        $stmt = $db->prepare('SELECT order_no FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $no = $stmt->fetchColumn();
        $db->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM invoices WHERE order_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
        log_action('order_delete', 'order', $id, 'حذف سفارش ' . $no);
        flash('سفارش ' . $no . ' حذف شد.', 'success');
        redirect('index.php?page=admin_orders');
    }

    // ================================================== مدیریت: کاربران
    case 'user_save': {
        require_admin();
        $id = (int)post('id');
        $name = post('name');
        $company = post('company');
        $phone = en_digits(post('phone'));
        $email = post('email');
        $role = post('role') === 'admin' ? 'admin' : 'buyer';
        $status = post('status') === 'inactive' ? 'inactive' : 'active';
        $nationalId = en_digits(post('national_id'));
        $economicCode = en_digits(post('economic_code'));
        $postalCode = en_digits(post('postal_code'));
        $province = post('province');
        $city = post('city');
        $address = post('address');
        $credit = (int)en_digits(post('credit', '0'));
        $notes = post('notes');
        $password = (string)($_POST['password'] ?? '');

        if (mb_strlen($name) < 3 || !valid_phone($phone)) {
            flash('نام و شماره همراه معتبر الزامی است.', 'error');
            redirect('index.php?page=admin_user_form' . ($id ? '&id=' . $id : ''));
        }

        $dup = $db->prepare('SELECT id FROM users WHERE phone = ? AND id != ?');
        $dup->execute([$phone, $id]);
        if ($dup->fetchColumn()) {
            flash('کاربر دیگری با این شماره همراه ثبت شده است.', 'error');
            redirect('index.php?page=admin_user_form' . ($id ? '&id=' . $id : ''));
        }

        if ($id > 0) {
            $db->prepare('UPDATE users SET name=?, company=?, phone=?, email=?, role=?, status=?, national_id=?, economic_code=?, postal_code=?, province=?, city=?, address=?, credit=?, notes=? WHERE id=?')
                ->execute([$name, $company ?: null, $phone, $email ?: null, $role, $status, $nationalId ?: null, $economicCode ?: null, $postalCode ?: null, $province ?: null, $city ?: null, $address ?: null, $credit, $notes ?: null, $id]);
            if ($password !== '') {
                $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                notify($id, 'گذرواژه حساب شما بازنشانی شد', 'کارشناس پشتیبانی گذرواژه جدیدی برای حساب شما تنظیم کرد.', 'index.php?page=login');
            }
            log_action('user_update', 'user', $id, 'ویرایش حساب کاربری ' . $name);
            flash('حساب کاربری به‌روزرسانی شد.', 'success');
        } else {
            if (strlen($password) < 6) {
                flash('برای کاربر جدید گذرواژه حداقل ۶ کاراکتری تعیین کنید.', 'error');
                redirect('index.php?page=admin_user_form');
            }
            $db->prepare('INSERT INTO users (role, name, company, phone, email, password_hash, national_id, economic_code, postal_code, province, city, address, credit, status, notes, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$role, $name, $company ?: null, $phone, $email ?: null, password_hash($password, PASSWORD_DEFAULT), $nationalId ?: null, $economicCode ?: null, $postalCode ?: null, $province ?: null, $city ?: null, $address ?: null, $credit, $status, $notes ?: null, date('Y-m-d H:i:s')]);
            $newId = (int)$db->lastInsertId();
            notify($newId, 'حساب کاربری شما توسط مدیریت ایجاد شد', 'برای ورود از شماره همراه و گذرواژه اعلامی استفاده کنید.', 'index.php?page=login');
            log_action('user_create', 'user', $newId, 'ایجاد حساب کاربری ' . $name . ' با نقش ' . ($role === 'admin' ? 'مدیر' : 'خریدار'));
            flash('کاربر جدید ایجاد شد.', 'success');
        }
        redirect('index.php?page=admin_users');
    }

    case 'user_credit': {
        require_admin();
        $id = (int)post('id');
        $amount = (int)en_digits(post('amount', '0'));
        $reason = post('reason', 'تنظیم اعتبار توسط مدیریت');
        if ($amount === 0) {
            flash('مبلغ اعتبار را وارد کنید.', 'error');
            redirect('index.php?page=admin_user&id=' . $id);
        }
        $db->prepare('UPDATE users SET credit = credit + ? WHERE id = ?')->execute([$amount, $id]);
        notify($id, $amount > 0 ? 'افزایش اعتبار سازمانی' : 'کاهش اعتبار سازمانی',
            ($amount > 0 ? 'مبلغ ' : 'مبلغ ') . money(abs($amount)) . ' در کارپوشه شما ' . ($amount > 0 ? 'افزایش' : 'کاهش') . ' یافت. توضیح: ' . $reason,
            'index.php?page=panel');
        log_action('user_credit', 'user', $id, 'تغییر اعتبار به میزان ' . $amount . ' تومان - ' . $reason);
        flash('اعتبار کاربر به‌روزرسانی شد.', 'success');
        redirect('index.php?page=admin_user&id=' . $id);
    }

    case 'user_toggle': {
        require_admin();
        $id = (int)post('id');
        $stmt = $db->prepare('SELECT role, status FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if (!$u) {
            redirect('index.php?page=admin_users');
        }
        if ($u['role'] === 'admin') {
            flash('حساب مدیر سیستم قابل غیرفعال‌سازی نیست.', 'error');
            redirect('index.php?page=admin_users');
        }
        $new = $u['status'] === 'active' ? 'inactive' : 'active';
        $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $id]);
        log_action('user_toggle', 'user', $id, 'تغییر وضعیت حساب به ' . ($new === 'active' ? 'فعال' : 'غیرفعال'));
        flash('وضعیت حساب کاربر ' . ($new === 'active' ? 'فعال' : 'غیرفعال') . ' شد.', 'success');
        redirect('index.php?page=admin_users');
    }

    case 'user_delete': {
        require_admin();
        $id = (int)post('id');
        $stmt = $db->prepare('SELECT role, name FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if (!$u || $u['role'] === 'admin') {
            flash('حذف حساب مدیر سیستم مجاز نیست.', 'error');
            redirect('index.php?page=admin_users');
        }
        $cnt = $db->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
        $cnt->execute([$id]);
        if ((int)$cnt->fetchColumn() > 0) {
            $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$id]);
            log_action('user_disable', 'user', $id, 'غیرفعال‌سازی کاربر دارای سابقه سفارش: ' . $u['name']);
            flash('این کاربر سابقه سفارش دارد؛ به‌جای حذف، حساب غیرفعال شد.', 'info');
        } else {
            $db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM favorites WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            log_action('user_delete', 'user', $id, 'حذف کاربر ' . $u['name']);
            flash('کاربر حذف شد.', 'success');
        }
        redirect('index.php?page=admin_users');
    }

    // ================================================== مدیریت: استعلام‌ها
    case 'rfq_update': {
        require_admin();
        $id = (int)post('id');
        $status = post('status');
        $quote = (int)en_digits(post('quote_amount', '0'));
        $reply = post('admin_reply');

        $stmt = $db->prepare('SELECT * FROM rfqs WHERE id = ?');
        $stmt->execute([$id]);
        $rfq = $stmt->fetch();
        if (!$rfq) {
            flash('استعلام یافت نشد.', 'error');
            redirect('index.php?page=admin_rfqs');
        }
        $status = in_array($status, $RFQ_STATUSES, true) ? $status : $rfq['status'];
        $quotedAt = $quote > 0 ? date('Y-m-d H:i:s') : $rfq['quoted_at'];

        $db->prepare('UPDATE rfqs SET status = ?, quote_amount = ?, admin_reply = ?, quoted_at = ? WHERE id = ?')
            ->execute([$status, $quote ?: null, $reply ?: null, $quotedAt, $id]);

        if ($rfq['user_id']) {
            notify($rfq['user_id'], 'به‌روزرسانی استعلام ' . $rfq['rfq_code'],
                'وضعیت استعلام شما: «' . rfq_status_label($status) . '»' . ($quote ? ' | مبلغ پیشنهادی: ' . money($quote) : ''),
                'index.php?page=panel_rfqs');
        }
        log_action('rfq_update', 'rfq', $id, 'به‌روزرسانی استعلام ' . $rfq['rfq_code'] . ' به ' . rfq_status_label($status));
        flash('استعلام به‌روزرسانی شد.', 'success');
        redirect('index.php?page=admin_rfq&id=' . $id);
    }

    case 'rfq_delete': {
        require_admin();
        $id = (int)post('id');
        $lookup = $db->prepare('SELECT attachments_json FROM rfqs WHERE id = ?');
        $lookup->execute([$id]);
        $rfq = $lookup->fetch();
        $db->prepare('DELETE FROM rfqs WHERE id = ?')->execute([$id]);
        if ($rfq) {
            rfq_attachments_delete_for_rfq($rfq['attachments_json'] ?? '[]');
        }
        log_action('rfq_delete', 'rfq', $id, 'حذف استعلام');
        flash('استعلام حذف شد.', 'success');
        redirect('index.php?page=admin_rfqs');
    }

    // ================================================== مدیریت: تخفیف‌ها
    case 'coupon_save': {
        require_admin();
        $id = (int)post('id');
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', post('code')));
        $type = post('type') === 'fixed' ? 'fixed' : 'percent';
        $amount = (int)en_digits(post('amount', '0'));
        $minTotal = (int)en_digits(post('min_total', '0'));
        $maxUses = (int)en_digits(post('max_uses', '0'));
        $expires = post('expires_at');
        $active = post('is_active') === '1' ? 1 : 0;
        $desc = post('description');

        if ($code === '' || $amount <= 0) {
            flash('کد تخفیف و مقدار تخفیف الزامی است.', 'error');
            redirect('index.php?page=admin_coupons');
        }
        if ($type === 'percent' && $amount > 80) {
            flash('درصد تخفیف نمی‌تواند بیشتر از ۸۰ باشد.', 'error');
            redirect('index.php?page=admin_coupons');
        }

        if ($id > 0) {
            $db->prepare('UPDATE coupons SET code=?, type=?, amount=?, min_total=?, max_uses=?, expires_at=?, is_active=?, description=? WHERE id=?')
                ->execute([$code, $type, $amount, $minTotal, $maxUses, $expires ?: null, $active, $desc ?: null, $id]);
            log_action('coupon_update', 'coupon', $id, 'ویرایش کد تخفیف ' . $code);
            flash('کد تخفیف به‌روزرسانی شد.', 'success');
        } else {
            try {
                $db->prepare('INSERT INTO coupons (code, type, amount, min_total, max_uses, used, expires_at, is_active, description, created_at) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)')
                    ->execute([$code, $type, $amount, $minTotal, $maxUses, $expires ?: null, $active, $desc ?: null, date('Y-m-d H:i:s')]);
                log_action('coupon_create', 'coupon', (int)$db->lastInsertId(), 'ایجاد کد تخفیف ' . $code);
                flash('کد تخفیف ایجاد شد.', 'success');
            } catch (PDOException $e) {
                flash('این کد تخفیف قبلاً ثبت شده است.', 'error');
            }
        }
        redirect('index.php?page=admin_coupons');
    }

    case 'coupon_toggle':
    case 'coupon_delete': {
        require_admin();
        $id = (int)post('id');
        if ($act === 'coupon_delete') {
            $db->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
            flash('کد تخفیف حذف شد.', 'success');
        } else {
            $db->prepare('UPDATE coupons SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$id]);
            flash('وضعیت کد تخفیف تغییر کرد.', 'success');
        }
        log_action($act, 'coupon', $id, 'تغییر وضعیت کد تخفیف');
        redirect('index.php?page=admin_coupons');
    }

    case 'notify_send': {
        require_admin();
        $uid = (int)post('user_id');
        $title = post('title');
        $body = post('body');
        if ($uid && $title !== '') {
            notify($uid, $title, $body, 'index.php?page=panel_notifications');
            log_action('notify_send', 'user', $uid, 'ارسال پیام مدیریت: ' . $title);
            flash('پیام برای خریدار ارسال شد.', 'success');
        } else {
            flash('عنوان و متن پیام الزامی است.', 'error');
        }
        redirect('index.php?page=admin_user&id=' . $uid);
    }

    // ================================================== پرداخت آنلاین ملت: شروع مجدد / ادامه امن همان تلاش
    case 'mellat_continue': {
        $orderId = (int)post('order_id');
        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            http_response_code(404);
            exit('سفارش یافت نشد.');
        }
        $u = current_user();
        $authorized = is_admin()
            || ($u && !empty($order['user_id']) && (int)$order['user_id'] === (int)$u['id'])
            || (empty($order['user_id']) && (int)($_SESSION['recent_order_id'] ?? 0) === (int)$order['id']);
        if (!$authorized) {
            http_response_code(403);
            exit('اجازهٔ دسترسی به این پرداخت را ندارید.');
        }
        $backUrl = is_admin()
            ? 'index.php?page=admin_order&id=' . (int)$order['id']
            : (($u && !empty($order['user_id'])) ? 'index.php?page=panel_order&no=' . rawurlencode($order['order_no']) : 'index.php?page=order_success&no=' . rawurlencode($order['order_no']));
        if ($order['payment_method'] !== 'mellat') {
            flash('روش پرداخت این سفارش درگاه ملت نیست.', 'error');
            redirect($backUrl);
        }
        if ($order['payment_status'] === 'paid') {
            flash('پرداخت این سفارش قبلاً تأیید شده است.', 'success');
            redirect($backUrl);
        }
        if ($order['status'] === 'canceled') {
            flash('سفارش لغوشده قابل پرداخت نیست؛ با پشتیبانی تماس بگیرید.', 'error');
            redirect($backUrl);
        }

        $lastAttempt = mellat_latest_attempt($db, $orderId);
        if ($lastAttempt && $lastAttempt['status'] === 'initiating') {
            $startedAt = strtotime((string)($lastAttempt['created_at'] ?? ''));
            if ($startedAt !== false && $startedAt >= time() - 180) {
                flash('درخواست پرداخت قبلی هنوز در حال ایجاد است؛ لطفاً چند لحظه دیگر دوباره بررسی کنید.', 'info');
                redirect($backUrl);
            }
            $db->prepare("UPDATE payment_attempts SET status = 'request_failed', response_code = ?, credential_enc = NULL, updated_at = ? WHERE id = ? AND status = 'initiating'")
                ->execute(['timeout', date('Y-m-d H:i:s'), (int)$lastAttempt['id']]);
            $lastAttempt = mellat_latest_attempt($db, $orderId);
        }
        if ($lastAttempt && $lastAttempt['status'] === 'verifying') {
            $lastUpdated = strtotime((string)($lastAttempt['updated_at'] ?? ''));
            if ($lastUpdated !== false && $lastUpdated < time() - 180) {
                $db->prepare("UPDATE payment_attempts SET status = 'verification_pending', updated_at = ? WHERE id = ? AND status = 'verifying'")
                    ->execute([date('Y-m-d H:i:s'), (int)$lastAttempt['id']]);
                $lastAttempt = mellat_latest_attempt($db, $orderId);
            } else {
                flash('پاسخ درگاه هنوز در حال بررسی است؛ لطفاً چند لحظه دیگر دوباره وضعیت را بررسی کنید.', 'info');
                redirect($backUrl);
            }
        }
        if ($lastAttempt && !empty($lastAttempt['ref_id'])
            && in_array($lastAttempt['status'], ['redirected', 'verification_pending', 'verified', 'settle_pending'], true)) {
            mellat_redirect_to_bank($lastAttempt['ref_id']);
        }
        $paymentStart = mellat_start_order_payment($db, $order);
        if (!empty($paymentStart['ok'])) {
            log_action('mellat_payment_start', 'order', $orderId, 'آغاز تلاش پرداخت آنلاین سفارش ' . $order['order_no']);
            mellat_redirect_to_bank($paymentStart['ref_id']);
        }
        flash($paymentStart['message'] ?? 'شروع پرداخت آنلاین ناموفق بود؛ بعداً دوباره تلاش کنید.', 'error');
        redirect($backUrl);
    }

    // ================================================== مرکز مدیریت: تنظیمات، اعلان و محتوای صفحات
    case 'site_control_save': {
        require_admin();
        $errors = [];
        $values = [
            'site_name' => post('site_name'),
            'site_slogan' => post('site_slogan'),
            'phone' => post('phone'),
            'email' => post('email'),
            'address' => post('address'),
            'work_hours' => post('work_hours'),
            'bank_info' => post('bank_info'),
            'company_national_id' => post('company_national_id'),
            'company_economic_code' => post('company_economic_code'),
            'invoice_prefix' => post('invoice_prefix'),
        ];
        $lengthLimits = [
            'site_name' => 180, 'site_slogan' => 240, 'phone' => 80, 'email' => 254,
            'address' => 500, 'work_hours' => 180, 'bank_info' => 1200,
            'company_national_id' => 50, 'company_economic_code' => 80,
        ];
        if (mb_strlen($values['site_name'], 'UTF-8') < 2) $errors[] = 'نام فروشگاه را کامل وارد کنید.';
        foreach ($lengthLimits as $field => $limit) {
            if (mb_strlen($values[$field], 'UTF-8') > $limit) $errors[] = 'طول یکی از اطلاعات فروشگاه بیش از حد مجاز است.';
        }
        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'نشانی ایمیل معتبر نیست.';
        if (!preg_match('/^[A-Za-z0-9_-]{1,16}$/', $values['invoice_prefix'])) $errors[] = 'پیش‌شماره صورتحساب باید ۱ تا ۱۶ نویسه لاتین، عدد، خط تیره یا زیرخط باشد.';

        $siteUrlRaw = post('site_url');
        $siteUrl = mellat_normalize_site_url($siteUrlRaw);
        if ($siteUrlRaw !== '' && $siteUrl === '') $errors[] = 'نشانی سایت باید یک URL عمومی HTTPS، بدون نام کاربری، پارامتر یا fragment باشد.';

        $vatRaw = en_digits(post('vat_rate'));
        if (!preg_match('/^[0-9]{1,2}(?:\.[0-9]{1,2})?$/', $vatRaw) || (float)$vatRaw < 0 || (float)$vatRaw > 30) {
            $errors[] = 'نرخ مالیات باید عددی بین ۰ تا ۳۰ درصد باشد.';
        }
        $shippingRaw = en_digits(post('shipping_cost'));
        $freeShippingRaw = en_digits(post('free_shipping_min'));
        if (!ctype_digit($shippingRaw) || !ctype_digit($freeShippingRaw)) $errors[] = 'هزینه‌های ارسال باید عدد صحیح و نامنفی باشند.';
        if (strlen($shippingRaw) > 12 || strlen($freeShippingRaw) > 12) $errors[] = 'مقدار هزینهٔ ارسال بیش از حد مجاز است.';

        $announcementTitle = post('announcement_title');
        $announcementBody = post('announcement_body');
        $announcementLink = post('announcement_link');
        if (mb_strlen($announcementTitle, 'UTF-8') > 120 || mb_strlen($announcementBody, 'UTF-8') > 1200) $errors[] = 'طول اعلان عمومی بیش از حد مجاز است.';
        if ($announcementLink !== '' && safe_local_url($announcementLink, '') === '') $errors[] = 'پیوند اعلان باید از نوع مسیر داخلی index.php باشد.';

        $terminalId = en_digits(post('mellat_terminal_id'));
        $mellatUsername = trim(post('mellat_username'));
        $mellatPassword = isset($_POST['mellat_password']) && is_scalar($_POST['mellat_password']) ? (string)$_POST['mellat_password'] : '';
        $clearMellatPassword = post('clear_mellat_password') === '1';
        if ($terminalId !== '' && !preg_match('/^[0-9]{1,20}$/', $terminalId)) $errors[] = 'شماره ترمینال ملت باید فقط شامل رقم باشد.';
        if (mb_strlen($mellatUsername, 'UTF-8') > 120 || strlen($mellatPassword) > 240) $errors[] = 'طول یکی از مشخصات درگاه ملت بیش از حد مجاز است.';
        if ($clearMellatPassword && $mellatPassword !== '') $errors[] = 'برای حذف رمز یا جایگزینی آن، یکی از دو روش را انتخاب کنید؛ همزمان هر دو مجاز نیست.';

        $contentFields = [
            'content_page_title_home' => 180,
            'content_home_hero_badge' => 180, 'content_home_hero_title' => 240, 'content_home_hero_text' => 1200,
            'content_home_cta_catalog' => 80, 'content_home_cta_rfq' => 80,
            'content_home_stat_products_label' => 120, 'content_home_stat_buyers_label' => 120,
            'content_home_stat_orders_label' => 120, 'content_home_stat_support_value' => 40,
            'content_home_stat_support_label' => 120, 'content_home_catalog_heading' => 180,
            'content_home_featured_heading' => 180, 'content_home_categories_heading' => 180,
            'content_home_cta_band_title' => 240, 'content_home_cta_band_text' => 1000,
            'content_home_cta_band_button' => 80,
            'content_page_title_about' => 180, 'content_about_badge' => 180,
            'content_about_hero_title' => 240, 'content_about_intro' => 1600,
            'content_about_stat_years_value' => 40, 'content_about_stat_years_label' => 120,
            'content_about_stat_products_label' => 120, 'content_about_stat_orders_label' => 120,
            'content_about_stat_compliance_value' => 40, 'content_about_stat_compliance_label' => 120,
            'content_about_tax_heading' => 180, 'content_about_tax_points' => 1600,
            'content_about_procurement_heading' => 180, 'content_about_procurement_points' => 1600,
            'content_about_steps_heading' => 180,
            'content_about_step_1_title' => 180, 'content_about_step_1_text' => 800,
            'content_about_step_2_title' => 180, 'content_about_step_2_text' => 800,
            'content_about_step_3_title' => 180, 'content_about_step_3_text' => 800,
            'content_about_cta_title' => 240, 'content_about_cta_text' => 800, 'content_about_cta_button' => 80,
            'content_page_title_contact' => 180, 'content_contact_heading' => 240,
            'content_contact_intro' => 1000, 'content_contact_email_note' => 300,
            'content_contact_faq_invoice_q' => 240, 'content_contact_faq_invoice_a' => 800,
            'content_contact_faq_payment_q' => 240, 'content_contact_faq_payment_a' => 800,
            'content_contact_faq_shipping_q' => 240, 'content_contact_faq_shipping_a' => 800,
            'content_contact_faq_warranty_q' => 240, 'content_contact_faq_warranty_a' => 800,
            'content_page_title_rfq' => 180, 'content_rfq_heading' => 240, 'content_rfq_intro' => 1000,
            'content_rfq_done_heading' => 180, 'content_rfq_done_code_label' => 180,
            'content_rfq_done_note' => 800, 'content_rfq_submit_label' => 80,
            'content_rfq_response_note' => 180, 'content_rfq_benefits_heading' => 180,
            'content_rfq_benefits' => 1200,
            'content_page_title_register' => 180, 'content_register_heading' => 240,
            'content_register_intro' => 1000, 'content_register_side_title' => 180,
            'content_register_benefits' => 1200, 'content_register_privacy_note' => 800,
        ];
        $contentValues = [];
        foreach ($contentFields as $field => $limit) {
            if (!isset($_POST[$field]) || !is_scalar($_POST[$field])) continue;
            $value = trim((string)$_POST[$field]);
            if (mb_strlen($value, 'UTF-8') > $limit) {
                $errors[] = 'یکی از متن‌های صفحات عمومی طولانی‌تر از حد مجاز است.';
                break;
            }
            $contentValues[$field] = str_replace(["\r\n", "\r"], "\n", $value);
        }

        $encryptedPassword = null;
        if ($mellatPassword !== '') {
            $encryptedPassword = payment_secret_encrypt($mellatPassword);
            if (!is_string($encryptedPassword)) $errors[] = 'رمز درگاه به‌صورت امن ذخیره نشد؛ دسترسی نوشتن به پوشه tmp و افزونه OpenSSL را بررسی کنید.';
        }
        if ($errors) {
            flash(implode(' ', array_unique($errors)), 'error');
            redirect('index.php?page=admin_control');
        }

        $settingsToSave = $values + [
            'site_url' => $siteUrl,
            'vat_rate' => $vatRaw,
            'shipping_cost' => (string)(int)$shippingRaw,
            'free_shipping_min' => (string)(int)$freeShippingRaw,
            'announcement_enabled' => post('announcement_enabled') === '1' ? '1' : '0',
            'announcement_title' => $announcementTitle !== '' ? $announcementTitle : 'اطلاعیه',
            'announcement_body' => $announcementBody,
            'announcement_link' => $announcementLink,
            'mellat_enabled' => post('mellat_enabled') === '1' ? '1' : '0',
            'mellat_terminal_id' => $terminalId,
            'mellat_username' => $mellatUsername,
        ];
        if ($encryptedPassword !== null) {
            $settingsToSave['mellat_password_enc'] = $encryptedPassword;
        } elseif ($clearMellatPassword) {
            $settingsToSave['mellat_password_enc'] = '';
        }

        try {
            $db->beginTransaction();
            foreach ($settingsToSave as $key => $value) set_setting($key, $value);
            foreach ($contentValues as $key => $value) set_setting($key, $value);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            flash('ذخیره تنظیمات انجام نشد؛ دوباره تلاش کنید.', 'error');
            redirect('index.php?page=admin_control');
        }
        log_action('site_control_update', 'settings', null, 'به‌روزرسانی مرکز مدیریت، محتوای صفحات و پیکربندی درگاه ملت');
        flash('تغییرات مرکز مدیریت ذخیره شد. رمز درگاه در فرم دوباره نمایش داده نمی‌شود.', 'success');
        redirect('index.php?page=admin_control');
    }

    case 'admin_broadcast': {
        require_admin();
        $title = post('title');
        $body = post('body');
        $link = post('link');
        if ($title === '' || $body === '' || mb_strlen($title, 'UTF-8') > 160 || mb_strlen($body, 'UTF-8') > 1600) {
            flash('عنوان و متن اعلان را وارد کنید؛ طول متن بیش از حد مجاز است.', 'error');
            redirect('index.php?page=admin_control');
        }
        if ($link !== '' && safe_local_url($link, '') === '') {
            flash('پیوند اعلان باید از نوع مسیر داخلی index.php باشد.', 'error');
            redirect('index.php?page=admin_control');
        }
        $link = $link === '' ? 'index.php?page=panel_notifications' : $link;
        $stmt = $db->query("SELECT id FROM users WHERE role = 'buyer' AND status = 'active'");
        $insert = $db->prepare('INSERT INTO notifications (user_id, title, body, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)');
        $now = date('Y-m-d H:i:s');
        $count = 0;
        try {
            $db->beginTransaction();
            foreach ($stmt as $user) {
                $insert->execute([(int)$user['id'], $title, $body, $link, $now]);
                $count++;
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            flash('ارسال اعلان همگانی انجام نشد؛ دوباره تلاش کنید.', 'error');
            redirect('index.php?page=admin_control');
        }
        log_action('admin_broadcast', 'notification', null, 'ارسال اعلان داخلی همگانی برای ' . $count . ' خریدار فعال');
        flash('اعلان داخلی برای ' . fa_num($count) . ' خریدار فعال ارسال شد.', 'success');
        redirect('index.php?page=admin_control');
    }

    // ================================================== مدیریت: تنظیمات
    case 'settings_save': {
        require_admin();
        $keys = ['site_name', 'site_slogan', 'phone', 'email', 'address', 'work_hours', 'vat_rate', 'shipping_cost', 'free_shipping_min', 'company_national_id', 'company_economic_code', 'bank_info', 'invoice_prefix'];
        foreach ($keys as $k) {
            if (isset($_POST[$k])) {
                set_setting($k, post($k));
            }
        }
        log_action('settings_update', 'settings', null, 'به‌روزرسانی تنظیمات فروشگاه');
        flash('تنظیمات با موفقیت ذخیره شد.', 'success');
        redirect('index.php?page=admin_settings');
    }

    case 'logs_clear': {
        require_admin();
        $days = max(1, (int)en_digits(post('days', '30')));
        $db->prepare('DELETE FROM activity_logs WHERE created_at < ?')->execute([date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]);
        log_action('logs_clear', 'system', null, 'پاک‌سازی گزارش رویدادهای قدیمی‌تر از ' . $days . ' روز');
        flash('گزارش‌های قدیمی پاک شد.', 'success');
        redirect('index.php?page=admin_logs');
    }
}
