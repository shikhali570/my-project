<?php
/**
 * پارس سازه و آفیس | عیب‌یاب خطای ۵۰۰ (Diagnostic)
 * ------------------------------------------------
 * اگر سایت خطای ۵۰۰ یا صفحه سفید می‌دهد، این فایل را در مرورگر باز کنید:
 *     https://your-domain.ir/check.php
 * نتیجه نشان می‌دهد کدام پیش‌نیاز برقرار نیست.
 *
 * ⚠️ پس از رفع مشکل، این فایل را از هاست حذف کنید.
 */

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');

$rows = array();

// ---------------------------------------------------------------- ۱) نسخه PHP
$rows[] = array(
    'title' => 'نسخه PHP',
    'value' => PHP_VERSION . ' (' . PHP_SAPI . ')',
    'ok'    => version_compare(PHP_VERSION, '7.4.0', '>='),
    'hint'  => 'حداقل ۷.۴ لازم است؛ پیشنهاد: ۸.۰ یا بالاتر',
);

// ------------------------------------------------------------ ۲) افزونه‌ها
$extensions = array(
    'pdo'        => 'اتصال به پایگاه داده',
    'pdo_sqlite' => 'درایور SQLite (پایگاه داده پروژه)',
    'mbstring'   => 'کار با متن فارسی',
    'json'       => 'خروجی JSON در api.php',
    'session'    => 'ورود و نشست کاربران',
);

foreach ($extensions as $ext => $label) {
    $loaded = extension_loaded($ext);
    $rows[] = array(
        'title' => 'افزونه ' . $ext,
        'value' => $loaded ? 'فعال' : 'غیرفعال',
        'ok'    => $loaded,
        'hint'  => $label,
    );
}

// ------------------------------------------------- ۳) دسترسی نوشتن در پوشه
$writable = is_writable(__DIR__);
$rows[] = array(
    'title' => 'دسترسی نوشتن در پوشه پروژه',
    'value' => $writable ? 'قابل نوشتن' : 'فقط خواندنی',
    'ok'    => $writable,
    'hint'  => 'برای ساخت فایل parssaze.db لازم است؛ در IIS کاربر IIS_IUSRS و در لینوکس کاربر وب‌سرور باید دسترسی Modify داشته باشد',
);

// ------------------------------------------------------- ۴) فایل‌های پروژه
$required = array('index.php', 'config.php', 'inc/schema.php', 'inc/seed.php', 'inc/functions.php', 'inc/auth.php', 'inc/actions.php', 'views/home.php');
$missing = array();
foreach ($required as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $missing[] = $file;
    }
}
$rows[] = array(
    'title' => 'فایل‌های ضروری پروژه',
    'value' => count($missing) === 0 ? 'کامل' : ('ناقص: ' . implode(' ، ', $missing)),
    'ok'    => count($missing) === 0,
    'hint'  => 'بسته دانلودشده را کامل و بدون تغییر ساختار پوشه‌ها استخراج کنید',
);

// ------------------------------------------- ۵) آزمون ساخت/اتصال پایگاه داده
$dbFile = __DIR__ . '/parssaze.db';
$dbOk = false;
$dbValue = '';
$dbHint = 'فایل parssaze.db در همین پوشه ساخته می‌شود (اگر از قبل هست، فقط وصل می‌شود)';
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE IF NOT EXISTS _diag (id INTEGER PRIMARY KEY, t TEXT)');
    $pdo->exec('DROP TABLE _diag');
    $dbOk = true;
    $dbValue = file_exists($dbFile) ? 'اتصال موفق (فایل موجود است)' : 'اتصال موفق (فایل ساخته شد)';
} catch (Exception $e) {
    $dbValue = 'خطا: ' . $e->getMessage();
}
$rows[] = array(
    'title' => 'پایگاه داده SQLite',
    'value' => $dbValue,
    'ok'    => $dbOk,
    'hint'  => $dbHint,
);

// ------------------------------------------------------------ ۶) محیط سرور
$rows[] = array(
    'title' => 'نرم‌افزار وب‌سرور',
    'value' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'نامشخص',
    'ok'    => true,
    'hint'  => 'برای IIS فایل web.config و برای Apache فایل .htaccess لازم است',
);
$rows[] = array(
    'title' => 'نمایش خطاها (display_errors)',
    'value' => (string)ini_get('display_errors'),
    'ok'    => true,
    'hint'  => 'برای عیب‌یابی روی On و در حالت نهایی روی Off باشد',
);

$allOk = true;
foreach ($rows as $row) {
    if (!$row['ok']) {
        $allOk = false;
    }
}

