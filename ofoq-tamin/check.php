<?php
/**
 * عیب‌یاب نصب (Diagnostic) — نسخه ۲
 * ---------------------------------
 * اگر سایت خطای ۵۰۰ / صفحه سفید می‌دهد، این فایل را در مرورگر باز کنید:
 *     https://your-domain.ir/check.php
 *
 * این نسخه مخصوص هاست‌های ویندوز/IIS هم هست: وضعیت FastCGI، مسیر ذخیره نشست‌ها،
 * سطح دسترسی واقعی نوشتن روی دیسک، اعتبار فایل web.config و اثرگذاری .user.ini
 * بررسی می‌شود.
 *
 * ⚠️ پس از رفع مشکل، این فایل را از هاست حذف کنید.
 */

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');

$IS_WINDOWS = (DIRECTORY_SEPARATOR === '\\');
$groups = array();

// --------------------------------------------------------------- توابع کمکی
function add_row(&$groups, $group, $title, $value, $ok, $hint = '')
{
    if (!isset($groups[$group])) {
        $groups[$group] = array();
    }
    $groups[$group][] = array('title' => $title, 'value' => $value, 'ok' => $ok, 'hint' => $hint);
}

/** آیا پوشه واقعاً قابل نوشتن است؟ (در ویندوز is_writable قابل اعتماد نیست) */
function real_writable($dir)
{
    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }
    $probe = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.wtest_' . bin2hex(random_bytes(4)) . '.tmp';
    $ok = @file_put_contents($probe, 'ok');
    if ($ok === false) {
        return false;
    }
    @unlink($probe);
    return true;
}

/** خواندن پوشه بدون خطا */
function dir_info($dir)
{
    if (!is_dir($dir)) {
        return array('files' => 0, 'size' => 0);
    }
    $files = 0;
    $size = 0;
    $items = @scandir($dir);
    if (is_array($items)) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_file($path)) {
                $files++;
                $size += (int)@filesize($path);
            }
        }
    }
    return array('files' => $files, 'size' => $size);
}

function bytes_human($bytes)
{
    $bytes = (float)$bytes;
    foreach (array('B', 'KB', 'MB', 'GB') as $unit) {
        if ($bytes < 1024) {
            return round($bytes, 1) . ' ' . $unit;
        }
        $bytes /= 1024;
    }
    return round($bytes, 1) . ' TB';
}

/** مقدار ini به شکل خوانا */
function ini_view($key)
{
    $value = ini_get($key);
    if ($value === false || $value === '') {
        return '(خالی)';
    }
    return (string)$value;
}

/** آیا مقدار boolean یک کلید ini روشن است؟ */
function ini_on($key)
{
    $value = strtolower(trim((string)ini_get($key)));
    return in_array($value, array('1', 'on', 'yes', 'true'), true);
}

function ini_bytes($key)
{
    $value = trim((string)ini_get($key));
    if ($value === '' || $value === '-1') {
        return -1;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    switch ($unit) {
        case 'g':
            $number *= 1024 * 1024 * 1024;
            break;
        case 'm':
            $number *= 1024 * 1024;
            break;
        case 'k':
            $number *= 1024;
            break;
    }
    return (int)$number;
}

// =============================================================== ۱) PHP پایه
$phpMinOk = version_compare(PHP_VERSION, '7.4.0', '>=');
$phpGood = version_compare(PHP_VERSION, '8.0.0', '>=');

add_row($groups, 'PHP', 'نسخه PHP', PHP_VERSION . ($phpGood ? ' ✔' : ($phpMinOk ? ' (حداقل لازم؛ ۸.۰ توصیه می‌شود)' : ' — کمتر از حداقل!')), $phpMinOk,
    'حداقل ۷.۴؛ پیشنهاد ۸.۰ یا بالاتر. در Plesk: Websites & Domains ← PHP Settings');
add_row($groups, 'PHP', 'حالت اجرا (SAPI)', PHP_SAPI, true,
    $IS_WINDOWS ? 'روی IIS این مقدار باید cgi-fcgi باشد. اگر چیزی مثل isapi یا cli دیدید، نگاشت PHP روی سرور اشتباه است' : 'روی لینوکس معمولاً fpm-fcgi یا apache2handler');
add_row($groups, 'PHP', 'سیستم عامل سرور', PHP_OS . ($IS_WINDOWS ? ' (ویندوز)' : ' (لینوکس/یونیکس)'), true, '');
add_row($groups, 'PHP', 'فایل php.ini فعال', (string)(php_ini_loaded_file() ?: 'یافت نشد'), true,
    'اگر «یافت نشد» بود یا مسیر آن اشتباه بود، PHP با تنظیمات پیش‌فرض اجرا می‌شود');
$scanned = (string)php_ini_scanned_files();
add_row($groups, 'PHP', 'پوشه‌های تنظیمات افزوده', trim($scanned) === '' ? '(ندارد)' : trim($scanned), true, 'فایل‌های ini که در پوشه conf.d خوانده می‌شوند');
add_row($groups, 'PHP', 'محدودیت حافظه (memory_limit)', ini_view('memory_limit'), ini_bytes('memory_limit') >= 64 * 1024 * 1024 || ini_bytes('memory_limit') < 0,
    'برای این برنامه حداقل ۶۴ مگابایت لازم است (۱۲۸ یا ۲۵۶ توصیه می‌شود)');
add_row($groups, 'PHP', 'حداکثر زمان اجرا', ini_view('max_execution_time') . ' ثانیه', (int)ini_get('max_execution_time') >= 30 || (int)ini_get('max_execution_time') === 0, 'مقدار ۹۰ ثانیه در .user.ini تنظیم شده است');
add_row($groups, 'PHP', 'در حال اجرا روی خروجی بافر', ini_on('output_buffering') ? ini_view('output_buffering') : 'خاموش', true,
    'بافر خروجی به جلوگیری از خطای «headers already sent» کمک می‌کند ولی لازم نیست');

// =========================================================== ۲) افزونه‌ها
$extensions = array(
    'pdo'        => array('اتصال به پایگاه داده', true),
    'pdo_sqlite' => array('درایور SQLite (پایگاه داده این برنامه)', true),
    'mbstring'   => array('کار با متن فارسی (mb_strlen / mb_substr)', true),
    'json'       => array('خروجی JSON در api.php', true),
    'session'    => array('ورود کاربران و سبد سفارش', true),
    'xml'        => array('بررسی اعتبار web.config (اختیاری)', false),
    'dom'        => array('بررسی XML با DOM (اختیاری)', false),
    'iconv'      => array('تبدیل کدگذاری متن (اختیاری)', false),
    'fileinfo'   => array('تشخیص نوع فایل (اختیاری)', false),
    'openssl'    => array('اتصال‌های رمزنگاری‌شده (اختیاری)', false),
);

$missingRequiredExt = array();
foreach ($extensions as $ext => $meta) {
    $loaded = extension_loaded($ext);
    if (!$loaded && $meta[1]) {
        $missingRequiredExt[] = $ext;
    }
    add_row(
        $groups,
        'افزونه‌های PHP',
        'افزونه ' . $ext,
        $loaded ? 'فعال' : 'غیرفعال' . ($meta[1] ? ' (ضروری!)' : ' (اختیاری)'),
        $loaded || !$meta[1],
        $meta[0]
    );
}

if (class_exists('PDO')) {
    $drivers = @PDO::getAvailableDrivers();
    $driverList = is_array($drivers) ? implode(' ، ', $drivers) : '';
    add_row($groups, 'افزونه‌های PHP', 'درایورهای فعال PDO', $driverList !== '' ? $driverList : '(هیچ‌کدام)', in_array('sqlite', is_array($drivers) ? $drivers : array(), true),
        'برای این برنامه درایور sqlite الزامی است');
}
add_row($groups, 'افزونه‌های PHP', 'تعداد افزونه‌های بارگذاری‌شده', (string)count((array)get_loaded_extensions()), true, '');

// ==================================================== ۳) فایل‌ها و دسترسی‌ها
$required = array(
    'index.php', 'config.php', 'check.php',
    'inc/schema.php', 'inc/seed.php', 'inc/functions.php', 'inc/auth.php', 'inc/actions.php',
    'header.php', 'footer.php', 'admin_header.php', 'panel_header.php',
    'style.css', 'app.js', 'api.php',
    'views/home.php', 'views/login.php', 'views/admin/dashboard.php', 'views/panel/dashboard.php',
);
$missing = array();
foreach ($required as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $missing[] = $file;
    }
}
add_row($groups, 'فایل‌ها و دسترسی‌ها', 'فایل‌های ضروری پروژه',
    count($missing) === 0 ? 'کامل (از ' . count($required) . ' فایل بررسی‌شده، همه موجود)' : ('ناقص — ' . implode(' ، ', $missing)),
    count($missing) === 0,
    'بسته ZIP را کامل استخراج کنید؛ پوشه‌های inc و views باید کنار index.php باشند');

