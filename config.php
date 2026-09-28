<?php
/**
 * پارس سازه و آفیس | فایل راه‌اندازی (Bootstrap)
 * ------------------------------------------------
 * شامل: راه‌اندازی نشست امن، اتصال پایگاه داده SQLite،
 * ساخت/به‌روزرسانی خودکار جداول (Migration)، داده‌های اولیه و توابع کمکی.
 */

date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');

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
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
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
    die('خطا در اتصال به پایگاه داده: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

require_once __DIR__ . '/inc/schema.php';   // ساخت جداول + ستون‌های جدید
require_once __DIR__ . '/inc/seed.php';     // داده‌های نمونه (فقط بار اول)
require_once __DIR__ . '/inc/functions.php'; // توابع کمکی و منطق تجاری

install_schema($db);
seed_database($db, $isNew);
