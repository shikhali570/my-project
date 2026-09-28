<?php
require_once 'config.php';

$message = '';
$msgType = 'info';

// پیام‌های لحظه‌ای ثبت‌شده قبل از ریدایرکت
if (isset($_SESSION['flash'])) {
    $message = (string)($_SESSION['flash']['m'] ?? '');
    $msgType = (string)($_SESSION['flash']['t'] ?? 'info');
    unset($_SESSION['flash']);
}

// اعتبارسنجی CSRF در درخواست‌های POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die("خطای امنیتی: توکن نامعتبر است.");
    }
}

// ==================== عملیات GET (سبد خرید و حساب) ====================
if (isset($_GET['action'])) {
    $act = $_GET['action'];
    $pid = (int)($_GET['id'] ?? 0);

    // افزودن کالا به سبد (با رعایت سقف موجودی)
    if ($act === 'add_cart') {
        $qty = max(1, (int)($_GET['qty'] ?? 1));
        $stmt = $db->prepare("SELECT id, name, stock FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $prod = $stmt->fetch();

        if (!$prod) {
            flash('کالای موردنظر یافت نشد.', 'error');
        } else {
            $stock = max(0, (int)$prod['stock']);
            $inCart = (int)($_SESSION['cart'][$pid] ?? 0);
            if ($stock <= 0) {
                flash('این کالا در حال حاضر ناموجود است.', 'error');
            } elseif ($inCart + $qty > $stock) {
                $_SESSION['cart'][$pid] = $stock;
                flash(($inCart >= $stock)
                    ? 'قبلاً به سقف موجودی این کالا رسیده‌اید.'
                    : 'تعداد درخواستی به سقف موجودی (' . fa_digits($stock) . ' عدد) محدود شد.', 'error');
            } else {
                $_SESSION['cart'][$pid] = $inCart + $qty;
                flash('«' . $prod['name'] . '» به سبد خرید اضافه شد.', 'success');
            }
        }

        // بازگشت به همان نمای کاتالوگ (دسته/جستجو/مرتب‌سازی)
        if (($_GET['back'] ?? '') === 'catalog') {
            $params = ['page' => 'home'];
            $c = preg_replace('/[^a-z_]/', '', (string)($_GET['cat'] ?? ''));
            $s = preg_replace('/[^a-z_]/', '', (string)($_GET['sort'] ?? ''));
            $qq = trim((string)($_GET['q'] ?? ''));
            if ($c !== '') $params['cat'] = $c;
            if ($s !== '') $params['sort'] = $s;
            if ($qq !== '') $params['q'] = mb_substr_fallback($qq, 0, 60);
            header("Location: index.php?" . http_build_query($params));
        } else {
            header("Location: index.php?page=cart");
        }
        exit;
    }

    // تغییر تعداد / حذف سطر
    if ($act === 'cart_update') {
        $op = $_GET['op'] ?? '';
        if (isset($_SESSION['cart'][$pid])) {
            if ($op === 'inc') {
                $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
                $stmt->execute([$pid]);
                $pr = $stmt->fetch();
                $max = $pr ? max(0, (int)$pr['stock']) : 0;
                if ($_SESSION['cart'][$pid] < $max) {
                    $_SESSION['cart'][$pid]++;
                } else {
                    flash('به سقف موجودی این کالا رسیده‌اید.', 'error');
                }
            }
            if ($op === 'dec') {
                $_SESSION['cart'][$pid]--;
                if ($_SESSION['cart'][$pid] <= 0) unset($_SESSION['cart'][$pid]);
            }
            if ($op === 'del') unset($_SESSION['cart'][$pid]);
        }
        header("Location: index.php?page=cart");
        exit;
    }

    // خالی کردن سبد
    if ($act === 'clear_cart') {
        $_SESSION['cart'] = [];
        flash('سبد خرید شما خالی شد.', 'success');
        header("Location: index.php?page=cart");
        exit;
    }

    // خروج از حساب (سبد خرید حفظ می‌شود)
    if ($act === 'logout') {
        unset($_SESSION['user']);
        unset($_SESSION['last_invoice']);
        flash('از حساب خود خارج شدید.', 'info');
        header("Location: index.php?page=home");
        exit;
    }

    // سفارش مجدد اقلام یک فاکتور (فقط برای مالک فاکتور)
    if ($act === 'reorder') {
        $invId = (int)$pid;
        $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$invId]);
        $invRow = $stmt->fetch();
        $user = $_SESSION['user'] ?? null;
        $allowed = $invRow && $user && (
            (!empty($invRow['user_id']) && (int)$invRow['user_id'] === (int)$user['id'])
            || (($user['phone'] ?? '') !== '' && $invRow['buyer_phone'] === $user['phone'])
        );

        if (!$allowed) {
            flash('دسترسی غیرمجاز یا فاکتور موردنظر یافت نشد.', 'error');
            header("Location: index.php?page=home");
            exit;
        }

        $items = json_decode($invRow['items_json'], true) ?: [];
        $added = 0;
        $skipped = 0;
        foreach ($items as $it) {
            $itId = (int)($it['id'] ?? 0);
            if ($itId > 0) {
                $st = $db->prepare("SELECT id, stock FROM products WHERE id = ?");
                $st->execute([$itId]);
            } else {
                // فاکتورهای قدیمی: تطبیق از روی شناسه کالا
                $st = $db->prepare("SELECT id, stock FROM products WHERE tax_id = ?");
                $st->execute([(string)($it['tax_id'] ?? '')]);
            }
            $pRow = $st->fetch();
            if (!$pRow || (int)$pRow['stock'] <= 0) {
                $skipped++;
                continue;
            }
            $_SESSION['cart'][$pRow['id']] = min(max(1, (int)($it['qty'] ?? 1)), (int)$pRow['stock']);
            $added++;
        }

        if ($added > 0) {
            $extra = $skipped > 0 ? ' (' . fa_digits($skipped) . ' کالای ناموجود نادیده گرفته شد)' : '';
            flash('اقلام فاکتور به سبد خرید منتقل شد' . $extra . '.', 'success');
        } else {
            flash('هیچ کالای موجودی در این فاکتور یافت نشد.', 'error');
        }
        header("Location: index.php?page=cart");
        exit;
    }
}