$rootWritable = real_writable(__DIR__);
add_row($groups, 'فایل‌ها و دسترسی‌ها', 'نوشتن واقعی در پوشه پروژه (آزمون فایل آزمایشی)', $rootWritable ? 'موفق' : 'ناموفق (فقط‌خواندنی)',
    $rootWritable,
    $IS_WINDOWS
        ? 'در IIS: روی پوشه سایت راست‌کلیک ← Properties ← Security؛ کاربر IIS_IUSRS (یا حساب Application Pool) با دسترسی Modify. از پنل هاست هم می‌توان پوشه را writable کرد'
        : 'در لینوکس: chmod 755 پوشه‌ها و کاربر وب‌سرور مالک/نویسنده باشد (775 در صورت نیاز)');

$dbPath = __DIR__ . '/parssaze.db';
$dbExists = file_exists($dbPath);
add_row($groups, 'فایل‌ها و دسترسی‌ها', 'فایل پایگاه داده (parssaze.db)',
    $dbExists ? ('موجود — ' . bytes_human(@filesize($dbPath))) : 'هنوز ساخته نشده (در اولین اجرا ساخته می‌شود)',
    true,
    'این فایل در زمان اجرا کنار index.php ساخته می‌شود و باید قابل نوشتن باشد');
if ($dbExists) {
    $dbWritable = @is_writable($dbPath);
    $dbWritableReal = false;
    if ($dbWritable) {
        $handle = @fopen($dbPath, 'ab');
        if ($handle) {
            $dbWritableReal = true;
            @fclose($handle);
        }
    }
    add_row($groups, 'فایل‌ها و دسترسی‌ها', 'قابل نوشتن بودن فایل پایگاه داده', $dbWritableReal ? 'بله' : 'خیر',
        $dbWritableReal,
        $IS_WINDOWS ? 'در IIS روی همان فایل هم باید دسترسی Modify داشته باشد، نه فقط پوشه' : 'کاربر وب‌سرور باید روی فایل و پوشه دسترسی نوشتن داشته باشد');
}

$tmpDir = __DIR__ . '/tmp';
$tmpExists = is_dir($tmpDir);
$tmpWritable = $tmpExists ? real_writable($tmpDir) : false;
add_row($groups, 'فایل‌ها و دسترسی‌ها', 'پوشه tmp (فایل‌های موقت)',
    $tmpExists ? ($tmpWritable ? 'موجود و قابل نوشتن' : 'موجود ولی غیرقابل نوشتن') : 'وجود ندارد',
    $tmpExists && $tmpWritable,
    'برای ذخیره فایل‌های نشست استفاده می‌شود. اگر وجود ندارد، خود برنامه با اولین اجرا آن را می‌سازد');

$sessionDir = $tmpDir . '/sessions';
$sessionDirExists = is_dir($sessionDir);
$sessionDirWritable = $sessionDirExists ? real_writable($sessionDir) : false;
$sessionDirInfo = dir_info($sessionDir);
add_row($groups, 'فایل‌ها و دسترسی‌ها', 'پوشه tmp/sessions (ذخیره نشست‌ها)',
    $sessionDirExists
        ? ($sessionDirWritable
            ? 'موجود و قابل نوشتن — ' . $sessionDirInfo['files'] . ' فایل فعال (' . bytes_human($sessionDirInfo['size']) . ')'
            : 'موجود ولی غیرقابل نوشتن')
        : 'وجود ندارد (در نیاز، خودکار ساخته می‌شود)',
    !$sessionDirExists || $sessionDirWritable,
    'اگر مسیر پیش‌فرض نشست هاست قابل نوشتن نباشد، برنامه از این پوشه استفاده می‌کند');

// ============================================================== ۴) نشست‌ها
$savePath = trim((string)ini_get('session.save_path'));
$savePathClean = $savePath;
if ($savePathClean !== '' && strpos($savePathClean, ';') !== false) {
    $parts = explode(';', $savePathClean);
    $savePathClean = trim((string)end($parts));
}
$savePathOk = ($savePathClean !== '' && is_dir($savePathClean) && is_writable($savePathClean));
$savePathUnknown = ($savePath === '');

add_row($groups, 'نشست (Session)', 'session.save_path',
    $savePath === '' ? '(خالی — پوشه موقت سیستم)' : $savePath,
    $savePathOk || $savePathUnknown,
    'اگر خالی یا غیرقابل نوشتن باشد، ورود کاربر «نمی‌ماند» و پیام‌های سیستم ناپدید می‌شوند؛ برنامه در این حالت خودکار از tmp/sessions استفاده می‌کند');
add_row($groups, 'نشست (Session)', 'پوشه موقت سیستم', (string)(sys_get_temp_dir() ?: 'نامشخص'),
    true,
    $IS_WINDOWS
        ? 'روی IIS با fastcgi.impersonate = 1 کاربر محدود به IUSR نمی‌تواند در پوشه temp ویندوز بنویسد؛ همان دلیل اهمیت tmp/sessions است'
        : 'اگر این پوشه غیرقابل نوشتن باشد، tmp/sessions جایگزین می‌شود');
