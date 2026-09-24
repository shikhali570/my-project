<?php
require_once 'config.php';

$message = '';
$msgType = 'info';

// اعتبارسنجی CSRF در درخواست‌های POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die("خطای امنیتی: توکن نامعتبر است.");
    }
}

// مدیریت سبد و نشست‌ها
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
    if ($act === 'logout') {
        unset($_SESSION['user']);
        header("Location: index.php?page=home");
        exit;
    }
}

// لاگین / عضویت
if (isset($_POST['btn_login'])) {
    $phone = trim($_POST['phone'] ?? '');
    $comp = trim($_POST['company'] ?? '');
    $name = trim($_POST['name'] ?? '');

    if (preg_match('/^09[0-9]{9}$/', $phone)) {
        $stmt = $db->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if (!$user) {
            $stmt = $db->prepare("INSERT INTO users (name, phone, company, national_id, economic_code, postal_code, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name ?: 'مهندس خریدار', $phone, $comp ?: 'شرکت ساختمانی', '10103456789', '411589342110', '1587563124', 'تهران، دفتر پروژه']);
            $user = [
                'id' => $db->lastInsertId(),
                'name' => $name ?: 'مهندس خریدار',
                'phone' => $phone,
                'company' => $comp ?: 'شرکت ساختمانی',
                'national_id' => '10103456789',
                'economic_code' => '411589342110',
                'postal_code' => '1587563124',
                'address' => 'تهران، دفتر پروژه'
            ];
        }
        $_SESSION['user'] = $user;
        header("Location: index.php?page=dashboard");
        exit;
    } else {
        $message = "شماره همراه باید ۱۱ رقم با پیش‌شماره ۰۹ باشد.";
        $msgType = "error";
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

        $invoiceNo = 'INV-' . date('ym') . '-' . random_int(1000, 9999);
        $taxUniqueId = 'A1847-' . random_int(10000000000, 99999999999) . '-0021';
        $trackingCode = 'TRK-' . random_int(100000, 999999);

        $stmt = $db->prepare("INSERT INTO invoices (invoice_no, tax_unique_id, tracking_code, buyer_name, buyer_phone, buyer_tax_id, subtotal, tax_amount, total_amount, items_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $invoiceNo, $taxUniqueId, $trackingCode, $cName, $cPhone, $cTaxId,
            $subtotal, $taxAmount, $grandTotal, json_encode($items, JSON_UNESCAPED_UNICODE), date('Y/m/d H:i')
        ]);

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
        $stmt = $db->prepare("INSERT INTO rfqs (rfq_code, company, phone, description, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$rfqCode, $comp, $phone, $desc, date('Y/m/d')]);
        $message = "استعلام با کد پیگیری {$rfqCode} با موفقیت ثبت شد.";
        $msgType = "success";
    } else {
        $message = "لطفاً همه موارد استعلام را به درستی تکمیل فرمایید.";
        $msgType = "error";
    }
}

include 'header.php';

if ($message): ?>
  <div class="toast-bar <?= $msgType ?>" id="siteToast"><?= e($message) ?></div>
<?php endif;

$page = $_GET['page'] ?? 'home';

// ۱. صفحه خانه (محصولات)
if ($page === 'home'): ?>
  <section class="hero">
    <h1>خرید بی‌واسطه تجهیزات کارگاهی و مهندسی</h1>
    <p>صدور فوری صورتحساب الکترونیکی نوع ۱ با شناسه اختصاصی و پذیرش قطعی در ممیزی مالیاتی.</p>
  </section>

  <div class="sec-head">
    <h2 class="sec-title">تجهیزات و ادوات مهندسی پرتقاضا</h2>
  </div>

  <div class="pro-grid">
    <?php
    $cat = $_GET['cat'] ?? null;
    if ($cat) {
        $stmt = $db->prepare("SELECT * FROM products WHERE category = ?");
        $stmt->execute([$cat]);
    } else {
        $stmt = $db->query("SELECT * FROM products");
    }
    while ($p = $stmt->fetch()): ?>
      <div class="pro-card">
        <div class="pro-thumb"><?= $p['icon'] ?></div>
        <div class="pro-body">
          <span class="pro-brand"><?= e($p['brand']) ?></span>
          <h4 class="pro-name"><?= e($p['name']) ?></h4>
          <span class="pro-taxcode">شناسه کالا: <?= e($p['tax_id']) ?></span>
          <div class="pro-bottom">
            <div class="pro-price"><?= number_format($p['price']) ?> <small>تومان</small></div>
            <a href="index.php?action=add_cart&id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">+ سبد</a>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

<?php // ۲. سبد خرید
elseif ($page === 'cart'): ?>
  <div class="cart-wrap">
    <div>
      <h2 style="font-size:18px;font-weight:800;margin-bottom:16px">سبد تجهیزات انتخابی</h2>
      <div class="cart-table">
        <?php if (empty($_SESSION['cart'])): ?>
          <div style="padding:40px;text-align:center;color:var(--g500)">سبد سفارش شما در حال حاضر خالی است.</div>
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
              <div style="font-size:26px"><?= $cItem['icon'] ?></div>
              <div style="flex:1">
                <div style="font-weight:700"><?= e($cItem['name']) ?></div>
                <div style="font-size:11px;color:var(--g500)">شناسه مؤدیان: <?= e($cItem['tax_id']) ?></div>
              </div>
              <div style="display:flex;align-items:center;gap:6px">
                <a href="index.php?action=cart_update&op=dec&id=<?= $cItem['id'] ?>" class="btn btn-sm btn-secondary">-</a>
                <span style="font-weight:700;padding:0 6px"><?= $qty ?></span>
                <a href="index.php?action=cart_update&op=inc&id=<?= $cItem['id'] ?>" class="btn btn-sm btn-secondary">+</a>
              </div>
              <div style="font-weight:800;width:120px;text-align:left"><?= number_format($rowTot) ?> ت</div>
              <a href="index.php?action=cart_update&op=del&id=<?= $cItem['id'] ?>" style="color:var(--red);font-weight:800">✕</a>
            </div>
          <?php endforeach;
        endif; ?>
      </div>
    </div>

    <?php if (!empty($_SESSION['cart'])):
      $tax = (int)round($subtotal * 0.10);
      $total = $subtotal + $tax; ?>
      <div class="cart-summary">
        <h3 style="font-size:15px;font-weight:800;border-bottom:1px solid var(--g200);padding-bottom:10px;margin-bottom:14px">صدور پیش‌فاکتور با شناسه مالیاتی</h3>
        <form method="POST" action="index.php">
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
            <input type="text" name="tax_id" value="<?= e($_SESSION['user']['national_id'] ?? '10103456789') ?>">
          </div>
          <div class="sum-line"><span>جمع خالص اقلام:</span><span><?= number_format($subtotal) ?> ت</span></div>
          <div class="sum-line"><span>مالیات ارزش افزوده (۱۰٪):</span><span><?= number_format($tax) ?> ت</span></div>
          <div class="sum-line total"><span>مبلغ قابل پرداخت:</span><span><?= number_format($total) ?> تومان</span></div>
          <button type="submit" name="btn_checkout" class="btn btn-green" style="width:100%;margin-top:14px">صدور و ارسال به کارپوشه دارایی</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

<?php // ۳. چاپ فاکتور استاندارد A4
elseif ($page === 'invoice'):
  $taxId = $_GET['id'] ?? '';
  $stmt = $db->prepare("SELECT * FROM invoices WHERE tax_unique_id = ?");
  $stmt->execute([$taxId]);
  $inv = $stmt->fetch();
  if (!$inv): echo "<p>صورتحسابی یافت نشد.</p>"; else:
    $items = json_decode($inv['items_json'], true); ?>
    <div class="invoice-box-printable">
      <div style="display:flex;justify-content:space-between;border-bottom:2px solid var(--navy);padding-bottom:12px;margin-bottom:16px">
        <div>
          <h2 style="font-size:16px;font-weight:800;color:var(--navy)">صورتحساب الکترونیکی فروش کالا و خدمات</h2>
          <small style="color:var(--g500)">مطابق ماده ۵ قانون پایانه‌های فروشگاهی و سامانه مؤدیان</small>
        </div>
        <div style="text-align:left;font-size:12px">
          <div>شماره منحصر به‌فرد مالیاتی: <strong style="color:var(--blue)"><?= e($inv['tax_unique_id']) ?></strong></div>
          <div>شماره فاکتور: <?= e($inv['invoice_no']) ?> | تاریخ: <?= e($inv['created_at']) ?></div>
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
              <td><?= $idx + 1 ?></td>
              <td><code><?= e($it['tax_id']) ?></code></td>
              <td><?= e($it['name']) ?></td>
              <td><?= $it['qty'] ?></td>
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

    <div class="no-print flex-center gap-10" style="margin-top:20px">
      <button class="btn btn-green" onclick="window.print()">🖨️ چاپ فاکتور رسمی A4</button>
      <a href="index.php?page=dashboard" class="btn btn-secondary">بازگشت به پنل کاربری</a>
    </div>
  <?php endif;

// ۴. داشبورد کاربری
elseif ($page === 'dashboard'):
  if (empty($_SESSION['user'])) { header("Location: index.php?page=home"); exit; } ?>
  <div class="dashboard-layout">
    <aside class="dash-sidebar">
      <div class="user-profile-widget">
        <div class="user-avatar-lg">م</div>
        <h3 style="font-size:15px;font-weight:800;color:var(--navy)"><?= e($_SESSION['user']['company'] ?? $_SESSION['user']['name']) ?></h3>
        <p style="font-size:12px;color:var(--g500)"><?= e($_SESSION['user']['phone']) ?></p>
      </div>
      <ul class="dash-menu">
        <li class="dash-menu-item active">🧾 فاکتورهای رسمی صادر شده</li>
        <li class="dash-menu-item logout" onclick="window.location.href='index.php?action=logout'">🚪 خروج از حساب</li>
      </ul>
    </aside>
    <main class="dash-main">
      <div class="dash-title"><span>صورتحساب‌های الکترونیکی ثبت‌شده</span></div>
      <?php
      $stmt = $db->query("SELECT * FROM invoices ORDER BY id DESC");
      $invoices = $stmt->fetchAll(); ?>
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
            <tr><td colspan="6" style="text-align:center">هنوز هیچ فاکتوری صادر نگردیده است.</td></tr>
          <?php else: foreach ($invoices as $in): ?>
            <tr>
              <td><strong><?= e($in['invoice_no']) ?></strong></td>
              <td><code><?= e($in['tax_unique_id']) ?></code></td>
              <td><?= e($in['created_at']) ?></td>
              <td><?= number_format($in['total_amount']) ?></td>
              <td><span class="status-pill status-success"><?= e($in['status']) ?></span></td>
              <td><a href="index.php?page=invoice&id=<?= e($in['tax_unique_id']) ?>" class="btn btn-sm btn-primary">مشاهده / چاپ</a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </main>
  </div>

<?php // ۵. استعلام قیمت پروژه (RFQ)
elseif ($page === 'rfq'): ?>
  <div class="rfq-box">
    <h2 style="font-size:18px;font-weight:800;color:var(--navy);margin-bottom:8px">📋 فرم رسمی استعلام قیمت پروژه (RFQ)</h2>
    <p style="font-size:12.5px;color:var(--g500);margin-bottom:16px">نیازمندی‌های کارگاهی خود را ثبت کنید تا با اعمال تخفیف سازمانی قیمت‌گذاری گردد.</p>
    <form method="POST" action="index.php">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:12px">
        <div class="input-group">
          <label>نام شرکت یا پیمانکار *</label>
          <input type="text" name="rfq_company" required>
        </div>
        <div class="input-group">
          <label>شماره تماس مسئول تدارکات *</label>
          <input type="text" name="rfq_phone" required maxlength="11">
        </div>
      </div>
      <div class="input-group">
        <label>شرح اقلام و مقادیر درخواستی *</label>
        <textarea name="rfq_desc" rows="4" required placeholder="مثلاً: ۱۰ حلقه رول پلاتر عرض ۹۰، ۲۰ عدد کلاه عایق برق JSP..."></textarea>
      </div>
      <button type="submit" name="btn_rfq" class="btn btn-orange">ارسال استعلام رسمی</button>
    </form>
  </div>

<?php // ۶. درباره شرکت
elseif ($page === 'about'): ?>
  <div class="rfq-box">
    <h2 style="font-size:18px;font-weight:800;color:var(--navy);margin-bottom:10px">درباره پارس سازه و آفیس</h2>
    <p style="line-height:1.9;color:var(--g700)">پارس سازه و آفیس مرجع تخصصی تأمین ابزار دقیق، ادوات نقشه‌برداری، حفاظت فردی کارگاهی و ملزومات اسنادی دفاتر فنی پروژه‌های عمرانی است. تمامی اقلام با انطباق ۱۰۰ درصدی با پایانه فروشگاهی و صورتحساب الکترونیکی سامانه مؤدیان عرضه می‌شوند.</p>
  </div>
<?php endif;

include 'footer.php';