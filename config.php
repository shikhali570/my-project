<?php
/**
 * هستهٔ مرکزی سایت «پارس سازه و آفیس»
 * - سشن امن و توکن CSRF
 * - پایگاه دادهٔ SQLite + مهاجرت ستون‌های جدید + داده‌های اولیه
 * - توابع کمکی (خروجی امن، تاریخ جلالی، اعداد فارسی، پیام لحظه‌ای)
 */

// جلوگیری از خروجی زودهنگام تا فراخوانی‌های header() (مثل ریدایرکت) همیشه سالم بمانند
if (ob_get_level() === 0) {
    @ob_start();
}

// ---------- سشن ----------
if (session_status() === PHP_SESSION_NONE) {
    // اگر مسیر پیش‌فرض ذخیرهٔ سشن در دسترس نبود، به پوشهٔ موقت منتقل شود
    // (در کانتینرها و محیط‌های بدون مسیر /var/lib/php معمول است)
    $savePath = (string)ini_get('session.save_path');
    if ($savePath === '' || !is_dir($savePath) || !is_writable($savePath)) {
        $fallback = rtrim(sys_get_temp_dir(), '/\\') . '/parssaze_sessions';
        if (!is_dir($fallback)) {
            @mkdir($fallback, 0770, true);
        }
        if (is_dir($fallback) && is_writable($fallback)) {
            @ini_set('session.save_path', $fallback);
        }
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    // سازگاری با پیش‌نمایش پروکسی (صفحه ممکن است در iframe دامنهٔ دیگر باز شود)؛
    // امنیت ارسال فرم‌ها همچنان با توکن CSRF و بررسی‌های سمت سرور تضمین می‌شود.
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
    session_start();
}

// ---------- توکن CSRF ----------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------- هدرهای امنیتی ----------
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ---------- پایگاه دادهٔ SQLite ----------
try {
    $db = new PDO('sqlite:' . __DIR__ . '/parssaze.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // ساخت جداول (شامل ستون‌های جدید برای نصب تازه)
    $db->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT NOT NULL,
            brand TEXT NOT NULL,
            price INTEGER NOT NULL,
            tax_id TEXT NOT NULL,
            stock INTEGER DEFAULT 10,
            icon TEXT DEFAULT '📦',
            description TEXT DEFAULT ''
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
            user_id INTEGER,
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
            user_id INTEGER,
            rfq_code TEXT UNIQUE NOT NULL,
            company TEXT NOT NULL,
            phone TEXT NOT NULL,
            description TEXT NOT NULL,
            created_at TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'در انتظار بررسی'
        );
    ");

    // مهاجرت جداول موجود (در صورت وجود ستون، بی‌صدا نادیده گرفته می‌شود)
    $migrations = [
        "ALTER TABLE invoices ADD COLUMN user_id INTEGER",
        "ALTER TABLE rfqs ADD COLUMN user_id INTEGER",
        "ALTER TABLE rfqs ADD COLUMN status TEXT NOT NULL DEFAULT 'در انتظار بررسی'",
        "ALTER TABLE products ADD COLUMN description TEXT DEFAULT ''",
    ];
    foreach ($migrations as $sql) {
        try {
            $db->exec($sql);
        } catch (PDOException $e) {
            // ستون از قبل وجود دارد
        }
    }

    // داده‌های اولیهٔ کاتالوگ
    $seed = [
            ['متر لیزری ۱۰۰ متری لایکا Disto D2 بلوتوث‌دار', 'surveying', 'Leica', 9800000, '2710000185962', 12, '📏', 'متر لیزری دیجیتال ۱۰۰ متری لایکا مدل Disto D2 با بلوتوث، دقت ±۲ میلی‌متر در ۱۰۰ متر، مناسب برداشت‌های سریع اجرا و دفاتر فنی.'],
            ['تراز لیزری ۳۶۰ درجه سه خط سبز بوش GLL', 'surveying', 'Bosch', 16500000, '2710000185963', 6, '📐', 'تراز لیزری سه خط سبز با پوشش ۳۶۰ درجه، خودترازگیری و کیف حمل، مناسب نصب تأسیسات، سقف کاذب و اجراي دقیق ترازها.'],
            ['کلاه ایمنی عایق برق مهندسی JSP کلاس E', 'hse', 'JSP', 680000, '2710000294110', 45, '⛑️', 'کلاه ایمنی مهندسی JSP با عایق الکتریکی کلاس E، بند تنظیم‌شونده و تهویه، مطابق استاندارد EN 397 برای محیط‌های برقی.'],
            ['جلیقه شبرنگ ۴ جیب زیپی اعلا', 'hse', 'SafeTech', 295000, '2710000294111', 80, '🦺', 'جلیقه کار شبرنگ با چهار جیب و زیپ، قابلیت دید بالا در شب، مناسب نیروهای اجرایی کارگاه و مأموران ترافیک.'],
            ['رول پلاتر تحریر ۸۰ گرم عرض ۹۰ سانت (۵۰ متری)', 'plotter', 'Double A', 740000, '2710000389104', 30, '🖨️', 'رول کاغذ پلاتر تحریر ۸۰ گرم عرض ۹۰ سانتی‌متر با هستهٔ ۲ اینچ، مناسب چاپ نقشه‌های اجرایی و تیتر پروژه.'],
            ['زونکن عطف ۸ سانت لبه فلزی پاپکو', 'stationery', 'Papco', 185000, '2710000512892', 100, '📁', 'زونکن اداری پاپکو با عطف ۸ سانت و لبهٔ فلزی مقاوم، مناسب بایگانی نقشه‌ها، اسناد مالی و دفاتر فنی.'],
    ];

    // درج کاتالوگ فقط وقتی جدول خالی است
    if ((int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn() === 0) {
        $stmt = $db->prepare("INSERT INTO products (name, category, brand, price, tax_id, stock, icon, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($seed as $row) {
            $stmt->execute($row);
        }
    }

    // بروزرسانی توضیحات برای دیتابیس‌های قدیمی‌تر (بدون تأثیر روی ردیف‌های سفارشی)
    $backfill = $db->prepare("UPDATE products SET description = ? WHERE tax_id = ? AND (description IS NULL OR description = '')");
    foreach ($seed as $row) {
        $backfill->execute([$row[7], $row[4]]);
    }
} catch (PDOException $e) {
    die("خطا در پایگاه داده: " . htmlspecialchars($e->getMessage()));
}

// ---------- توابع کمکی ----------

/** فرار از حملات XSS */
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** تبدیل ارقام انگلیسی به فارسی */
function fa_digits($str) {
    return strtr((string)$str, [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ]);
}

/** حذف همهٔ غیرارقام (برای شناسه‌ها و کدها) */
function digits_only($str) {
    return preg_replace('/\D+/', '', (string)$str);
}

/** تبدیل تاریخ میلادی «Y/m/d [H:i]» به شمسی با ارقام فارسی */
function jalali_date($gregorian) {
    $gregorian = trim((string)$gregorian);
    if ($gregorian === '') {
        return '';
    }
    $time = '';
    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})(?:\s+(\d{1,2}:\d{2}))?/', $gregorian, $m)) {
        $gy = (int)$m[1];
        $gm = (int)$m[2];
        $gd = (int)$m[3];
        $time = isset($m[4]) ? $m[4] : '';
    } else {
        return $gregorian;
    }

    // الگوریتم استاندارد میلادی → هجری شمسی
    $gDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? $gy + 1 : $gy;
    $days = 365 * $gy + (int)floor(($gy2 + 3) / 4) - (int)floor(($gy2 + 99) / 100)
          + (int)floor(($gy2 + 399) / 400) - 80 + $gd + $gDays[$gm - 1];
    $jy += 33 * (int)floor($days / 12053);
    $days %= 12053;
    $jy += 4 * (int)floor($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)floor(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + (int)floor($days / 31) : 7 + (int)floor(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));

    $out = fa_digits(sprintf('%04d/%02d/%02d', $jy, $jm, $jd));
    return $out . ($time !== '' ? ' ' . fa_digits($time) : '');
}

