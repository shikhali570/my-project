<?php
// فعال‌سازی سشن ایمن
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// ساخت توکن CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// هدرهای امنیتی
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// اتصال پایگاه داده SQLite
try {
    $db = new PDO('sqlite:' . __DIR__ . '/parssaze.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // ساخت جداول در صورت عدم وجود
    $db->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT NOT NULL,
            brand TEXT NOT NULL,
            price INTEGER NOT NULL,
            tax_id TEXT NOT NULL,
            stock INTEGER DEFAULT 10,
            icon TEXT DEFAULT '📦'
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            phone TEXT UNIQUE NOT NULL,
            company TEXT,
            national_id TEXT,
            economic_code TEXT,
            postal_code TEXT,
            address TEXT
        );

        CREATE TABLE IF NOT EXISTS invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_no TEXT UNIQUE NOT NULL,
            tax_unique_id TEXT UNIQUE NOT NULL,
            tracking_code TEXT NOT NULL,
            buyer_name TEXT NOT NULL,
            buyer_phone TEXT NOT NULL,
            buyer_tax_id TEXT,
            subtotal INTEGER NOT NULL,
            tax_amount INTEGER NOT NULL,
            total_amount INTEGER NOT NULL,
            items_json TEXT NOT NULL,
            created_at TEXT NOT NULL,
            status TEXT DEFAULT 'ثبت قطعی در سامانه مؤدیان'
        );

        CREATE TABLE IF NOT EXISTS rfqs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rfq_code TEXT UNIQUE NOT NULL,
            company TEXT NOT NULL,
            phone TEXT NOT NULL,
            description TEXT NOT NULL,
            created_at TEXT NOT NULL
        );
    ");

    // تزریق کالاهای پیش‌فرض
    if ($db->query("SELECT COUNT(*) FROM products")->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO products (name, category, brand, price, tax_id, stock, icon) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $seed = [
            ['متر لیزری ۱۰۰ متری لایکا Disto D2 بلوتوث‌دار', 'surveying', 'Leica', 9800000, '2710000185962', 12, '📏'],
            ['تراز لیزری ۳۶۰ درجه سه خط سبز بوش GLL', 'surveying', 'Bosch', 16500000, '2710000185963', 6, '📐'],
            ['کلاه ایمنی عایق برق مهندسی JSP کلاس E', 'hse', 'JSP', 680000, '2710000294110', 45, '⛑️'],
            ['جلیقه شبرنگ ۴ جیب زیپی اعلا', 'hse', 'SafeTech', 295000, '2710000294111', 80, '🦺'],
            ['رول پلاتر تحریر ۸۰ گرم عرض ۹۰ سانت (۵۰ متری)', 'plotter', 'Double A', 740000, '2710000389104', 30, '🖨️'],
            ['زونکن عطف ۸ سانت لبه فلزی پاپکو', 'stationery', 'Papco', 185000, '2710000512892', 100, '📁']
        ];
        foreach ($seed as $row) {
            $stmt->execute($row);
        }
    }
} catch (PDOException $e) {
    die("خطا در پایگاه داده: " . htmlspecialchars($e->getMessage()));
}

// تابع فرار از حملات XSS
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}