// ==================== پردازش فرم‌های POST ====================

// ورود / عضویت خریدار
if (isset($_POST['btn_login'])) {
    $phone = trim($_POST['phone'] ?? '');
    $comp = trim($_POST['company'] ?? '');
    $name = trim($_POST['name'] ?? '');

    if (preg_match('/^09[0-9]{9}$/', $phone)) {
        $stmt = $db->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        $isNew = false;

        if (!$user) {
            $stmt = $db->prepare("INSERT INTO users (name, phone, company, national_id, economic_code, postal_code, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name !== '' ? $name : 'مهندس خریدار',
                $phone,
                $comp !== '' ? $comp : 'شرکت ساختمانی',
                '10103456789', '411589342110', '1587563124', 'تهران، دفتر پروژه'
            ]);
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([(int)$db->lastInsertId()]);
            $user = $stmt->fetch();
            $isNew = true;
        }

        $_SESSION['user'] = $user;
        flash($isNew
            ? 'حساب کاربری شما ساخته شد؛ خوش آمدید. اطلاعات حقوقی خود را در پنل کاربری تکمیل کنید.'
            : 'خوش آمدید؛ ورود شما با موفقیت انجام شد.', 'success');
        header("Location: index.php?page=dashboard");
        exit;
    } else {
        $message = "شماره همراه باید ۱۱ رقم با پیش‌شماره ۰۹ باشد.";
        $msgType = "error";
    }
}

// بروزرسانی پروفایل / اطلاعات حقوقی خریدار
if (isset($_POST['btn_profile'])) {
    if (empty($_SESSION['user'])) {
        header("Location: index.php?page=home");
        exit;
    }
    $uid = (int)$_SESSION['user']['id'];
    $pName = trim($_POST['p_name'] ?? '');
    $pCompany = trim($_POST['p_company'] ?? '');
    $pNid = digits_only($_POST['p_national_id'] ?? '');
    $pEco = digits_only($_POST['p_economic_code'] ?? '');
    $pPostal = digits_only($_POST['p_postal_code'] ?? '');
    $pAddress = trim($_POST['p_address'] ?? '');

    $err = '';
    if ($pName === '') {
        $err = 'نام و نام‌خانوادگی رابط خرید الزامی است.';
    } elseif ($_POST['p_national_id'] !== '' && strlen($pNid) !== 11) {
        $err = 'شناسه ملی باید ۱۱ رقم باشد.';
    } elseif ($_POST['p_economic_code'] !== '' && strlen($pEco) !== 11) {
        $err = 'کد اقتصادی باید ۱۱ رقم باشد.';
    } elseif ($_POST['p_postal_code'] !== '' && strlen($pPostal) !== 10) {
        $err = 'کد پستی باید ۱۰ رقم باشد.';
    }

    if ($err === '') {
        $stmt = $db->prepare("UPDATE users SET name = ?, company = ?, national_id = ?, economic_code = ?, postal_code = ?, address = ? WHERE id = ?");
        $stmt->execute([$pName, $pCompany, $pNid, $pEco, $pPostal, $pAddress, $uid]);
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $_SESSION['user'] = $stmt->fetch();
        flash('اطلاعات پروفایل و مشخصات حقوقی شما بروزرسانی شد.', 'success');
        header("Location: index.php?page=dashboard&tab=profile");
        exit;
    }
    $message = $err;
    $msgType = 'error';
}

// صدور فاکتور الکترونیکی (نیازمند ورود به حساب)
if (isset($_POST['btn_checkout'])) {
    if (empty($_SESSION['user'])) {
        flash('برای صدور فاکتور رسمی، ابتدا وارد حساب خریدار خود شوید.', 'error');
        header("Location: index.php?page=cart");
        exit;
    }
    if (empty($_SESSION['cart'])) {
        header("Location: index.php?page=cart");
        exit;
    }

    $cName = trim($_POST['company_name'] ?? '');
    $cPhone = trim($_POST['phone'] ?? '');
    $cTaxId = digits_only($_POST['tax_id'] ?? '');

    if ($cName === '' || !preg_match('/^09[0-9]{9}$/', $cPhone)) {
        $message = "لطفاً اطلاعات را با فرمت درست وارد کنید.";
        $msgType = "error";
    } else {
        $pIds = array_keys($_SESSION['cart']);
        $inClause = implode(',', array_fill(0, count($pIds), '?'));
        $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($inClause)");
        $stmt->execute($pIds);
        $prodList = $stmt->fetchAll();

        if (!$prodList) {
            $_SESSION['cart'] = [];
            flash('کالاهای انتخابی دیگر در کاتالوگ موجود نیستند.', 'error');
            header("Location: index.php?page=cart");
            exit;
        }

        // بررسی موجودی پیش از صدور
        $stockErr = '';
        foreach ($prodList as $pr) {
            if ((int)$_SESSION['cart'][$pr['id']] > (int)$pr['stock']) {
                $stockErr = 'موجودی «' . $pr['name'] . '» کافی نیست (موجودی فعلی: ' . fa_digits((int)$pr['stock']) . ' عدد). لطفاً تعداد را اصلاح کنید.';
                break;
            }
        }

        if ($stockErr !== '') {
            $message = $stockErr;
            $msgType = 'error';
        } else {
            $subtotal = 0;
            $items = [];
            foreach ($prodList as $pr) {
                $qty = (int)$_SESSION['cart'][$pr['id']];
                $line = $pr['price'] * $qty;
                $subtotal += $line;
                $items[] = [
                    'id' => (int)$pr['id'],
                    'name' => $pr['name'],
                    'brand' => $pr['brand'],
                    'tax_id' => $pr['tax_id'],
                    'price' => $pr['price'],
                    'qty' => $qty,
                    'total' => $line
                ];
            }

            $taxAmount = (int)round($subtotal * 0.10);
            $grandTotal = $subtotal + $taxAmount;
            $userId = (int)$_SESSION['user']['id'];
            $checkoutOk = false;
            $taxUniqueId = null;

            try {
                $db->beginTransaction();

                // کسر موجودی با شرط ایمن (رقابت هم‌زمان)
                foreach ($prodList as $pr) {
                    $qty = (int)$_SESSION['cart'][$pr['id']];
                    $st = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                    $st->execute([$qty, $pr['id'], $qty]);
                    if ($st->rowCount() === 0) {
                        throw new RuntimeException('موجودی «' . $pr['name'] . '» به اندازهٔ کافی نیست. تعداد را کم کنید و دوباره تلاش کنید.');
                    }
                }

                // درج فاکتور با تلاش مجدد برای شماره‌های یکتا
                for ($try = 0; $try < 5 && !$checkoutOk; $try++) {
                    $invoiceNo = 'INV-' . date('ym') . '-' . random_int(1000, 9999);
                    $taxUniqueId = 'A1847-' . random_int(10000000000, 99999999999) . '-0021';
                    $trackingCode = 'TRK-' . random_int(100000, 999999);
                    try {
                        $stmt = $db->prepare("INSERT INTO invoices (user_id, invoice_no, tax_unique_id, tracking_code, buyer_name, buyer_phone, buyer_tax_id, subtotal, tax_amount, total_amount, items_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $userId, $invoiceNo, $taxUniqueId, $trackingCode, $cName, $cPhone, $cTaxId,
                            $subtotal, $taxAmount, $grandTotal,
                            json_encode($items, JSON_UNESCAPED_UNICODE),
                            date('Y/m/d H:i')
                        ]);
                        $checkoutOk = true;
                    } catch (PDOException $pe) {
                        if ($try === 4) throw $pe;
                    }
                }

                $db->commit();
            } catch (Exception $ex) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $message = $ex->getMessage();
                $msgType = 'error';
            }

            if ($checkoutOk) {
                $_SESSION['cart'] = [];
                $_SESSION['last_invoice'] = $taxUniqueId;
                flash('صورتحساب الکترونیکی شما با موفقیت صادر شد.', 'success');
                header("Location: index.php?page=invoice&id=" . urlencode($taxUniqueId));
                exit;
            }
        }
    }
}

