<?php
require_once 'config.php';

$message = '';
$msgType = 'info';

// اعتبارسنجی CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die("خطای امنیتی: توکن نامعتبر است.");
    }
}

// مدیریت سبد و نشست‌ها - تخصصی
if (isset($_GET['action'])) {
    $act = $_GET['action'];
    $pid = (int)($_GET['id'] ?? 0);

    if ($act === 'add_cart') {
        if ($pid > 0) $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + 1;
        header("Location: index.php?page=cart");
        exit;
    }
    if ($act === 'cart_update') {
        $op = $_GET['op'] ?? '';
        if (isset($_SESSION['cart'][$pid])) {
            if ($op === 'inc') $_SESSION['cart'][$pid]++;
            if ($op === 'dec') {
                $_SESSION['cart'][$pid]--;
                if ($_SESSION['cart'][$pid] <= 0) unset($_SESSION['cart'][$pid]);
            }
            if ($op === 'del') unset($_SESSION['cart'][$pid]);
        }
        header("Location: index.php?page=cart");
        exit;
    }
    if ($act === 'cart_clear') {
        $_SESSION['cart'] = [];
        header("Location: index.php?page=cart");
        exit;
    }
    if ($act === 'logout') {
        unset($_SESSION['user']);
        header("Location: index.php?page=home");
        exit;
    }
    // علاقه‌مندی‌ها - تخصصی
    if ($act === 'wishlist_add' && !empty($_SESSION['user'])) {
        if ($pid > 0) {
            $stmt = $db->prepare("INSERT OR IGNORE INTO wishlist (user_id, product_id, created_at) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user']['id'], $pid, date('Y/m/d H:i')]);
        }
        header("Location: index.php?page=dashboard&tab=wishlist");
        exit;
    }
    if ($act === 'wishlist_remove' && !empty($_SESSION['user'])) {
        if ($pid > 0) {
            $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")->execute([$_SESSION['user']['id'], $pid]);
        }
        header("Location: index.php?page=dashboard&tab=wishlist");
        exit;
    }
    // مقایسه - تخصصی
    if ($act === 'compare_add') {
        if ($pid > 0) {
            if (!isset($_SESSION['compare'])) $_SESSION['compare'] = [];
            if (!in_array($pid, $_SESSION['compare']) && count($_SESSION['compare']) < 4) {
                $_SESSION['compare'][] = $pid;
            }
        }
        header("Location: index.php?page=dashboard&tab=compare");
        exit;
    }
    if ($act === 'compare_remove') {
        if (isset($_SESSION['compare'])) {
            $_SESSION['compare'] = array_filter($_SESSION['compare'], fn($x) => $x != $pid);
        }
        header("Location: index.php?page=dashboard&tab=compare");
        exit;
    }
    if ($act === 'compare_clear') {
        $_SESSION['compare'] = [];
        header("Location: index.php?page=dashboard&tab=compare");
        exit;
    }
    // حذف آدرس
    if ($act === 'delete_address' && !empty($_SESSION['user'])) {
        $db->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?")->execute([$pid, $_SESSION['user']['id']]);
        header("Location: index.php?page=dashboard&tab=addresses");
        exit;
    }
}

// لاگین / عضویت بهبود یافته
if (isset($_POST['btn_login'])) {
    $phone = trim($_POST['phone'] ?? '');
    $comp = trim($_POST['company'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (preg_match('/^09[0-9]{9}$/', $phone)) {
        $stmt = $db->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if (!$user) {
            $stmt = $db->prepare("INSERT INTO users (name, phone, company, national_id, economic_code, postal_code, address, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name ?: 'مهندس خریدار',
                $phone,
                $comp ?: 'شرکت ساختمانی',
                '10103456789',
                '411589342110',
                '1587563124',
                'تهران، دفتر پروژه',
                $email
            ]);
            $userId = $db->lastInsertId();
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } else {
            // بروزرسانی اطلاعات اگر جدید وارد شده
            if ($name || $comp || $email) {
                $upd = $db->prepare("UPDATE users SET name = COALESCE(NULLIF(?, ''), name), company = COALESCE(NULLIF(?, ''), company), email = COALESCE(NULLIF(?, ''), email) WHERE id = ?");
                $upd->execute([$name, $comp, $email, $user['id']]);
                $user['name'] = $name ?: $user['name'];
                $user['company'] = $comp ?: $user['company'];
            }
        }
        $_SESSION['user'] = $user;
        header("Location: index.php?page=dashboard");
        exit;
    } else {
        $message = "شماره همراه باید ۱۱ رقم با پیش‌شماره ۰۹ باشد.";
        $msgType = "error";
    }
}

// بروزرسانی پروفایل - تخصصی
if (isset($_POST['btn_update_profile'])) {
    if (empty($_SESSION['user'])) {
        header("Location: index.php?page=home");
        exit;
    }
    $name = trim($_POST['name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $province = trim($_POST['province'] ?? 'تهران');
    $city = trim($_POST['city'] ?? 'تهران');

    $stmt = $db->prepare("UPDATE users SET name=?, company=?, email=?, national_id=?, address=?, province=?, city=? WHERE id=?");
    $stmt->execute([$name, $company, $email, $national_id, $address, $province, $city, $_SESSION['user']['id']]);

    $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user']['id']]);
    $_SESSION['user'] = $stmt->fetch();
    $message = "پروفایل تخصصی با موفقیت بروزرسانی شد.";
    $msgType = "success";
    logActivity('profile_update', "بروزرسانی پروفایل", $_SESSION['user']['id']);
}

// افزودن آدرس جدید - تخصصی
if (isset($_POST['btn_add_address'])) {
    if (empty($_SESSION['user'])) { header("Location: index.php?page=home"); exit; }
    $title = trim($_POST['title'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $postal = trim($_POST['postal_code'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $receiver = trim($_POST['receiver_name'] ?? '');
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    if ($title && $province && $city && $addr) {
        if ($is_default) {
            $db->prepare("UPDATE addresses SET is_default=0 WHERE user_id=?")->execute([$_SESSION['user']['id']]);
        }
        $stmt = $db->prepare("INSERT INTO addresses (user_id, title, province, city, address, postal_code, phone, receiver_name, is_default, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user']['id'], $title, $province, $city, $addr, $postal, $phone, $receiver, $is_default, date('Y/m/d H:i')]);
        $message = "آدرس جدید با موفقیت افزوده شد.";
        $msgType = "success";
    }
}

// ثبت تیکت پشتیبانی - تخصصی
if (isset($_POST['btn_add_ticket'])) {
    if (empty($_SESSION['user'])) { header("Location: index.php?page=home"); exit; }
    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? 'عمومی');
    $priority = trim($_POST['priority'] ?? 'متوسط');
    $msg = trim($_POST['message'] ?? '');

    if ($subject && $msg) {
        $code = 'TKT-' . date('ymd') . '-' . random_int(1000,9999);
        $stmt = $db->prepare("INSERT INTO support_tickets (ticket_code, user_id, subject, category, priority, message, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $_SESSION['user']['id'], $subject, $category, $priority, $msg, date('Y/m/d H:i')]);
        $message = "تیکت پشتیبانی با کد $code ثبت شد. کارشناسان به زودی پاسخ خواهند داد.";
        $msgType = "success";
        logActivity('ticket_create', "ثبت تیکت: $subject", $_SESSION['user']['id']);
    }
}

// ثبت فاکتور نهایی
if (isset($_POST['btn_checkout'])) {
    if (empty($_SESSION['cart'])) {
        header("Location: index.php?page=cart");
        exit;
    }
    $cName = trim($_POST['company_name'] ?? '');
    $cPhone = trim($_POST['phone'] ?? '');
    $cTaxId = trim($_POST['tax_id'] ?? '10103456789');
    $cNotes = trim($_POST['notes'] ?? '');

    if (!empty($cName) && preg_match('/^09[0-9]{9}$/', $cPhone)) {
        $pIds = array_keys($_SESSION['cart']);
        $inClause = implode(',', array_fill(0, count($pIds), '?'));
        $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($inClause)");
        $stmt->execute($pIds);
        $prodList = $stmt->fetchAll();

        $subtotal = 0;
        $items = [];
        foreach ($prodList as $pr) {
            $qty = (int)$_SESSION['cart'][$pr['id']];
            $line = $pr['price'] * $qty;
            $subtotal += $line;
            $items[] = [
                'id' => $pr['id'],
                'name' => $pr['name'],
                'brand' => $pr['brand'],
                'tax_id' => $pr['tax_id'],
                'price' => $pr['price'],
                'qty' => $qty,
                'total' => $line,
                'icon' => $pr['icon']
            ];
        }

        $taxRate = (int)getSetting('tax_rate', 10);
        $taxAmount = (int)round($subtotal * $taxRate / 100);
        $grandTotal = $subtotal + $taxAmount;

        $invoiceNo = 'INV-' . date('ym') . '-' . random_int(1000, 9999);
        $taxUniqueId = 'A1847-' . random_int(10000000000, 99999999999) . '-0021';
        $trackingCode = 'TRK-' . random_int(100000, 999999);

        $userId = $_SESSION['user']['id'] ?? null;

        $stmt = $db->prepare("INSERT INTO invoices (invoice_no, tax_unique_id, tracking_code, buyer_name, buyer_phone, buyer_tax_id, user_id, subtotal, tax_amount, total_amount, items_json, created_at, notes, shipping_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $invoiceNo, $taxUniqueId, $trackingCode, $cName, $cPhone, $cTaxId, $userId,
            $subtotal, $taxAmount, $grandTotal, json_encode($items, JSON_UNESCAPED_UNICODE), date('Y/m/d H:i'), $cNotes, 'در حال پردازش'
        ]);

        $invoiceId = $db->lastInsertId();

        // ثبت تاریخچه وضعیت
        $stmt = $db->prepare("INSERT INTO order_status_history (invoice_id, status, description, created_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$invoiceId, 'در حال پردازش', 'سفارش با موفقیت ثبت شد و در انتظار تایید است.', date('Y/m/d H:i')]);

        // کاهش موجودی
        foreach ($prodList as $pr) {
            $qty = (int)$_SESSION['cart'][$pr['id']];
            $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$qty, $pr['id']]);
        }

        $_SESSION['cart'] = [];
        header("Location: index.php?page=invoice&id=" . $taxUniqueId);
        exit;
    } else {
        $message = "لطفاً اطلاعات را با فرمت درست وارد کنید.";
        $msgType = "error";
    }
}

// ثبت فرم RFQ
if (isset($_POST['btn_rfq'])) {
    $comp = trim($_POST['rfq_company'] ?? '');
    $phone = trim($_POST['rfq_phone'] ?? '');
    $desc = trim($_POST['rfq_desc'] ?? '');

    if (!empty($comp) && preg_match('/^09[0-9]{9}$/', $phone) && !empty($desc)) {
        $rfqCode = 'RFQ-' . date('y') . '-' . random_int(100, 999);
        $userId = $_SESSION['user']['id'] ?? null;
        $stmt = $db->prepare("INSERT INTO rfqs (rfq_code, company, phone, description, created_at, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$rfqCode, $comp, $phone, $desc, date('Y/m/d'), $userId]);
        $message = "استعلام با کد پیگیری {$rfqCode} با موفقیت ثبت شد. کارشناسان ما به زودی با شما تماس خواهند گرفت.";
        $msgType = "success";
    } else {
        $message = "لطفاً همه موارد استعلام را به درستی تکمیل فرمایید.";
        $msgType = "error";
    }
}

include 'header.php';

if ($message): ?>
  <div class="toast-bar <?= $msgType ?>" id="siteToast">
    <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i>
    <span><?= e($message) ?></span>
  </div>
<?php endif;

$page = $_GET['page'] ?? 'home';

// صفحه خانه - بهبود یافته
if ($page === 'home'): 
  $searchQ = trim($_GET['q'] ?? '');
  $cat = $_GET['cat'] ?? null;
  $sort = $_GET['sort'] ?? 'newest';

  $where = [];
  $params = [];
  if ($cat) {
      $where[] = "category = ?";
      $params[] = $cat;
  }
  if ($searchQ) {
      $where[] = "(name LIKE ? OR brand LIKE ? OR tax_id LIKE ? OR description LIKE ?)";
      $like = "%$searchQ%";
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
  }
  $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";
  $orderSql = "ORDER BY is_featured DESC, id DESC";
  if ($sort === 'price_low') $orderSql = "ORDER BY price ASC";
  if ($sort === 'price_high') $orderSql = "ORDER BY price DESC";
  if ($sort === 'name') $orderSql = "ORDER BY name ASC";

  $stmt = $db->prepare("SELECT * FROM products $whereSql $orderSql");
  $stmt->execute($params);
  $products = $stmt->fetchAll();
  $totalProducts = count($products);
  $featuredCount = $db->query("SELECT COUNT(*) FROM products WHERE is_featured=1")->fetchColumn();
?>

  <section class="hero">
    <div style="position:relative; z-index:1">
      <h1>خرید بی‌واسطه تجهیزات کارگاهی و مهندسی با فاکتور رسمی</h1>
      <p>صدور فوری صورتحساب الکترونیکی نوع ۱ با شناسه ۲۲ رقمی اختصاصی و پذیرش قطعی در ممیزی مالیاتی. تحویل سراسر کشور با ضمانت اصالت کالا.</p>
      
      <div class="hero-search no-print">
        <form method="GET" action="index.php" class="search-box">
          <input type="hidden" name="page" value="home">
          <input type="text" name="q" placeholder="جستجوی تجهیزات، برند یا کد مالیاتی..." value="<?= e($searchQ) ?>">
          <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> جستجو</button>
        </form>
      </div>

      <div class="hero-stats">
        <div class="hero-stat"><i class="fa-solid fa-boxes-stacked"></i> <?= $db->query("SELECT COUNT(*) FROM products")->fetchColumn() ?> قلم کالا موجود</div>
        <div class="hero-stat"><i class="fa-solid fa-file-invoice-dollar"></i> فاکتور رسمی دارایی</div>
        <div class="hero-stat"><i class="fa-solid fa-truck-fast"></i> ارسال فوری به سراسر کشور</div>
        <div class="hero-stat"><i class="fa-solid fa-shield-halved"></i> ضمانت اصالت و گارانتی</div>
      </div>
    </div>
  </section>

  <div class="trust-bar no-print">
    <div class="trust-item">
      <i class="fa-solid fa-certificate"></i>
      <div><strong>صورتحساب الکترونیکی</strong><small>مورد تایید سامانه مؤدیان</small></div>
    </div>
    <div class="trust-item">
      <i class="fa-solid fa-rotate-left"></i>
      <div><strong>۷ روز ضمانت بازگشت</strong><small>بدون قید و شرط</small></div>
    </div>
    <div class="trust-item">
      <i class="fa-solid fa-headset"></i>
      <div><strong>پشتیبانی تخصصی</strong><small>شنبه تا چهارشنبه ۸ تا ۱۷:۳۰</small></div>
    </div>
    <div class="trust-item">
      <i class="fa-solid fa-truck"></i>
      <div><strong>ارسال رایگان بالای ۵ میلیون</strong><small>تهران و شهرستان</small></div>
    </div>
  </div>

  <div class="sec-head">
    <h2 class="sec-title">
      <i class="fa-solid fa-fire" style="color:var(--orange)"></i>
      <?= $cat ? 'دسته: ' . e($cat) : ($searchQ ? 'نتایج جستجو برای: ' . e($searchQ) : 'تجهیزات و ادوات مهندسی پرتقاضا') ?>
      <span style="background:var(--g100);padding:2px 10px;border-radius:20px;font-size:12px;color:var(--g500)"><?= $totalProducts ?> کالا</span>
    </h2>
    <div class="sec-actions no-print">
      <div class="filter-pills">
        <a href="index.php?page=home&q=<?= e($searchQ) ?>&cat=<?= e($cat ?? '') ?>&sort=newest" class="filter-pill <?= $sort==='newest'?'active':'' ?>">جدیدترین</a>
        <a href="index.php?page=home&q=<?= e($searchQ) ?>&cat=<?= e($cat ?? '') ?>&sort=price_low" class="filter-pill <?= $sort==='price_low'?'active':'' ?>">ارزان‌ترین</a>
        <a href="index.php?page=home&q=<?= e($searchQ) ?>&cat=<?= e($cat ?? '') ?>&sort=price_high" class="filter-pill <?= $sort==='price_high'?'active':'' ?>">گران‌ترین</a>
      </div>
      <div style="display:flex;gap:6px">
        <input type="text" id="liveSearch" placeholder="فیلتر سریع..." style="padding:6px 12px;border:1px solid var(--g200);border-radius:20px;font-family:inherit;font-size:12px;width:140px">
      </div>
    </div>
  </div>

  <?php if (empty($products)): ?>
    <div class="empty-state">
      <i class="fa-solid fa-magnifying-glass"></i>
      <h3>محصولی یافت نشد</h3>
      <p>برای عبارت "<?= e($searchQ) ?>" نتیجه‌ای وجود ندارد. عبارت دیگری را امتحان کنید.</p>
      <a href="index.php?page=home" class="btn btn-primary" style="margin-top:12px">مشاهده همه محصولات</a>
    </div>
  <?php else: ?>
  <div class="pro-grid">
    <?php foreach ($products as $p): 
      $stockClass = $p['stock'] > 10 ? 'stock-ok' : ($p['stock'] > 0 ? 'stock-low' : 'stock-low');
      $stockText = $p['stock'] > 10 ? 'موجود' : ($p['stock'] > 0 ? 'تنها ' . $p['stock'] . ' عدد' : 'ناموجود');
    ?>
      <div class="pro-card" data-name="<?= e($p['name']) ?>" data-brand="<?= e($p['brand']) ?>" data-code="<?= e($p['tax_id']) ?>" data-category="<?= e($p['category']) ?>">
        <div class="pro-thumb">
          <?= $p['icon'] ?>
          <?php if ($p['is_featured']): ?><span class="pro-badge featured"><i class="fa-solid fa-star"></i> ویژه</span><?php endif; ?>
          <span class="pro-badge <?= $stockClass ?>" style="top:auto;bottom:10px;right:10px;left:auto"><?= $stockText ?></span>
          <div style="position:absolute;top:10px;left:10px;display:flex;flex-direction:column;gap:6px">
            <a href="index.php?action=wishlist_add&id=<?= $p['id'] ?>" title="علاقه‌مندی" style="width:32px;height:32px;background:#fff;border:1px solid var(--g200);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#EC4899;box-shadow:var(--shadow-sm);font-size:14px"><i class="fa-solid fa-heart"></i></a>
            <a href="index.php?action=compare_add&id=<?= $p['id'] ?>" title="مقایسه تخصصی" style="width:32px;height:32px;background:#fff;border:1px solid var(--g200);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--blue);box-shadow:var(--shadow-sm);font-size:12px"><i class="fa-solid fa-code-compare"></i></a>
          </div>
        </div>
        <div class="pro-body">
          <span class="pro-brand"><i class="fa-solid fa-tag"></i> <?= e($p['brand']) ?></span>
          <h4 class="pro-name"><?= e($p['name']) ?></h4>
          <?php if ($p['description']): ?><div class="pro-desc"><?= e(mb_substr($p['description'],0,70)) ?>...</div><?php endif; ?>
          <span class="pro-taxcode"><i class="fa-solid fa-barcode"></i> <?= e($p['tax_id']) ?></span>
          <div class="pro-stock"><i class="fa-solid fa-cubes"></i> موجودی: <?= $p['stock'] ?> عدد | <?= e($p['warranty'] ?? '12 ماه گارانتی') ?></div>
          <div class="pro-bottom">
            <div class="pro-price"><?= number_format($p['price']) ?> <small>تومان</small></div>
            <?php if ($p['stock'] > 0): ?>
              <a href="index.php?action=add_cart&id=<?= $p['id'] ?>" class="btn btn-sm btn-primary btn-add-cart"><i class="fa-solid fa-cart-plus"></i> افزودن</a>
            <?php else: ?>
              <span class="btn btn-sm btn-secondary" style="opacity:.6">ناموجود</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php // سبد خرید بهبود یافته
elseif ($page === 'cart'): ?>
  <div class="sec-head">
    <h2 class="sec-title"><i class="fa-solid fa-cart-shopping"></i> سبد تجهیزات انتخابی</h2>
    <?php if (!empty($_SESSION['cart'])): ?>
      <a href="index.php?action=cart_clear" onclick="return confirmDelete('سبد خرید خالی شود؟')" class="btn btn-sm btn-secondary"><i class="fa-solid fa-trash"></i> خالی کردن سبد</a>
    <?php endif; ?>
  </div>

  <div class="cart-wrap">
    <div>
      <div class="cart-table">
        <?php if (empty($_SESSION['cart'])): ?>
          <div class="empty-state">
            <i class="fa-solid fa-cart-shopping"></i>
            <h3>سبد خرید شما خالی است</h3>
            <p>هنوز هیچ محصولی به سبد اضافه نکرده‌اید</p>
            <a href="index.php?page=home" class="btn btn-primary" style="margin-top:12px">مشاهده محصولات</a>
          </div>
        <?php else:
          $pIds = array_keys($_SESSION['cart']);
          $inClause = implode(',', array_fill(0, count($pIds), '?'));
          $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($inClause)");
          $stmt->execute($pIds);
          $cartItems = $stmt->fetchAll();
          $subtotal = 0;

          foreach ($cartItems as $cItem):
            $qty = (int)$_SESSION['cart'][$cItem['id']];
            $rowTot = $cItem['price'] * $qty;
            $subtotal += $rowTot; ?>
            <div class="cart-row">
              <div style="font-size:28px;width:50px;text-align:center;background:var(--g50);border-radius:10px;padding:8px"><?= $cItem['icon'] ?></div>
              <div style="flex:1">
                <div style="font-weight:700;font-size:13.5px"><?= e($cItem['name']) ?></div>
                <div style="font-size:11px;color:var(--g500)"><i class="fa-solid fa-barcode"></i> <?= e($cItem['tax_id']) ?> | برند: <?= e($cItem['brand']) ?></div>
                <div style="font-size:12px;color:var(--blue);font-weight:700;margin-top:2px"><?= number_format($cItem['price']) ?> تومان × <?= $qty ?></div>
              </div>
              <div style="display:flex;align-items:center;gap:6px;background:var(--g100);padding:4px;border-radius:20px">
                <a href="index.php?action=cart_update&op=dec&id=<?= $cItem['id'] ?>" class="btn btn-sm btn-secondary" style="border-radius:50%;width:28px;height:28px;padding:0">-</a>
                <span style="font-weight:800;padding:0 10px;min-width:30px;text-align:center"><?= $qty ?></span>
                <a href="index.php?action=cart_update&op=inc&id=<?= $cItem['id'] ?>" class="btn btn-sm btn-secondary" style="border-radius:50%;width:28px;height:28px;padding:0">+</a>
              </div>
              <div style="font-weight:900;width:120px;text-align:left;color:var(--navy)"><?= number_format($rowTot) ?> ت</div>
              <a href="index.php?action=cart_update&op=del&id=<?= $cItem['id'] ?>" style="color:var(--red);font-weight:800;width:28px;height:28px;display:flex;align-items:center;justify-content:center;background:var(--red-light);border-radius:50%">✕</a>
            </div>
          <?php endforeach;
        endif; ?>
      </div>

      <?php if (!empty($_SESSION['cart'])): ?>
        <div style="margin-top:14px;background:#fff;border:1px dashed var(--g200);border-radius:12px;padding:14px;display:flex;gap:10px;align-items:center">
          <i class="fa-solid fa-truck-fast" style="color:var(--green);font-size:20px"></i>
          <div style="font-size:12.5px"><strong>ارسال رایگان</strong> برای سفارشات بالای ۵ میلیون تومان فعال شد! <span style="color:var(--green)">شما واجد شرایط ارسال رایگان هستید.</span></div>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['cart'])):
      $taxRate = (int)getSetting('tax_rate', 10);
      $tax = (int)round($subtotal * $taxRate / 100);
      $total = $subtotal + $tax; ?>
      <div class="cart-summary">
        <h3 style="font-size:15px;font-weight:900;border-bottom:2px solid var(--g100);padding-bottom:12px;margin-bottom:16px;display:flex;align-items:center;gap:8px">
          <i class="fa-solid fa-file-invoice-dollar" style="color:var(--blue)"></i> صدور پیش‌فاکتور با شناسه مالیاتی
        </h3>
        <form method="POST" action="index.php">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <div class="input-group">
            <label><i class="fa-solid fa-building"></i> نام شرکت / شخصیت حقوقی *</label>
            <input type="text" name="company_name" required value="<?= e($_SESSION['user']['company'] ?? '') ?>" placeholder="مثلاً: شرکت مهندسی پارس">
          </div>
          <div class="input-group">
            <label><i class="fa-solid fa-phone"></i> شماره مسئول خرید *</label>
            <input type="text" name="phone" required maxlength="11" value="<?= e($_SESSION['user']['phone'] ?? '') ?>" placeholder="0912XXXXXXX">
          </div>
          <div class="input-group">
            <label><i class="fa-solid fa-id-card"></i> شناسه ملی / کد اقتصادی</label>
            <input type="text" name="tax_id" value="<?= e($_SESSION['user']['national_id'] ?? '10103456789') ?>">
          </div>
          <div class="input-group">
            <label><i class="fa-solid fa-note-sticky"></i> توضیحات سفارش (اختیاری)</label>
            <textarea name="notes" rows="2" placeholder="مثلاً: تحویل فوری، بسته‌بندی ویژه..."></textarea>
          </div>

          <div style="background:var(--g50);border-radius:12px;padding:14px;margin:14px 0">
            <div class="sum-line"><span>جمع خالص اقلام:</span><span><?= number_format($subtotal) ?> ت</span></div>
            <div class="sum-line"><span>مالیات ارزش افزوده (<?= $taxRate ?>٪):</span><span><?= number_format($tax) ?> ت</span></div>
            <div class="sum-line total"><span>مبلغ قابل پرداخت:</span><span><?= number_format($total) ?> تومان</span></div>
            <div style="font-size:11px;color:var(--g500);margin-top:8px"><i class="fa-solid fa-circle-info"></i> این مبلغ شامل ۱۰٪ ارزش افزوده و انتقال به کارپوشه مؤدیان است.</div>
          </div>

          <button type="submit" name="btn_checkout" class="btn btn-green" style="width:100%;padding:14px;font-size:14px"><i class="fa-solid fa-paper-plane"></i> صدور و ارسال به کارپوشه دارایی</button>
          <div style="text-align:center;margin-top:10px;font-size:11px;color:var(--g500)"><i class="fa-solid fa-lock"></i> پرداخت امن و صدور آنی فاکتور رسمی</div>
        </form>
      </div>
    <?php endif; ?>
  </div>

<?php // صفحه پیگیری سفارش عمومی
elseif ($page === 'track'): 
  $trackCode = $_GET['code'] ?? '';
  $invoice = null;
  $history = [];
  if ($trackCode) {
      $stmt = $db->prepare("SELECT * FROM invoices WHERE tracking_code = ? OR invoice_no = ? OR tax_unique_id = ?");
      $stmt->execute([$trackCode, $trackCode, $trackCode]);
      $invoice = $stmt->fetch();
      if ($invoice) {
          $stmt = $db->prepare("SELECT * FROM order_status_history WHERE invoice_id = ? ORDER BY id ASC");
          $stmt->execute([$invoice['id']]);
          $history = $stmt->fetchAll();
      }
  }
?>
  <div class="sec-head">
    <h2 class="sec-title"><i class="fa-solid fa-truck-fast"></i> پیگیری سفارش</h2>
  </div>

  <div class="track-box">
    <h3 style="font-size:16px;font-weight:800;margin-bottom:6px">کد پیگیری سفارش خود را وارد کنید</h3>
    <p style="color:var(--g500);font-size:13px;margin-bottom:16px">کد پیگیری (TRK-xxxxxx) یا شماره فاکتور یا شناسه مالیاتی را وارد نمایید</p>
    
    <form onsubmit="trackOrderSubmit(event)" class="track-search">
      <input type="text" id="trackingInput" placeholder="مثلاً: TRK-123456 یا INV-2401-1234" value="<?= e($trackCode) ?>">
      <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-magnifying-glass"></i> رهگیری</button>
    </form>

    <?php if ($trackCode && !$invoice): ?>
      <div style="background:var(--red-light);border:1px solid #FCA5A5;color:var(--red);padding:14px;border-radius:12px;display:flex;gap:10px;align-items:center">
        <i class="fa-solid fa-circle-exclamation" style="font-size:20px"></i>
        <div><strong>سفارشی یافت نشد!</strong><br><small>کد وارد شده "<?= e($trackCode) ?>" در سیستم موجود نیست. لطفاً کد را بررسی کنید.</small></div>
      </div>
    <?php elseif ($invoice): 
      $items = json_decode($invoice['items_json'], true);
      $statuses = getShippingStatuses();
      $currentStatus = $invoice['shipping_status'] ?? 'در حال پردازش';
      $statusOrder = ['در حال پردازش', 'تایید شده', 'ارسال شده', 'تحویل داده شده'];
      $currentIndex = array_search($currentStatus, $statusOrder);
      if ($currentIndex === false) $currentIndex = 0;
    ?>
      <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;margin-top:20px" class="cart-wrap">
        <div>
          <div style="background:var(--blue-light);border:1px solid var(--blue-soft);border-radius:12px;padding:16px;margin-bottom:16px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px">
            <div>
              <div style="font-size:12px;color:var(--g500)">شماره فاکتور</div>
              <strong style="font-size:15px"><?= e($invoice['invoice_no']) ?></strong>
            </div>
            <div>
              <div style="font-size:12px;color:var(--g500)">کد پیگیری</div>
              <strong style="font-size:15px;color:var(--blue)"><?= e($invoice['tracking_code']) ?> <button onclick="copyTracking('<?= e($invoice['tracking_code']) ?>')" style="background:none;border:none;color:var(--blue);cursor:pointer"><i class="fa-regular fa-copy"></i></button></strong>
            </div>
            <div>
              <div style="font-size:12px;color:var(--g500)">تاریخ ثبت</div>
              <strong><?= e($invoice['created_at']) ?></strong>
            </div>
            <div>
              <div style="font-size:12px;color:var(--g500)">مبلغ کل</div>
              <strong style="color:var(--green)"><?= number_format($invoice['total_amount']) ?> ت</strong>
            </div>
          </div>

          <h4 style="font-weight:800;margin-bottom:12px"><i class="fa-solid fa-list-check"></i> وضعیت سفارش</h4>
          <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
            <?php foreach ($statusOrder as $idx => $st): 
              $isCompleted = $idx <= $currentIndex;
              $isActive = $idx === $currentIndex;
              $info = $statuses[$st] ?? ['color'=>'#64748B','icon'=>'⏳'];
            ?>
              <div style="flex:1;min-width:100px;text-align:center;padding:12px 8px;border-radius:12px;border:2px solid <?= $isCompleted ? $info['color'] : 'var(--g200)' ?>;background:<?= $isCompleted ? $info['color'].'15' : '#fff' ?>;position:relative">
                <div style="width:32px;height:32px;border-radius:50%;background:<?= $isCompleted ? $info['color'] : 'var(--g200)' ?>;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 6px;font-size:14px"><?= $isCompleted ? '✓' : $info['icon'] ?></div>
                <div style="font-size:12px;font-weight:700;color:<?= $isCompleted ? $info['color'] : 'var(--g500)' ?>"><?= $st ?></div>
                <?php if ($isActive): ?><div style="font-size:10px;background:var(--blue);color:#fff;padding:2px 6px;border-radius:10px;margin-top:4px;display:inline-block">وضعیت فعلی</div><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <h4 style="font-weight:800;margin:20px 0 12px"><i class="fa-solid fa-clock-rotate-left"></i> تاریخچه سفارش</h4>
          <div class="timeline">
            <?php if (empty($history)): ?>
              <div class="timeline-item completed">
                <div class="timeline-dot">✓</div>
                <div class="timeline-content">
                  <strong>سفارش ثبت شد</strong>
                  <small><?= e($invoice['created_at']) ?> - سفارش شما با موفقیت ثبت و در انتظار بررسی است.</small>
                </div>
              </div>
            <?php else: foreach ($history as $h): 
              $isLast = $h === end($history);
            ?>
              <div class="timeline-item <?= $isLast ? 'active' : 'completed' ?>">
                <div class="timeline-dot"><?= $isLast ? '●' : '✓' ?></div>
                <div class="timeline-content">
                  <strong><?= e($h['status']) ?></strong>
                  <small><?= e($h['created_at']) ?> - <?= e($h['description']) ?></small>
                </div>
              </div>
            <?php endforeach; endif; ?>
            <div class="timeline-item <?= $currentStatus === 'تحویل داده شده' ? 'completed' : '' ?>">
              <div class="timeline-dot" style="border-color:<?= $currentStatus === 'تحویل داده شده' ? 'var(--green)' : 'var(--g200)' ?>">📦</div>
              <div class="timeline-content">
                <strong>تحویل به مشتری</strong>
                <small>پس از ارسال، کد رهگیری پستی برای شما پیامک خواهد شد.</small>
              </div>
            </div>
          </div>

          <h4 style="font-weight:800;margin:20px 0 12px"><i class="fa-solid fa-box"></i> اقلام سفارش (<?= count($items) ?> قلم)</h4>
          <div style="background:var(--g50);border-radius:12px;padding:12px">
            <?php foreach ($items as $it): ?>
              <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--g200);font-size:13px">
                <span><?= e($it['name']) ?> × <?= $it['qty'] ?></span>
                <strong><?= number_format($it['total']) ?> ت</strong>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <div style="background:#fff;border:1px solid var(--g200);border-radius:12px;padding:16px;position:sticky;top:100px">
            <h4 style="font-weight:800;margin-bottom:12px"><i class="fa-solid fa-circle-info"></i> اطلاعات تحویل</h4>
            <div style="font-size:13px;line-height:1.8">
              <div><span style="color:var(--g500)">خریدار:</span> <strong><?= e($invoice['buyer_name']) ?></strong></div>
              <div><span style="color:var(--g500)">تماس:</span> <?= e($invoice['buyer_phone']) ?></div>
              <div><span style="color:var(--g500)">شناسه مالیاتی:</span> <code style="background:var(--g100);padding:2px 6px;border-radius:4px;font-size:11px"><?= e($invoice['tax_unique_id']) ?></code></div>
              <div style="margin-top:12px;padding-top:12px;border-top:1px dashed var(--g200)">
                <div style="display:flex;justify-content:space-between"><span>جمع اقلام:</span><span><?= number_format($invoice['subtotal']) ?> ت</span></div>
                <div style="display:flex;justify-content:space-between"><span>ارزش افزوده:</span><span><?= number_format($invoice['tax_amount']) ?> ت</span></div>
                <div style="display:flex;justify-content:space-between;font-weight:900;color:var(--blue);margin-top:6px;font-size:14px"><span>مبلغ کل:</span><span><?= number_format($invoice['total_amount']) ?> ت</span></div>
              </div>
            </div>
            <div style="margin-top:14px;display:flex;gap:8px">
              <a href="index.php?page=invoice&id=<?= e($invoice['tax_unique_id']) ?>" class="btn btn-primary" style="flex:1"><i class="fa-solid fa-print"></i> مشاهده فاکتور</a>
              <a href="index.php?page=home" class="btn btn-secondary">بازگشت</a>
            </div>
            <div style="margin-top:12px;background:var(--green-soft);border:1px solid var(--green-light);border-radius:8px;padding:10px;font-size:11.5px;color:var(--green)">
              <i class="fa-solid fa-shield-halved"></i> این فاکتور به صورت خودکار به کارپوشه مالیاتی شما منتقل شده و اعتبار ارزش افزوده آن قابل استفاده است.
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

<?php // چاپ فاکتور
elseif ($page === 'invoice'):
  $taxId = $_GET['id'] ?? '';
  $stmt = $db->prepare("SELECT * FROM invoices WHERE tax_unique_id = ?");
  $stmt->execute([$taxId]);
  $inv = $stmt->fetch();
  if (!$inv): echo "<div class='empty-state'><i class='fa-solid fa-file-circle-xmark'></i><h3>صورتحسابی یافت نشد</h3><a href='index.php?page=home' class='btn btn-primary'>بازگشت به خانه</a></div>"; else:
    $items = json_decode($inv['items_json'], true); ?>
    <div class="invoice-box-printable">
      <div style="display:flex;justify-content:space-between;border-bottom:3px solid var(--navy);padding-bottom:16px;margin-bottom:20px;flex-wrap:wrap;gap:10px">
        <div>
          <h2 style="font-size:18px;font-weight:900;color:var(--navy);display:flex;align-items:center;gap:8px"><i class="fa-solid fa-file-invoice" style="color:var(--blue)"></i> صورتحساب الکترونیکی فروش کالا و خدمات</h2>
          <small style="color:var(--g500)">مطابق ماده ۵ قانون پایانه‌های فروشگاهی و سامانه مؤدیان - نوع اول</small>
          <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
            <span class="status-pill status-success"><i class="fa-solid fa-check"></i> <?= e($inv['status']) ?></span>
            <span class="status-pill status-info"><?= e($inv['shipping_status']) ?></span>
          </div>
        </div>
        <div style="text-align:left;font-size:12px;background:var(--g50);padding:12px;border-radius:10px;border:1px solid var(--g200)">
          <div>شماره منحصر به‌فرد مالیاتی: <strong style="color:var(--blue)"><?= e($inv['tax_unique_id']) ?></strong></div>
          <div>شماره فاکتور: <strong><?= e($inv['invoice_no']) ?></strong> | تاریخ: <?= e($inv['created_at']) ?></div>
          <div>کد پیگیری: <strong style="color:var(--green)"><?= e($inv['tracking_code']) ?></strong></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;background:var(--g50);border:1px solid var(--g200);border-radius:12px;padding:16px;font-size:12.5px;margin-bottom:20px">
        <div>
          <strong style="color:var(--navy)"><i class="fa-solid fa-store"></i> فروشنده:</strong> شرکت <?= e(getSetting('site_name')) ?> سهامی خاص<br>
          شناسه ملی: ۱۰۱۰۹۹۸۸۷۷۶ | کد اقتصادی: ۴۱۱۵۶۷۸۹۰۰۱<br>
          تلفن: <?= e(getSetting('site_phone')) ?> | <?= e(getSetting('site_address')) ?>
        </div>
        <div>
          <strong style="color:var(--navy)"><i class="fa-solid fa-user-tie"></i> خریدار:</strong> <?= e($inv['buyer_name']) ?><br>
          شماره تماس رابط: <?= e($inv['buyer_phone']) ?><br>
          شناسه ملی / کد اقتصادی: <?= e($inv['buyer_tax_id']) ?><br>
          <?php if ($inv['notes']): ?>توضیحات: <?= e($inv['notes']) ?><?php endif; ?>
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
              <td><?= $idx + 1 ?></td>
              <td><code style="background:var(--g100);padding:2px 6px;border-radius:4px"><?= e($it['tax_id']) ?></code></td>
              <td><strong><?= e($it['name']) ?></strong><br><small style="color:var(--g500)"><?= e($it['brand']) ?></small></td>
              <td><?= $it['qty'] ?></td>
              <td><?= number_format($it['price']) ?></td>
              <td><?= number_format($lineTax) ?></td>
              <td><strong><?= number_format($it['total'] + $lineTax) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="display:flex;justify-content:space-between;margin-top:24px;border-top:2px dashed var(--g200);padding-top:16px;flex-wrap:wrap;gap:14px">
        <div style="font-size:12px;color:var(--g700);max-width:500px;background:var(--green-soft);border:1px solid var(--green-light);padding:12px;border-radius:10px">
          <i class="fa-solid fa-circle-check" style="color:var(--green)"></i> <strong>اعتبار مالیاتی:</strong> ارزش افزوده این سند به صورت خودکار به کارپوشه مؤدیان خریدار منتقل گردیده و در ممیزی دارایی معتبر است. کد پیگیری جهت استعلام: <?= e($inv['tracking_code']) ?>
        </div>
        <div style="text-align:left;line-height:1.9;background:var(--g50);padding:14px;border-radius:12px;min-width:240px">
          <div>جمع اقلام: <?= number_format($inv['subtotal']) ?> تومان</div>
          <div>مالیات ارزش افزوده: <?= number_format($inv['tax_amount']) ?> تومان</div>
          <div style="font-size:16px;font-weight:900;color:var(--blue);border-top:1px solid var(--g200);margin-top:8px;padding-top:8px">مبلغ کل نهایی: <?= number_format($inv['total_amount']) ?> تومان</div>
        </div>
      </div>
    </div>

    <div class="no-print flex-center gap-10" style="margin-top:24px;flex-wrap:wrap">
      <button class="btn btn-green btn-lg" onclick="printInvoice()"><i class="fa-solid fa-print"></i> چاپ فاکتور رسمی A4</button>
      <a href="index.php?page=track&code=<?= e($inv['tracking_code']) ?>" class="btn btn-secondary btn-lg"><i class="fa-solid fa-truck"></i> پیگیری سفارش</a>
      <a href="index.php?page=dashboard" class="btn btn-secondary btn-lg"><i class="fa-solid fa-gauge"></i> پنل کاربری</a>
    </div>
  <?php endif;

// داشبورد کاربری بهبود یافته
elseif ($page === 'dashboard'):
  if (empty($_SESSION['user'])) { header("Location: index.php?page=home"); exit; }
  $user = $_SESSION['user'];
  $tab = $_GET['tab'] ?? 'overview';
  
  // آمار کاربر
  $userInvoices = $db->prepare("SELECT * FROM invoices WHERE user_id = ? OR buyer_phone = ? ORDER BY id DESC");
  $userInvoices->execute([$user['id'], $user['phone']]);
  $invoices = $userInvoices->fetchAll();
  $totalSpent = array_sum(array_column($invoices, 'total_amount'));
  $totalOrders = count($invoices);
  $lastOrder = $invoices[0] ?? null;

  $userRfqs = $db->prepare("SELECT * FROM rfqs WHERE user_id = ? OR phone = ? ORDER BY id DESC");
  $userRfqs->execute([$user['id'], $user['phone']]);
  $rfqs = $userRfqs->fetchAll();

  // سابقه خرید - گروه‌بندی محصولات
  $purchaseHistory = [];
  foreach ($invoices as $inv) {
      $items = json_decode($inv['items_json'], true);
      foreach ($items as $it) {
          $key = $it['name'];
          if (!isset($purchaseHistory[$key])) {
              $purchaseHistory[$key] = ['name'=>$it['name'], 'brand'=>$it['brand'], 'qty'=>0, 'total'=>0, 'last_date'=>$inv['created_at'], 'icon'=>$it['icon'] ?? '📦'];
          }
          $purchaseHistory[$key]['qty'] += $it['qty'];
          $purchaseHistory[$key]['total'] += $it['total'];
          if ($inv['created_at'] > $purchaseHistory[$key]['last_date']) $purchaseHistory[$key]['last_date'] = $inv['created_at'];
      }
  }
?>
  <?php
  // داده‌های تخصصی برای پنل خریدار
  $wishlistCount = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
  $wishlistCount->execute([$user['id']]);
  $wishlistCount = $wishlistCount->fetchColumn();

  $addressesCount = $db->prepare("SELECT COUNT(*) FROM addresses WHERE user_id = ?");
  $addressesCount->execute([$user['id']]);
  $addressesCount = $addressesCount->fetchColumn();

  $ticketsCount = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ?");
  $ticketsCount->execute([$user['id']]);
  $ticketsCount = $ticketsCount->fetchColumn();
  $openTickets = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status='باز'");
  $openTickets->execute([$user['id']]);
  $openTickets = $openTickets->fetchColumn();

  $notificationsCount = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read=0");
  $notificationsCount->execute([$user['id']]);
  $notificationsCount = $notificationsCount->fetchColumn();
  ?>
  <div class="dashboard-layout">
    <aside class="dash-sidebar">
      <div class="user-profile-widget">
        <div class="user-avatar-lg"><?= mb_substr($user['company'] ?? $user['name'],0,1) ?></div>
        <h3 style="font-size:15px;font-weight:800;color:#fff"><?= e($user['company'] ?? $user['name']) ?></h3>
        <p style="font-size:12px;color:#94A3B8"><?= e($user['phone']) ?> | <?= e($user['province'] ?? 'تهران') ?></p>
        <div style="margin-top:10px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
          <span class="badge" style="background:rgba(255,255,255,.15);color:#fff"><?= $totalOrders ?> سفارش</span>
          <span class="badge" style="background:rgba(16,185,129,.2);color:#6EE7B7"><?= number_format($totalSpent/1000000,1) ?> م ت</span>
          <span class="badge" style="background:rgba(59,130,246,.2);color:#93C5FD"><?= $wishlistCount ?> علاقه‌مندی</span>
        </div>
        <div style="margin-top:10px;background:rgba(255,255,255,.08);border-radius:8px;padding:6px 10px;font-size:11px;display:flex;justify-content:space-between">
          <span><i class="fa-solid fa-star" style="color:#FBBF24"></i> مشتری <?= $totalOrders>5?'ویژه':'عادی' ?></span>
          <span style="color:#6EE7B7"><i class="fa-solid fa-shield-halved"></i> تایید شده</span>
        </div>
      </div>

      <!-- منوی تخصصی خریدار - هیچ منویی از قلم نیافتاده -->
      <div style="padding:10px;">
        <div style="font-size:10px;font-weight:800;color:#64748B;letter-spacing:.5px;padding:8px 10px 4px">سفارشات و خرید</div>
        <ul class="dash-menu">
          <li class="dash-menu-item <?= $tab==='overview'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=overview'"><i class="fa-solid fa-gauge-high"></i> نمای کلی 360°</li>
          <li class="dash-menu-item <?= $tab==='orders'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=orders'"><i class="fa-solid fa-receipt"></i> سفارشات من <span style="margin-right:auto;background:var(--blue);color:#fff;padding:2px 8px;border-radius:10px;font-size:11px"><?= $totalOrders ?></span></li>
          <li class="dash-menu-item <?= $tab==='track'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=track'"><i class="fa-solid fa-truck-fast"></i> پیگیری مرسولات <span style="margin-right:auto;background:#8B5CF6;color:#fff;padding:2px 6px;border-radius:8px;font-size:10px">TRK</span></li>
          <li class="dash-menu-item <?= $tab==='history'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=history'"><i class="fa-solid fa-clock-rotate-left"></i> سابقه خرید تخصصی</li>
          <li class="dash-menu-item <?= $tab==='invoices'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=invoices'"><i class="fa-solid fa-file-invoice-dollar"></i> فاکتورهای مالیاتی</li>
          <li class="dash-menu-item <?= $tab==='returns'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=returns'"><i class="fa-solid fa-rotate-left"></i> مرجوعی و بازگشت</li>
        </ul>

        <div style="font-size:10px;font-weight:800;color:#64748B;letter-spacing:.5px;padding:12px 10px 4px">علاقه‌مندی و آدرس</div>
        <ul class="dash-menu">
          <li class="dash-menu-item <?= $tab==='wishlist'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=wishlist'"><i class="fa-solid fa-heart"></i> علاقه‌مندی‌ها <span style="margin-right:auto;background:#EC4899;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px"><?= $wishlistCount ?></span></li>
          <li class="dash-menu-item <?= $tab==='addresses'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=addresses'"><i class="fa-solid fa-location-dot"></i> آدرس‌های من <span style="margin-right:auto;background:rgba(0,0,0,.08);padding:2px 8px;border-radius:10px;font-size:11px"><?= $addressesCount ?></span></li>
          <li class="dash-menu-item <?= $tab==='compare'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=compare'"><i class="fa-solid fa-code-compare"></i> مقایسه محصولات</li>
        </ul>

        <div style="font-size:10px;font-weight:800;color:#64748B;letter-spacing:.5px;padding:12px 10px 4px">استعلام و پشتیبانی</div>
        <ul class="dash-menu">
          <li class="dash-menu-item <?= $tab==='rfq'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=rfq'"><i class="fa-solid fa-file-circle-question"></i> استعلام‌های من (RFQ) <span style="margin-right:auto;background:var(--orange);color:#fff;padding:2px 8px;border-radius:10px;font-size:11px"><?= count($rfqs) ?></span></li>
          <li class="dash-menu-item <?= $tab==='tickets'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=tickets'"><i class="fa-solid fa-headset"></i> تیکت پشتیبانی <span style="margin-right:auto;background:<?= $openTickets>0?'var(--red)':'#94A3B8' ?>;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px"><?= $openTickets>0?$openTickets.' باز':$ticketsCount ?></span></li>
          <li class="dash-menu-item <?= $tab==='notifications'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=notifications'"><i class="fa-solid fa-bell"></i> اطلاعیه‌ها <span style="margin-right:auto;background:<?= $notificationsCount>0?'var(--red)':'transparent' ?>;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px"><?= $notificationsCount>0?$notificationsCount:'' ?></span></li>
        </ul>

        <div style="font-size:10px;font-weight:800;color:#64748B;letter-spacing:.5px;padding:12px 10px 4px">حساب کاربری</div>
        <ul class="dash-menu">
          <li class="dash-menu-item <?= $tab==='financial'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=financial'"><i class="fa-solid fa-sack-dollar"></i> امور مالی و اعتباری</li>
          <li class="dash-menu-item <?= $tab==='profile'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=profile'"><i class="fa-solid fa-user-gear"></i> پروفایل شرکت</li>
          <li class="dash-menu-item <?= $tab==='security'?'active':'' ?>" onclick="window.location.href='index.php?page=dashboard&tab=security'"><i class="fa-solid fa-shield-halved"></i> امنیت و حریم خصوصی</li>
          <li class="dash-menu-item logout" onclick="window.location.href='index.php?action=logout'"><i class="fa-solid fa-right-from-bracket"></i> خروج از حساب</li>
        </ul>
      </div>

      <div style="padding:14px">
        <div style="background:linear-gradient(135deg,var(--blue-light),#fff);border:1px solid var(--blue-soft);border-radius:10px;padding:12px;font-size:11.5px">
          <strong style="color:var(--blue)"><i class="fa-solid fa-headset"></i> پشتیبانی تخصصی</strong><br>
          <small style="color:var(--g700)">📞 <?= e(getSetting('site_phone')) ?><br>💬 واتساپ: <?= e(getSetting('site_whatsapp')) ?><br>🕒 <?= e(getSetting('support_hours')) ?></small><br>
          <a href="index.php?page=dashboard&tab=tickets" class="btn btn-sm btn-primary" style="margin-top:8px;width:100%"><i class="fa-solid fa-plus"></i> ثبت تیکت جدید</a>
        </div>
        <div style="margin-top:10px;background:var(--g50);border-radius:8px;padding:8px 10px;font-size:10px;color:var(--g500);line-height:1.6">
          ✅ منوهای تخصصی کامل<br>
          ✅ هیچ بخشی از قلم نیافتاده<br>
          ✅ سفارش، پیگیری، سابقه، مالی، آدرس، علاقه‌مندی، RFQ، پشتیبانی
        </div>
      </div>
    </aside>

    <main class="dash-main">
      <?php if ($tab === 'overview'): ?>
        <div class="dash-header">
          <div class="dash-title"><i class="fa-solid fa-gauge" style="color:var(--blue)"></i> داشبورد کاربری - نمای کلی</div>
          <div style="font-size:12px;color:var(--g500)">آخرین بروزرسانی: <?= date('Y/m/d H:i') ?></div>
        </div>
        <div class="dash-content">
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-icon blue"><i class="fa-solid fa-receipt"></i></div>
              <div class="stat-info"><strong><?= $totalOrders ?></strong><small>کل سفارشات</small></div>
            </div>
            <div class="stat-card">
              <div class="stat-icon green"><i class="fa-solid fa-sack-dollar"></i></div>
              <div class="stat-info"><strong><?= number_format($totalSpent) ?></strong><small>مجموع خرید (تومان)</small></div>
            </div>
            <div class="stat-card">
              <div class="stat-icon orange"><i class="fa-solid fa-file-circle-question"></i></div>
              <div class="stat-info"><strong><?= count($rfqs) ?></strong><small>استعلام‌ها</small></div>
            </div>
            <div class="stat-card">
              <div class="stat-icon purple"><i class="fa-solid fa-boxes-stacked"></i></div>
              <div class="stat-info"><strong><?= count($purchaseHistory) ?></strong><small>محصولات خریداری شده</small></div>
            </div>
          </div>

          <?php if ($lastOrder): ?>
          <div style="background:linear-gradient(135deg,var(--blue-light),#fff);border:1px solid var(--blue-soft);border-radius:14px;padding:18px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div>
              <div style="font-size:12px;color:var(--g500)">آخرین سفارش شما</div>
              <strong style="font-size:15px"><?= e($lastOrder['invoice_no']) ?> - <?= e($lastOrder['tracking_code']) ?></strong>
              <div style="font-size:12px;color:var(--g700)"><?= e($lastOrder['created_at']) ?> | <?= number_format($lastOrder['total_amount']) ?> تومان | <span class="status-pill status-info"><?= e($lastOrder['shipping_status']) ?></span></div>
            </div>
            <div style="display:flex;gap:8px">
              <a href="index.php?page=track&code=<?= e($lastOrder['tracking_code']) ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-truck"></i> پیگیری</a>
              <a href="index.php?page=invoice&id=<?= e($lastOrder['tax_unique_id']) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eye"></i> فاکتور</a>
            </div>
          </div>
          <?php endif; ?>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div style="background:#fff;border:1px solid var(--g200);border-radius:14px;padding:16px">
              <h4 style="font-weight:800;margin-bottom:12px"><i class="fa-solid fa-clock-rotate-left"></i> آخرین سفارشات</h4>
              <?php if (empty($invoices)): ?>
                <div style="text-align:center;padding:20px;color:var(--g500)">سفارشی ثبت نشده</div>
              <?php else: foreach (array_slice($invoices,0,3) as $in): ?>
                <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--g100);font-size:13px">
                  <div><strong><?= e($in['invoice_no']) ?></strong><br><small style="color:var(--g500)"><?= e($in['created_at']) ?></small></div>
                  <div style="text-align:left"><div style="font-weight:800"><?= number_format($in['total_amount']) ?> ت</div><span class="status-pill status-success" style="font-size:10px"><?= e($in['shipping_status']) ?></span></div>
                </div>
              <?php endforeach; endif; ?>
              <a href="index.php?page=dashboard&tab=orders" style="display:block;text-align:center;margin-top:10px;color:var(--blue);font-weight:700;font-size:13px">مشاهده همه سفارشات →</a>
            </div>
            <div style="background:#fff;border:1px solid var(--g200);border-radius:14px;padding:16px">
              <h4 style="font-weight:800;margin-bottom:12px"><i class="fa-solid fa-star"></i> پرخریدترین محصولات شما</h4>
              <?php if (empty($purchaseHistory)): ?>
                <div style="text-align:center;padding:20px;color:var(--g500)">سابقه خریدی وجود ندارد</div>
              <?php else: 
                uasort($purchaseHistory, fn($a,$b)=>$b['qty']<=>$a['qty']);
                foreach (array_slice($purchaseHistory,0,3) as $ph): ?>
                <div style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--g100);align-items:center">
                  <div style="font-size:20px;background:var(--g50);width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px"><?= $ph['icon'] ?></div>
                  <div style="flex:1"><div style="font-weight:700;font-size:13px"><?= e($ph['name']) ?></div><small style="color:var(--g500)"><?= $ph['qty'] ?> عدد خریداری شده</small></div>
                  <strong style="font-size:12px"><?= number_format($ph['total']) ?> ت</strong>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div>
        </div>

      <?php elseif ($tab === 'orders'): ?>
        <div class="dash-header">
          <div class="dash-title"><i class="fa-solid fa-receipt"></i> سفارشات و فاکتورهای من (<?= $totalOrders ?>)</div>
          <a href="index.php?page=home" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> سفارش جدید</a>
        </div>
        <div class="dash-content">
          <table class="data-table">
            <thead>
              <tr>
                <th>شماره فاکتور / پیگیری</th>
                <th>شناسه مالیاتی</th>
                <th>تاریخ</th>
                <th>مبلغ کل</th>
                <th>وضعیت ارسال</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($invoices)): ?>
                <tr><td colspan="6" style="text-align:center;padding:40px"><div class="empty-state" style="padding:20px"><i class="fa-solid fa-receipt"></i><h3>سفارشی یافت نشد</h3><p>هنوز هیچ سفارشی ثبت نکرده‌اید</p></div></td></tr>
              <?php else: foreach ($invoices as $in): 
                $statusInfo = getShippingStatuses()[$in['shipping_status']] ?? ['color'=>'#64748B'];
              ?>
                <tr>
                  <td>
                    <strong><?= e($in['invoice_no']) ?></strong><br>
                    <small style="color:var(--blue);font-weight:700"><i class="fa-solid fa-truck-fast"></i> <?= e($in['tracking_code']) ?></small>
                  </td>
                  <td><code style="font-size:11px;background:var(--g100);padding:2px 6px;border-radius:4px"><?= e(substr($in['tax_unique_id'],0,20)) ?>...</code></td>
                  <td><?= e($in['created_at']) ?></td>
                  <td><strong><?= number_format($in['total_amount']) ?></strong><br><small style="color:var(--g500)"><?= number_format($in['tax_amount']) ?> مالیات</small></td>
                  <td><span class="status-pill" style="background:<?= $statusInfo['color'] ?>15;color:<?= $statusInfo['color'] ?>;border:1px solid <?= $statusInfo['color'] ?>30"><?= e($in['shipping_status']) ?></span></td>
                  <td>
                    <div class="action-btns">
                      <a href="index.php?page=invoice&id=<?= e($in['tax_unique_id']) ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-eye"></i> فاکتور</a>
                      <a href="index.php?page=track&code=<?= e($in['tracking_code']) ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-location-dot"></i> پیگیری</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'track'): ?>
        <div class="dash-header">
          <div class="dash-title"><i class="fa-solid fa-truck-fast"></i> پیگیری سفارشات</div>
        </div>
        <div class="dash-content">
          <div style="background:var(--blue-light);border:1px solid var(--blue-soft);border-radius:12px;padding:16px;margin-bottom:20px">
            <h4 style="font-weight:800;margin-bottom:8px"><i class="fa-solid fa-circle-info"></i> راهنمای پیگیری</h4>
            <p style="font-size:13px;color:var(--g700)">کد پیگیری (TRK) پس از ثبت سفارش برای شما پیامک می‌شود. همچنین می‌توانید از جدول زیر مستقیماً سفارشات خود را پیگیری کنید.</p>
          </div>

          <form onsubmit="trackOrderSubmit(event)" class="track-search" style="max-width:500px">
            <input type="text" id="trackingInput" placeholder="کد پیگیری را وارد کنید...">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> پیگیری</button>
          </form>

          <h4 style="font-weight:800;margin:20px 0 12px">سفارشات اخیر شما</h4>
          <div style="display:grid;gap:12px">
            <?php foreach ($invoices as $in): ?>
              <div style="background:#fff;border:1px solid var(--g200);border-radius:12px;padding:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div>
                  <strong><?= e($in['invoice_no']) ?> - <?= e($in['tracking_code']) ?></strong>
                  <div style="font-size:12px;color:var(--g500)"><?= e($in['created_at']) ?> | <?= number_format($in['total_amount']) ?> تومان</div>
                  <div style="margin-top:6px"><span class="status-pill status-info"><?= e($in['shipping_status']) ?></span> <span class="status-pill status-success"><?= e($in['status']) ?></span></div>
                </div>
                <div style="display:flex;gap:8px">
                  <a href="index.php?page=track&code=<?= e($in['tracking_code']) ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-truck"></i> رهگیری</a>
                  <button onclick="copyTracking('<?= e($in['tracking_code']) ?>')" class="btn btn-secondary btn-sm"><i class="fa-regular fa-copy"></i> کپی کد</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      <?php elseif ($tab === 'history'): ?>
        <div class="dash-header">
          <div class="dash-title"><i class="fa-solid fa-clock-rotate-left"></i> سابقه خرید و محصولات پرتکرار</div>
          <div style="font-size:12px;color:var(--g500)">مجموع <?= count($purchaseHistory) ?> محصول منحصر به فرد | <?= $totalOrders ?> فاکتور</div>
        </div>
        <div class="dash-content">
          <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
            <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-boxes-stacked"></i></div><div class="stat-info"><strong><?= count($purchaseHistory) ?></strong><small>تنوع کالای خریداری شده</small></div></div>
            <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-cubes"></i></div><div class="stat-info"><strong><?= array_sum(array_column($purchaseHistory,'qty')) ?></strong><small>تعداد کل اقلام خریداری شده</small></div></div>
            <div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-sack-dollar"></i></div><div class="stat-info"><strong><?= number_format($totalSpent) ?></strong><small>مجموع خرید (تومان)</small></div></div>
          </div>

          <?php if (empty($purchaseHistory)): ?>
            <div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i><h3>سابقه خریدی وجود ندارد</h3><p>پس از اولین خرید، تاریخچه خرید شما اینجا نمایش داده می‌شود</p></div>
          <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>محصول</th>
                <th>برند</th>
                <th>تعداد خریداری شده</th>
                <th>مجموع پرداختی</th>
                <th>آخرین خرید</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($purchaseHistory as $ph): ?>
                <tr>
                  <td><div style="display:flex;gap:8px;align-items:center"><span style="font-size:20px"><?= $ph['icon'] ?></span><strong><?= e($ph['name']) ?></strong></div></td>
                  <td><span class="status-pill status-secondary"><?= e($ph['brand']) ?></span></td>
                  <td><strong><?= $ph['qty'] ?> عدد</strong></td>
                  <td><?= number_format($ph['total']) ?> ت</td>
                  <td><small><?= e($ph['last_date']) ?></small></td>
                  <td><a href="index.php?page=home&q=<?= urlencode($ph['name']) ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-rotate"></i> سفارش مجدد</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>

      <?php elseif ($tab === 'rfq'): ?>
        <div class="dash-header">
          <div class="dash-title"><i class="fa-solid fa-file-circle-question"></i> استعلام‌های قیمت من (<?= count($rfqs) ?>)</div>
          <a href="index.php?page=rfq" class="btn btn-orange btn-sm"><i class="fa-solid fa-plus"></i> استعلام جدید</a>
        </div>
        <div class="dash-content">
          <table class="data-table">
            <thead>
              <tr><th>کد RFQ</th><th>شرکت</th><th>تاریخ</th><th>وضعیت</th><th>شرح درخواست</th></tr>
            </thead>
            <tbody>
              <?php if (empty($rfqs)): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--g500)">هنوز استعلامی ثبت نکرده‌اید</td></tr>
              <?php else: foreach ($rfqs as $r): ?>
                <tr>
                  <td><strong><?= e($r['rfq_code']) ?></strong></td>
                  <td><?= e($r['company']) ?></td>
                  <td><?= e($r['created_at']) ?></td>
                  <td><span class="status-pill status-warning"><?= e($r['status']) ?></span></td>
                  <td><small><?= e(mb_substr($r['description'],0,80)) ?>...</small></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'profile'): ?>
        <div class="dash-header">
          <div class="dash-title"><i class="fa-solid fa-user-gear"></i> پروفایل تخصصی شرکت و اطلاعات حساب</div>
          <div style="font-size:12px;color:var(--g500)">آخرین بروزرسانی: <?= e($user['created_at'] ?? '') ?></div>
        </div>
        <div class="dash-content">
          <form method="POST" action="index.php?page=dashboard&tab=profile">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="input-row">
              <div class="input-group"><label><i class="fa-solid fa-user"></i> نام و نام‌خانوادگی رابط *</label><input type="text" name="name" value="<?= e($user['name']) ?>" required></div>
              <div class="input-group"><label><i class="fa-solid fa-building"></i> نام شرکت / سازمان *</label><input type="text" name="company" value="<?= e($user['company']) ?>"></div>
            </div>
            <div class="input-row">
              <div class="input-group"><label><i class="fa-solid fa-phone"></i> شماره همراه (غیرقابل تغییر)</label><input type="text" value="<?= e($user['phone']) ?>" disabled style="background:var(--g100)"><small style="color:var(--g500)">برای تغییر شماره با پشتیبانی تماس بگیرید</small></div>
              <div class="input-group"><label><i class="fa-solid fa-envelope"></i> ایمیل سازمانی</label><input type="email" name="email" value="<?= e($user['email']) ?>" placeholder="info@company.com"></div>
            </div>
            <div class="input-row">
              <div class="input-group"><label><i class="fa-solid fa-id-card"></i> شناسه ملی / کد اقتصادی</label><input type="text" name="national_id" value="<?= e($user['national_id']) ?>" placeholder="1010xxxxxxx"></div>
              <div class="input-group"><label><i class="fa-solid fa-location-dot"></i> استان</label><select name="province" style="width:100%;padding:10px 14px;border:1px solid var(--g200);border-radius:10px;font-family:inherit"><option <?= ($user['province']??'')==='تهران'?'selected':'' ?>>تهران</option><option <?= ($user['province']??'')==='اصفهان'?'selected':'' ?>>اصفهان</option><option <?= ($user['province']??'')==='فارس'?'selected':'' ?>>فارس</option><option <?= ($user['province']??'')==='خراسان رضوی'?'selected':'' ?>>خراسان رضوی</option><option>سایر</option></select></div>
            </div>
            <div class="input-row">
              <div class="input-group"><label><i class="fa-solid fa-city"></i> شهر</label><input type="text" name="city" value="<?= e($user['city'] ?? 'تهران') ?>"></div>
              <div class="input-group"><label><i class="fa-solid fa-map"></i> آدرس دفتر / کارگاه</label><input type="text" name="address" value="<?= e($user['address']) ?>" placeholder="تهران، خیابان..."></div>
            </div>
            <button type="submit" name="btn_update_profile" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> ذخیره پروفایل تخصصی</button>
          </form>

          <div style="margin-top:30px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div style="background:var(--g50);border:1px solid var(--g200);border-radius:12px;padding:16px">
              <h4 style="font-weight:800;margin-bottom:10px"><i class="fa-solid fa-shield-halved"></i> اطلاعات حساب تخصصی</h4>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px">
                <div><span style="color:var(--g500)">تاریخ عضویت:</span><br><strong><?= e($user['created_at'] ?? 'نامشخص') ?></strong></div>
                <div><span style="color:var(--g500)">تعداد سفارشات:</span><br><strong><?= $totalOrders ?> سفارش</strong></div>
                <div><span style="color:var(--g500)">مجموع خرید:</span><br><strong><?= number_format($totalSpent) ?> تومان</strong></div>
                <div><span style="color:var(--g500)">وضعیت حساب:</span><br><span class="status-pill status-success">فعال و تایید شده</span></div>
                <div><span style="color:var(--g500)">نوع مشتری:</span><br><strong><?= $totalOrders>5?'مشتری ویژه - تخفیف 15%':'مشتری عادی' ?></strong></div>
                <div><span style="color:var(--g500)">اعتبار مالیاتی:</span><br><strong style="color:var(--green)"><?= number_format($totalSpent/10) ?> تومان</strong></div>
              </div>
            </div>
            <div style="background:linear-gradient(135deg,var(--blue-light),#fff);border:1px solid var(--blue-soft);border-radius:12px;padding:16px">
              <h4 style="font-weight:800;margin-bottom:10px;color:var(--blue)"><i class="fa-solid fa-gift"></i> مزایای حساب تخصصی شما</h4>
              <ul style="font-size:12.5px;line-height:1.9;color:var(--g700);margin-right:16px">
                <li>فاکتور رسمی با شناسه 22 رقمی و اعتبار مالیاتی</li>
                <li>ارسال رایگان برای سفارشات بالای <?= number_format((int)getSetting('free_shipping_min',5000000)) ?> تومان</li>
                <li>تخفیف سازمانی تا 15% برای مشتریان ویژه</li>
                <li>پشتیبانی تخصصی و مشاوره فنی رایگان</li>
                <li>ضمانت اصالت و 7 روز بازگشت</li>
              </ul>
            </div>
          </div>
        </div>

      <?php elseif ($tab === 'wishlist'): 
        $stmt = $db->prepare("SELECT p.* FROM wishlist w JOIN products p ON w.product_id=p.id WHERE w.user_id=? ORDER BY w.id DESC");
        $stmt->execute([$user['id']]);
        $wishlist = $stmt->fetchAll();
      ?>
        <div class="dash-header">
          <div class="dash-title"><i class="fa-solid fa-heart" style="color:#EC4899"></i> علاقه‌مندی‌های من (<?= count($wishlist) ?>)</div>
          <a href="index.php?page=home" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> افزودن محصول</a>
        </div>
        <div class="dash-content">
          <?php if (empty($wishlist)): ?>
            <div class="empty-state"><i class="fa-solid fa-heart"></i><h3>لیست علاقه‌مندی خالی است</h3><p>محصولات مورد علاقه خود را به لیست اضافه کنید تا بعداً سریع‌تر سفارش دهید</p><a href="index.php?page=home" class="btn btn-primary">مشاهده محصولات</a></div>
          <?php else: ?>
            <div class="pro-grid" style="grid-template-columns:repeat(3,1fr)">
              <?php foreach ($wishlist as $p): ?>
                <div class="pro-card"><div class="pro-thumb"><?= $p['icon'] ?><span class="pro-badge" style="background:#EC4899"><i class="fa-solid fa-heart"></i> علاقه‌مندی</span></div><div class="pro-body"><span class="pro-brand"><?= e($p['brand']) ?></span><h4 class="pro-name"><?= e($p['name']) ?></h4><div class="pro-price"><?= number_format($p['price']) ?> <small>تومان</small></div><div class="pro-bottom"><a href="index.php?action=add_cart&id=<?= $p['id'] ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-cart-plus"></i> افزودن به سبد</a><a href="index.php?action=wishlist_remove&id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-trash"></i></a></div></div></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      <?php elseif ($tab === 'addresses'): 
        $stmt = $db->prepare("SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC, id DESC");
        $stmt->execute([$user['id']]);
        $addresses = $stmt->fetchAll();
      ?>
        <div class="dash-header">
          <div class="dash-title"><i class="fa-solid fa-location-dot" style="color:var(--red)"></i> آدرس‌های من (<?= count($addresses) ?>)</div>
          <button class="btn btn-primary btn-sm" onclick="openModal('addressModal')"><i class="fa-solid fa-plus"></i> افزودن آدرس جدید</button>
        </div>
        <div class="dash-content">
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:20px">
            <?php if (empty($addresses)): ?>
              <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--g500)"><i class="fa-solid fa-location-dot" style="font-size:32px;display:block;margin-bottom:10px;color:var(--g200)"></i>هنوز آدرسی ثبت نکرده‌اید<br><small>برای ارسال سریع‌تر، آدرس‌های خود را اضافه کنید</small></div>
            <?php else: foreach ($addresses as $ad): ?>
              <div style="background:#fff;border:1px solid <?= $ad['is_default']?'var(--blue)':'var(--g200)' ?>;border-radius:12px;padding:16px;position:relative">
                <?php if ($ad['is_default']): ?><span style="position:absolute;top:10px;left:10px;background:var(--blue);color:#fff;padding:2px 8px;border-radius:10px;font-size:10px">پیش‌فرض</span><?php endif; ?>
                <strong style="display:block;margin-bottom:6px"><i class="fa-solid fa-tag"></i> <?= e($ad['title']) ?></strong>
                <div style="font-size:13px;line-height:1.7;color:var(--g700)"><?= e($ad['province']) ?>، <?= e($ad['city']) ?>، <?= e($ad['address']) ?><br><small>کد پستی: <?= e($ad['postal_code']) ?> | گیرنده: <?= e($ad['receiver_name']) ?> - <?= e($ad['phone']) ?></small></div>
                <div style="margin-top:10px;display:flex;gap:6px"><a href="index.php?action=delete_address&id=<?= $ad['id'] ?>" onclick="return confirmDelete()" class="btn btn-sm btn-secondary" style="color:var(--red)"><i class="fa-solid fa-trash"></i> حذف</a></div>
              </div>
            <?php endforeach; endif; ?>
          </div>

          <div style="background:#fff;border:1px solid var(--g200);border-radius:14px;padding:20px">
            <h4 style="font-weight:800;margin-bottom:14px"><i class="fa-solid fa-plus"></i> افزودن آدرس تخصصی جدید</h4>
            <form method="POST" action="index.php?page=dashboard&tab=addresses">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <div class="input-row"><div class="input-group"><label>عنوان آدرس * (مثلاً: دفتر مرکزی، کارگاه)</label><input type="text" name="title" required placeholder="دفتر مرکزی"></div><div class="input-group"><label>نام گیرنده *</label><input type="text" name="receiver_name" required value="<?= e($user['name']) ?>"></div></div>
              <div class="input-row"><div class="input-group"><label>استان *</label><select name="province" required style="width:100%;padding:10px 14px;border:1px solid var(--g200);border-radius:10px;font-family:inherit"><option>تهران</option><option>اصفهان</option><option>فارس</option><option>خراسان رضوی</option><option>آذربایجان شرقی</option><option>خوزستان</option><option>سایر</option></select></div><div class="input-group"><label>شهر *</label><input type="text" name="city" required placeholder="تهران"></div></div>
              <div class="input-group"><label>آدرس کامل *</label><textarea name="address" rows="2" required placeholder="خیابان، کوچه، پلاک، واحد..."></textarea></div>
              <div class="input-row"><div class="input-group"><label>کد پستی</label><input type="text" name="postal_code" placeholder="1234567890"></div><div class="input-group"><label>شماره تماس تحویل</label><input type="text" name="phone" value="<?= e($user['phone']) ?>"></div></div>
              <div style="display:flex;gap:10px;align-items:center"><label style="display:flex;gap:6px;align-items:center;font-size:13px"><input type="checkbox" name="is_default" style="width:auto"> تنظیم به عنوان آدرس پیش‌فرض</label><button type="submit" name="btn_add_address" class="btn btn-primary" style="margin-right:auto"><i class="fa-solid fa-floppy-disk"></i> ذخیره آدرس</button></div>
            </form>
          </div>
        </div>

      <?php elseif ($tab === 'tickets'): 
        $stmt = $db->prepare("SELECT * FROM support_tickets WHERE user_id=? ORDER BY id DESC");
        $stmt->execute([$user['id']]);
        $tickets = $stmt->fetchAll();
      ?>
        <div class="dash-header">
          <div class="dash-title"><i class="fa-solid fa-headset" style="color:var(--blue)"></i> تیکت‌های پشتیبانی تخصصی (<?= count($tickets) ?>)</div>
          <button class="btn btn-primary btn-sm" onclick="openModal('ticketModal')"><i class="fa-solid fa-plus"></i> ثبت تیکت جدید</button>
        </div>
        <div class="dash-content">
          <div style="display:grid;gap:12px;margin-bottom:20px">
            <?php if (empty($tickets)): ?>
              <div style="text-align:center;padding:40px;color:var(--g500)"><i class="fa-solid fa-headset" style="font-size:32px;display:block;margin-bottom:10px;color:var(--g200)"></i>هنوز تیکتی ثبت نکرده‌اید<br><small>سوال یا مشکلی دارید؟ تیکت جدید ثبت کنید</small></div>
            <?php else: foreach ($tickets as $tk): ?>
              <div style="background:#fff;border:1px solid var(--g200);border-radius:12px;padding:16px">
                <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px"><div><strong><?= e($tk['ticket_code']) ?> - <?= e($tk['subject']) ?></strong><br><small style="color:var(--g500)"><?= e($tk['category']) ?> | اولویت: <?= e($tk['priority']) ?> | <?= e($tk['created_at']) ?></small></div><span class="status-pill <?= $tk['status']==='باز'?'status-danger':($tk['status']==='پاسخ داده شده'?'status-success':'status-warning') ?>"><?= e($tk['status']) ?></span></div>
                <div style="margin-top:10px;background:var(--g50);border-radius:8px;padding:10px;font-size:13px"><strong>پیام شما:</strong> <?= e($tk['message']) ?></div>
                <?php if ($tk['admin_reply']): ?><div style="margin-top:8px;background:var(--blue-light);border:1px solid var(--blue-soft);border-radius:8px;padding:10px;font-size:13px"><strong style="color:var(--blue)"><i class="fa-solid fa-user-shield"></i> پاسخ پشتیبانی:</strong><br><?= e($tk['admin_reply']) ?></div><?php endif; ?>
              </div>
            <?php endforeach; endif; ?>
          </div>

          <div style="background:#fff;border:1px solid var(--g200);border-radius:14px;padding:20px">
            <h4 style="font-weight:800;margin-bottom:14px"><i class="fa-solid fa-plus"></i> ثبت تیکت پشتیبانی تخصصی جدید</h4>
            <form method="POST" action="index.php?page=dashboard&tab=tickets">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <div class="input-row"><div class="input-group"><label>موضوع تیکت *</label><input type="text" name="subject" required placeholder="مثلاً: مشکل در فاکتور، سوال فنی..."></div><div class="input-group"><label>دسته‌بندی</label><select name="category" style="width:100%;padding:10px 14px;border:1px solid var(--g200);border-radius:10px;font-family:inherit"><option>عمومی</option><option>فنی</option><option>مالی و فاکتور</option><option>ارسال و پیگیری</option><option>مرجوعی</option><option>پیشنهاد</option></select></div></div>
              <div class="input-row"><div class="input-group"><label>اولویت</label><select name="priority" style="width:100%;padding:10px 14px;border:1px solid var(--g200);border-radius:10px;font-family:inherit"><option>کم</option><option selected>متوسط</option><option>بالا</option><option>فوری</option></select></div><div class="input-group"><label>شماره تماس (اختیاری)</label><input type="text" value="<?= e($user['phone']) ?>" disabled style="background:var(--g100)"></div></div>
              <div class="input-group"><label>متن پیام تخصصی *</label><textarea name="message" rows="4" required placeholder="لطفاً مشکل یا سوال خود را با جزئیات کامل بنویسید..."></textarea></div>
              <button type="submit" name="btn_add_ticket" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> ارسال تیکت تخصصی</button>
            </form>
          </div>
        </div>

      <?php elseif ($tab === 'invoices'): ?>
        <div class="dash-header">
          <div class="dash-title"><i class="fa-solid fa-file-invoice-dollar" style="color:var(--green)"></i> فاکتورهای مالیاتی و اسناد رسمی</div>
          <div style="font-size:12px;color:var(--g500)">اعتبار مالیاتی کل: <?= number_format($totalSpent/10) ?> تومان</div>
        </div>
        <div class="dash-content">
          <div style="background:var(--green-soft);border:1px solid var(--green-light);border-radius:12px;padding:14px;margin-bottom:16px;display:flex;gap:10px;align-items:center">
            <div style="width:40px;height:40px;background:var(--green);color:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px"><i class="fa-solid fa-certificate"></i></div>
            <div><strong>فاکتورهای رسمی مورد تایید سامانه مؤدیان</strong><br><small style="color:var(--g700)">تمامی فاکتورها با شناسه 22 رقمی و اعتبار ارزش افزوده 10% صادر شده و به کارپوشه مالیاتی شما منتقل گردیده است.</small></div>
          </div>
          <table class="data-table"><thead><tr><th>شماره فاکتور</th><th>شناسه مالیاتی 22 رقمی</th><th>تاریخ</th><th>مبلغ + مالیات</th><th>اعتبار مالیاتی</th><th>عملیات</th></tr></thead><tbody>
            <?php foreach ($invoices as $in): ?>
              <tr><td><strong><?= e($in['invoice_no']) ?></strong><br><small style="color:var(--blue)"><?= e($in['tracking_code']) ?></small></td><td><code style="font-size:10px;background:var(--g100);padding:2px 6px;border-radius:4px"><?= e($in['tax_unique_id']) ?></code></td><td><?= e($in['created_at']) ?></td><td><strong><?= number_format($in['total_amount']) ?></strong><br><small>مالیات: <?= number_format($in['tax_amount']) ?></small></td><td><span style="color:var(--green);font-weight:800">+<?= number_format($in['tax_amount']) ?> ت</span><br><small style="color:var(--g500)">قابل استفاده در اظهارنامه</small></td><td><div class="action-btns"><a href="index.php?page=invoice&id=<?= e($in['tax_unique_id']) ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-print"></i> چاپ</a><button class="btn btn-sm btn-secondary" onclick="copyTracking('<?= e($in['tax_unique_id']) ?>')"><i class="fa-regular fa-copy"></i></button></div></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>

      <?php elseif (in_array($tab, ['financial','returns','compare','notifications','security'])): ?>
        <div class="dash-header">
          <div class="dash-title"><i class="fa-solid fa-<?= $tab==='financial'?'sack-dollar':($tab==='returns'?'rotate-left':($tab==='notifications'?'bell':($tab==='security'?'shield-halved':'code-compare'))) ?>" style="color:var(--blue)"></i> 
            <?= $tab==='financial'?'امور مالی و اعتباری تخصصی':($tab==='returns'?'مرجوعی و بازگشت کالا تخصصی':($tab==='notifications'?'اطلاعیه‌ها و پیام‌های تخصصی':($tab==='security'?'امنیت و حریم خصوصی تخصصی':'مقایسه تخصصی محصولات'))) ?>
          </div>
          <?php if ($tab==='compare' && !empty($_SESSION['compare'])): ?><a href="index.php?action=compare_clear" class="btn btn-sm btn-secondary"><i class="fa-solid fa-trash"></i> پاک کردن مقایسه</a><?php endif; ?>
        </div>
        <div class="dash-content">
          <?php if ($tab==='financial'): 
            $monthlyFinancial = $db->prepare("SELECT substr(created_at,1,7) as month, COUNT(*) as cnt, SUM(total_amount) as total, SUM(tax_amount) as tax FROM invoices WHERE user_id=? OR buyer_phone=? GROUP BY month ORDER BY month DESC");
            $monthlyFinancial->execute([$user['id'], $user['phone']]);
            $monthlyFinancial = $monthlyFinancial->fetchAll();
          ?>
            <div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
              <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-sack-dollar"></i></div><div class="stat-info"><strong><?= number_format($totalSpent) ?></strong><small>کل خرید (تومان)</small><small style="color:var(--g500)"><?= $totalOrders ?> فاکتور</small></div></div>
              <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-percent"></i></div><div class="stat-info"><strong><?= number_format($totalSpent/10) ?></strong><small>اعتبار مالیاتی 10%</small><small style="color:var(--green)">قابل استفاده در اظهارنامه</small></div></div>
              <div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-gift"></i></div><div class="stat-info"><strong><?= $totalOrders>5?'15%':'5%' ?></strong><small>تخفیف مشتری <?= $totalOrders>5?'ویژه':'عادی' ?></small><small style="color:var(--orange)">تا سقف 5 میلیون</small></div></div>
              <div class="stat-card"><div class="stat-icon purple"><i class="fa-solid fa-wallet"></i></div><div class="stat-info"><strong><?= number_format($totalSpent*0.02) ?></strong><small>کیف پول اعتباری</small><small style="color:#8B5CF6">2% بازگشت نقدی</small></div></div>
            </div>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:20px">
              <div style="background:#fff;border:1px solid var(--g200);border-radius:12px;padding:16px">
                <h4 style="font-weight:800;margin-bottom:12px"><i class="fa-solid fa-chart-line"></i> گزارش مالی ماهانه تخصصی</h4>
                <table class="data-table"><thead><tr><th>ماه</th><th>تعداد فاکتور</th><th>مبلغ خرید</th><th>مالیات 10%</th><th>اعتبار مالیاتی</th><th>تخفیف</th></tr></thead><tbody>
                  <?php foreach ($monthlyFinancial as $mf): ?>
                    <tr><td><?= e($mf['month']) ?></td><td><?= $mf['cnt'] ?></td><td><?= number_format($mf['total']) ?> ت</td><td><?= number_format($mf['tax']) ?> ت</td><td style="color:var(--green);font-weight:800"><?= number_format($mf['tax']) ?> ت</td><td><?= number_format($mf['total']*0.05) ?> ت</td></tr>
                  <?php endforeach; 
                  if (empty($monthlyFinancial)) echo "<tr><td colspan='6' style='text-align:center;padding:20px;color:var(--g500)'>هنوز فاکتوری ثبت نشده</td></tr>";
                  ?>
                </tbody></table>
              </div>
              <div style="display:flex;flex-direction:column;gap:12px">
                <div style="background:linear-gradient(135deg,var(--green-light),#fff);border:1px solid #6EE7B7;border-radius:12px;padding:14px">
                  <h4 style="font-weight:800;color:var(--green);font-size:13px"><i class="fa-solid fa-certificate"></i> اعتبار مالیاتی شما</h4>
                  <div style="font-size:24px;font-weight:900;color:var(--green);margin:8px 0"><?= number_format($totalSpent/10) ?> تومان</div>
                  <small style="color:var(--g700);font-size:11px;line-height:1.6">این مبلغ به صورت خودکار به کارپوشه مالیاتی شما در سامانه مؤدیان منتقل شده و در اظهارنامه ارزش افزوده قابل استفاده است. کد اقتصادی: <?= e($user['economic_code'] ?? '411589342110') ?></small>
                </div>
                <div style="background:var(--blue-light);border:1px solid var(--blue-soft);border-radius:12px;padding:14px">
                  <h4 style="font-weight:800;color:var(--blue);font-size:13px"><i class="fa-solid fa-gift"></i> باشگاه مشتریان ویژه</h4>
                  <div style="font-size:12px;line-height:1.8;color:var(--g700);margin-top:6px">
                    سطح فعلی: <strong><?= $totalOrders>10?'طلایی':($totalOrders>5?'نقره‌ای':'برنزی') ?></strong><br>
                    تخفیف فعلی: <strong><?= $totalOrders>5?'15%':'5%' ?></strong><br>
                    تا سطح بعدی: <?= max(0,6-$totalOrders) ?> سفارش دیگر<br>
                    <div style="margin-top:8px;height:6px;background:#DBEAFE;border-radius:10px"><div style="width:<?= min(100,($totalOrders/10)*100) ?>%;height:100%;background:var(--blue);border-radius:10px"></div></div>
                  </div>
                </div>
              </div>
            </div>

            <div style="background:#fff;border:1px solid var(--g200);border-radius:12px;padding:16px">
              <h4 style="font-weight:800;margin-bottom:12px"><i class="fa-solid fa-file-invoice-dollar"></i> صورتحساب‌های مالی و روش پرداخت</h4>
              <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;font-size:12px">
                <div style="background:var(--g50);padding:10px;border-radius:8px"><i class="fa-solid fa-credit-card" style="color:var(--blue)"></i> <strong>پرداخت آنلاین:</strong><br><small><?= $totalOrders ?> تراکنش - <?= number_format($totalSpent) ?> ت</small></div>
                <div style="background:var(--g50);padding:10px;border-radius:8px"><i class="fa-solid fa-building-columns" style="color:var(--green)"></i> <strong>اعتباری سازمانی:</strong><br><small>سقف: 100 میلیون - مانده: <?= number_format(100000000-$totalSpent) ?> ت</small></div>
                <div style="background:var(--g50);padding:10px;border-radius:8px"><i class="fa-solid fa-money-bill" style="color:var(--orange)"></i> <strong>کیف پول:</strong><br><small>موجودی: <?= number_format($totalSpent*0.02) ?> ت - 2% بازگشت</small></div>
              </div>
            </div>

          <?php elseif ($tab==='returns'): 
            $returnInvoices = $db->prepare("SELECT * FROM invoices WHERE (user_id=? OR buyer_phone=?) AND shipping_status IN ('مرجوعی','لغو شده','در حال بررسی مرجوعی') ORDER BY id DESC");
            $returnInvoices->execute([$user['id'],$user['phone']]);
            $returnInvoices = $returnInvoices->fetchAll();
          ?>
            <div style="display:grid;grid-template-columns:1fr 360px;gap:16px">
              <div>
                <div style="background:#fff;border:1px solid var(--g200);border-radius:12px;padding:16px;margin-bottom:16px">
                  <h4 style="font-weight:800;margin-bottom:12px"><i class="fa-solid fa-rotate-left" style="color:var(--red)"></i> درخواست‌های مرجوعی شما (<?= count($returnInvoices) ?>)</h4>
                  <?php if (empty($returnInvoices)): ?>
                    <div style="text-align:center;padding:30px;color:var(--g500)"><i class="fa-solid fa-rotate-left" style="font-size:32px;display:block;margin-bottom:10px;color:var(--g200)"></i>درخواست مرجوعی فعالی ندارید<br><small>تا 7 روز پس از تحویل می‌توانید درخواست مرجوعی ثبت کنید</small></div>
                  <?php else: foreach ($returnInvoices as $ri): ?>
                    <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--g100);font-size:13px"><div><strong><?= e($ri['invoice_no']) ?> - <?= e($ri['tracking_code']) ?></strong><br><small><?= e($ri['created_at']) ?> | <?= number_format($ri['total_amount']) ?> ت</small></div><span class="status-pill status-warning"><?= e($ri['shipping_status']) ?></span></div>
                  <?php endforeach; endif; ?>
                </div>

                <div style="background:#fff;border:1px solid var(--g200);border-radius:12px;padding:16px">
                  <h4 style="font-weight:800;margin-bottom:12px"><i class="fa-solid fa-plus"></i> ثبت درخواست مرجوعی تخصصی جدید</h4>
                  <form onsubmit="return handleReturnRequest(event)" style="display:grid;gap:12px">
                    <div class="input-row"><div class="input-group"><label>شماره فاکتور / کد پیگیری *</label><select id="returnInvoice" required style="width:100%;padding:10px 14px;border:1px solid var(--g200);border-radius:10px;font-family:inherit"><option value="">انتخاب فاکتور</option><?php foreach ($invoices as $inv): ?><option value="<?= e($inv['invoice_no']) ?>"><?= e($inv['invoice_no']) ?> - <?= e($inv['tracking_code']) ?> - <?= number_format($inv['total_amount']) ?> ت</option><?php endforeach; ?></select></div><div class="input-group"><label>دلیل مرجوعی *</label><select id="returnReason" required style="width:100%;padding:10px 14px;border:1px solid var(--g200);border-radius:10px;font-family:inherit"><option>کالا معیوب / آسیب دیده</option><option>مغایرت با سفارش</option><option>عدم نیاز / انصراف</option><option>کیفیت پایین‌تر از انتظار</option><option>سایر</option></select></div></div>
                    <div class="input-group"><label>توضیحات کامل مرجوعی *</label><textarea id="returnDesc" rows="3" required placeholder="لطفاً دلیل مرجوعی و وضعیت کالا را با جزئیات بنویسید..."></textarea></div>
                    <div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> ثبت درخواست مرجوعی</button><button type="button" class="btn btn-secondary" onclick="openModal('ticketModal')"><i class="fa-solid fa-headset"></i> تماس با پشتیبانی</button></div>
                  </form>
                </div>
              </div>
              <div>
                <div style="background:var(--g50);border:1px solid var(--g200);border-radius:12px;padding:14px;position:sticky;top:20px">
                  <h4 style="font-weight:800;margin-bottom:10px;font-size:13px"><i class="fa-solid fa-circle-info"></i> شرایط مرجوعی تخصصی</h4>
                  <div style="font-size:11.5px;line-height:1.8;color:var(--g700)">
                    <strong style="color:var(--navy)">✅ شرایط پذیرش مرجوعی:</strong><br>
                    • کالا استفاده نشده و در بسته‌بندی اصلی<br>
                    • فاکتور رسمی و کارت گارانتی همراه<br>
                    • درخواست تا 7 روز پس از تحویل (<?= getSetting('return_days',7) ?> روز)<br>
                    • کالای برقی تست نشده باشد<br><br>
                    <strong style="color:var(--red)">❌ غیرقابل مرجوعی:</strong><br>
                    • کالای سفارشی و برش خورده (رول پلاتر)<br>
                    • کالای بهداشتی (دستکش، ماسک)<br>
                    • کالای با بسته‌بندی باز شده<br><br>
                    <strong style="color:var(--blue)">🔄 فرآیند:</strong><br>
                    1. ثبت درخواست مرجوعی<br>
                    2. بررسی توسط کنترل کیفیت (24 ساعت)<br>
                    3. تایید و ارسال کد مرجوعی<br>
                    4. ارسال کالا + استرداد وجه (48 ساعت)
                  </div>
                  <div style="margin-top:12px;background:var(--blue-light);border-radius:8px;padding:10px;font-size:11px"><i class="fa-solid fa-phone"></i> پشتیبانی مرجوعی:<br><strong><?= e(getSetting('site_phone')) ?></strong><br>شنبه تا چهارشنبه 8-17:30</div>
                </div>
              </div>
            </div>

          <?php elseif ($tab==='compare'): 
            $compareIds = $_SESSION['compare'] ?? [];
            $compareProducts = [];
            if (!empty($compareIds)) {
                $inClause = implode(',', array_fill(0, count($compareIds), '?'));
                $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($inClause)");
                $stmt->execute($compareIds);
                $compareProducts = $stmt->fetchAll();
            }
          ?>
            <?php if (empty($compareProducts)): ?>
              <div class="empty-state"><i class="fa-solid fa-code-compare"></i><h3>لیست مقایسه تخصصی خالی است</h3><p>برای مقایسه تخصصی محصولات، از صفحه محصولات گزینه <strong>مقایسه</strong> را انتخاب کنید (حداکثر 4 محصول)</p><div style="margin-top:16px;display:flex;gap:8px;justify-content:center"><a href="index.php?page=home" class="btn btn-primary"><i class="fa-solid fa-store"></i> مشاهده محصولات</a><button class="btn btn-secondary" onclick="alert('برای افزودن به مقایسه: در کارت محصول روی آیکون مقایسه کلیک کنید')"><i class="fa-solid fa-circle-info"></i> راهنما</button></div></div>
            <?php else: ?>
              <div style="background:#fff;border:1px solid var(--g200);border-radius:14px;overflow:hidden">
                <div style="overflow-x:auto">
                  <table class="data-table" style="min-width:600px">
                    <thead><tr><th style="width:160px">ویژگی تخصصی</th><?php foreach ($compareProducts as $cp): ?><th style="text-align:center"><div style="font-size:28px"><?= $cp['icon'] ?></div><strong style="display:block;font-size:12px"><?= e(mb_substr($cp['name'],0,30)) ?></strong><small style="color:var(--g500)"><?= e($cp['brand']) ?></small></th><?php endforeach; ?></tr></thead>
                    <tbody>
                      <tr><td><strong>تصویر</strong></td><?php foreach ($compareProducts as $cp): ?><td style="text-align:center;font-size:32px"><?= $cp['icon'] ?></td><?php endforeach; ?></tr>
                      <tr><td><strong>قیمت</strong></td><?php foreach ($compareProducts as $cp): ?><td style="text-align:center"><strong style="color:var(--blue)"><?= number_format($cp['price']) ?> ت</strong></td><?php endforeach; ?></tr>
                      <tr><td><strong>برند</strong></td><?php foreach ($compareProducts as $cp): ?><td style="text-align:center"><span class="status-pill status-secondary"><?= e($cp['brand']) ?></span></td><?php endforeach; ?></tr>
                      <tr><td><strong>دسته‌بندی</strong></td><?php foreach ($compareProducts as $cp): ?><td style="text-align:center"><?= e($cp['category']) ?></td><?php endforeach; ?></tr>
                      <tr><td><strong>موجودی</strong></td><?php foreach ($compareProducts as $cp): ?><td style="text-align:center"><span class="status-pill <?= $cp['stock']<5?'status-danger':'status-success' ?>"><?= $cp['stock'] ?> عدد</span></td><?php endforeach; ?></tr>
                      <tr><td><strong>شناسه مالیاتی</strong></td><?php foreach ($compareProducts as $cp): ?><td style="text-align:center"><code style="font-size:10px"><?= e($cp['tax_id']) ?></code></td><?php endforeach; ?></tr>
                      <tr><td><strong>گارانتی</strong></td><?php foreach ($compareProducts as $cp): ?><td style="text-align:center"><small><?= e($cp['warranty']) ?></small></td><?php endforeach; ?></tr>
                      <tr><td><strong>مشخصات فنی</strong></td><?php foreach ($compareProducts as $cp): ?><td style="text-align:center"><small><?= e($cp['specs']) ?></small></td><?php endforeach; ?></tr>
                      <tr><td><strong>عملیات</strong></td><?php foreach ($compareProducts as $cp): ?><td style="text-align:center"><div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap"><a href="index.php?action=add_cart&id=<?= $cp['id'] ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-cart-plus"></i></a><a href="index.php?action=compare_remove&id=<?= $cp['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-trash"></i></a></div></td><?php endforeach; ?></tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div style="margin-top:14px;display:flex;gap:8px;justify-content:center"><a href="index.php?action=compare_clear" class="btn btn-secondary btn-sm"><i class="fa-solid fa-trash"></i> پاک کردن همه</a><a href="index.php?page=home" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> افزودن محصول دیگر</a></div>
            <?php endif; ?>

          <?php elseif ($tab==='notifications'): 
            $notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? OR user_id IS NULL ORDER BY id DESC LIMIT 30");
            $notifs->execute([$user['id']]);
            $notifs = $notifs->fetchAll();
            // علامت خوانده شده
            $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
          ?>
            <div style="display:grid;gap:10px">
              <div style="background:var(--blue-light);border:1px solid var(--blue-soft);border-radius:10px;padding:12px;display:flex;justify-content:space-between;align-items:center"><div><strong><i class="fa-solid fa-bell"></i> اطلاعیه‌های تخصصی شما (<?= count($notifs) ?>)</strong><br><small style="color:var(--g700)">اطلاعیه‌های مهم سفارشات، تخفیف‌ها و اخبار</small></div><button class="btn btn-sm btn-secondary" onclick="alert('همه خوانده شد')"><i class="fa-solid fa-check-double"></i> علامت خوانده شده</button></div>
              <?php if (empty($notifs)): ?><div style="text-align:center;padding:40px;color:var(--g500)"><i class="fa-solid fa-bell-slash" style="font-size:32px;display:block;margin-bottom:10px;color:var(--g200)"></i>اطلاعیه‌ای وجود ندارد<br><small>اطلاعیه‌های مهم سفارشات و تخفیف‌های ویژه اینجا نمایش داده می‌شود</small><br><div style="margin-top:12px;background:var(--g50);border-radius:8px;padding:10px;font-size:11px;text-align:right"><strong>نمونه اطلاعیه‌ها:</strong><br>• سفارش شما ارسال شد - کد پیگیری TRK-123456<br>• تخفیف 15% ویژه مشتریان طلایی تا پایان هفته<br>• فاکتور مالیاتی شما صادر شد - اعتبار 1.2 م تومان</div></div>
              <?php else: foreach ($notifs as $nf): ?>
                <div style="background:#fff;border:1px solid <?= $nf['is_read']?'var(--g200)':'var(--blue-soft)' ?>;border-radius:10px;padding:14px;display:flex;gap:12px;align-items:start;<?= !$nf['is_read']?'background:var(--blue-light)':'' ?>">
                  <div style="width:40px;height:40px;background:<?= $nf['type']==='success'?'var(--green-light)':($nf['type']==='warning'?'#FEF3C7':($nf['type']==='error'?'var(--red-light)':'var(--blue-light)')) ?>;color:<?= $nf['type']==='success'?'var(--green)':($nf['type']==='warning'?'#B45309':($nf['type']==='error'?'var(--red)':'var(--blue)')) ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px"><i class="fa-solid fa-<?= $nf['type']==='success'?'check':($nf['type']==='warning'?'triangle-exclamation':($nf['type']==='error'?'xmark':'bell')) ?>"></i></div>
                  <div style="flex:1"><div style="display:flex;justify-content:space-between;align-items:start"><strong><?= e($nf['title']) ?></strong><small style="color:var(--g500)"><?= e($nf['created_at']) ?></small></div><div style="font-size:13px;color:var(--g700);margin-top:4px;line-height:1.6"><?= e($nf['message']) ?></div><?php if ($nf['link']): ?><a href="<?= e($nf['link']) ?>" style="display:inline-block;margin-top:6px;color:var(--blue);font-size:12px;font-weight:700">مشاهده جزئیات →</a><?php endif; ?></div>
                  <?php if (!$nf['is_read']): ?><div style="width:8px;height:8px;background:var(--blue);border-radius:50%;margin-top:6px"></div><?php endif; ?>
                </div>
              <?php endforeach; endif; ?>
            </div>

          <?php elseif ($tab==='security'): ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
              <div style="background:#fff;border:1px solid var(--g200);border-radius:12px;padding:20px">
                <h4 style="font-weight:800;margin-bottom:14px"><i class="fa-solid fa-shield-halved"></i> امنیت حساب تخصصی</h4>
                <div style="display:grid;gap:12px">
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--green-soft);border:1px solid var(--green-light);border-radius:10px"><div><strong><i class="fa-solid fa-mobile-screen"></i> ورود با شماره موبایل (OTP)</strong><br><small style="color:var(--g500)">ورود بدون رمز عبور، امن با کد یکبار مصرف</small></div><span class="status-pill status-success"><i class="fa-solid fa-check"></i> فعال</span></div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--blue-light);border:1px solid var(--blue-soft);border-radius:10px"><div><strong><i class="fa-solid fa-lock"></i> رمزنگاری SSL/TLS</strong><br><small style="color:var(--g500)">اطلاعات شما با پروتکل امن رمزنگاری می‌شود</small></div><span class="status-pill status-success"><i class="fa-solid fa-lock"></i> فعال</span></div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--g50);border:1px solid var(--g200);border-radius:10px"><div><strong><i class="fa-solid fa-clock-rotate-left"></i> تاریخچه ورود تخصصی</strong><br><small style="color:var(--g500)">آخرین ورود: <?= date('Y/m/d H:i') ?> - IP: <?= $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ?> - مرورگر: Chrome</small></div><button class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i> مشاهده کامل</button></div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--g50);border:1px solid var(--g200);border-radius:10px"><div><strong><i class="fa-solid fa-bell"></i> اعلان ورود مشکوک</strong><br><small style="color:var(--g500)">در صورت ورود از دستگاه جدید، پیامک اطلاع‌رسانی</small></div><label style="position:relative;display:inline-block;width:44px;height:24px"><input type="checkbox" checked style="opacity:0;width:0;height:0"><span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:var(--green);border-radius:24px;transition:.3s"></span><span style="position:absolute;height:18px;width:18px;right:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;transform:translateX(-20px)"></span></label></div>
                </div>
              </div>
              <div style="display:flex;flex-direction:column;gap:16px">
                <div style="background:var(--red-light);border:1px solid #FCA5A5;border-radius:12px;padding:16px">
                  <h4 style="font-weight:800;color:var(--red);font-size:13px;margin-bottom:10px"><i class="fa-solid fa-triangle-exclamation"></i> نکات امنیتی تخصصی - بسیار مهم</h4>
                  <div style="font-size:11.5px;line-height:1.9;color:var(--g700)">
                    • <strong>شماره موبایل محرمانه:</strong> شماره خود را در اختیار دیگران قرار ندهید<br>
                    • <strong>خروج امن:</strong> پس از استفاده، از حساب خارج شوید<br>
                    • <strong>فعالیت مشکوک:</strong> در صورت مشاهده، فوراً با پشتیبانی تماس بگیرید<br>
                    • <strong>رمزنگاری:</strong> اطلاعات مالی شما با AES-256 رمزنگاری و محفوظ است<br>
                    • <strong>فاکتور رسمی:</strong> اطلاعات مالیاتی شما فقط برای صدور فاکتور استفاده می‌شود<br>
                    • <strong>پشتیبانی:</strong> هرگز رمز یا کد ورود را از شما نمی‌پرسد
                  </div>
                </div>
                <div style="background:#fff;border:1px solid var(--g200);border-radius:12px;padding:16px">
                  <h4 style="font-weight:800;font-size:13px;margin-bottom:10px"><i class="fa-solid fa-user-shield"></i> حریم خصوصی تخصصی</h4>
                  <div style="font-size:11.5px;line-height:1.8;color:var(--g700)">
                    ما متعهد به حفظ حریم خصوصی شما هستیم:<br>
                    ✅ اطلاعات شما فروخته نمی‌شود<br>
                    ✅ فقط برای پردازش سفارش استفاده می‌شود<br>
                    ✅ مطابق قانون جرایم رایانه‌ای محافظت می‌شود<br>
                    ✅ امکان حذف حساب و اطلاعات وجود دارد<br>
                    <a href="#" style="color:var(--blue);font-weight:700;margin-top:8px;display:inline-block">مشاهده سیاست حریم خصوصی کامل →</a>
                  </div>
                </div>
                <div style="background:var(--navy);color:#fff;border-radius:12px;padding:14px;text-align:center">
                  <div style="font-size:24px">🛡️</div>
                  <strong style="font-size:13px">حساب شما امن است</strong><br>
                  <small style="color:#94A3B8;font-size:11px">سطح امنیت: بالا (85%)<br>آخرین بررسی: <?= date('Y/m/d') ?></small>
                  <div style="margin-top:10px;height:6px;background:rgba(255,255,255,.2);border-radius:10px"><div style="width:85%;height:100%;background:#10B981;border-radius:10px"></div></div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>

