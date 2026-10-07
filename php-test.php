<?php
/**
 * آزمون خام PHP (دسترسی‌نداشته و بدون وابستگی)
 * ------------------------------------------------------------------
 * این فایل هیچ چیزی از برنامه را بارگذاری نمی‌کند: نه config.php، نه پایگاه
 * داده، نه نشست. فقط می‌گوید «آیا PHP روی این دامنه اجرا می‌شود یا نه».
 *
 * کاربرد: وقتی سایت خطای ۵۰۰ می‌دهد یا صفحه سفید است، اول این آدرس را باز کنید:
 *     https://your-domain.ir/php-test.php
 *
 *   • اگر همین صفحه متن و جدول نشان داد  → PHP اجرا می‌شود و مشکل از برنامه است
 *                                          (بروید سراغ check.php)
 *   • اگر فایل دانلود شد یا متن کد دیده شد → هندلر PHP روی دامنه ثبت نشده است
 *                                          (از پنل هاست فعال کنید)
 *   • اگر خطای ۵۰۰.۱۹ دیدید               → مشکل از web.config است
 *   • اگر «Security Alert: The PHP CGI cannot be accessed directly» دیدید
 *                                          → تنظیم cgi.force_redirect / REDIRECT_STATUS
 *
 * ⚠️ پس از رفع مشکل این فایل را حذف کنید.
 * کد عمداً با نگارش قدیمی PHP نوشته شده تا روی PHP 5 هم اجرا شود.
 */

@header('Content-Type: text/html; charset=UTF-8');
@header('X-Robots-Tag: noindex, nofollow');

$checks = array();
$checks[] = array('نسخه PHP', PHP_VERSION, version_compare(PHP_VERSION, '7.4.0', '>='), 'حداقل ۷.۴ لازم است (پیشنهاد ۸.۰ یا بالاتر)');
$checks[] = array('حالت اجرا (SAPI)', PHP_SAPI, true, 'روی IIS باید cgi-fcgi باشد');
$checks[] = array('سیستم عامل سرور', PHP_OS, true, '');
$checks[] = array('فایل php.ini فعال', php_ini_loaded_file() ? php_ini_loaded_file() : '(یافت نشد)', true, '');
$checks[] = array('پوشه کاری PHP (getcwd)', function_exists('getcwd') ? getcwd() : '(نامشخص)', true,
    'روی IIS این مسیر ریشه سایت نیست؛ برنامه با chdir خودش را روی ریشه سایت تنظیم می‌کند');
$checks[] = array('پوشه این فایل', dirname(__FILE__), true, 'فایل‌های برنامه باید کنار همین فایل باشند');
$checks[] = array('دسترسی به .htaccess (آپاچی)', file_exists(dirname(__FILE__) . '/.htaccess') ? 'موجود' : 'وجود ندارد', true, '');
$checks[] = array('دسترسی به web.config (IIS)', file_exists(dirname(__FILE__) . '/web.config') ? 'موجود' : 'وجود ندارد', true, '');

$extensions = array('pdo', 'pdo_sqlite', 'mbstring', 'json', 'session');
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    $required = in_array($ext, array('pdo', 'pdo_sqlite', 'mbstring'), true);
    $checks[] = array('افزونه ' . $ext, $loaded ? 'فعال' : 'غیرفعال' . ($required ? ' (ضروری!)' : ''), $loaded || !$required, '');
}

$writeOk = false;
if (function_exists('file_put_contents')) {
    $probe = dirname(__FILE__) . '/.wtest_' . mt_rand(1000, 9999) . '.tmp';
    $writeOk = @file_put_contents($probe, 'ok') !== false;
    if ($writeOk) {
        @unlink($probe);
    }
}
$checks[] = array('نوشتن در پوشه سایت (آزمون واقعی)', $writeOk ? 'موفق' : 'ناموفق', $writeOk,
    'برای ساخت parssaze.db و پوشه tmp/sessions لازم است (IIS: کاربر IIS_IUSRS با دسترسی Modify)');

$allOk = true;
foreach ($checks as $row) {
    if (!$row[2]) {
        $allOk = false;
    }
}