// ثبت استعلام قیمت پروژه (RFQ)
if (isset($_POST['btn_rfq'])) {
    $comp = trim($_POST['rfq_company'] ?? '');
    $phone = trim($_POST['rfq_phone'] ?? '');
    $desc = trim($_POST['rfq_desc'] ?? '');

    if ($comp !== '' && preg_match('/^09[0-9]{9}$/', $phone) && $desc !== '') {
        $rfqCode = 'RFQ-' . date('y') . '-' . random_int(100, 999);
        $userId = !empty($_SESSION['user']) ? (int)$_SESSION['user']['id'] : null;
        $stmt = $db->prepare("INSERT INTO rfqs (user_id, rfq_code, company, phone, description, created_at, status) VALUES (?, ?, ?, ?, ?, ?, 'در انتظار بررسی')");
        $stmt->execute([$userId, $rfqCode, $comp, $phone, $desc, date('Y/m/d')]);
        flash("استعلام با کد پیگیری {$rfqCode} با موفقیت ثبت شد. کارشناسان ما در اسرع وقت پیش‌فاکتور را صادر می‌کنند.", 'success');
        header("Location: index.php?page=rfq");
        exit;
    } else {
        $message = "لطفاً همه موارد استعلام را به درستی تکمیل فرمایید.";
        $msgType = "error";
    }
}

// پیگیری استعلام ثبت‌شده
if (isset($_POST['btn_track'])) {
    $code = trim($_POST['track_code'] ?? '');
    $phone = trim($_POST['track_phone'] ?? '');
    $stmt = $db->prepare("SELECT * FROM rfqs WHERE rfq_code = ? AND phone = ?");
    $stmt->execute([$code, $phone]);
    $row = $stmt->fetch();
    if ($row) {
        $_SESSION['track_result'] = $row;
        flash('استعلام شما یافت شد.', 'success');
    } else {
        flash('استعلامی با این کد پیگیری و شماره تماس یافت نشد.', 'error');
    }
    header("Location: index.php?page=rfq");
    exit;
}

// ==================== صفحهٔ فعال و کنترل دسترسی ====================

$allowedPages = ['home', 'cart', 'invoice', 'dashboard', 'rfq', 'about'];
$page = $_GET['page'] ?? 'home';
if (!in_array($page, $allowedPages, true)) {
    $page = 'home';
}

// پنل کاربری: نیازمند ورود
if ($page === 'dashboard' && empty($_SESSION['user'])) {
    flash('برای دسترسی به پنل کاربری ابتدا وارد حساب خود شوید.', 'error');
    header("Location: index.php?page=home");
    exit;
}

// فاکتور: فقط برای صاحب فاکتور (یا پس از صدور همین نشست)
$inv = null;
if ($page === 'invoice') {
    $taxId = (string)($_GET['id'] ?? '');
    $stmt = $db->prepare("SELECT * FROM invoices WHERE tax_unique_id = ?");
    $stmt->execute([$taxId]);
    $inv = $stmt->fetch();

    $owner = false;
    if ($inv && !empty($_SESSION['user'])) {
        $u = $_SESSION['user'];
        $owner = (!empty($inv['user_id']) && (int)$inv['user_id'] === (int)$u['id'])
              || (($u['phone'] ?? '') !== '' && $inv['buyer_phone'] === $u['phone']);
    }
    $freshView = $inv && isset($_SESSION['last_invoice']) && hash_equals((string)$_SESSION['last_invoice'], $taxId);

    if (!$inv || (!$owner && !$freshView)) {
        flash('این صورتحساب در دسترس شما نیست یا یافت نشد.', 'error');
        header("Location: index.php?page=" . (!empty($_SESSION['user']) ? 'dashboard' : 'home'));
        exit;
    }
}

include 'header.php';

if ($message): ?>
  <div class="toast-bar <?= e($msgType) ?>" id="siteToast" role="status">
    <span><?= e($message) ?></span>
    <button class="toast-close" type="button" aria-label="بستن پیام" onclick="this.closest('.toast-bar').remove()">✕</button>
  </div>
