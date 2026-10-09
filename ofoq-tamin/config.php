<?php
/**
 * پارس سازه و آفیس | فایل راه‌اندازی (Bootstrap)
 * ------------------------------------------------
 * ترتیب کارها در این فایل مهم است:
 *   ۱) نمایش خطاهای مهلک (تا هیچ‌وقت «صفحه سفید» دیده نشود)
 *   ۲) نشست (Session) با مسیر ذخیره‌سازی قابل‌نوشتن به‌صورت خودکار
 *   ۳) اتصال پایگاه داده SQLite، ساخت جداول و داده‌های اولیه
 *
 * نکته برای هاست ویندوز/IIS: اگر مسیر پیش‌فرض ذخیره نشست‌ها قابل نوشتن نباشد
 * (حالت fastcgi.impersonate = 1)، برنامه خودش پوشه ./tmp/sessions می‌سازد و
 * از آن استفاده می‌کند؛ وگرنه ورود کاربران و پیام‌های سیستم کار نمی‌کند.
 */

date_default_timezone_set('Asia/Tehran');

// اگر افزونه mbstring روی هاست نصب نباشد، همین‌جا با پیام راهنما متوقف می‌شویم
// (به‌جای خطای ۵۰۰ مبهم در میانه اجرای برنامه).
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

define('APP_VERSION', '2.2.0');
define('APP_ROOT', __DIR__);
define('DB_FILE', __DIR__ . '/parssaze.db');
define('APP_TMP', __DIR__ . '/tmp');
// تصاویر آپلودی کالاها (uploads/products). فایل uploads/web.config اجرای اسکریپت را در این پوشه می‌بندد.
define('APP_UPLOADS', __DIR__ . '/uploads');
// حداکثر حجم هر تصویر کالا (بایت). باید از upload_max_filesize در .user.ini کمتر باشد.
define('PRODUCT_IMAGE_MAX_BYTES', 5 * 1024 * 1024);

// پوشه کاری (Current Working Directory) را روی ریشه برنامه ثابت می‌کنیم.
// روی IIS/FastCGI پوشه کاری معمولاً ریشه سایت نیست (مثلاً پوشه php-cgi یا
// System32\inetsrv است) و در آن حالت هر include نسبی شکست می‌خورد و
// خطای ۵۰۰ «Failed opening required ...» می‌دهد. این خط تضمین می‌کند
// مسیرهای نسبی هم همیشه درست باز شوند.
@chdir(APP_ROOT);

// ===============================================================
// ۱) نمایش خطاهای مهلک و استثناهای مدیریت‌نشده
//    این بخش عمداً «اولین» کد اجرایی برنامه است تا اگر مشکل در نشست یا
//    پایگاه داده رخ داد هم کاربر پیام فارسی قابل‌فهم ببیند، نه صفحه سفید.
// ===============================================================

/** آیا مسیر موردنظر واقعاً قابل نوشتن است؟ (is_writable در ویندوز قابل اعتماد نیست) */
function app_is_writable_dir($dir)
{
    if (!is_dir($dir)) {
        return false;
    }

    if (!is_writable($dir)) {
        return false;
    }

    if (!function_exists('file_put_contents')) {
        return true;
    }

    $probe = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.probe_' . bin2hex(random_bytes(4)) . '.tmp';
    $written = @file_put_contents($probe, 'ok');
    if ($written === false || $written !== 2) {
        return false;
    }
    @unlink($probe);

    return true;
}

/** ساخت پوشه به‌صورت بازگشتی (بدون هشدار در صورت نبود دسترسی) */
function app_make_dir($dir)
{
    if (is_dir($dir)) {
        return true;
    }
    @mkdir($dir, 0775, true);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    return is_dir($dir);
}

