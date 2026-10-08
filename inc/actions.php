<?php
/**
 * پردازش همه عملیات‌های تغییردهنده داده (سبد، سفارش، پنل مدیریت و پنل خریدار)
 * تمام درخواست‌ها POST و محافظت‌شده با توکن CSRF هستند.
 */

$act = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $act !== '') {
    csrf_guard();
}

// وضعیت‌هایی که مدیر می‌تواند برای سفارش ثبت کند
$ORDER_STATUSES = array_keys(order_statuses());
$PAYMENT_STATUSES = array_keys(payment_statuses());
$RFQ_STATUSES = array_keys(rfq_statuses());

switch ($act) {

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

        // فقط روش‌های شناخته‌شده پذیرفته می‌شوند
        $paymentMethod = in_array($input['payment_method'], ['transfer', 'credit', 'wallet'], true)
            ? $input['payment_method']
            : 'transfer';

        $errors = [];
        if (mb_strlen($customer) < 3) {
            $errors['customer_name'] = 'نام رابط خرید را کامل وارد کنید (حداقل ۳ حرف).';
        }
        if (!valid_phone($phone)) {
            $errors['phone'] = 'شماره همراه معتبر نیست؛ ۱۱ رقم و با ۰۹ شروع شود.';
        }
        if (mb_strlen($address) < 10) {
            $errors['address'] = 'نشانی تحویل را کامل‌تر وارد کنید (شامل خیابان و پلاک).';
        }
        if ($paymentMethod === 'wallet') {
            if (!$u) {
                $errors['payment_method'] = 'برای پرداخت از اعتبار کارپوشه، ابتدا وارد حساب خریدار شوید.';
            } elseif ((int)$u['credit'] < $totals['total']) {
                $errors['payment_method'] = 'اعتبار کارپوشه شما (' . money($u['credit']) . ') برای پرداخت این سفارش کافی نیست.';
            }
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

        $stmt = $db->prepare('INSERT INTO orders (order_no, user_id, customer_name, company, phone, email, tax_id, province, city, address, note, subtotal, discount, tax_amount, shipping, total, coupon_code, status, payment_status, payment_method, created_at, updated_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $orderNo, $u ? (int)$u['id'] : null, $customer, $company ?: null, $phone, $email ?: null, $taxId ?: null,
            $province ?: null, $city ?: null, $address, $note ?: null,
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
            $itemsJson[] = ['name' => $it['name'], 'brand' => $it['brand'], 'tax_id' => $it['tax_id'], 'price' => (int)$it['price'], 'qty' => (int)$it['qty'], 'total' => (int)$it['line_total']];
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
        $input = [
            'company' => post('company'),
            'phone' => post('phone'),
            'title' => post('title'),
            'description' => post('description'),
        ];
        $company = $input['company'];
        $phone = en_digits($input['phone']);
        $title = $input['title'];
        $desc = $input['description'];

        $errors = [];
        if (mb_strlen($company) < 3) {
            $errors['company'] = 'نام شرکت یا پیمانکار را کامل وارد کنید (حداقل ۳ حرف).';
        }
        if (!valid_phone($phone)) {
            $errors['phone'] = 'شماره همراه معتبر نیست؛ ۱۱ رقم و با ۰۹ شروع شود.';
        }
        if (mb_strlen($desc) < 10) {
            $errors['description'] = 'شرح اقلام را کامل‌تر بنویسید؛ مثلاً نام کالا، مشخصات و تعداد (حداقل ۱۰ حرف).';
        }
        if ($errors) {
            remember_form('rfq', $input, $errors);
            flash('لطفاً موارد مشخص‌شده را اصلاح کنید.', 'error');
            redirect('index.php?page=rfq');
        }

        $code = gen_rfq_code($db);
        $stmt = $db->prepare('INSERT INTO rfqs (rfq_code, user_id, company, phone, title, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$code, user_id() ?: null, $company, $phone, $title ?: null, $desc, 'new', date('Y-m-d H:i:s')]);

        if (user_id()) {
            notify(user_id(), 'استعلام ' . $code . ' ثبت شد', 'کارشناسان فروش تا حداکثر ۲۴ ساعت کاری قیمت سازمانی را اعلام می‌کنند.', 'index.php?page=panel_rfqs');
        }
        notify_admins('استعلام جدید ' . $code, $company . ' درخواست قیمت ثبت کرد.', 'index.php?page=admin_rfqs');
        log_action('rfq_create', 'rfq', (int)$db->lastInsertId(), 'ثبت استعلام ' . $code);

        flash('استعلام شما با کد پیگیری ' . $code . ' ثبت شد. پاسخ قیمت در پنل خریدار قابل مشاهده است.', 'success');
        redirect('index.php?page=rfq&done=' . urlencode($code));
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

        if (mb_strlen($data['name']) < 5 || $data['price'] <= 0 || $data['tax_id'] === '') {
            flash('نام کالا، قیمت و شناسه کالای مالیاتی الزامی است.', 'error');
            redirect('index.php?page=admin_product_form' . ($id ? '&id=' . $id : ''));
        }

        if ($id > 0) {
            $sql = 'UPDATE products SET sku=?, name=?, category=?, brand=?, price=?, old_price=?, tax_id=?, unit=?, stock=?, min_stock=?, icon=?, image=?, description=?, specs=?, is_active=? WHERE id=?';
            $stmt = $db->prepare($sql);
            $stmt->execute([...array_values($data), $id]);
            log_action('product_update', 'product', $id, 'ویرایش کالا: ' . $data['name']);
            flash('کالا با موفقیت به‌روزرسانی شد.', 'success');
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $stmt = $db->prepare('INSERT INTO products (sku, name, category, brand, price, old_price, tax_id, unit, stock, min_stock, icon, image, description, specs, is_active, created_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $data['sku'] = $data['sku'] ?: 'SKU-' . random_int(1000, 9999);
            $stmt->execute([$data['sku'], $data['name'], $data['category'], $data['brand'], $data['price'], $data['old_price'] ?: null, $data['tax_id'], $data['unit'], $data['stock'], $data['min_stock'], $data['icon'], $data['image'] ?: null, $data['description'], $data['specs'], $data['is_active'], $data['created_at']]);
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

        $items = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $items->execute([$id]);
        $rows = [];
        foreach ($items->fetchAll() as $it) {
            $rows[] = ['name' => $it['name'], 'brand' => $it['brand'], 'tax_id' => $it['tax_id'], 'price' => (int)$it['price'], 'qty' => (int)$it['qty'], 'total' => (int)$it['total']];
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
        $db->prepare('DELETE FROM rfqs WHERE id = ?')->execute([$id]);
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