<?php endif;

// ==================== ۱. صفحهٔ خانه (کاتالوگ) ====================
if ($page === 'home'): ?>
  <section class="hero">
    <h1>خرید بی‌واسطه تجهیزات کارگاهی و مهندسی</h1>
    <p>صدور فوری صورتحساب الکترونیکی نوع ۱ با شناسه اختصاصی و پذیرش قطعی در ممیزی مالیاتی.</p>
    <div class="hero-actions">
      <a href="#catalog" class="btn btn-primary">مشاهدهٔ تجهیزات</a>
      <a href="index.php?page=rfq" class="btn btn-ghost">📋 ثبت استعلام پروژه (RFQ)</a>
    </div>
    <div class="hero-trust">
      <span>🧾 صورتحساب رسمی با شناسهٔ اختصاصی مالیاتی</span>
      <span>🛡️ ضمانت اصالت و انطباق کالا</span>
      <span>🚚 ارسال به سراسر کشور</span>
    </div>
  </section>

  <div class="sec-head" id="catalog">
    <h2 class="sec-title">تجهیزات و ادوات مهندسی پرتقاضا</h2>
  </div>

  <?php
  // پارامترهای کاتالوگ: دسته، جستجو و مرتب‌سازی
  $validCats = ['surveying', 'hse', 'plotter', 'stationery'];
  $cat = isset($_GET['cat']) && in_array($_GET['cat'], $validCats, true) ? $_GET['cat'] : null;
  $qRaw = trim((string)($_GET['q'] ?? ''));
  if (!preg_match('/^.{0,60}/us', $qRaw, $qm)) {
      $qRaw = substr($qRaw, 0, 60);
  } else {
      $qRaw = $qm[0];
  }
  $sorts = [
      'default'   => 'id ASC',
      'price_asc' => 'price ASC',
      'price_desc'=> 'price DESC',
      'name'      => 'name ASC',
  ];
  $sortKey = isset($_GET['sort']) && isset($sorts[$_GET['sort']]) ? $_GET['sort'] : 'default';

  $where = [];
  $params = [];
  if ($cat !== null) {
      $where[] = 'category = ?';
      $params[] = $cat;
  }
  if ($qRaw !== '') {
      $where[] = '(name LIKE ? ESCAPE \'#\' OR brand LIKE ? ESCAPE \'#\' OR tax_id LIKE ? ESCAPE \'#\')';
      $like = '%' . str_replace(['#', '%', '_'], ['##', '#%', '#_'], $qRaw) . '%';
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
  }
  $sql = 'SELECT * FROM products'
       . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
       . ' ORDER BY ' . $sorts[$sortKey];
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $products = $stmt->fetchAll();
  ?>

  <form class="catalog-toolbar" method="get" action="index.php" role="search">
    <input type="hidden" name="page" value="home">
    <?php if ($cat !== null): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
    <input class="search-input" type="search" name="q" value="<?= e($qRaw) ?>" placeholder="جستجو بر اساس نام کالا، برند یا شناسه مالیاتی…" aria-label="جستجوی کالا">
    <select class="toolbar-sort" name="sort" aria-label="مرتب‌سازی">
      <option value="default" <?= $sortKey === 'default' ? 'selected' : '' ?>>پیش‌فرض</option>
      <option value="price_asc" <?= $sortKey === 'price_asc' ? 'selected' : '' ?>>ارزان‌ترین</option>
      <option value="price_desc" <?= $sortKey === 'price_desc' ? 'selected' : '' ?>>گران‌ترین</option>
      <option value="name" <?= $sortKey === 'name' ? 'selected' : '' ?>>نام (الفبا)</option>
    </select>
    <button type="submit" class="btn btn-primary">جستجو</button>
    <?php if ($qRaw !== '' || $cat !== null || $sortKey !== 'default'): ?>
      <a href="index.php?page=home" class="btn btn-secondary">پاک کردن فیلترها</a>
    <?php endif; ?>
  </form>

  <div class="result-meta">
    <?= fa_digits(count($products)) ?> کالا نمایش داده شد
    <?php if ($qRaw !== ''): ?> — نتایج جستجو برای «<?= e($qRaw) ?>»<?php endif; ?>
  </div>

  <?php if (!$products): ?>
    <div class="empty-state">
      <span class="es-icon">🔍</span>
      کالایی مطابق جستجوی شما یافت نشد.
      <div style="margin-top:14px"><a href="index.php?page=home" class="btn btn-primary btn-sm">نمایش همهٔ کالاها</a></div>
    </div>
  <?php else: ?>
    <div class="pro-grid">
      <?php foreach ($products as $p):
        $stock = (int)$p['stock'];
        if ($stock <= 0) {
            $stockBadge = '<span class="stock-badge out">ناموجود</span>';
        } elseif ($stock <= 5) {
            $stockBadge = '<span class="stock-badge low">موجودی محدود: ' . fa_digits($stock) . ' عدد</span>';
        } else {
            $stockBadge = '<span class="stock-badge ok">موجود — ' . fa_digits($stock) . ' عدد</span>';
        }
        $addParams = ['action' => 'add_cart', 'id' => (int)$p['id'], 'back' => 'catalog'];
        if ($cat !== null) $addParams['cat'] = $cat;
        if ($qRaw !== '') $addParams['q'] = $qRaw;
        if ($sortKey !== 'default') $addParams['sort'] = $sortKey;
        $addUrl = 'index.php?' . http_build_query($addParams);
      ?>
        <div class="pro-card"
             data-id="<?= (int)$p['id'] ?>"
             data-icon="<?= e($p['icon']) ?>"
             data-name="<?= e($p['name']) ?>"
             data-brand="<?= e($p['brand']) ?>"
             data-tax="<?= e($p['tax_id']) ?>"
             data-price="<?= (int)$p['price'] ?>"
             data-stock="<?= $stock ?>"
             data-desc="<?= e($p['description'] ?? '') ?>"
             data-addurl="<?= e($addUrl) ?>">
          <div class="pro-thumb pro-open" role="button" tabindex="0" aria-label="نمایش جزئیات <?= e($p['name']) ?>"><?= $p['icon'] ?></div>
          <div class="pro-body">
            <span class="pro-brand"><?= e($p['brand']) ?></span>
            <h4 class="pro-name pro-open" role="button" tabindex="0"><?= e($p['name']) ?></h4>
            <span class="pro-taxcode">شناسه کالا: <?= e($p['tax_id']) ?></span>
            <?= $stockBadge ?>
            <div class="pro-bottom">
              <div class="pro-price"><?= number_format($p['price']) ?> <small>تومان</small></div>
              <?php if ($stock > 0): ?>
                <a href="<?= e($addUrl) ?>" class="btn btn-sm btn-primary">+ سبد</a>
              <?php else: ?>
                <span class="btn btn-sm btn-disabled" aria-disabled="true">ناموجود</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php // ==================== ۲. سبد خرید ====================