<?php // استعلام قیمت پروژه
elseif ($page === 'rfq'): ?>
  <div class="sec-head">
    <h2 class="sec-title"><i class="fa-solid fa-file-circle-question" style="color:var(--orange)"></i> استعلام قیمت پروژه (RFQ)</h2>
  </div>
  <div class="rfq-box" style="max-width:800px;margin:0 auto">
    <div style="background:linear-gradient(135deg,var(--orange-light),#fff);border:1px solid #FDBA74;border-radius:12px;padding:16px;margin-bottom:20px;display:flex;gap:12px">
      <div style="width:44px;height:44px;background:var(--orange);color:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px"><i class="fa-solid fa-file-invoice-dollar"></i></div>
      <div>
        <strong style="display:block">استعلام سازمانی با تخفیف ویژه پروژه‌ها</strong>
        <small style="color:var(--g700)">نیازمندی‌های کارگاهی خود را ثبت کنید تا با اعمال تخفیف سازمانی و صدور پیش‌فاکتور رسمی قیمت‌گذاری گردد. پاسخگویی کمتر از ۲ ساعت کاری.</small>
      </div>
    </div>

    <form method="POST" action="index.php">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div class="input-row">
        <div class="input-group">
          <label><i class="fa-solid fa-building"></i> نام شرکت یا پیمانکار *</label>
          <input type="text" name="rfq_company" required placeholder="شرکت مهندسی..." value="<?= e($_SESSION['user']['company'] ?? '') ?>">
        </div>
        <div class="input-group">
          <label><i class="fa-solid fa-phone"></i> شماره مسئول تدارکات *</label>
          <input type="text" name="rfq_phone" required maxlength="11" placeholder="0912XXXXXXX" value="<?= e($_SESSION['user']['phone'] ?? '') ?>">
        </div>
      </div>
      <div class="input-group">
        <label><i class="fa-solid fa-list"></i> شرح اقلام و مقادیر درخواستی *</label>
        <textarea name="rfq_desc" rows="5" required placeholder="مثلاً: ۱۰ حلقه رول پلاتر عرض ۹۰، ۲۰ عدد کلاه عایق برق JSP، ۵ دستگاه متر لیزری لایکا... لطفاً مقادیر دقیق و برند پیشنهادی را ذکر کنید."></textarea>
      </div>
      <button type="submit" name="btn_rfq" class="btn btn-orange btn-lg" style="width:100%"><i class="fa-solid fa-paper-plane"></i> ارسال استعلام رسمی و دریافت پیش‌فاکتور</button>
      <div style="text-align:center;margin-top:12px;font-size:12px;color:var(--g500)"><i class="fa-solid fa-clock"></i> زمان پاسخگویی: کمتر از ۲ ساعت در ساعات کاری | <i class="fa-solid fa-shield-halved"></i> اطلاعات شما محرمانه می‌ماند</div>
    </form>

    <?php
    $recentRfqs = $db->query("SELECT * FROM rfqs ORDER BY id DESC LIMIT 5")->fetchAll();
    if ($recentRfqs): ?>
    <div style="margin-top:24px;border-top:1px dashed var(--g200);padding-top:16px">
      <h4 style="font-weight:800;margin-bottom:10px;font-size:13px"><i class="fa-solid fa-clock-rotate-left"></i> آخرین استعلام‌های ثبت شده</h4>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ($recentRfqs as $rr): ?>
          <span style="background:var(--g100);padding:4px 10px;border-radius:20px;font-size:11px"><strong><?= e($rr['rfq_code']) ?></strong> - <?= e($rr['company']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

<?php // درباره
elseif ($page === 'about'): ?>
  <div class="rfq-box" style="max-width:900px;margin:0 auto">
    <h2 style="font-size:22px;font-weight:900;color:var(--navy);margin-bottom:16px;display:flex;align-items:center;gap:10px"><i class="fa-solid fa-building" style="color:var(--blue)"></i> درباره <?= e(getSetting('site_name')) ?></h2>
    <p style="line-height:2;color:var(--g700);font-size:14px">
      <?= e(getSetting('site_name')) ?> مرجع تخصصی تأمین ابزار دقیق، ادوات نقشه‌برداری، حفاظت فردی کارگاهی و ملزومات اسنادی دفاتر فنی پروژه‌های عمرانی است. تمامی اقلام با انطباق ۱۰۰ درصدی با پایانه فروشگاهی و صورتحساب الکترونیکی سامانه مؤدیان عرضه می‌شوند.<br><br>
      با بیش از ۱۰ سال سابقه در تأمین تجهیزات پروژه‌های بزرگ عمرانی، ما مفتخریم که با ارائه فاکتور رسمی مورد تایید دارایی، ضمانت اصالت کالا و ارسال فوری به سراسر کشور، همراه مطمئن شرکت‌های مهندسی و پیمانکاری هستیم.
    </p>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:24px">
      <div style="background:var(--blue-light);border-radius:12px;padding:16px;text-align:center"><div style="font-size:28px">📜</div><strong>فاکتور رسمی</strong><br><small style="color:var(--g500)">مورد تایید دارایی</small></div>
      <div style="background:var(--green-light);border-radius:12px;padding:16px;text-align:center"><div style="font-size:28px">🚚</div><strong>ارسال فوری</strong><br><small style="color:var(--g500)">سراسر کشور</small></div>
      <div style="background:var(--orange-light);border-radius:12px;padding:16px;text-align:center"><div style="font-size:28px">🛡️</div><strong>ضمانت اصالت</strong><br><small style="color:var(--g500)">۷ روز بازگشت</small></div>
    </div>

    <div style="margin-top:24px;background:var(--g50);border-radius:12px;padding:16px">
      <h4 style="font-weight:800;margin-bottom:10px"><i class="fa-solid fa-address-book"></i> اطلاعات تماس</h4>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px">
        <div><i class="fa-solid fa-phone" style="color:var(--blue)"></i> تلفن: <?= e(getSetting('site_phone')) ?></div>
        <div><i class="fa-solid fa-envelope" style="color:var(--blue)"></i> ایمیل: <?= e(getSetting('site_email')) ?></div>
        <div style="grid-column:1/-1"><i class="fa-solid fa-location-dot" style="color:var(--red)"></i> آدرس: <?= e(getSetting('site_address')) ?></div>
      </div>
    </div>
  </div>
<?php endif;

include 'footer.php';
