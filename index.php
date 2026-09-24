<?php
require_once 'config.php';

$message = '';
$msgType = 'info';

// اعتبارسنجی CSRF در درخواست‌های POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        if (isAjaxRequest()) {
            jsonOut(['ok' => false, 'error' => 'توکن امنیتی نامعتبر است.'], 403);
        }
        die("خطای امنیتی: توکن نامعتبر است.");
    }
}

// ------------------------------------------------------------
// مدیریت سبد و نشست‌ها
// ------------------------------------------------------------
if (isset($_GET['action'])) {
    $act = (string)$_GET['action'];
    $pid = (int)($_GET['id'] ?? 0);

    if ($act === 'add_cart') {
        $stock = 0;
        if ($pid > 0) {
            $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $stock = (int)($stmt->fetchColumn() ?: 0);
            $current = (int)($_SESSION['cart'][$pid] ?? 0);
            if ($stock > 0 && $current < $stock) {
                $_SESSION['cart'][$pid] = $current + 1;
            }
        }
        if (isAjaxRequest()) {
            jsonOut([
                'ok'         => true,
                'cart'       => (int)array_sum($_SESSION['cart'] ?? []),
                'stock_left' => max(0, $stock - (int)($_SESSION['cart'][$pid] ?? 0)),
            ]);
        }
        redirect('index.php?page=cart');
    }
    if ($act === 'cart_update') {
        $op = (string)($_GET['op'] ?? '');
        if ($op === 'inc' && $pid > 0) {
            $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $stock = (int)($stmt->fetchColumn() ?: 0);
            if (isset($_SESSION['cart'][$pid]) && $stock > 0 && (int)$_SESSION['cart'][$pid] < $stock) {
                $_SESSION['cart'][$pid] = (int)$_SESSION['cart'][$pid] + 1;
            }
        } elseif (isset($_SESSION['cart'][$pid])) {
            if ($op === 'dec') {
                $_SESSION['cart'][$pid] = (int)$_SESSION['cart'][$pid] - 1;
                if ($_SESSION['cart'][$pid] <= 0) {
                    unset($_SESSION['cart'][$pid]);
                }
            }
            if ($op === 'del') {
                unset($_SESSION['cart'][$pid]);
            }
        }
        redirect('index.php?page=cart');
    }
    if ($act === 'logout') {
        unset($_SESSION['user']);
        redirect('index.php?page=home');
    }
}

// ------------------------------------------------------------
// لاگین / عضویت
// ------------------------------------------------------------
if (isset($_POST['btn_login'])) {
    $phone = trim($_POST['phone'] ?? '');
    $comp  = trim($_POST['company'] ?? '');
    $name  = trim($_POST['name'] ?? '');

    if (preg_match('/^09[0-9]{9}$/', $phone)) {
        $stmt = $db->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if (!$user) {
            $stmt = $db->prepare("INSERT INTO users (name, phone, company, national_id, economic_code, postal_code, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name ?: 'مهندس خریدار', $phone, $comp ?: 'شرکت ساختمانی', '10103456789', '411589342110', '1587563124', 'تهران، دفتر پروژه']);
            $user = [
                'id'            => (int)$db->lastInsertId(),
                'name'          => $name ?: 'مهندس خریدار',
                'phone'         => $phone,
                'company'       => $comp ?: 'شرکت ساختمانی',
                'national_id'   => '10103456789',
                'economic_code' => '411589342110',
                'postal_code'   => '1587563124',
                'address'       => 'تهران، دفتر پروژه',
            ];
        }
        $_SESSION['user'] = $user;
        redirect('index.php?page=dashboard');
    } else {
        $message = "شماره همراه باید ۱۱ رقم با پیش‌شماره ۰۹ باشد.";
        $msgType = "error";
    }
}

// ------------------------------------------------------------
// ثبت فاکتور نهایی
// ------------------------------------------------------------
if (isset($_POST['btn_checkout'])) {
    if (empty($_SESSION['cart'])) {
        redirect('index.php?page=cart');
    }
    $cName = trim($_POST['company_name'] ?? '');
    $cPhone = trim($_POST['phone'] ?? '');
    $cTaxId = trim($_POST['tax_id'] ?? '10103456789');

    if (!empty($cName) && preg_match('/^09[0-9]{9}$/', $cPhone)) {
        $pIds = array_map('intval', array_keys($_SESSION['cart']));
        $inClause = implode(',', array_fill(0, count($pIds), '?'));
        $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($inClause)");
        $stmt->execute($pIds);
        $prodList = $stmt->fetchAll();

        $subtotal = 0;
        $items = [];
        foreach ($prodList as $pr) {
            if (!isset($_SESSION['cart'][(int)$pr['id']])) {
                continue;
            }
            $qty = (int)$_SESSION['cart'][(int)$pr['id']];
            $line = (int)$pr['price'] * $qty;
            $subtotal += $line;
            $items[] = [
                'name'  => $pr['name'],
                'brand' => $pr['brand'],
                'tax_id' => $pr['tax_id'],
                'price' => (int)$pr['price'],
                'qty'   => $qty,
                'total' => $line,
            ];
        }

        if (empty($items)) {
            redirect('index.php?page=cart');
        }

        $taxAmount = (int)round($subtotal * 0.10);
        $grandTotal = $subtotal + $taxAmount;

        $invoiceNo = 'INV-' . date('ym') . '-' . random_int(1000, 9999);
        // ۱۱ رقم تصادفی (به‌صورت رشته تا از overflow روی PHP ۳۲بیت پرهیز شود)
        $taxDigits = '';
        for ($i = 0; $i < 11; $i++) {
            $taxDigits .= random_int(0, 9);
        }
        $taxUniqueId = 'A1847-' . $taxDigits . '-0021';
        $trackingCode = 'TRK-' . random_int(100000, 999999);

        $stmt = $db->prepare("INSERT INTO invoices (invoice_no, tax_unique_id, tracking_code, buyer_name, buyer_phone, buyer_tax_id, subtotal, tax_amount, total_amount, items_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $invoiceNo, $taxUniqueId, $trackingCode, $cName, $cPhone, $cTaxId,
            $subtotal, $taxAmount, $grandTotal, json_encode($items, JSON_UNESCAPED_UNICODE), date('Y/m/d H:i')
        ]);

        $_SESSION['cart'] = [];
        redirect('index.php?page=invoice&id=' . $taxUniqueId);
    } else {
        $message = "لطفاً اطلاعات را با فرمت درست وارد کنید.";
        $msgType = "error";
    }
}