elseif ($page === 'cart'):
  $subtotal = 0;
  $cartItems = [];
  $stockIssues = [];
  if (!empty($_SESSION['cart'])) {
      $pIds = array_keys($_SESSION['cart']);
      $inClause = implode(',', array_fill(0, count($pIds), '?'));
      $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($inClause)");
      $stmt->execute($pIds);
      $cartItems = $stmt->fetchAll();
      foreach ($cartItems as $cItem) {
          $q = (int)$_SESSION['cart'][$cItem['id']];
          $subtotal += $cItem['price'] * $q;
          if ($q > (int)$cItem['stock']) {
              $stockIssues[] = $cItem;
          }
      }
  }
  ?>
  <div class="cart-wrap">
    <div>
      <div class="cart-head">
        <h2 style="font-size:18px;font-weight:800">سبد تجهیزات انتخابی</h2>
        <?php if (!empty($cartItems)): ?>
          <a href="index.php?action=clear_cart" class="btn btn-sm btn-secondary" data-confirm="همهٔ اقلام سبد خرید حذف شوند؟">🗑 خالی کردن سبد</a>
        <?php endif; ?>
      </div>

      <?php if ($stockIssues): ?>
        <div class="notice notice-warn">
          <span>⚠️ موجودی برخی کالاها کافی نیست؛ پیش از صدور فاکتور تعداد آن‌ها را کم کنید.</span>
        </div>
      <?php endif; ?>

      <div class="cart-table">
        <?php if (empty($cartItems)): ?>
          <div class="empty-state">
            <span class="es-icon">🛒</span>
            سبد سفارش شما در حال حاضر خالی است.
            <div style="margin-top:14px"><a href="index.php?page=home" class="btn btn-primary btn-sm">مشاهدهٔ کاتالوگ</a></div>
          </div>
        <?php else:
          foreach ($cartItems as $cItem):
            $qty = (int)$_SESSION['cart'][$cItem['id']];
            $rowTot = $cItem['price'] * $qty;
            $maxStock = max(0, (int)$cItem['stock']); ?>
            <div class="cart-row">
              <div style="font-size:26px"><?= $cItem['icon'] ?></div>
              <div style="flex:1;min-width:140px">
                <div style="font-weight:700"><?= e($cItem['name']) ?></div>
                <div style="font-size:11px;color:var(--g500)">شناسه مؤدیان: <?= e($cItem['tax_id']) ?> — قیمت واحد: <?= number_format($cItem['price']) ?> ت</div>
                <?php if ($qty > $maxStock): ?>
                  <div style="font-size:11px;color:var(--red);font-weight:700">فقط <?= fa_digits($maxStock) ?> عدد موجود است</div>
                <?php endif; ?>
              </div>
              <div style="display:flex;align-items:center;gap:6px">
                <a href="index.php?action=cart_update&op=dec&id=<?= $cItem['id'] ?>" class="btn btn-sm btn-secondary" aria-label="کاهش تعداد" <?= $qty <= 1 ? 'data-confirm="این کالا از سبد حذف شود؟"' : '' ?>>-</a>
                <span style="font-weight:700;padding:0 6px"><?= fa_digits($qty) ?></span>
                <a href="index.php?action=cart_update&op=inc&id=<?= $cItem['id'] ?>" class="btn btn-sm btn-secondary" aria-label="افزایش تعداد" <?= $qty >= $maxStock ? 'title="به سقف موجودی رسیدید"' : '' ?>>+</a>
              </div>
              <div style="font-weight:800;width:120px;text-align:left"><?= number_format($rowTot) ?> ت</div>
              <a href="index.php?action=cart_update&op=del&id=<?= $cItem['id'] ?>" style="color:var(--red);font-weight:800" aria-label="حذف از سبد" data-confirm="حذف این کالا از سبد؟">✕</a>
            </div>
          <?php endforeach;
        endif; ?>
      </div>
    </div>

    <?php if (!empty($cartItems)):
      $tax = (int)round($subtotal * 0.10);
      $total = $subtotal + $tax; ?>
      <div class="cart-summary">
        <h3 style="font-size:15px;font-weight:800;border-bottom:1px solid var(--g200);padding-bottom:10px;margin-bottom:14px">صدور پیش‌فاکتور با شناسه مالیاتی</h3>

        <?php if (empty($_SESSION['user'])): ?>
          <div class="notice notice-info" style="flex-direction:column;align-items:flex-start">
            <span>🔐 برای صدور فاکتور رسمی و دسترسی به پنل مالی، ابتدا با شمارهٔ همراه خود وارد شوید.</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('authModal')">ورود / ثبت‌نام خریدار</button>
          </div>
        <?php else: ?>
          <form method="POST" action="index.php?page=cart">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="input-group">
              <label>نام شخصیت حقوقی / شرکت خریدار *</label>
              <input type="text" name="company_name" required value="<?= e($_SESSION['user']['company'] ?? '') ?>">
            </div>
            <div class="input-group">
              <label>شماره تماس مسئول خرید یا تدارکات *</label>
              <input type="text" name="phone" required maxlength="11" value="<?= e($_SESSION['user']['phone'] ?? '') ?>">
            </div>
            <div class="input-group">
              <label>شناسه ملی شرکت (۱۱ رقم)</label>
              <input type="text" name="tax_id" maxlength="11" value="<?= e($_SESSION['user']['national_id'] ?? '10103456789') ?>">
            </div>
            <div class="sum-line"><span>جمع خالص اقلام:</span><span><?= number_format($subtotal) ?> ت</span></div>
            <div class="sum-line"><span>مالیات ارزش افزوده (۱۰٪):</span><span><?= number_format($tax) ?> ت</span></div>
            <div class="sum-line total"><span>مبلغ قابل پرداخت:</span><span><?= number_format($total) ?> تومان</span></div>
            <button type="submit" name="btn_checkout" class="btn btn-green" style="width:100%;margin-top:14px">صدور و ارسال به کارپوشه دارایی</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