// ------------------------------------------- ۷) انتهای فایل خطاهای PHP
$logPath = (string)ini_get('error_log');
$logTail = '';
if ($logPath !== '' && is_file($logPath) && is_readable($logPath)) {
    $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines) && count($lines) > 0) {
        $logTail = implode("\n", array_slice($lines, -12));
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>عیب‌یاب نصب | پارس سازه و آفیس</title>
<style>
  body { font-family: Tahoma, Tahomas, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 24px; line-height: 1.9; }
  .box { max-width: 900px; margin: 0 auto; }
  .warn { background: #fee2e2; border: 1px solid #fca5a5; color: #7f1d1d; padding: 12px 16px; border-radius: 10px; font-weight: bold; }
  .head { background: #0f172a; color: #fff; padding: 16px 20px; border-radius: 12px; margin: 16px 0; }
  .head h1 { margin: 0 0 6px; font-size: 20px; }
  .head p { margin: 0; opacity: .8; font-size: 14px; }
  .sum { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: bold; }
  .sum.ok { background: #dcfce7; border: 1px solid #86efac; color: #14532d; }
  .sum.bad { background: #fef3c7; border: 1px solid #fcd34d; color: #78350f; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  th, td { padding: 10px 14px; text-align: right; border-bottom: 1px solid #e2e8f0; font-size: 14px; vertical-align: top; }
  th { background: #f1f5f9; }
  tr:last-child td { border-bottom: 0; }
  .yes { color: #15803d; font-weight: bold; }
  .no { color: #b91c1c; font-weight: bold; }
  pre { background: #0f172a; color: #e2e8f0; padding: 14px; border-radius: 10px; overflow: auto; direction: ltr; text-align: left; font-size: 12px; }
  h2 { font-size: 17px; margin-top: 28px; }
  li { margin-bottom: 6px; }
</style>
</head>
<body>
<div class="box">

  <div class="warn">⚠️ این فایل ابزار عیب‌یابی است؛ پس از رفع مشکل آن را از هاست حذف کنید.</div>

  <div class="head">
    <h1>عیب‌یاب نصب — پارس سازه و آفیس</h1>
    <p>بررسی پیش‌نیازهای اجرا و ریشه خطای ۵۰۰</p>
  </div>

  <?php if ($allOk): ?>
    <div class="sum ok">همه پیش‌نیازهای ضروری برقرار است. اگر هنوز خطای ۵۰۰ می‌بینید، پوشه <code>inc</code> یا <code>web.config</code> را بررسی کنید و متن خطای پایین همین صفحه را بخوانید.</div>
  <?php else: ?>
    <div class="sum bad">یک یا چند پیش‌نیاز برقرار نیست؛ ردیف‌های قرمزرنگ زیر علت خطای ۵۰۰ هستند. با پنل هاست (بخش PHP / Select PHP Version) نسخه PHP و افزونه‌ها را اصلاح کنید.</div>
  <?php endif; ?>

  <table>
    <tr>
      <th>بررسی</th>
      <th>وضعیت</th>
      <th>توضیح</th>
    </tr>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="<?php echo $row['ok'] ? 'yes' : 'no'; ?>">
          <?php echo $row['ok'] ? '✔ ' : '✘ '; ?><?php echo htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8'); ?>
        </td>
        <td><?php echo htmlspecialchars($row['hint'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>راهنمای رفع خطای ۵۰۰</h2>
  <ol>
    <li><b>نسخه PHP:</b> در پنل هاست، نسخه PHP را روی ۸.۰ (یا حداقل ۷.۴) بگذارید.</li>
    <li><b>افزونه pdo_sqlite:</b> باید فعال باشد؛ اگر در پنل قابل انتخاب نیست، از پشتیبانی هاست بخواهید فعال کند.</li>
    <li><b>دسترسی نوشتن:</b> پوشه پروژه باید برای کاربر وب‌سرور قابل نوشتن باشد (ویندوز/IIS: کاربر <code>IIS_IUSRS</code> با دسترسی Modify — لینوکس: کاربر وب‌سرور با دسترسی 755/775). بدون این دسترسی، فایل <code>parssaze.db</code> ساخته نمی‌شود.</li>
    <li><b>web.config ناسازگار:</b> اگر بعد از گذاشتن <code>web.config</code> سایت بالا نیامد، موقتاً نام آن را به <code>web.config.off</code> تغییر دهید تا مشخص شود مشکل از آن بوده است.</li>
    <li><b>استخراج ناقص:</b> بسته ZIP را کامل استخراج کنید؛ پوشه‌های <code>inc</code> و <code>views</code> باید کنار <code>index.php</code> باشند.</li>
  </ol>

  <h2>آدرس‌های آزمایشی</h2>
  <ul>
    <li><code>index.php</code> — صفحه اصلی فروشگاه</li>
    <li><code>index.php?page=login</code> — صفحه ورود (حساب مدیر: <code>09120000000</code> / <code>admin1234</code>)</li>
    <li><code>index.php?page=admin</code> — داشبورد مدیریت (پس از ورود)</li>
  </ul>

  <?php if ($logTail !== ''): ?>
    <h2>آخرین خطاهای ثبت‌شده در error_log</h2>
    <pre><?php echo htmlspecialchars($logTail, ENT_QUOTES, 'UTF-8'); ?></pre>
  <?php endif; ?>

</div>
</body>
</html>