// ------------------------------------------------------------
// ثبت فرم RFQ
// ------------------------------------------------------------
if (isset($_POST['btn_rfq'])) {
    $comp = trim($_POST['rfq_company'] ?? '');
    $phone = trim($_POST['rfq_phone'] ?? '');
    $desc = trim($_POST['rfq_desc'] ?? '');

    if (!empty($comp) && preg_match('/^09[0-9]{9}$/', $phone) && !empty($desc)) {
        $rfqCode = 'RFQ-' . date('y') . '-' . random_int(100, 999);
        $stmt = $db->prepare("INSERT INTO rfqs (rfq_code, company, phone, description, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$rfqCode, $comp, $phone, $desc, date('Y/m/d')]);
        $message = "استعلام با کد پیگیری {$rfqCode} با موفقیت ثبت شد.";
        $msgType = "success";
    } else {
        $message = "لطفاً همه موارد استعلام را به درستی تکمیل فرمایید.";
        $msgType = "error";
    }
}

$page = (string)($_GET['page'] ?? 'home');
if (!in_array($page, ['home', 'cart', 'invoice', 'dashboard', 'rfq', 'about'], true)) {
    $page = 'home';
}

// شمارش محصولات برای چип‌های دسته‌بندی
$catCounts = ['' => 0];
foreach ($db->query("SELECT category, COUNT(*) AS c FROM products GROUP BY category") as $row) {
    $catCounts[$row['category']] = (int)$row['c'];
}
$catCounts[''] = array_sum($catCounts);

include 'header.php';

if ($message): ?>
  <div class="toast-bar <?= e($msgType) ?> no-print" id="siteToast" role="status">
    <span class="toast-icon"><?= $msgType === 'success' ? '✓' : ($msgType === 'error' ? '!' : 'i') ?></span>
    <span><?= e($message) ?></span>
  </div>
<?php endif; ?>

<?php // ----------------------------------------------------------
// ۱. صفحه خانه (محصولات)
// ---------------------------------------------------------- ?>
<?php if ($page === 'home'):
  // --- فیلتر، جستجو و مرتب‌سازی ---
  $cat = (string)($_GET['cat'] ?? '');
  if (!isset(CATEGORIES[$cat])) {
      $cat = '';
  }
  $q = trim((string)($_GET['q'] ?? ''));
  $sort = (string)($_GET['sort'] ?? '');

  $sortBase = 'index.php?page=home'
    . ($cat !== '' ? '&cat=' . urlencode($cat) : '')
    . ($q !== '' ? '&q=' . urlencode($q) : '');

  $where = [];
  $params = [];
  if ($cat !== '') {
      $where[] = 'category = ?';
      $params[] = $cat;
  }
  if ($q !== '') {
      $where[] = '(name LIKE ? OR brand LIKE ? OR tax_id LIKE ?)';
      $params[] = '%' . $q . '%';
      $params[] = '%' . $q . '%';
      $params[] = '%' . $q . '%';
  }
  $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $orderBy = 'id ASC';
  if ($sort === 'price_asc') {
      $orderBy = 'price ASC, id ASC';
  } elseif ($sort === 'price_desc') {
      $orderBy = 'price DESC, id ASC';
  } elseif ($sort === 'name') {
      $orderBy = 'name ASC, id ASC';
  }
  $stmt = $db->prepare("SELECT * FROM products $whereSql ORDER BY $orderBy");
  $stmt->execute($params);
?>

  <section class="hero">
    <div class="hero-inner">
      <div class="hero-text">
        <span class="hero-badge">🏷️ متصل به سامانه مؤدیان و پایانه‌های فروشگاهی</span>
        <h1>خرید <span class="accent">بی‌واسطه</span> تجهیزات کارگاهی و مهندسی</h1>
        <p>از ابزار دقیق و نقشه‌برداری تا ایمنی HSE و ملزومات دفاتر فنی؛ با صدور آنی صورتحساب الکترونیکی نوع ۱ و شناسه اختصاصی، بدون واسطه و با تخفیف سازمانی.</p>
        <div class="hero-actions">
          <a href="#products" class="btn btn-primary btn-lg">مشاهده تجهیزات</a>
          <a href="index.php?page=rfq" class="btn btn-ghost-light btn-lg">ارسال استعلام پروژه</a>
        </div>
        <ul class="hero-stats">
          <li><strong><?= faNum($catCounts['']) ?></strong><span>کالای ثبت‌شده</span></li>
          <li><strong><?= faNum(count(CATEGORIES)) ?></strong><span>دسته‌بندی تخصصی</span></li>
          <li><strong>۱۰۰٪</strong><span>صورتحساب رسمی</span></li>
        </ul>
      </div>
      <div class="hero-visual" aria-hidden="true">
        <div class="invoice-mock">
          <div class="im-top">
            <div>
              <strong>صورتحساب الکترونیکی فروش</strong>
              <small>نوع ۱ — مطابق ماده ۵ قانون پایانه‌ها</small>
            </div>
            <span class="im-status">✓ ثبت قطعی</span>
          </div>
          <div class="im-row"><span>متر لیزری لایکا D2</span><b><?= fmtPrice(9800000) ?> ت</b></div>
          <div class="im-row"><span>رول پلاتر تحریر ۹۰cm</span><b><?= fmtPrice(740000) ?> ت</b></div>
          <div class="im-row muted"><span>ارزش افزوده (۱۰٪)</span><b><?= fmtPrice(1054000) ?> ت</b></div>
          <div class="im-total"><span>مبلغ قابل پرداخت</span><b><?= fmtPrice(11594000) ?> تومان</b></div>
          <div class="im-code" dir="ltr">A1847-84721093485-0021</div>
        </div>
      </div>
    </div>
  </section>

  <section class="trust-strip">
    <div class="trust-card">
      <span class="trust-icon ti-blue">🧾</span>
      <div><h4>صورتحساب رسمی نوع ۱</h4><p>صدور آنی با شناسه ۲۲ رقمی و انتقال خودکار اعتبار ارزش افزوده به کارپوشه مؤدیان</p></div>
    </div>
    <div class="trust-card">
      <span class="trust-icon ti-amber">🏭</span>
      <div><h4>خرید بی‌واسطه</h4><p>قیمت کارخانه بدون واسطه، همراه با تخفیف سازمانی برای سفارش‌های پروژه‌ای</p></div>
    </div>
    <div class="trust-card">
      <span class="trust-icon ti-green">🚚</span>
      <div><h4>ارسال سریع</h4><p>ارسال روزانه به سراسر کشور با کد رهگیری و پیگیری آنلاین</p></div>
    </div>
    <div class="trust-card">
      <span class="trust-icon ti-purple">🛡️</span>
      <div><h4>ضمانت اصالت</h4><p>همه اقلام اورجینال با گارانتی رسمی سازنده و برگشتی</p></div>
    </div>
  </section>

  <div class="sec-head" id="products">
    <div>
      <h2 class="sec-title">تجهیزات و ادوات مهندسی پرتقاضا</h2>
      <p class="sec-sub"><?= faNum($catCounts['']) ?> کالا با گارانتی اصالت و قیمت روز</p>
    </div>
    <div class="sec-tools">
      <form class="search-mini" action="index.php" method="GET" role="search">
        <input type="hidden" name="page" value="home">
        <input type="search" name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="جستجو در نام، برند، شناسه کالا…" aria-label="جستجوی کالا">
      </form>
      <label class="sort-select">
        <span>مرتب‌سازی:</span>
        <select onchange="if(this.value) location.href=this.value">
          <option value="<?= e($sortBase) ?>" <?= $sort === '' ? 'selected' : '' ?>>پیش‌فرض</option>
          <option value="<?= e($sortBase . '&sort=price_asc') ?>" <?= $sort === 'price_asc' ? 'selected' : '' ?>>قیمت: ارزان به گران</option>
          <option value="<?= e($sortBase . '&sort=price_desc') ?>" <?= $sort === 'price_desc' ? 'selected' : '' ?>>قیمت: گران به ارزان</option>
          <option value="<?= e($sortBase . '&sort=name') ?>" <?= $sort === 'name' ? 'selected' : '' ?>>نام الفبا</option>
        </select>
      </label>
    </div>
  </div>

  <?php if (isset($_GET['q'])): ?>
    <div class="result-banner">
      <span>نتایج جستجو برای «<b><?= e((string)$_GET['q']) ?></b>»</span>
      <a href="index.php?page=home" class="clear-q">✕ حذف فیلتر</a>
    </div>
  <?php endif; ?>

  <div class="chip-row">
    <a class="chip <?= !isset($_GET['cat']) && !isset($_GET['q']) ? 'active' : '' ?>" href="index.php?page=home">همه تجهیزات <i><?= faNum($catCounts['']) ?></i></a>
    <?php foreach (CATEGORIES as $key => $c): ?>
      <a class="chip <?= (($_GET['cat'] ?? '') === $key) ? 'active' : '' ?>" href="index.php?page=home&cat=<?= e($key) ?>"><?= $c['icon'] ?> <?= e($c['label']) ?> <i><?= faNum($catCounts[$key] ?? 0) ?></i></a>
    <?php endforeach; ?>
  </div>

  <div class="pro-grid">
    <?php
    $found = 0;
    while ($p = $stmt->fetch()):
        $found++;
        $stock = stockInfo($p['stock']);
        $out = ((int)$p['stock'] <= 0);
    ?>
      <article class="pro-card cat-<?= e($p['category']) ?>">
        <div class="pro-thumb">
          <span class="pro-emoji"><?= $p['icon'] ?></span>
          <span class="stock-badge <?= e($stock['cls']) ?>"><?= e($stock['label']) ?></span>
        </div>
        <div class="pro-body">
          <span class="pro-brand"><?= e($p['brand']) ?></span>
          <h4 class="pro-name" title="<?= e($p['name']) ?>"><?= e($p['name']) ?></h4>
          <span class="pro-taxcode">شناسه کالا: <code dir="ltr"><?= e($p['tax_id']) ?></code></span>
          <div class="pro-bottom">
            <div class="pro-price"><strong><?= fmtPrice($p['price']) ?></strong> <small>تومان</small></div>
            <?php if ($out): ?>
              <span class="btn btn-sm btn-ghost disabled">ناموجود</span>
            <?php else: ?>
              <a href="index.php?action=add_cart&id=<?= (int)$p['id'] ?>"
                 class="btn btn-sm btn-primary js-add-cart"
                 data-id="<?= (int)$p['id'] ?>">
                <span class="add-label">＋ افزودن به سبد</span>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endwhile; ?>
  </div>

  <?php if ($found === 0): ?>
    <div class="empty-state">
      <span class="empty-icon">🔍</span>
      <h3>کالایی با این مشخصات پیدا نشد</h3>
      <p>عبارت جستجو را بررسی کنید یا از لیست کامل استفاده کنید.</p>
      <a href="index.php?page=home" class="btn btn-primary">مشاهده همه تجهیزات</a>
    </div>
  <?php endif; ?>

  <section class="rfq-band">
    <div>
      <h3>لیست خرید بزرگ‌تری دارید؟</h3>
      <p>نیازمندی‌های پروژه را ثبت کنید تا تیم تدارکات با اعمال تخفیف سازمانی و زمان‌بندی ارسال، پیش‌فاکتور رسمی ارسال کند.</p>
    </div>
    <a href="index.php?page=rfq" class="btn btn-amber btn-lg">📋 ارسال استعلام رسمی (RFQ)</a>
  </section>

<?php // ----------------------------------------------------------
// ۲. سبد خرید
// ----------------------------------------------------------
elseif ($page === 'cart'): ?>

  <div class="stepper no-print">
    <div class="step active"><span class="step-num">۱</span> سبد خرید</div>
    <div class="step-line"></div>
    <div class="step"><span class="step-num">۲</span> صدور فاکتور رسمی</div>
    <div class="step-line"></div>
    <div class="step"><span class="step-num">۳</span> بایگانی و چاپ</div>
  </div>

  <?php
  $cartItems = [];
  $subtotal = 0;
  if (!empty($_SESSION['cart'])) {
      $pIds = array_map('intval', array_keys($_SESSION['cart']));
      $inClause = implode(',', array_fill(0, count($pIds), '?'));
      $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($inClause)");
      $stmt->execute($pIds);
      $cartItems = $stmt->fetchAll();
  }
  foreach ($cartItems as $cItem) {
      $subtotal += (int)$cItem['price'] * (int)$_SESSION['cart'][$cItem['id']];
  }
  $tax = (int)round($subtotal * 0.10);
  $total = $subtotal + $tax;
  ?>

  <div class="cart-wrap<?= empty($_SESSION['cart']) ? ' cart-wrap-solo' : '' ?>">
    <div class="cart-list">
      <h2 class="page-title">سبد تجهیزات انتخابی <small>(<?= faNum(array_sum($_SESSION['cart'] ?? [])) ?> قلم)</small></h2>
      <?php if (empty($_SESSION['cart'])): ?>
        <div class="empty-state">
          <span class="empty-icon">🛒</span>
          <h3>سبد سفارش شما خالی است</h3>
          <p>از بخش کاتالوگ، تجهیزات موردنیاز پروژه خود را اضافه کنید.</p>
          <a href="index.php?page=home" class="btn btn-primary">مشاهده تجهیزات</a>
        </div>
      <?php else: foreach ($cartItems as $cItem):
            $qty = (int)$_SESSION['cart'][$cItem['id']];
            $rowTot = (int)$cItem['price'] * $qty;
            $stock = stockInfo($cItem['stock']);
            $maxed = ((int)$cItem['stock'] > 0 && $qty >= (int)$cItem['stock']);
      ?>
        <div class="cart-row">
          <div class="cart-thumb cat-<?= e($cItem['category']) ?>"><?= $cItem['icon'] ?></div>
          <div class="cart-info">
            <div class="cart-name"><?= e($cItem['name']) ?></div>
            <div class="cart-meta">
              <span>شناسه مؤدیان: <code dir="ltr"><?= e($cItem['tax_id']) ?></code></span>
              <span class="cart-unit">قیمت واحد: <?= fmtPrice($cItem['price']) ?> ت</span>
            </div>
            <?php if ($maxed): ?><div class="cart-maxnote">حداکثر موجودی این کالا به سبد افزوده شد.</div><?php endif; ?>
          </div>
          <div class="qty-stepper">
            <a href="index.php?action=cart_update&op=dec&id=<?= (int)$cItem['id'] ?>" class="qbtn" aria-label="کاهش تعداد">−</a>
            <span class="qty-num"><?= faNum($qty) ?></span>
            <a href="index.php?action=cart_update&op=inc&id=<?= (int)$cItem['id'] ?>" class="qbtn <?= $maxed ? 'disabled' : '' ?>" aria-label="افزایش تعداد" <?= $maxed ? 'tabindex="-1" aria-disabled="true"' : '' ?>>＋</a>
          </div>
          <div class="cart-rowtotal"><?= fmtPrice($rowTot) ?> <small>ت</small></div>
          <a href="index.php?action=cart_update&op=del&id=<?= (int)$cItem['id'] ?>" class="cart-remove" title="حذف از سبد" aria-label="حذف از سبد">🗑</a>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <?php if (!empty($_SESSION['cart'])): ?>
      <aside class="cart-summary">
        <h3 class="sum-title">خلاصه پیش‌فاکتور</h3>
        <div class="sum-line"><span>جمع خالص اقلام:</span><span><?= fmtPrice($subtotal) ?> ت</span></div>
        <div class="sum-line"><span>مالیات ارزش افزوده (۱۰٪):</span><span><?= fmtPrice($tax) ?> ت</span></div>
        <div class="sum-line total"><span>مبلغ قابل پرداخت:</span><span><?= fmtPrice($total) ?> تومان</span></div>

        <form method="POST" action="index.php" class="checkout-form">
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
          <div class="input-group">
            <label>نام شخصیت حقوقی / شرکت خریدار *</label>
            <input type="text" name="company_name" required value="<?= e($_SESSION['user']['company'] ?? '') ?>">
          </div>
          <div class="input-group">
            <label>شماره تماس مسئول خرید یا تدارکات *</label>
            <input type="tel" dir="ltr" class="ltr-input" name="phone" required maxlength="11" value="<?= e($_SESSION['user']['phone'] ?? '') ?>">
          </div>
          <div class="input-group">
            <label>شناسه ملی شرکت (۱۱ رقم)</label>
            <input type="text" name="tax_id" value="<?= e($_SESSION['user']['national_id'] ?? '10103456789') ?>">
          </div>
          <button type="submit" name="btn_checkout" class="btn btn-green btn-lg">🧾 صدور فاکتور رسمی و ارسال به کارپوشه</button>
          <p class="sum-note">با صدور فاکتور، اعتبار ارزش افزوده به صورت خودکار به کارپوشه مؤدیان شرکت شما منتقل می‌شود.</p>
        </form>
      </aside>
    <?php endif; ?>
  </div>

<?php // ----------------------------------------------------------
// ۳. چاپ فاکتور استاندارد A4
// ----------------------------------------------------------
elseif ($page === 'invoice'):
    $taxId = (string)($_GET['id'] ?? '');
    $stmt = $db->prepare("SELECT * FROM invoices WHERE tax_unique_id = ?");
    $stmt->execute([$taxId]);
    $inv = $stmt->fetch();
    if (!$inv): ?>
      <div class="empty-state">
        <span class="empty-icon">📄</span>
        <h3>صورتحسابی یافت نشد</h3>
        <p>شاید لینک اشتباه باشد یا فاکتور موردنظر صادر نشده باشد.</p>
        <a href="index.php?page=home" class="btn btn-primary">بازگشت به فروشگاه</a>
      </div>
  <?php else:
      $items = json_decode($inv['items_json'], true) ?: []; ?>
    <div class="invoice-actions no-print">
      <button class="btn btn-green btn-lg" onclick="window.print()">🖨️ چاپ / ذخیره PDF</button>
      <a href="index.php?page=dashboard" class="btn btn-secondary btn-lg">بازگشت به پنل کاربری</a>
    </div>

    <div class="invoice-document invoice-box-printable">
      <div class="inv-head">
        <div class="inv-head-right">
          <div class="inv-logo">🏗️</div>
          <div>
            <strong><?= e(SITE['name']) ?></strong>
            <small><?= e(SITE['tagline']) ?></small>
          </div>
        </div>
        <div class="inv-head-left">
          <h2>صورتحساب الکترونیکی فروش کالا و خدمات</h2>
          <small>مطابق ماده ۵ قانون پایانه‌های فروشگاهی و سامانه مؤدیان — صورتحساب نوع ۱</small>
        </div>
      </div>

      <div class="inv-meta">
        <div class="inv-meta-box">
          <span>شماره فاکتور</span>
          <strong dir="ltr"><?= e($inv['invoice_no']) ?></strong>
        </div>
        <div class="inv-meta-box">
          <span>تاریخ صدور</span>
          <strong><?= e($inv['created_at']) ?></strong>
        </div>
        <div class="inv-meta-box">
          <span>کد رهگیری</span>
          <strong dir="ltr"><?= e($inv['tracking_code']) ?></strong>
        </div>
        <div class="inv-meta-box highlight">
          <span>شماره منحصر‌به‌فرد مالیاتی</span>
          <strong dir="ltr"><?= e($inv['tax_unique_id']) ?></strong>
        </div>
      </div>

      <div class="inv-parties">
        <div class="inv-party">
          <h5>فروشنده</h5>
          <p><b><?= e(SITE['name']) ?></b> (سهامی خاص)<br>
          شناسه ملی: <span dir="ltr"><?= e(SITE['national_id']) ?></span><br>
          کد اقتصادی: <span dir="ltr"><?= e(SITE['economic_code']) ?></span><br>
          <?= e(SITE['address']) ?><br>
          تلفن: <span dir="ltr"><?= e(SITE['phone']) ?></span></p>
        </div>
        <div class="inv-party">
          <h5>خریدار</h5>
          <p><b><?= e($inv['buyer_name']) ?></b><br>
          شماره تماس رابط: <span dir="ltr"><?= e($inv['buyer_phone']) ?></span><br>
          شناسه ملی / کد اقتصادی: <span dir="ltr"><?= e($inv['buyer_tax_id']) ?></span></p>
        </div>
      </div>

      <table class="data-table inv-table">
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
              <td><?= faNum($idx + 1) ?></td>
              <td><code dir="ltr"><?= e($it['tax_id']) ?></code></td>
              <td><?= e($it['name']) ?><small><?= e($it['brand']) ?></small></td>
              <td><?= faNum($it['qty']) ?></td>
              <td><?= fmtPrice($it['price']) ?></td>
              <td><?= fmtPrice($lineTax) ?></td>
              <td><b><?= fmtPrice($it['total'] + $lineTax) ?></b></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="inv-foot">
        <div class="inv-note">
          📌 اعتبار مالیاتی ارزش افزوده این سند به صورت خودکار به کارپوشه مؤدیان خریدار منتقل گردیده و در ممیزی دارایی معتبر است.
        </div>
        <div class="inv-totals">
          <div class="sum-line"><span>جمع اقلام:</span><span><?= fmtPrice($inv['subtotal']) ?> تومان</span></div>
          <div class="sum-line"><span>مالیات ارزش افزوده:</span><span><?= fmtPrice($inv['tax_amount']) ?> تومان</span></div>
          <div class="sum-line grand"><span>مبلغ کل نهایی:</span><span><?= fmtPrice($inv['total_amount']) ?> تومان</span></div>
        </div>
      </div>

      <div class="inv-sign">
        <div class="sign-box"><span>امضاء و مهر فروشنده</span></div>
        <div class="sign-box"><span>دریافت‌کننده خریدار</span></div>
      </div>
    </div>
  <?php endif; ?>

<?php // ----------------------------------------------------------
// ۴. داشبورد کاربری
// ----------------------------------------------------------
elseif ($page === 'dashboard'):
    if (empty($_SESSION['user'])) {
        redirect('index.php?page=home');
    }
    $stmt = $db->query("SELECT * FROM invoices ORDER BY id DESC");
    $invoices = $stmt->fetchAll();
    $invCount = count($invoices);
    $invTotal = (int)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices")->fetchColumn();
    $invItems = 0;
    foreach ($invoices as $in) {
        $its = json_decode($in['items_json'], true) ?: [];
        foreach ($its as $it) {
            $invItems += (int)$it['qty'];
        }
    }
    $rfqCount = (int)$db->query("SELECT COUNT(*) FROM rfqs")->fetchColumn();
?>
  <div class="dash-head">
    <div>
      <h2 class="page-title">سلام، <?= e($_SESSION['user']['company'] ?? $_SESSION['user']['name']) ?> 👋</h2>
      <p class="sec-sub">صورتحساب‌های الکترونیکی شما به صورت آنی در این بخش بایگانی می‌شوند.</p>
    </div>
  </div>

  <div class="stat-cards">
    <div class="stat-card sc-blue">
      <span class="stat-icon">🧾</span>
      <div><strong><?= faNum($invCount) ?></strong><span>فکتور صادرشده</span></div>
    </div>
    <div class="stat-card sc-green">
      <span class="stat-icon">💰</span>
      <div><strong><?= $invTotal ? fmtPrice($invTotal) . ' ت' : '—' ?></strong><span>مجموع مبالغ</span></div>
    </div>
    <div class="stat-card sc-amber">
      <span class="stat-icon">📦</span>
      <div><strong><?= faNum($invItems) ?></strong><span>قلم کالا ثبت‌شده</span></div>
    </div>
    <div class="stat-card sc-purple">
      <span class="stat-icon">📋</span>
      <div><strong><?= faNum($rfqCount) ?></strong><span>استعلام ثبت‌شده</span></div>
    </div>
  </div>

  <div class="dashboard-layout">
    <aside class="dash-sidebar">
      <div class="user-profile-widget">
        <div class="user-avatar-lg"><?= e(firstChar($_SESSION['user']['company'] ?? $_SESSION['user']['name'])) ?></div>
        <h3><?= e($_SESSION['user']['company'] ?? $_SESSION['user']['name']) ?></h3>
        <p><span dir="ltr"><?= e($_SESSION['user']['phone']) ?></span></p>
      </div>
      <ul class="dash-menu">
        <li class="dash-menu-item active">🧾 فاکتورهای رسمی صادر شده</li>
        <li class="dash-menu-item" onclick="window.location.href='index.php?page=rfq'">📋 استعلام پروژه (RFQ)</li>
        <li class="dash-menu-item logout" onclick="window.location.href='index.php?action=logout'">🚪 خروج از حساب</li>
      </ul>
    </aside>
    <main class="dash-main">
      <div class="dash-title"><span>صورتحساب‌های الکترونیکی ثبت‌شده</span></div>
      <table class="data-table">
        <thead>
          <tr>
            <th>شماره فاکتور</th>
            <th>شناسه مالیاتی</th>
            <th>تاریخ</th>
            <th>مبلغ کل (تومان)</th>
            <th>وضعیت</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($invoices)): ?>
            <tr><td colspan="6"><div class="table-empty">هنوز هیچ فاکتوری صادر نگردیده است. اولین خرید خود را انجام دهید. 🛒</div></td></tr>
          <?php else: foreach ($invoices as $in): ?>
            <tr>
              <td><strong dir="ltr"><?= e($in['invoice_no']) ?></strong></td>
              <td><code dir="ltr"><?= e($in['tax_unique_id']) ?></code></td>
              <td><?= e($in['created_at']) ?></td>
              <td><?= fmtPrice($in['total_amount']) ?></td>
              <td><span class="status-pill status-success">✓ <?= e($in['status']) ?></span></td>
              <td><a href="index.php?page=invoice&id=<?= e($in['tax_unique_id']) ?>" class="btn btn-sm btn-primary">مشاهده / چاپ</a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </main>
  </div>