<?php // ==================== ۳. چاپ فاکتور استاندارد A4 ====================
elseif ($page === 'invoice'):
  $items = json_decode($inv['items_json'], true) ?: []; ?>
    <div class="invoice-box-printable">
      <div style="display:flex;justify-content:space-between;border-bottom:2px solid var(--navy);padding-bottom:12px;margin-bottom:16px">
        <div>
          <h2 style="font-size:16px;font-weight:800;color:var(--navy)">صورتحساب الکترونیکی فروش کالا و خدمات</h2>
          <small style="color:var(--g500)">مطابق ماده ۵ قانون پایانه‌های فروشگاهی و سامانه مؤدیان</small>
        </div>
        <div style="text-align:left;font-size:12px">
          <div>شماره منحصر به‌فرد مالیاتی: <strong style="color:var(--blue)"><?= e($inv['tax_unique_id']) ?></strong></div>
          <div>شماره فاکتور: <?= e($inv['invoice_no']) ?> | تاریخ: <?= jalali_date($inv['created_at']) ?></div>
          <div>کد پیگیری: <?= e($inv['tracking_code']) ?></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;background:var(--g50);border:1px solid var(--g200);border-radius:var(--r);padding:12px;font-size:12px;margin-bottom:16px">
        <div>
          <strong>فروشنده:</strong> شرکت پارس سازه و آفیس سهامی خاص<br>
          شناسه ملی: ۱۰۱۰۹۹۸۸۷۷۶ | کد اقتصادی: ۴۱۱۵۶۷۸۹۰۰۱<br>
          تلفن: ۰۲۱-۸۸۷۷۶۶۵۵ | تهران، خیابان بهشتی، ساختمان پارس
        </div>
        <div>
          <strong>خریدار:</strong> <?= e($inv['buyer_name']) ?><br>
          شماره تماس رابط: <?= e($inv['buyer_phone']) ?><br>
          شناسه ملی / کد اقتصادی: <?= e($inv['buyer_tax_id']) ?>
        </div>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th>ردیف</th>
            <th>کد کالا</th>
            <th>شرح کالا یا خدمات</th>
            <th>تعداد</th>
            <th>قیمت واحد</th>
            <th>ارزش افزوده (۱۰٪)</th>
            <th>مبلغ کل</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $idx => $it):
            $lineTax = (int)round($it['total'] * 0.10); ?>
            <tr>
              <td><?= fa_digits($idx + 1) ?></td>
              <td><code><?= e($it['tax_id']) ?></code></td>
              <td><?= e($it['name']) ?></td>
              <td><?= fa_digits($it['qty']) ?></td>
              <td><?= number_format($it['price']) ?></td>
              <td><?= number_format($lineTax) ?></td>
              <td><?= number_format($it['total'] + $lineTax) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="display:flex;justify-content:space-between;margin-top:20px;border-top:1px dashed var(--g200);padding-top:14px">
        <div style="font-size:11.5px;color:var(--g700);max-width:550px">
          📌 اعتبار مالیاتی ارزش افزوده این سند به صورت خودکار به کارپوشه مؤدیان خریدار منتقل گردیده و در ممیزی دارایی معتبر است.
        </div>
        <div style="text-align:left;line-height:1.8">
          <div>جمع اقلام: <?= number_format($inv['subtotal']) ?> تومان</div>
          <div>مالیات ارزش افزوده: <?= number_format($inv['tax_amount']) ?> تومان</div>
          <div style="font-size:14px;font-weight:800;color:var(--blue)">مبلغ کل نهایی: <?= number_format($inv['total_amount']) ?> تومان</div>
        </div>
      </div>
    </div>

    <div class="no-print flex-center gap-10" style="margin-top:20px;flex-wrap:wrap">
      <button class="btn btn-green" onclick="window.print()">🖨️ چاپ فاکتور رسمی A4</button>
      <a href="index.php?action=reorder&id=<?= (int)$inv['id'] ?>" class="btn btn-secondary">🔁 سفارش مجدد اقلام</a>
      <a href="index.php?page=dashboard" class="btn btn-secondary">بازگشت به پنل کاربری</a>
    </div>