add_row($groups, 'نشست (Session)', 'فایل PHP-safe برای نشست‌ها در پوشه tmp/sessions', $sessionDirExists && $sessionDirWritable ? 'آماده' : 'آماده نیست',
    true, 'در صورت نبود دسترسی، برنامه با هشدار کار می‌کند ولی ورود کاربران ممکن است پایدار نباشد');
$cookieHardening = ini_on('session.cookie_httponly') && ini_on('session.use_strict_mode');
add_row($groups, 'نشست (Session)', 'سخت‌سازی کوکی نشست (httponly / use_strict_mode)',
    ini_view('session.cookie_httponly') . ' / ' . ini_view('session.use_strict_mode'),
    $cookieHardening,
    'برنامه در زمان اجرا هر دو را روشن می‌کند، پس این ردیف برای خود سایت مشکلی ایجاد نمی‌کند؛ مقدار اینجا فقط تنظیم سراسری سرور را نشان می‌دهد. برای روشن‌کردن دائمی، در .user.ini یا پنل هاست تنظیم کنید');

// ============================================ ۵) آزمون عملی نشست (End-to-End)
$sessionTestOk = false;
$sessionTestValue = '';
if (!headers_sent()) {
    $previousName = session_name();
    $previousPath = ini_get('session.save_path');
    $previousLevel = error_reporting(0);
    @session_write_close();
    session_name('DIAG_SID');
    if ($sessionDirExists && $sessionDirWritable) {
        @ini_set('session.save_path', $sessionDir);
    }
    @session_id('diag' . bin2hex(random_bytes(6)));
    $started = @session_start();
    if ($started) {
        $_SESSION['diag_time'] = time();
        $sessionTestOk = true;
        $sessionTestValue = 'موفق — نوشتن و خواندن نشست انجام شد (شناسه: ' . substr(session_id(), 0, 8) . '…)';
        @session_write_close();
        @session_start();
        if (!isset($_SESSION['diag_time'])) {
            $sessionTestOk = false;
            $sessionTestValue = 'نوشتن موفق بود ولی خواندن مقدار ناموفق (مشکل فایل‌های نشست)';
        }
    } else {
        $sessionTestValue = 'ناموفق — session_start() اجرا نشد';
    }
    @session_write_close();
    error_reporting($previousLevel);
    @ini_set('session.save_path', $previousPath);
    session_name($previousName);
    @session_id('');
    @session_start();
} else {
    $sessionTestValue = 'قابل بررسی نیست (خروجی صفحه شروع شده است)';
}
add_row($groups, 'نشست (Session)', 'آزمون عملی ساخت و خواندن نشست', $sessionTestValue, $sessionTestOk,
    'اگر این ردیف قرمز است، مشکل «ورود ناموفق» یا «پیام‌های ناپدیدشونده» از همین قسمت است: مسیر ذخیره نشست باید قابل نوشتن باشد');

// ========================================================= ۶) پایگاه داده
$dbOk = false;
$dbValue = '';
$dbHint = 'فایل parssaze.db در همین پوشه ساخته می‌شود (اگر از قبل هست، فقط وصل می‌شود)';
try {
    if (!extension_loaded('pdo_sqlite')) {
        throw new Exception('افزونه pdo_sqlite فعال نیست');
    }
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE IF NOT EXISTS _diag (id INTEGER PRIMARY KEY, t TEXT)');
    $pdo->exec("INSERT INTO _diag (t) VALUES ('ok')");
    $found = $pdo->query("SELECT COUNT(*) FROM _diag WHERE t = 'ok'")->fetchColumn();
    $pdo->exec('DROP TABLE _diag');
    if ((int)$found < 1) {
        throw new Exception('نوشتن و خواندن در پایگاه داده انجام نشد');
    }
    $dbOk = true;
    $integrity = '';
    try {
        $integrityRow = $pdo->query('PRAGMA integrity_check')->fetchColumn();
        if ($integrityRow && strtolower((string)$integrityRow) !== 'ok') {
            $integrity = ' — هشدار یکپارچگی: ' . $integrityRow;
        }
    } catch (Exception $ignore) {
        $integrity = '';
    }
    $dbValue = 'اتصال، نوشتن و خواندن موفق (فایل: ' . ($dbExists ? 'از قبل موجود' : 'ساخته شد') . ')' . $integrity;
} catch (Exception $e) {
    $dbValue = 'خطا: ' . $e->getMessage();
}
add_row($groups, 'پایگاه داده', 'آزمون کامل SQLite', $dbValue, $dbOk, $dbHint);

$tablesCount = null;
if ($dbOk) {
    try {
        $tablesCount = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn();
    } catch (Exception $ignore) {
        $tablesCount = null;
    }
}
add_row($groups, 'پایگاه داده', 'جداول موجود در پایگاه داده',
    $tablesCount === null ? 'قابل خواندن نیست' : ($tablesCount . ' جدول'),
    true,
    'در نصب سالم ۱۲ جدول ساخته می‌شود: users، categories، products، orders، order_items، invoices، rfqs، coupons، favorites، notifications، activity_logs، settings');

// ============================================================== ۷) سرور
$serverSoftware = isset($_SERVER['SERVER_SOFTWARE']) ? (string)$_SERVER['SERVER_SOFTWARE'] : 'نامشخص';
$isIIS = (stripos($serverSoftware, 'IIS') !== false) || (stripos($serverSoftware, 'Microsoft') !== false) || isset($_SERVER['APPL_PHYSICAL_PATH']) || isset($_SERVER['APP_POOL_ID']);
add_row($groups, 'سرور وب', 'نرم‌افزار سرور', $serverSoftware . ($isIIS ? ' — تشخیص: IIS ویندوز' : ''), true,
    $isIIS ? 'راهنمای کامل رفع خطا برای IIS در فایل docs/IIS-SETUP.md' : 'برای آپاچی/لایت‌اسپید فایل .htaccess استفاده می‌شود');