/** سال شمسی جاری برای فوتر */
function jalali_year() {
    $j = jalali_date(date('Y/m/d'));
    return mb_substr_fallback($j, 0, 4);
}

/** برش UTF-8 بدون وابستگی به mbstring (هم‌ارز mb_substr) */
function mb_substr_fallback($str, $start, $len) {
    $start = max(0, (int)$start);
    $len = (int)$len;
    if ($len <= 0) {
        return '';
    }
    $str = (string)$str;
    $end = $start + $len;
    if (preg_match('/^.{' . $start . ',' . $end . '}/us', $str, $m)) {
        $prefix = $m[0];
        if ($start === 0) {
            return $prefix;
        }
        if (preg_match('/^.{' . $start . '}/us', $str, $h)) {
            return substr($prefix, strlen($h[0]));
        }
        return '';
    }
    return substr($str, $start, $len);
}

/** طول رشتهٔ UTF-8 بدون وابستگی به mbstring */
function mb_strlen_fallback($str) {
    if (preg_match_all('/./us', (string)$str, $m)) {
        return count($m[0]);
    }
    return strlen((string)$str);
}

/** ثبت پیام لحظه‌ای (بین ریدایرکت‌ها منتقل می‌شود) */
function flash($message, $type = 'info') {
    $_SESSION['flash'] = ['m' => $message, 't' => $type];
}