<?php // ==================== ۴. پنل کاربری ====================
elseif ($page === 'dashboard'):
  $u = $_SESSION['user'];
  $uid = (int)$u['id'];
  $uphone = (string)($u['phone'] ?? '');
  $tab = $_GET['tab'] ?? 'invoices';
  if (!in_array($tab, ['invoices', 'rfqs', 'profile'], true)) $tab = 'invoices';

  $stmt = $db->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? OR buyer_phone = ?");
  $stmt->execute([$uid, $uphone]);
  $invCount = (int)$stmt->fetchColumn();

  $stmt = $db->prepare("SELECT COUNT(*) FROM rfqs WHERE user_id = ? OR phone = ?");
  $stmt->execute([$uid, $uphone]);
  $rfqCount = (int)$stmt->fetchColumn();
  ?>
  <div class="dashboard-layout">
    <aside class="dash-sidebar">
      <div class="user-profile-widget">
        <div class="user-avatar-lg">م</div>
        <h3 style="font-size:15px;font-weight:800;color:var(--navy)"><?= e($u['company'] ?? '') ?: e($u['name'] ?? '') ?></h3>
        <p style="font-size:12px;color:var(--g500)"><?= e($uphone) ?></p>
      </div>
      <ul class="dash-menu">
        <li>
          <a class="dash-menu-item <?= $tab === 'invoices' ? 'active' : '' ?>" href="index.php?page=dashboard&tab=invoices">
            🧾 فاکتورهای رسمی صادر شده <span class="dash-count"><?= fa_digits($invCount) ?></span>
          </a>
        </li>
        <li>
          <a class="dash-menu-item <?= $tab === 'rfqs' ? 'active' : '' ?>" href="index.php?page=dashboard&tab=rfqs">
            📋 استعلام‌های ثبت‌شده <span class="dash-count"><?= fa_digits($rfqCount) ?></span>
          </a>
        </li>
        <li>
          <a class="dash-menu-item <?= $tab === 'profile' ? 'active' : '' ?>" href="index.php?page=dashboard&tab=profile">
            👤 پروفایل و اطلاعات حقوقی
          </a>
        </li>
        <li>
          <a class="dash-menu-item logout" href="index.php?action=logout">🚪 خروج از حساب</a>
        </li>
      </ul>
    </aside>

    <main class="dash-main">
      <?php if ($tab === 'invoices'): ?>
        <div class="dash-title"><span>صورتحساب‌های الکترونیکی ثبت‌شده</span></div>
        <?php
        $stmt = $db->prepare("SELECT * FROM invoices WHERE user_id = ? OR buyer_phone = ? ORDER BY id DESC");
        $stmt->execute([$uid, $uphone]);
        $invoices = $stmt->fetchAll(); ?>
        <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>شماره فاکتور</th>
              <th>شناسه مالیاتی</th>
              <th>تاریخ (شمسی)</th>
              <th>مبلغ کل (تومان)</th>
              <th>وضعیت</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($invoices)): ?>
              <tr><td colspan="6" style="text-align:center">هنوز هیچ فاکتوری صادر نگردیده است.</td></tr>
            <?php else: foreach ($invoices as $in): ?>
              <tr>
                <td>
                  <strong><?= e($in['invoice_no']) ?></strong>
                  <div style="font-size:10.5px;color:var(--g500)">رهگیری: <?= e($in['tracking_code']) ?></div>
                </td>
                <td><code><?= e($in['tax_unique_id']) ?></code></td>
                <td><?= jalali_date($in['created_at']) ?></td>
                <td><?= number_format($in['total_amount']) ?></td>
                <td><span class="status-pill status-success"><?= e($in['status']) ?></span></td>
                <td style="white-space:nowrap">
                  <a href="index.php?page=invoice&id=<?= e($in['tax_unique_id']) ?>" class="btn btn-sm btn-primary">مشاهده / چاپ</a>
                  <a href="index.php?action=reorder&id=<?= (int)$in['id'] ?>" class="btn btn-sm btn-secondary" title="افزودن اقلام این فاکتور به سبد">🔁</a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>

      <?php elseif ($tab === 'rfqs'): ?>
        <div class="dash-title"><span>استعلام‌های قیمت ثبت‌شده (RFQ)</span></div>
        <?php
        $stmt = $db->prepare("SELECT * FROM rfqs WHERE user_id = ? OR phone = ? ORDER BY id DESC");
        $stmt->execute([$uid, $uphone]);
        $myRfqs = $stmt->fetchAll(); ?>
        <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>کد پیگیری</th>
              <th>تاریخ ثبت (شمسی)</th>
              <th>شرح درخواست</th>
              <th>وضعیت</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($myRfqs)): ?>
              <tr><td colspan="4" style="text-align:center">هنوز استعلامی ثبت نکرده‌اید. <a href="index.php?page=rfq" style="color:var(--blue);font-weight:700">ثبت استعلام جدید</a></td></tr>
            <?php else: foreach ($myRfqs as $rf): ?>
              <tr>
                <td><strong><?= e($rf['rfq_code']) ?></strong></td>
                <td><?= jalali_date($rf['created_at']) ?></td>
                <td title="<?= e($rf['description']) ?>"><?= e(mb_substr_fallback($rf['description'], 0, 70)) ?><?= mb_strlen_fallback($rf['description']) > 70 ? '…' : '' ?></td>
                <td><span class="status-pill status-pending"><?= e($rf['status']) ?></span></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>

      <?php else: // پروفایل
        $pd = (isset($_POST['btn_profile']) && $message !== '') ? $_POST : null;
        $fv = function ($postKey, $userKey) use ($pd, $u) {
            return $pd !== null ? (string)($pd[$postKey] ?? '') : (string)($u[$userKey] ?? '');
        }; ?>
        <div class="dash-title"><span>پروفایل و اطلاعات حقوقی خریدار</span></div>
        <p style="font-size:12px;color:var(--g500);margin-bottom:16px">این اطلاعات در صدور صورتحساب الکترونیکی و پیش‌فاکتورهای رسمی استفاده می‌شود.</p>
        <form method="POST" action="index.php?page=dashboard&tab=profile">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <div class="profile-grid">
            <div class="input-group">
              <label>نام و نام‌خانوادگی رابط *</label>
              <input type="text" name="p_name" required value="<?= e($fv('p_name', 'name')) ?>">
            </div>
            <div class="input-group">
              <label>نام شرکت / مؤسسه</label>
              <input type="text" name="p_company" value="<?= e($fv('p_company', 'company')) ?>">
            </div>
            <div class="input-group">
              <label>شناسه ملی (۱۱ رقم)</label>
              <input type="text" name="p_national_id" maxlength="11" value="<?= e($fv('p_national_id', 'national_id')) ?>">
            </div>
            <div class="input-group">
              <label>کد اقتصادی (۱۱ رقم)</label>
              <input type="text" name="p_economic_code" maxlength="11" value="<?= e($fv('p_economic_code', 'economic_code')) ?>">
            </div>
            <div class="input-group">
              <label>کد پستی (۱۰ رقم)</label>
              <input type="text" name="p_postal_code" maxlength="10" value="<?= e($fv('p_postal_code', 'postal_code')) ?>">
            </div>
            <div class="input-group">
              <label>شمارهٔ همراه (قابل تغییر نیست)</label>
              <input type="text" value="<?= e($uphone) ?>" disabled style="background:var(--g100);color:var(--g500)">
            </div>
            <div class="input-group full">
              <label>نشانی دفتر / پروژه</label>
              <textarea name="p_address" rows="2"><?= e($fv('p_address', 'address')) ?></textarea>
            </div>
          </div>
          <button type="submit" name="btn_profile" class="btn btn-primary">ذخیرهٔ تغییرات</button>
        </form>
      <?php endif; ?>
    </main>
  </div>