if (!function_exists('app_fatal_page')) {
    /**
     * نمایش پیام خطای قابل‌فهم (فارسی) به‌جای صفحه سفید یا ۵۰۰ خالی.
     * اگر هدرها قبلاً ارسال شده باشند، فقط متن پیام اضافه می‌شود.
     */
    function app_fatal_page($message, $file, $line, $title = 'خطای اجرای برنامه (۵۰۰)')
    {
        $showDetails = (bool)ini_get('display_errors');
        $heading = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $details = '';
        if ($showDetails) {
            $details = '<p><b>پیام فنی:</b> ' . htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') . '</p>';
            if ($file !== '') {
                $details .= '<p><b>فایل:</b> ' . htmlspecialchars((string)$file, ENT_QUOTES, 'UTF-8')
                    . ' — خط ' . (int)$line . '</p>';
            }
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<title>' . $heading . '</title></head>'
            . '<body style="font-family:Tahoma,sans-serif;padding:24px;line-height:2;background:#f8fafc">'
            . '<h1 style="color:#b91c1c">' . $heading . '</h1>'
            . '<p>اجرای برنامه متوقف شد. برای دیدن علت دقیق، فایل <code>check.php</code> را در '
            . 'مرورگر باز کنید و پیش‌نیازها را بررسی کنید: نسخه PHP (حداقل ۷.۴)، افزونه‌های '
            . '<code>pdo_sqlite</code> و <code>mbstring</code>، دسترسی نوشتن پوشه پروژه و '
            . 'وضعیت مسیر ذخیره نشست‌ها.</p>'
            . $details
            . '</body></html>';
    }
}

$GLOBALS['APP_FATAL_SHOWN'] = false;

// استثناها و خطاهای مدیریت‌نشده (مانند فراخوانی تابع ناموجود)
set_exception_handler(function ($e) {
    if (!empty($GLOBALS['APP_FATAL_SHOWN'])) {
        return;
    }
    $GLOBALS['APP_FATAL_SHOWN'] = true;

    $message = is_object($e) && method_exists($e, 'getMessage') ? $e->getMessage() : 'خطای نامشخص';
    $file = is_object($e) && method_exists($e, 'getFile') ? $e->getFile() : '';
    $line = is_object($e) && method_exists($e, 'getLine') ? $e->getLine() : 0;

    app_fatal_page($message, $file, $line);
});

// خطاهای مهلک سطح موتور PHP (مانند اتمام حافظه یا فایل ناموجود در include)
register_shutdown_function(function () {
    if (!empty($GLOBALS['APP_FATAL_SHOWN'])) {
        return;
    }

    $error = error_get_last();
    if (!$error || !in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        return;
    }

    $GLOBALS['APP_FATAL_SHOWN'] = true;
    app_fatal_page($error['message'], isset($error['file']) ? $error['file'] : '', isset($error['line']) ? $error['line'] : 0);
});

// ===============================================================
// ۲) نشست (Session)
// ===============================================================

// مسیر ذخیره نشست‌ها: اگر مسیر پیش‌فرض سرور وجود نداشت یا قابل نوشتن نبود،
// پوشه ./tmp/sessions ساخته و استفاده می‌شود (مشکل رایج هاست‌های IIS با
// FastCGI و کاربر محدود؛ نشانه‌اش این است که ورود انجام می‌شود ولی «نمی‌ماند»).
$GLOBALS['APP_SESSION_DIR'] = '';
$GLOBALS['APP_SESSION_NOTE'] = '';

$serverSessionPath = trim((string)ini_get('session.save_path'));
// قالب «N;/path;MODE» که بعضی هاست‌ها استفاده می‌کنند
if ($serverSessionPath !== '' && strpos($serverSessionPath, ';') !== false) {
    $parts = explode(';', $serverSessionPath);
    $serverSessionPath = trim((string)end($parts));
}

if ($serverSessionPath === '') {
    // خالی‌بودن مقدار یعنی «پوشه موقت سیستم»؛ در ویندوز معمولاً وضعیت ISP IUSRS نامعلوم است
    $systemTemp = function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : '';
    $GLOBALS['APP_SESSION_NOTE'] = 'session.save_path روی این سرور خالی است (پوشه موقت سیستم: '
        . ($systemTemp !== '' ? $systemTemp : 'نامشخص') . ')';
    if ($systemTemp !== '' && app_is_writable_dir($systemTemp)) {
        $serverSessionPath = $systemTemp;
    }
}

if ($serverSessionPath === '' || !app_is_writable_dir($serverSessionPath)) {
    $appSessionPath = APP_TMP . DIRECTORY_SEPARATOR . 'sessions';
    if (app_make_dir($appSessionPath) && app_is_writable_dir($appSessionPath)) {
        ini_set('session.save_path', $appSessionPath);
        $GLOBALS['APP_SESSION_DIR'] = $appSessionPath;
        $GLOBALS['APP_SESSION_NOTE'] = 'مسیر پیش‌فرض سرور قابل نوشتن نبود؛ پوشه tmp/sessions به‌صورت خودکار استفاده شد.';
    } else {
        $GLOBALS['APP_SESSION_NOTE'] = 'مسیر ذخیره نشست قابل نوشتن نیست و ساخت پوشه tmp/sessions هم ممکن نشد. '
            . 'برای رفع مشکل، پوشه پروژه را برای کاربر وب‌سرور قابل نوشتن کنید (IIS: IIS_IUSRS با دسترسی Modify).';
    }
} else {
    $GLOBALS['APP_SESSION_DIR'] = $serverSessionPath;
}

session_name('PARSSAZE_SID');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', '86400');

// هشدارهای session_start نباید داخل HTML چاپ شوند (باعث خرابی هدرها و ریدایرکت می‌شود)
$sessionCookie = isset($_COOKIE[session_name()]) && preg_match('/^[a-zA-Z0-9,\\-]{22,128}$/', (string)$_COOKIE[session_name()])
    ? (string)$_COOKIE[session_name()]
    : null;

$GLOBALS['APP_SESSION_OK'] = false;

// موقتاً نمایش خطاها را برای session_start خاموش می‌کنیم
$previousLevel = error_reporting(0);

if (session_status() === PHP_SESSION_ACTIVE) {
    // حالت اجرای پروسه‌ای (مانند سرورهای توسعه): اگر نشست باز مربوط به بازدیدکننده
    // دیگری باشد، بسته و نشست درست همان بازدیدکننده بازخوانی می‌شود.
    if ($sessionCookie !== session_id()) {
        @session_write_close();
        @session_id($sessionCookie ?: bin2hex(random_bytes(16)));
        unset($_SESSION);
        $GLOBALS['APP_SESSION_OK'] = (bool)@session_start();
    } else {
        $GLOBALS['APP_SESSION_OK'] = true;
    }
} else {
    if ($sessionCookie === null) {
        @session_id(bin2hex(random_bytes(16)));
    }
    $GLOBALS['APP_SESSION_OK'] = (bool)@session_start();
}

error_reporting($previousLevel);

if (!$GLOBALS['APP_SESSION_OK']) {
    $GLOBALS['APP_SESSION_NOTE'] = trim($GLOBALS['APP_SESSION_NOTE'] . ' session_start() با خطا مواجه شد؛ '
        . 'صفحه check.php را باز کنید (ردیف «نشست»).');
}

// در هر حالت باید $_SESSION آرایه باشد تا بقیه برنامه خطا ندهد.
if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = array();
}