add_row($groups, 'سرور وب', 'پورت و پروتکل', (isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '?')
    . ' / ' . ((!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'HTTPS' : 'HTTP'), true, '');
add_row($groups, 'سرور وب', 'میزبان درخواست (HTTP_HOST)', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'نامشخص', true, '');
add_row($groups, 'سرور وب', 'مسیر فیزیکی اپلیکیشن (docroot)', isset($_SERVER['APPL_PHYSICAL_PATH']) ? $_SERVER['APPL_PHYSICAL_PATH'] : (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'نامشخص'), true,
    'این مسیر باید همان مسیر استخراج بسته ZIP باشد؛ اگر متفاوت است، فایل‌ها در پوشه دیگری هستند');
if ($isIIS) {
    add_row($groups, 'سرور وب', 'نام Application Pool', isset($_SERVER['APP_POOL_ID']) ? $_SERVER['APP_POOL_ID'] : 'نامشخص', true,
        'همین نام، کاربر اجرای PHP را مشخص می‌کند؛ روی پوشه سایت باید دسترسی Modify داشته باشد');
    add_row($groups, 'سرور وب', 'شناسه نمونه IIS (INSTANCE_ID)', isset($_SERVER['INSTANCE_ID']) ? $_SERVER['INSTANCE_ID'] : 'نامشخص', true, '');
}

// ============================================== ۸) تنظیمات PHP مهم
add_row($groups, 'تنظیمات PHP', 'display_errors', ini_view('display_errors') . (ini_on('display_errors') ? ' (روشن — برای عیب‌یابی خوب، برای محیط نهایی نه)' : ' (خاموش)'), true,
    'برای عیب‌یابی روی On باشد و پس از رفع مشکل در .user.ini یا پنل هاست Off شود');
add_row($groups, 'تنظیمات PHP', 'log_errors / error_log', ini_view('log_errors') . ' / ' . ini_view('error_log'), true, 'مسیر فایل لاگ خطاها؛ اگر روی هاست قابل خواندن باشد، انتهای همین صفحه نمایش داده می‌شود');
add_row($groups, 'تنظیمات PHP', 'open_basedir', ini_view('open_basedir'), trim((string)ini_get('open_basedir')) === '',
    'اگر مقدار داشته باشد، دسترسی PHP به مسیرهای بیرون آن ممنوع است و می‌تواند علت خطای اجرا باشد؛ tmp و parssaze.db باید داخل آن مسیرها باشند');
add_row($groups, 'تنظیمات PHP', 'disable_functions', ini_view('disable_functions'), stripos((string)ini_get('disable_functions'), 'random_bytes') === false,
    'اگر random_bytes یا سریال‌سازی توابع حیاتی مسدود باشد، برنامه با خطا متوقف می‌شود');
add_row($groups, 'تنظیمات PHP', 'upload_max_filesize / post_max_size', ini_view('upload_max_filesize') . ' / ' . ini_view('post_max_size'), true, '');
add_row($groups, 'تنظیمات PHP', 'upload_tmp_dir', ini_view('upload_tmp_dir'), true, $IS_WINDOWS ? 'روی IIS باید قابل نوشتن برای کاربر Application Pool باشد' : '');
add_row($groups, 'تنظیمات PHP', 'default_charset', ini_view('default_charset'), true, 'برای نمایش درست فارسی باید UTF-8 باشد (.user.ini این مقدار را تنظیم می‌کند)');
add_row($groups, 'تنظیمات PHP', 'date.timezone', ini_view('date.timezone'), true, 'برنامه خودش Asia/Tehran را تنظیم می‌کند');
$extDir = (string)ini_get('extension_dir');
$extDirOk = ($extDir !== '' && is_dir($extDir));
add_row($groups, 'تنظیمات PHP', 'پوشه افزونه‌ها (extension_dir)', $extDir === '' ? '(خالی)' : $extDir, $extDirOk,
    'اگر این مسیر وجود نداشته باشد، PHP نمی‌تواند افزونه‌هایی مثل pdo_sqlite و mbstring را بارگذاری کند؛ مقدار درست در php.ini سرور تنظیم می‌شود (پشتیبانی هاست).'
    . (extension_loaded('pdo_sqlite') ? ' — pdo_sqlite در همین لحظه بارگذاری شده، پس فعلاً مشکلی نیست' : ''));
add_row($groups, 'تنظیمات PHP', 'doc_root', ini_view('doc_root'), true,
    'روی هاست اشتراکی معمولاً خالی است؛ مقدار داشتنش به‌تنهایی مشکلی ایجاد نمی‌کند');

// پوشه کاری: روی IIS معمولاً ریشه سایت نیست و includeهای نسبی را می‌شکند
$cwd = function_exists('getcwd') ? (string)getcwd() : '';
$cwdOk = ($cwd === '' || rtrim(str_replace('\\', '/', $cwd), '/') === rtrim(str_replace('\\', '/', __DIR__), '/'));
add_row($groups, 'تنظیمات PHP', 'پوشه کاری فعلی (getcwd)',
    $cwd === '' ? '(نامشخص)' : $cwd, $cwdOk,
    'این ردیف برای اطلاع است: روی IIS پوشه کاری معمولاً ریشه سایت نیست و همین علت خطای «Failed opening required» در نسخه‌های قبلی بود. برنامه با chdir(APP_ROOT) و includeهای مبتنی بر APP_ROOT/__DIR__ مستقل از پوشه کاری شده است');

if ($isIIS) {
    add_row($groups, 'تنظیمات IIS/PHP', 'fastcgi.impersonate', ini_view('fastcgi.impersonate'),
        !ini_on('fastcgi.impersonate') || $sessionDirWritable || $rootWritable,
        'اگر روشن باشد (معمول روی IIS)، PHP با هویت کاربر درخواست‌کننده اجرا می‌شود و همین باعث می‌شود دسترسی نوشتن پوشه‌ها حساس شود; راه‌حل: پوشه سایت Modify برای IIS_IUSRS یا استفاده از tmp/sessions');
    add_row($groups, 'تنظیمات IIS/PHP', 'cgi.fix_pathinfo', ini_view('cgi.fix_pathinfo'), true, 'روی IIS معمولاً ۱ است و مشکلی ایجاد نمی‌کند');
    $forceRedirect = trim((string)ini_get('cgi.force_redirect'));
    $forceRedirectEmpty = ($forceRedirect === '');
    add_row($groups, 'تنظیمات IIS/PHP', 'cgi.force_redirect',
        $forceRedirectEmpty ? '(خالی — مقدار پیش‌فرض ۱)' : $forceRedirect,
        true,
        'اگر روی ۱ باشد و سرور متغیر REDIRECT_STATUS را به PHP نرساند، PHP با پیام «Security Alert! The PHP CGI cannot be accessed directly» و خطای ۵۰۰ از کار می‌افتد. حل آن کار پشتیبانی هاست است: تنظیم REDIRECT_STATUS=200 در محیط FastCGI یا قرار دادن cgi.force_redirect=0 در php.ini');
    add_row($groups, 'تنظیمات IIS/PHP', 'fastcgi.logging', ini_view('fastcgi.logging'), true,
        'روی IIS خطاهای FastCGI در Event Viewer (Windows Logs ← Application، منبع FastCGI/W3SVC) ثبت می‌شوند. اگر سایت ۵۰۰ می‌دهد و لاگ PHP خالی است، اینجا را ببینید');
    add_row($groups, 'تنظیمات IIS/PHP', 'متغیر محیطی PHP_FCGI_MAX_REQUESTS', (string)(getenv('PHP_FCGI_MAX_REQUESTS') ?: 'تعیین‌نشده'), true,
        'روی IIS بهتر است ۱۰۰۰ یا بیشتر باشد؛ مقدار کم می‌تواند باعث خطای ۵۰۰ متناوب شود');
    add_row($groups, 'تنظیمات IIS/PHP', 'php-cgi.exe در حال اجرا', (PHP_BINARY !== '' ? PHP_BINARY : 'نامشخص'), true,
        'این مسیر باید در تنظیمات Handler سرور ثبت شده باشد (روی Plesk خودکار انجام می‌شود)');
}

// ================================ ۸.۵) بازرسی کد: وابستگی به پوشه کاری
// include نسبی (بدون __DIR__/APP_ROOT) روی IIS شکست می‌خورد، چون پوشه کاری
// php-cgi ریشه سایت نیست؛ نتیجه‌اش خطای ۵۰۰ در همان صفحه است.
$scanFiles = array(
    'index.php', 'api.php', 'export.php', 'php-test.php',
    'views/home.php', 'views/product.php', 'views/panel/favorites.php',
    'views/partials/product_card.php', 'inc/functions.php', 'inc/auth.php', 'inc/actions.php',
);
$relativeIncludes = array();
$scannedCount = 0;
foreach ($scanFiles as $scanFile) {
    $full = __DIR__ . '/' . $scanFile;
    if (!is_file($full)) {
        continue;
    }
    $scannedCount++;
    $source = (string)@file_get_contents($full);
    if (preg_match_all('/(require|include)(_once)?\s*\(?\s*([\'"])([^\'"]+)\3/', $source, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            if (strpos($m[4], '__DIR__') !== false || strpos($m[4], 'APP_ROOT') !== false) {
                continue;
            }
            $relativeIncludes[] = $scanFile . ' → ' . $m[4];
        }
    }
}
add_row($groups, 'بازرسی کد', 'include نسبی وابسته به پوشه کاری',
    count($relativeIncludes) === 0
        ? ('وجود ندارد (از ' . $scannedCount . ' فایل بررسی‌شده)')
        : implode(' ، ', $relativeIncludes),
    count($relativeIncludes) === 0,
    'روی IIS پوشه کاری، ریشه سایت نیست؛ هر include نسبی باعث خطای ۵۰۰ «Failed opening required» می‌شود. در این نسخه همه includeها با __DIR__ یا APP_ROOT هستند و config.php هم chdir(APP_ROOT) می‌کند');
add_row($groups, 'بازرسی کد', 'قفل‌کردن پوشه کاری به ریشه برنامه (chdir)',
    defined('APP_ROOT') || strpos((string)@file_get_contents(__DIR__ . '/config.php'), 'chdir(APP_ROOT)') !== false ? 'انجام شده' : 'انجام نشده',
    strpos((string)@file_get_contents(__DIR__ . '/config.php'), 'chdir(APP_ROOT)') !== false,
    'در config.php پوشه کاری روی ریشه برنامه تنظیم می‌شود تا مسیرهای نسبی همیشه درست باشند');

// ======================================== ۹) اثرگذاری .user.ini و فایل‌ها
$userIniPath = __DIR__ . '/.user.ini';
$userIniExists = file_exists($userIniPath);
add_row($groups, 'فایل‌های تنظیمات', 'فایل .user.ini', $userIniExists ? 'موجود' : 'وجود ندارد', true,
    'تنظیمات PHP در سطح پوشه برای هاست‌های اشتراکی CGI/FastCGI (روی IIS هم خوانده می‌شود)');
if ($userIniExists) {
    $userIniRaw = (string)@file_get_contents($userIniPath);
    $userIniApplied = array();
    $userIniIgnored = array();
    foreach (preg_split('/\r\n|\r|\n/', $userIniRaw) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === ';' || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        $value = trim($value, "\"'");
        $current = ini_get($key);
        // یکسان‌سازی مقادیر: On/Off، ثابت‌های PHP (مثل E_ALL) و مقادیر عددی
        $norm = function ($v) {
            $v = trim((string)$v);
            if ($v !== '' && preg_match('/^[A-Z][A-Z0-9_]*$/', $v) && defined($v)) {
                $v = (string)constant($v);
            }
            $lower = strtolower($v);
            if (in_array($lower, array('on', 'yes', 'true'), true)) {
                return '1';
            }
            if (in_array($lower, array('off', 'no', 'false'), true)) {
                return '0';
            }
            if (is_numeric($v)) {
                return (string)(int)$v;
            }
            return $lower;
        };
        if (ini_get($key) === false) {
            $userIniIgnored[] = $key . ' (ناشناخته برای این نسخه PHP)';
        } elseif ($norm($current) === $norm($value)) {
            $userIniApplied[] = $key . ' = ' . $current;
        } else {
            $userIniIgnored[] = $key . ' (خواسته: ' . $value . ' / فعلی: ' . ($current === '' ? 'خالی' : $current) . ')';
        }
    }
    add_row($groups, 'فایل‌های تنظیمات', 'تنظیمات .user.ini که اعمال شده‌اند', count($userIniApplied) ? implode(' ، ', $userIniApplied) : 'هیچ‌کدام',
        count($userIniApplied) > 0,
        'PHP این فایل را تا user_ini.cache_ttl ثانیه (پیش‌فرض ۳۰۰ = ۵ دقیقه) کش می‌کند؛ بعد از هر ویرایش کمی صبر کنید یا Application Pool را ری‌استارت کنید');
    add_row($groups, 'فایل‌های تنظیمات', 'تنظیمات .user.ini که اعمال نشده‌اند',
        count($userIniIgnored) ? ('ℹ ' . implode(' ، ', $userIniIgnored)) : 'هیچ‌کدام',
        true,
        'اطلاعاتی است: یعنی هاست شما (یا پنل هاست) مقدار دیگری را تحمیل کرده و فایل .user.ini در آن کلید اثر نداشته. برنامه مقادیر حیاتی (نمایش خطا، مسیر نشست، محدودیت حافظه) را در زمان اجرا خودش تنظیم می‌کند؛ فقط اگر مقدار خواسته‌شده در پنل هاست هم قابل تنظیم است، از همان‌جا اصلاح کنید');
    add_row($groups, 'فایل‌های تنظیمات', 'user_ini.filename / cache_ttl',
        ini_view('user_ini.filename') . ' / ' . ini_view('user_ini.cache_ttl') . ' ثانیه', true,
        'اگر user_ini.filename خالی باشد، این هاست فایل .user.ini را نادیده می‌گیرد');
}

// ------------------------------------------------ بررسی فایل web.config
$webConfigPath = __DIR__ . '/web.config';
if (!file_exists($webConfigPath)) {
    add_row($groups, 'پیکربندی IIS (web.config)', 'فایل web.config', 'وجود ندارد', true,
        'برای هاست ویندوز/IIS لازم است (فقط برای فعال بودن «صفحه پیش‌فرض» و مسدودکردن فهرست پوشه‌ها). اگر سایت کار می‌کند نبودن آن هم اشکالی ندارد');
} else {
    $webConfigRaw = (string)@file_get_contents($webConfigPath);
    $xmlOk = true;
    $xmlValue = 'سالم و معتبر';
    $xmlHint = 'فایل web.config نامعتبر روی IIS همان لحظه خطای ۵۰۰.۱۹ می‌دهد';

    // بررسی ۰: کدگذاری فایل (خطای کلاسیک انتقال با FTP در حالت ASCII)
    $hasBom = (substr($webConfigRaw, 0, 3) === "\xEF\xBB\xBF");
    $nonAscii = preg_match('/[\x80-\xFF]/', $webConfigRaw);
    $hasCrlf = (strpos($webConfigRaw, "\r\n") !== false);

    // بررسی ۱: دو خط تیره پشت‌سرهم داخل توضیح‌ها (استاندارد XML ممنوع کرده است)
    if (preg_match_all('/<!--(.*?)-->/s', $webConfigRaw, $comments)) {
        foreach ($comments[1] as $commentBody) {
            if (strpos($commentBody, '--') !== false) {
                $xmlOk = false;
                $xmlValue = 'نامعتبر: دو خط تیره پشت‌سرهم داخل توضیح‌ها';
                $xmlHint = 'این حالت روی IIS خطای ۵۰۰.۱۹ می‌دهد؛ در توضیح‌های web.config از «--» استفاده نکنید';
                break;
            }
        }
    }

    // بررسی ۲: تجزیه واقعی XML (expat سخت‌گیر است و به رفتار IIS نزدیک‌تر)
    if ($xmlOk && function_exists('xml_parser_create')) {
        $parser = xml_parser_create('UTF-8');
        if ($parser !== false) {
            xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
            $parsed = @xml_parse($parser, $webConfigRaw, true);
            if ($parsed === 0) {
                $errorText = function_exists('xml_error_string')
                    ? trim((string)@xml_error_string(@xml_get_error_code($parser)))
                    : 'ساختار XML نامعتبر';
                $xmlOk = false;
                $xmlValue = 'نامعتبر: ' . $errorText . ' (خط ' . (int)@xml_get_current_line_number($parser) . ')';
            }
            xml_parser_free($parser);
        }
    } elseif ($xmlOk && function_exists('simplexml_load_string')) {
        $prev = function_exists('libxml_use_internal_errors') ? libxml_use_internal_errors(true) : null;
        $xml = @simplexml_load_string($webConfigRaw);
        if ($xml === false) {
            $errors = function_exists('libxml_get_errors') ? libxml_get_errors() : array();
            $xmlOk = false;
            $xmlValue = 'نامعتبر: ' . (isset($errors[0]) ? trim($errors[0]->message) . ' (خط ' . (int)$errors[0]->line . ')' : 'ساختار XML نامعتبر');
        }
        if (function_exists('libxml_clear_errors')) {
            libxml_clear_errors();
        }
        if ($prev !== null) {
            libxml_use_internal_errors($prev);
        }
    }

    if ($xmlOk && !function_exists('xml_parser_create') && !function_exists('simplexml_load_string')) {
        $xmlValue = 'بررسی نشد (افزونه XML روی این هاست فعال نیست؛ مشکلی برای سایت نیست)';
    }

    add_row($groups, 'پیکربندی IIS (web.config)', 'اعتبار XML فایل web.config', $xmlValue, $xmlOk, $xmlHint);

    // بررسی ۳: کدگذاری فایل
    $encodingNotes = array();
    if ($hasBom) {
        $encodingNotes[] = 'دارای BOM (کاراکتر مخفی در ابتدای فایل)';
    }
    if ($nonAscii) {
        $encodingNotes[] = 'شامل کاراکترهای غیرانگلیسی (خطر خرابی با انتقال FTP در حالت ASCII)';
    }
    if (!$hasCrlf) {
        $encodingNotes[] = 'پایان خط‌ها LF است (روی IIS کار می‌کند؛ CRLF امن‌تر است)';
    }
    add_row($groups, 'پیکربندی IIS (web.config)', 'کدگذاری و سلامت انتقال فایل',
        count($encodingNotes) ? implode(' — ', $encodingNotes) : 'سالم (ASCII خالص، بدون BOM)',
        !$hasBom && !$nonAscii,
        'یک بایت خراب در web.config کل سایت را با ۵۰۰.۱۹ از کار می‌اندازد. هنگام آپلود، حالت انتقال را روی Binary بگذارید');

    // بررسی ۴: بخش‌های قفل‌شده احتمالی
    $riskySections = array(
        'handlers'          => 'نگاشت هندلر PHP (بیشترین علت خطای ۵۰۰.۱۹ روی هاست اشتراکی)',
        'modules'           => 'ماژول‌های IIS (روی هاست اشتراکی قفل است)',
        'rewrite'           => 'ماژول URL Rewrite (فقط اگر روی سرور نصب باشد)',
        'authentication'    => 'احراز هویت IIS',
        'authorization'     => 'مجوزهای IIS',
        'fastCgi'           => 'تنظیمات FastCGI (قفل در سطح سرور)',
        'httpErrors'        => 'صفحه‌های خطا (بعضی هاست‌ها قفل کرده‌اند)',
        'security'          => 'requestFiltering / محدودسازی (روی اکثر هاست‌ها آزاد است)',
        'httpProtocol'      => 'هدرهای سراسری (معمولاً آزاد)',
        'staticContent'     => 'نوع‌های MIME (معمولاً آزاد)',
        'defaultDocument'   => 'صفحه پیش‌فرض (آزاد)',
        'directoryBrowse'   => 'فهرست پوشه‌ها (آزاد)',
    );
    $activeSections = array();
    $riskyActive = array();
    $checkRaw = preg_replace('/<!--.*?-->/s', '', $webConfigRaw); // توضیح‌ها را نادیده بگیر
    foreach ($riskySections as $section => $description) {
        if (preg_match('/<' . preg_quote($section, '/') . '[\s>\/]/i', (string)$checkRaw)) {
            $activeSections[] = $section;
            if (in_array($section, array('handlers', 'modules', 'rewrite', 'authentication', 'authorization', 'fastCgi'), true)) {
                $riskyActive[] = $section . ' (' . $description . ')';
            }
        }
    }
    add_row($groups, 'پیکربندی IIS (web.config)', 'بخش‌های فعال در فایل',
        count($activeSections) ? implode(' ، ', $activeSections) : 'هیچ بخشی',
        count($riskyActive) === 0,
        'اگر یکی از این بخش‌ها را از حالت توضیح خارج کرده‌اید و سایت خطای ۵۰۰.۱۹ می‌دهد، همان بخش روی هاست شما قفل است. یک‌بار یک بخش را فعال کنید و بعد از هر تغییر صفحه را تازه کنید');
    if (count($riskyActive)) {
        add_row($groups, 'پیکربندی IIS (web.config)', 'بخش‌های پرخطر فعال‌شده', implode(' ، ', $riskyActive), false,
            'این بخش‌ها را دوباره داخل توضیح بگذارید (کامنت کنید) و نگاشت PHP را از پنل هاست انجام دهید');
    }
}

// ------------------------------------------------ بررسی فایل .htaccess
$htaccessPath = __DIR__ . '/.htaccess';
$htaccessExists = file_exists($htaccessPath);
$htaccessValue = $htaccessExists ? 'موجود' : 'وجود ندارد';
$htaccessOk = true;
$htaccessHint = 'فقط برای هاست لینوکس/آپاچی لازم است';
if ($htaccessExists) {
    $htaccessRaw = (string)@file_get_contents($htaccessPath);
    $withoutIfModule = preg_replace('/<IfModule\b.*?<\/IfModule>/s', '', $htaccessRaw);
    if (preg_match('/^\s*(Options|php_value|php_flag|php_admin_value|Header|Require|RewriteEngine)\b/m', (string)$withoutIfModule, $risky)) {
        $htaccessOk = false;
        $htaccessValue = 'دستور پرخطر خارج از IfModule: ' . trim($risky[1]);
        $htaccessHint = 'این دستور روی هاست‌های اشتراکی خطای ۵۰۰ می‌دهد؛ آن را داخل <IfModule> بگذارید یا حذف کنید';
    }
}
add_row($groups, 'فایل‌های تنظیمات', 'فایل .htaccess', $htaccessValue, $htaccessOk, $htaccessHint);

// -------------------------------------------------- آخرین خطاهای PHP
$logPath = (string)ini_get('error_log');
$logTail = '';
if ($logPath !== '' && is_file($logPath) && is_readable($logPath)) {
    $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines) && count($lines) > 0) {
        $logTail = implode("\n", array_slice($lines, -15));
    }
}