<?php // ==================== ۵. استعلام قیمت پروژه (RFQ) ====================
elseif ($page === 'rfq'):
  $trackResult = $_SESSION['track_result'] ?? null;
  unset($_SESSION['track_result']); ?>
  <div class="rfq-grid">
    <div class="rfq-box">
      <h2 style="font-size:18px;font-weight:800;color:var(--navy);margin-bottom:8px">📋 فرم رسمی استعلام قیمت پروژه (RFQ)</h2>
      <p style="font-size:12.5px;color:var(--g500);margin-bottom:16px">نیازمندی‌های کارگاهی خود را ثبت کنید تا با اعمال تخفیف سازمانی قیمت‌گذاری گردد.</p>
      <form method="POST" action="index.php?page=rfq">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="profile-grid" style="margin-bottom:12px">
          <div class="input-group">
            <label>نام شرکت یا پیمانکار *</label>
            <input type="text" name="rfq_company" required value="<?= e($_SESSION['user']['company'] ?? '') ?>">
          </div>
          <div class="input-group">
            <label>شماره تماس مسئول تدارکات *</label>
            <input type="text" name="rfq_phone" required maxlength="11" value="<?= e($_SESSION['user']['phone'] ?? '') ?>">
          </div>
        </div>
        <div class="input-group">
          <label>شرح اقلام و مقادیر درخواستی *</label>
          <textarea name="rfq_desc" rows="4" required placeholder="مثلاً: ۱۰ حلقه رول پلاتر عرض ۹۰، ۲۰ عدد کلاه عایق برق JSP..."></textarea>
        </div>
        <button type="submit" name="btn_rfq" class="btn btn-orange">ارسال استعلام رسمی</button>
      </form>
    </div>

    <div class="rfq-box">
      <h2 style="font-size:18px;font-weight:800;color:var(--navy);margin-bottom:8px">🔎 پیگیری استعلام ثبت‌شده</h2>
      <p style="font-size:12.5px;color:var(--g500);margin-bottom:16px">کد پیگیری دریافتی هنگام ثبت استعلام را همراه شماره تماس وارد کنید.</p>
      <form method="POST" action="index.php?page=rfq">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="input-group">
          <label>کد پیگیری استعلام</label>
          <input type="text" name="track_code" placeholder="RFQ-۱۴۰۴-۱۲۳" value="<?= e($_GET['code'] ?? '') ?>">
        </div>
        <div class="input-group">
          <label>شماره تماس ثبت‌شده</label>
          <input type="text" name="track_phone" required maxlength="11" placeholder="۰۹۱۲XXXXXXX" value="<?= e($_SESSION['user']['phone'] ?? '') ?>">
        </div>
        <button type="submit" name="btn_track" class="btn btn-primary">پیگیری استعلام</button>
      </form>

      <?php if ($trackResult): ?>
        <div class="track-result">
          <div class="flex-between" style="margin-bottom:8px">
            <strong style="color:var(--navy)"><?= e($trackResult['rfq_code']) ?></strong>
            <span class="status-pill status-pending"><?= e($trackResult['status']) ?></span>
          </div>
          <div style="font-size:12px;color:var(--g500);margin-bottom:6px">
            ثبت: <?= jalali_date($trackResult['created_at']) ?> — <?= e($trackResult['company']) ?>
          </div>
          <div style="font-size:12.5px;line-height:1.8"><?= e($trackResult['description']) ?></div>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php // ==================== ۶. درباره شرکت ====================
elseif ($page === 'about'): ?>
  <div class="rfq-box">
    <h2 style="font-size:18px;font-weight:800;color:var(--navy);margin-bottom:10px">درباره پارس سازه و آفیس</h2>
    <p style="line-height:1.9;color:var(--g700)">پارس سازه و آفیس مرجع تخصصی تأمین ابزار دقیق، ادوات نقشه‌برداری، حفاظت فردی کارگاهی و ملزومات اسنادی دفاتر فنی پروژه‌های عمرانی است. تمامی اقلام با انطباق ۱۰۰ درصدی با پایانه فروشگاهی و صورتحساب الکترونیکی سامانه مؤدیان عرضه می‌شوند.</p>
    <div class="about-grid">
      <div class="about-card">
        <div class="about-icon">📏</div>
        <h4>ابزار دقیق و نقشه‌برداری</h4>
        <p>متر لیزری، تراز لیزری و تجهیزات برداشت اجرای برندهای معتبر جهانی.</p>
      </div>
      <div class="about-card">
        <div class="about-icon">🦺</div>
        <h4>ایمنی و HSE کارگاهی</h4>
        <p>کلاه ایمنی، جلیقه شبرنگ و تجهیزات حفاظت فردی مناسب استانداردهای روز.</p>
      </div>
      <div class="about-card">
        <div class="about-icon">🖨️</div>
        <h4>رول و چاپ نقشه</h4>
        <p>انواع رول پلاتر و کاغذ نقشه برای چاپ اجرایی و تیتر پروژه.</p>
      </div>
      <div class="about-card">
        <div class="about-icon">📁</div>
        <h4>بایگانی و زونکن</h4>
        <p>ملزومات اسنادی و بایگانی دفاتر فنی، مالی و پروژه‌های عمرانی.</p>
      </div>
    </div>
    <div class="notice notice-info" style="margin-top:18px;margin-bottom:0">
      <span>🧾 هر خرید با صدور صورتحساب الکترونیکی نوع ۱ و شناسهٔ اختصاصی مالیاتی همراه است و در پنل کاربری شما بایگانی می‌شود.</span>
    </div>
  </div>
<?php endif;

include 'footer.php';