// پاک‌سازی دوره‌ای فایل‌های نشست قدیمی پوشه خودمان
// (روی ویندوز/IIS زمان‌بند پاک‌سازی نشست‌ها وجود ندارد)
{
    $ownSessionDir = (string)$GLOBALS['APP_SESSION_DIR'];
    $usingOwnDir = ($ownSessionDir !== '' && strpos($ownSessionDir, APP_TMP) === 0);
    if ($usingOwnDir && random_int(1, 100) === 1) {
        $lifetime = (int)ini_get('session.gc_maxlifetime');
        $lifetime = $lifetime > 0 ? $lifetime : 86400;
        $handle = @opendir($ownSessionDir);
        if ($handle) {
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $file = $ownSessionDir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($file) && (@filemtime($file) + $lifetime) < time()) {
                    @unlink($file);
                }
            }
            closedir($handle);
        }
    }
}

// توکن CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// هدرهای امنیتی
if (!headers_sent()) {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $isPreviewHost = (bool)preg_match('/^(localhost|127\.0\.0\.1|[a-z0-9.-]+\.(e2b\.app|preview\.dev))(:(\d+))?$/', $host);

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');

    if ($isPreviewHost) {
        // محیط پیش‌نمایش/توسعه: اجازه نمایش داخل iframe ابزار پیش‌نمایش
        header("Content-Security-Policy: frame-ancestors *");
    } else {
        // محیط واقعی: جلوگیری از Clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        header("Content-Security-Policy: frame-ancestors 'self'");
    }
}

// ===============================================================
// ۳) پایگاه داده SQLite
// ===============================================================
try {
    $isNew = !file_exists(DB_FILE);
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    $isWindows = (DIRECTORY_SEPARATOR === '\\');
    $drivers = class_exists('PDO') ? @PDO::getAvailableDrivers() : array();
    $driverList = is_array($drivers) && count($drivers) ? implode(' ، ', $drivers) : 'هیچ‌کدام';

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<title>خطا در اتصال به پایگاه داده</title></head>'
        . '<body style="font-family:Tahoma,sans-serif;padding:24px;line-height:2;background:#f8fafc">'
        . '<h1 style="color:#b91c1c">خطا در اتصال به پایگاه داده</h1>'
        . '<p><b>پیام فنی:</b> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><b>درایورهای فعال PDO:</b> ' . htmlspecialchars($driverList, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><b>مسیر فایل پایگاه داده:</b> <code>' . htmlspecialchars(DB_FILE, ENT_QUOTES, 'UTF-8') . '</code></p>'
        . '<p><b>وضعیت پوشه پروژه:</b> '
        . (app_is_writable_dir(APP_ROOT) ? 'قابل نوشتن ✔' : 'غیرقابل نوشتن ✘') . '</p>'
        . '<p>راه‌حل‌ها:</p><ol>'
        . '<li>افزونه <code>pdo_sqlite</code> روی هاست فعال باشد (حداقل نسخه PHP: ۷.۴؛ پیشنهاد ۸.۰ یا بالاتر).</li>'
        . '<li>پوشه پروژه برای کاربر وب‌سرور قابل نوشتن باشد تا فایل <code>parssaze.db</code> ساخته شود'
        . ($isWindows
            ? ' (در IIS: کاربر <code>IIS_IUSRS</code> یا حساب Application Pool با دسترسی Modify).'
            : ' (در لینوکس: کاربر وب‌سرور، سطح دسترسی ۷۵۵ یا ۷۷۵).')
        . '</li>'
        . '<li>فایل <code>check.php</code> را در مرورگر باز کنید تا وضعیت همه پیش‌نیازها را ببینید.</li>'
        . '<li>راهنمای کامل ویندوز/IIS: <code>docs/IIS-SETUP.md</code></li>'
        . '</ol></body></html>';
    exit;
}

require_once APP_ROOT . '/inc/schema.php';   // ساخت جداول + ستون‌های جدید
require_once APP_ROOT . '/inc/seed.php';     // داده‌های نمونه (فقط بار اول)
require_once APP_ROOT . '/inc/functions.php'; // توابع کمکی و منطق تجاری

install_schema($db);
seed_database($db);