// ------------------------------------------------------- جمع‌بندی وضعیت
$allOk = true;
$failed = array();
foreach ($groups as $groupName => $groupRows) {
    foreach ($groupRows as $row) {
        if (!$row['ok']) {
            $allOk = false;
            $failed[] = $groupName . ' ← ' . $row['title'] . ': ' . $row['value'];
        }
    }
}

$totalChecks = 0;
foreach ($groups as $groupRows) {
    $totalChecks += count($groupRows);
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
  body { font-family: Tahoma, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 24px; line-height: 1.9; }
  .box { max-width: 1100px; margin: 0 auto; }
  .warn { background: #fee2e2; border: 1px solid #fca5a5; color: #7f1d1d; padding: 12px 16px; border-radius: 10px; font-weight: bold; }
  .head { background: #0f172a; color: #fff; padding: 16px 20px; border-radius: 12px; margin: 16px 0; }
  .head h1 { margin: 0 0 6px; font-size: 20px; }
  .head p { margin: 0; opacity: .8; font-size: 14px; }
  .sum { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: bold; }
  .sum.ok { background: #dcfce7; border: 1px solid #86efac; color: #14532d; }
  .sum.bad { background: #fef3c7; border: 1px solid #fcd34d; color: #78350f; }
  h2 { font-size: 17px; margin: 26px 0 8px; color: #0f172a; border-right: 4px solid #0ea5e9; padding-right: 10px; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  th, td { padding: 10px 14px; text-align: right; border-bottom: 1px solid #e2e8f0; font-size: 14px; vertical-align: top; }
  th { background: #f1f5f9; }
  tr:last-child td { border-bottom: 0; }
  .yes { color: #15803d; font-weight: bold; white-space: nowrap; }
  .no { color: #b91c1c; font-weight: bold; }
  td.hint { color: #64748b; font-size: 13px; }
  pre { background: #0f172a; color: #e2e8f0; padding: 14px; border-radius: 10px; overflow: auto; direction: ltr; text-align: left; font-size: 12px; }
  code { background: #f1f5f9; padding: 1px 5px; border-radius: 5px; direction: ltr; display: inline-block; }
  ol li, ul li { margin-bottom: 6px; }
  .failed { background: #fff1f2; border: 1px dashed #fda4af; border-radius: 10px; padding: 10px 16px; }
</style>
</head>
<body>
<div class="box">

  <div class="warn">⚠️ این فایل ابزار عیب‌یابی است و اطلاعات سرور را نشان می‌دهد؛ پس از رفع مشکل آن را از هاست حذف کنید.</div>

  <div class="head">
    <h1>عیب‌یاب نصب — پارس سازه و آفیس (نسخه ۲)</h1>
    <p><?= (int)$totalChecks ?> بررسی انجام شد • سیستم: <?= $IS_WINDOWS ? 'ویندوز / IIS' : 'لینوکس / آپاچی' ?> • SAPI: <?= htmlspecialchars(PHP_SAPI, ENT_QUOTES, 'UTF-8') ?></p>
  </div>

  <?php if ($allOk): ?>
    <div class="sum ok">✔ همه بررسی‌ها موفق بود. اگر هنوز خطای ۵۰۰ می‌بینید، ابتدا متن خطا را در Event Viewer ویندوز یا error_log ببینید و ردیف‌های زیر را با دقت بخوانید.</div>
  <?php else: ?>
    <div class="sum bad">✘ <?= count($failed) ?> مورد نیازمند اصلاح است؛ ردیف‌های قرمز زیر علت خطاست.</div>
    <div class="failed">
      <b>فهرست موارد ناموفق:</b>
      <ul>
        <?php foreach ($failed as $item): ?>
          <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php foreach ($groups as $groupName => $groupRows): ?>
    <h2><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></h2>
    <table>
      <tr>
        <th style="width:26%">بررسی</th>
        <th style="width:30%">وضعیت</th>
        <th>توضیح / راه‌حل</th>
      </tr>
      <?php foreach ($groupRows as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="<?= $row['ok'] ? 'yes' : 'no' ?>">
            <?= $row['ok'] ? '✔ ' : '✘ ' ?><?= htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8') ?>
          </td>
          <td class="hint"><?= htmlspecialchars($row['hint'], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endforeach; ?>

  <h2>راهنمای سریع خطاهای رایج روی IIS</h2>
  <table>
    <tr><th style="width:26%">نشانه</th><th>علت</th><th>راه‌حل</th></tr>
    <tr>
      <td><b>خطای ۵۰۰.۱۹</b> — «This configuration section cannot be used at this path»</td>
      <td>یکی از بخش‌های <code>web.config</code> روی این هاست قفل است (معمولاً <code>handlers</code> یا <code>modules</code>).</td>
      <td>نام فایل را موقتاً به <code>web.config.off</code> تغییر دهید؛ اگر سایت باز شد، بخش‌های کامنت‌شده را یکی‌یکی فعال کنید تا مقصر پیدا شود.</td>
    </tr>
    <tr>
      <td><b>خطای ۵۰۰.۰</b> یا «The FastCGI process exited unexpectedly»</td>
      <td>نسخه PHP روی دامنه انتخاب نشده، افزونه لازم نیست، یا <code>php.ini</code> مسیر اشتباه دارد.</td>
      <td>در پنل هاست (Plesk: <code>PHP Settings</code>) نسخه ۸.۰+ را انتخاب و افزونه‌های <code>pdo_sqlite</code> و <code>mbstring</code> را فعال کنید. جزئیات واقعی خطا در Event Viewer ویندوز ← Windows Logs ← Application ثبت می‌شود.</td>
    </tr>
    <tr>
      <td><b>صفحه سفید</b> بدون هیچ پیام</td>
      <td>خطای مهلک PHP و خاموش بودن <code>display_errors</code>.</td>
      <td>در این نسخه، برنامه خودش پیام فارسی نشان می‌دهد؛ اگر باز هم سفید بود، <code>display_errors</code> را در پنل روی <code>On</code> بگذارید و همین صفحه را دوباره باز کنید.</td>
    </tr>
    <tr>
      <td>مرورگر فایل PHP را <b>دانلود</b> می‌کند یا متن کد را نشان می‌دهد</td>
      <td>هندلر PHP روی IIS ثبت نشده است (PHP در سطح دامنه فعال نیست).</td>
      <td>در Plesk: <code>Websites &amp; Domains</code> ← <code>PHP Settings</code> ← انتخاب نسخه PHP. هرگز هندلر را دستی داخل <code>web.config</code> تعریف نکنید.</td>
    </tr>
    <tr>
      <td><b>خطای ۴۰۳.۱۴</b> یا فهرست فایل‌های پوشه</td>
      <td>سند پیش‌فرض <code>index.php</code> ثبت نشده یا <code>web.config</code> وجود ندارد.</td>
      <td>فایل <code>web.config</code> را کنار <code>index.php</code> بگذارید، یا آدرس <code>https://دامنه/index.php</code> را باز کنید.</td>
    </tr>
    <tr>
      <td>ورود انجام می‌شود ولی <b>«نمی‌ماند»</b> / پیام‌ها ناپدید می‌شوند</td>
      <td>مسیر ذخیره نشست‌ها (session.save_path) برای کاربر PHP قابل نوشتن نیست؛ روی IIS با <code>fastcgi.impersonate=1</code> شایع است.</td>
      <td>این نسخه خودکار پوشه <code>tmp/sessions</code> را می‌سازد. اگر ردیف «نشست» در همین صفحه قرمز است، پوشه سایت را برای <code>IIS_IUSRS</code> قابل نوشتن (Modify) کنید.</td>
    </tr>
    <tr>
      <td>«خطا در اتصال به پایگاه داده» یا نبود فایل <code>parssaze.db</code></td>
      <td>افزونه <code>pdo_sqlite</code> غیرفعال است یا پوشه پروژه قابل نوشتن نیست.</td>
      <td>افزونه را فعال کنید و روی پوشه سایت (و خود فایل <code>db</code>) دسترسی Modify بدهید.</td>
    </tr>
    <tr>
      <td>به‌هم‌ریختگی متن فارسی/سؤال‌جای‌علامت (؟؟؟)</td>
      <td>انتقال فایل‌ها با FTP در حالت ASCII یا کدگذاری نادرست.</td>
      <td>فایل‌ها را دوباره در حالت <b>Binary</b> آپلود کنید و مطمئن شوید <code>default_charset=UTF-8</code> است.</td>
    </tr>
  </table>

  <h2>گام‌های پیشنهادی برای هاست ویندوز (IIS / Plesk)</h2>
  <ol>
    <li>همه فایل‌ها را (با پوشه‌های <code>inc</code>، <code>views</code>، <code>tmp</code> و فایل <code>web.config</code>) در ریشه دامنه آپلود کنید؛ آپلود در حالت <b>Binary</b>.</li>
    <li>در پنل، نسخه PHP را <b>۸.۰ یا بالاتر</b> انتخاب و افزونه‌های <code>pdo_sqlite</code> و <code>mbstring</code> را فعال کنید.</li>
    <li>روی پوشه سایت و فایل <code>parssaze.db</code> (اگر وجود دارد) به کاربر <code>IIS_IUSRS</code> دسترسی <b>Modify</b> بدهید، یا از پنل هاست پوشه را writable کنید.</li>
    <li>ابتدا <code>check.php</code> را باز کنید؛ اگر همه ردیف‌ها سبز شد، <code>index.php</code> را باز کنید.</li>
    <li>اگر خطای ۵۰۰ بود، نام <code>web.config</code> را موقتاً <code>web.config.off</code> کنید و صفحه را دوباره باز کنید.</li>
    <li>برای دیدن علت واقعی خطا، در پنل هاست گزینه «نمایش خطاهای PHP» را روشن کنید یا Event Viewer ویندوز ← Windows Logs ← Application را ببینید.</li>
    <li>پس از موفقیت: <code>check.php</code> را حذف کنید و <code>display_errors</code> را <code>Off</code> کنید.</li>
  </ol>

  <h2>آدرس‌های آزمایشی</h2>
  <ul>
    <li><code>index.php</code> — صفحه اصلی فروشگاه</li>
    <li><code>index.php?page=login</code> — صفحه ورود (حساب مدیر: <code>09120000000</code> / <code>admin1234</code>)</li>
    <li><code>index.php?page=admin</code> — داشبورد مدیریت (پس از ورود)</li>
    <li><code>api.php?do=cart_count</code> — آزمون سریع خروجی JSON</li>
  </ul>

  <h2>محیط اجرای PHP (خلاصه فنی)</h2>
  <pre>PHP <?= htmlspecialchars(PHP_VERSION . ' (' . PHP_SAPI . ')', ENT_QUOTES, 'UTF-8') ?>
php.ini: <?= htmlspecialchars((string)(php_ini_loaded_file() ?: 'not found'), ENT_QUOTES, 'UTF-8') ?>
document_root: <?= htmlspecialchars((string)(isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : ''), ENT_QUOTES, 'UTF-8') ?>
app_root: <?= htmlspecialchars(__DIR__, ENT_QUOTES, 'UTF-8') ?>
session.save_path: <?= htmlspecialchars($savePath === '' ? '(empty)' : $savePath, ENT_QUOTES, 'UTF-8') ?>
tmp/sessions writable: <?= $sessionDirWritable ? 'yes' : 'no' ?>
project dir writable: <?= $rootWritable ? 'yes' : 'no' ?>
error_log: <?= htmlspecialchars($logPath === '' ? '(not set)' : $logPath, ENT_QUOTES, 'UTF-8') ?></pre>

  <?php if ($logTail !== ''): ?>
    <h2>آخرین خطاهای ثبت‌شده در error_log</h2>
    <pre><?= htmlspecialchars($logTail, ENT_QUOTES, 'UTF-8') ?></pre>
  <?php endif; ?>

</div>
</body>
</html>