<?php // ----------------------------------------------------------
// ۵. استعلام قیمت پروژه (RFQ)
// ----------------------------------------------------------
elseif ($page === 'rfq'): ?>
  <div class="rfq-wrap">
    <div class="rfq-box">
      <h2 class="page-title">📋 فرم رسمی استعلام قیمت پروژه (RFQ)</h2>
      <p class="sec-sub">نیازمندی‌های کارگاهی خود را ثبت کنید تا با اعمال تخفیف سازمانی قیمت‌گذاری و پیش‌فاکتور رسمی صادر شود.</p>
      <form method="POST" action="index.php" class="rfq-form">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <div class="form-grid-2">
          <div class="input-group">
            <label>نام شرکت یا پیمانکار *</label>
            <input type="text" name="rfq_company" required placeholder="شرکت پیمانکاری …">
          </div>
          <div class="input-group">
            <label>شماره تماس مسئول تدارکات *</label>
            <input type="tel" dir="ltr" class="ltr-input" name="rfq_phone" required maxlength="11" placeholder="09XXXXXXXXX">
          </div>
        </div>
        <div class="input-group">
          <label>شرح اقلام و مقادیر درخواستی *</label>
          <textarea name="rfq_desc" rows="5" required placeholder="مثلاً: ۱۰ حلقه رول پلاتر عرض ۹۰، ۲۰ عدد کلاه عایق برق JSP، ۵ متر لیزری لایکا…"></textarea>
        </div>
        <button type="submit" name="btn_rfq" class="btn btn-amber btn-lg">ارسال استعلام رسمی</button>
      </form>
    </div>

    <aside class="rfq-info">
      <h3>چرا استعلام رسمی؟</h3>
      <ul class="rfq-info-list">
        <li><b>تخفیف سازمانی</b><span>قیمت‌گذاری ویژه برای سفارش‌های پروژه‌ای با حجم بالا</span></li>
        <li><b>پیش‌فاکتور رسمی</b><span>بهای دقیق با شناسه مالیاتی کالا و مدت اعتبار مشخص</span></li>
        <li><b>زمان‌بندی ارسال</b><span>برنامه‌ریزی تحویل هم‌زمان اقلام در موقع پروژه</span></li>
        <li><b>پشتیبانی اختصاصی</b><span>تیم تدارکات ظرف ۲۴ ساعت کاری با شما تماس می‌گیرد</span></li>
      </ul>
      <div class="rfq-contact">
        <span>☎️</span>
        <div>
          <b>مرکز تدارکات</b>
          <span dir="ltr"><?= e(SITE['phone']) ?></span>
          <small><?= e(SITE['hours']) ?></small>
        </div>
      </div>
    </aside>
  </div>