$sessionOk = false;
if (function_exists('session_start')) {
    $oldLevel = error_reporting(0);
    $sessionOk = (bool)@session_start();
    if ($sessionOk) {
        @session_write_close();
    }
    error_reporting($oldLevel);
}
$checks[] = array('اجرای session_start()', $sessionOk ? 'موفق' : 'ناموفق (هشدار)', $sessionOk,
    'اگر ناموفق باشد، برنامه خودکار از پوشه tmp/sessions استفاده می‌کند؛ ولی ریشه‌اش دسترسی نوشتن پوشه است');

$serverSoftware = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '(نامشخص)';
$isIis = (stripos($serverSoftware, 'IIS') !== false) || isset($_SERVER['APPL_PHYSICAL_PATH']) || isset($_SERVER['APP_POOL_ID']);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>آزمون خام PHP</title>
<style>
  body { font-family: Tahoma, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 24px; line-height: 1.9; }
  .box { max-width: 860px; margin: 0 auto; }
  .big { background: #dcfce7; border: 1px solid #86efac; color: #14532d; padding: 16px 20px; border-radius: 12px; font-weight: bold; font-size: 17px; }
  .big.bad { background: #fee2e2; border-color: #fca5a5; color: #7f1d1d; }
  .note { background: #fef3c7; border: 1px solid #fcd34d; color: #78350f; padding: 12px 16px; border-radius: 10px; margin: 14px 0; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-top: 14px; }
  th, td { padding: 9px 14px; text-align: right; border-bottom: 1px solid #e2e8f0; font-size: 14px; vertical-align: top; }
  th { background: #f1f5f9; }
  tr:last-child td { border-bottom: 0; }
  .yes { color: #15803d; font-weight: bold; white-space: nowrap; }
  .no { color: #b91c1c; font-weight: bold; white-space: nowrap; }
  td.hint { color: #64748b; font-size: 13px; }
  code { background: #f1f5f9; padding: 1px 5px; border-radius: 5px; direction: ltr; display: inline-block; }
</style>
</head>
<body>
<div class="box">

  <div class="big <?= $allOk ? '' : 'bad' ?>">
    <?= $allOk ? '✔ PHP روی این دامنه اجرا می‌شود و پیش‌نیازهای ضروری فعال است.' : '✘ PHP اجرا می‌شود ولی یک یا چند پیش‌نیاز ضروری برقرار نیست (ردیف‌های قرمز زیر).' ?>
  </div>

  <div class="note">
    این صفحه فقط وضعیت وبی‌سرور را نشان می‌دهد. اگر همین صفحه باز شد، یعنی <b>مشکل از هندلر PHP نیست</b>
    و باید سراغ عیب‌یاب کامل یعنی <code>check.php</code> بروید.<br>
    اگر این صفحه <b>باز نشد</b>: فایل دانلود شد ← هندلر PHP ثبت نشده | خطای ۵۰۰.۱۹ ← مشکل <code>web.config</code> |
    خطای ۵۰۰.۰ ← انتخاب‌نشدن نسخه PHP در پنل هاست.<br>
    ⚠️ پس از رفع مشکل، هم این فایل و هم <code>check.php</code> را حذف کنید.
  </div>

  <table>
    <tr><th style="width:34%">بررسی</th><th style="width:22%">مقدار</th><th>توضیح</th></tr>
    <?php foreach ($checks as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="<?= $row[2] ? 'yes' : 'no' ?>"><?= $row[2] ? '✔ ' : '✘ ' ?><?= htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="hint"><?= htmlspecialchars($row[3], ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
    <tr>
      <td>نرم‌افزار وب‌سرور</td>
      <td class="yes"><?= htmlspecialchars($serverSoftware, ENT_QUOTES, 'UTF-8') ?></td>
      <td class="hint"><?= $isIis ? 'تشخیص: IIS ویندوز — راهنما: docs/IIS-SETUP.md' : 'آپاچی/لایت‌اسپید — راهنما: بخش ۹ فایل README' ?></td>
    </tr>
  </table>

  <p style="margin-top:18px">
    آزمون بعدی: <a href="check.php">check.php</a> (عیب‌یاب کامل) &nbsp;|&nbsp;
    صفحه اصلی: <a href="index.php">index.php</a>
  </p>

</div>
</body>
</html>
