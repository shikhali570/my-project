<?php
/**
 * پارس سازه و آفیس | فایل راه‌اندازی (Bootstrap)
 * ------------------------------------------------
 * شامل: راه‌اندازی نشست امن، اتصال پایگاه داده SQLite،
 * ساخت/به‌روزرسانی خودکار جداول (Migration)، داده‌های اولیه و توابع کمکی.
 */

date_default_timezone_set('Asia/Tehran');

// اگر افزونه mbstring روی هاست نصب نباشد، همین‌جا با پیام راهنما متوقف می‌شویم
// (به‌جای خطای ۵۰۰ مبهم در میانه اجرای برنامه).
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

define('APP_VERSION', '2.0.0');
define('APP_ROOT', __DIR__);
define('DB_FILE', __DIR__ . '/parssaze.db');

// ---------------------------------------------------------------- Session
session_name('PARSSAZE_SID');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', '86400');

$sessionCookie = isset($_COOKIE[session_name()]) && preg_match('/^[a-zA-Z0-9,\-]{22,128}$/', (string)$_COOKIE[session_name()])
    ? (string)$_COOKIE[session_name()]
    : null;

if (session_status() === PHP_SESSION_ACTIVE) {
    // حالت اجرای پروسه‌ای (مانند سرورهای توسعه): اگر نشست باز مربوط به بازدیدکننده
    // دیگری باشد، بسته و نشست درست همان بازدیدکننده بازخوانی می‌شود.
    if ($sessionCookie !== session_id()) {
        session_write_close();
        session_id($sessionCookie ?: bin2hex(random_bytes(16)));
        unset($_SESSION);
        session_start();
    }
} else {
    if ($sessionCookie === null) {
        session_id(bin2hex(random_bytes(16)));
    }
    session_start();
}

// توکن CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// هدرهای امنیتی
if (!headers_sent()) {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $isPreviewHost = (bool)preg_match('/^(localhost|127\.0\.0\.1|[a-z0-9.-]+\.(e2b\.app|preview\.dev))(:\d+)?$/', $host);

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

// ------------------------------------------------------------- Database
try {
    $isNew = !file_exists(DB_FILE);
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<title>خطا در اتصال به پایگاه داده</title></head>'
        . '<body style="font-family:Tahoma,sans-serif;padding:24px;line-height:2;background:#f8fafc">'
        . '<h1 style="color:#b91c1c">خطا در اتصال به پایگاه داده</h1>'
        . '<p><b>پیام فنی:</b> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>راه‌حل‌ها:</p><ol>'
        . '<li>افزونه <code>pdo_sqlite</code> روی هاست فعال باشد و نسخه PHP حداقل ۷.۴ باشد.</li>'
        . '<li>پوشه پروژه برای کاربر وب‌سرور قابل نوشتن باشد تا فایل <code>parssaze.db</code> ساخته شود '
        . '(در IIS کاربر <code>IIS_IUSRS</code> با دسترسی Modify).</li>'
        . '<li>فایل <code>check.php</code> را در مرورگر باز کنید تا وضعیت همه پیش‌نیازها را ببینید.</li>'
        . '</ol></body></html>';
    exit;
}

// ---------------------------------------------- نمایش خطاهای مهلک PHP
// جلوگیری از «صفحه سفید» یا خطای ۵۰۰ بدون توضیح: اگر برنامه با خطای مهلک یا
// استثنای مدیریت‌نشده متوقف شود، یک پیام فارسی قابل‌فهم نمایش داده می‌شود
// (جزئیات فنی فقط زمانی که display_errors روشن باشد) و کاربر به check.php
// راهنمایی می‌شود. همه مسیرها با پرچم APP_FATAL_SHOWN در برابر خروجی تکراری
// محافظت می‌شوند.
if (!function_exists('app_fatal_page')) {
    function app_fatal_page($message, $file, $line)
    {
        if (headers_sent()) {
            return;
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');

        echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<title>خطای اجرای برنامه</title></head>'
            . '<body style="font-family:Tahoma,sans-serif;padding:24px;line-height:2;background:#f8fafc">'
            . '<h1 style="color:#b91c1c">خطای اجرای برنامه (۵۰۰)</h1>'
            . '<p>اجرای برنامه متوقف شد. برای دیدن علت دقیق، فایل <code>check.php</code> را در '
            . 'مرورگر باز کنید و پیش‌نیازها را بررسی کنید: نسخه PHP (حداقل ۷.۴)، افزونه‌های '
            . '<code>pdo_sqlite</code> و <code>mbstring</code>، و دسترسی نوشتن پوشه پروژه.</p>';

        if (ini_get('display_errors')) {
            echo '<p><b>پیام فنی:</b> ' . htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') . '</p>';
            if ($file !== '') {
                echo '<p><b>فایل:</b> ' . htmlspecialchars((string)$file, ENT_QUOTES, 'UTF-8')
                    . ' — خط ' . (int)$line . '</p>';
            }
        }

        echo '</body></html>';
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

require_once __DIR__ . '/inc/schema.php';   // ساخت جداول + ستون‌های جدید
require_once __DIR__ . '/inc/seed.php';     // داده‌های نمونه (فقط بار اول)
require_once __DIR__ . '/inc/functions.php'; // توابع کمکی و منطق تجاری

install_schema($db);
seed_database($db, $isNew);