<?php // ----------------------------------------------------------
// ۶. درباره شرکت
// ----------------------------------------------------------
elseif ($page === 'about'): ?>
  <div class="about-wrap">
    <div class="about-hero">
      <h2 class="page-title">درباره <?= e(SITE['name']) ?></h2>
      <p>
        <?= e(SITE['name']) ?> مرجع تخصصی تأمین ابزار دقیق، ادوات نقشه‌برداری، حفاظت فردی کارگاهی و ملزومات اسنادی دفاتر فنی پروژه‌های عمرانی است.
        ما با حذف واسطه‌ها، مستقیماً از نمایندگی‌های رسمی برندهای معتبر خرید می‌کنیم تا قیمت نهایی به شما برسد.
      </p>
      <p>
        تمامی اقلام با انطباق کامل با پایانه فروشگاهی و صورتحساب الکترونیکی سامانه مؤدیان عرضه می‌شوند؛ بنابراین هر خرید شما با شناسه مالیاتی معتبر،
        قابل پیگیری در ممیزی دارایی و قابل استناد برای کسری ارزش افزوده است.
      </p>
    </div>
    <div class="about-grid">
      <div class="about-card">
        <span class="about-icon">🏛️</span>
        <h4>انطباق کامل مالیاتی</h4>
        <p>صدور آنی صورتحساب الکترونیکی نوع ۱ با شناسه ۲۲ رقمی کالا و انتقال خودکار اعتبار ارزش افزوده به کارپوشه مؤدیان خریدار.</p>
      </div>
      <div class="about-card">
        <span class="about-icon">🏭</span>
        <h4>تأمین بی‌واسطه</h4>
        <p>قرارداد مستقیم با نمایندگی‌های رسمی لایکا، بوش، JSP و برندهای تخصصی؛ بدون واسطه و با گارانتی اورجینال.</p>
      </div>
      <div class="about-card">
        <span class="about-icon">🚚</span>
        <h4>توزیع سراسری</h4>
        <p>ارسال روزانه به تمام استان‌ها و هماهنگی تحویل هم‌زمان چند قلم در یک موقع پروژه با کد رهگیری فعال.</p>
      </div>
      <div class="about-card">
        <span class="about-icon">🎧</span>
        <h4>پشتیبانی فنی</h4>
        <p>مشاوره انتخاب تجهیزات قبل از خرید و خدمات پس از فروش، کالیبراسیون و تعمیر ابزار دقیق توسط تیم فنی داخلی.</p>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
