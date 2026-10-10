<?php
/**
 * توابع کمکی، منطق تجاری و مدیریت وضعیت‌ها
 */

// ------------------------------------------------------------- خروجی امن
function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function post($key, $default = '')
{
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function get($key, $default = '')
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

/** تبدیل ارقام فارسی/عربی به انگلیسی */
function en_digits($str)
{
    $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($fa, $en, (string)$str);
}

/** نمایش ارقام فارسی + جداکننده هزارگان فارسی */
function fa_num($number, $decimals = 0)
{
    $out = number_format((float)$number, $decimals, '.', '٬');
    return str_replace(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], $out);
}

/** متن (مثل شماره نسخه ۲.۱.۰) را بدون تبدیل به عدد، با ارقام فارسی برمی‌گرداند */
function fa_text($text)
{
    return str_replace(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], (string)$text);
}

/** مبلغ به تومان با ارقام فارسی */
function money($amount)
{
    return fa_num((int)$amount) . ' تومان';
}

function money_short($amount)
{
    $amount = (int)$amount;
    if ($amount >= 1000000000) {
        return fa_num(round($amount / 1000000000, 2), 2) . ' میلیارد';
    }
    if ($amount >= 1000000) {
        return fa_num(round($amount / 1000000, 1), 1) . ' میلیون';
    }
    if ($amount >= 1000) {
        return fa_num(round($amount / 1000), 0) . ' هزار';
    }
    return fa_num($amount);
}

function valid_phone($phone)
{
    return (bool)preg_match('/^09[0-9]{9}$/', en_digits($phone));
}

// ------------------------------------------------------- تاریخ شمسی
function gregorian_to_jalali($gy, $gm, $gd)
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100))
        + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function jalali_to_gregorian($jy, $jm, $jd)
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (((int)($jy / 33)) * 8) + ((int)((($jy % 33) + 3) / 4)) + $jd
        + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * ((int)($days / 146097));
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * ((int)(--$days / 36524));
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0, 31, ($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 1; $gm <= 12 && $gd > $sal_a[$gm]; $gm++) {
        $gd -= $sal_a[$gm];
    }
    return [$gy, $gm, $gd];
}

/**
 * تاریخ شمسی خوانا
 * @param string|null $datetime تاریخ میلادی (Y-m-d H:i:s) یا null برای «الان»
 * @param bool $withTime نمایش ساعت
 */
function jdate($datetime = null, $withTime = false)
{
    if (!$datetime || $datetime === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    if (!$ts) {
        return e($datetime);
    }
    list($jy, $jm, $jd) = gregorian_to_jalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    $out = fa_num($jd) . ' ' . $months[$jm - 1] . ' ' . fa_num($jy);
    if ($withTime) {
        $out .= ' - ساعت ' . fa_num(date('H:i', $ts));
    }
    return $out;
}

function jdate_short($datetime)
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    list($jy, $jm, $jd) = gregorian_to_jalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    return fa_num($jy, 0) . '/' . fa_num(str_pad((string)$jm, 2, '0', STR_PAD_LEFT), 0) . '/' . fa_num(str_pad((string)$jd, 2, '0', STR_PAD_LEFT), 0);
}

/** فاصله زمانی نسبی: «۳ روز پیش» */
function time_ago($datetime)
{
    if (!$datetime) {
        return '—';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'همین حالا';
    }
    if ($diff < 3600) {
        return fa_num((int)($diff / 60)) . ' دقیقه پیش';
    }
    if ($diff < 86400) {
        return fa_num((int)($diff / 3600)) . ' ساعت پیش';
    }
    if ($diff < 2592000) {
        return fa_num((int)($diff / 86400)) . ' روز پیش';
    }
    if ($diff < 31536000) {
        return fa_num((int)($diff / 2592000)) . ' ماه پیش';
    }
    return fa_num((int)($diff / 31536000)) . ' سال پیش';
}

// ------------------------------------------------------------- تنظیمات
function settings($key = null, $default = '')
{
    static $cache = null;
    global $db;
    if ($cache === null) {
        $cache = [];
        foreach ($db->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    if ($key === null) {
        return $cache;
    }
    return isset($cache[$key]) && $cache[$key] !== '' ? $cache[$key] : $default;
}

function set_setting($key, $value)
{
    global $db;
    $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
    $stmt->execute([$key, (string)$value]);
}

function vat_rate()
{
    return ((float)settings('vat_rate', 10)) / 100;
}

// ------------------------------------------------------------------ CSRF
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

function csrf_verify($token = null)
{
    $token = $token === null ? ($_POST['csrf_token'] ?? '') : $token;
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_guard()
{
    if (!csrf_verify()) {
        http_response_code(403);
        die('خطای امنیتی: توکن نامعتبر است. لطفاً صفحه را دوباره بارگذاری کنید.');
    }
}

// ------------------------------------------------------------- پیام‌ها
function flash($message, $type = 'info')
{
    $_SESSION['flashes'][] = ['message' => $message, 'type' => $type];
}

function take_flashes()
{
    $list = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $list;
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/** فقط مسیرهای داخلی index.php مجازند (جلوگیری از ریدایرکت به سایت دیگر) */
function safe_local_url($url, $default = 'index.php?page=home')
{
    $url = (string)$url;
    if (preg_match('~^index\.php(\?[A-Za-z0-9_=&%.+\-]*)?$~', $url)) {
        return $url;
    }
    return $default;
}

// ------------------------------------------- فرم‌ها: ورودی قبلی و پیام خطای فیلد
/** ورودی‌ها و خطاهای یک فرم را برای نمایش پس از ریدایرکت نگه می‌دارد */
function remember_form($form, array $input, array $errors)
{
    $_SESSION['form_state'][$form] = ['old' => $input, 'errors' => $errors];
}

/** وضعیت فرم را می‌خواند و پاک می‌کند (یک‌بار مصرف) */
function take_form_state($form)
{
    $state = $_SESSION['form_state'][$form] ?? ['old' => [], 'errors' => []];
    unset($_SESSION['form_state'][$form]);
    return $state;
}

/** متن خطای یک فیلد فرم (در صورت وجود) */
function field_error(array $errors, $key)
{
    if (empty($errors[$key])) {
        return '';
    }
    return '<span class="field-error" id="fe-' . e($key) . '">' . e($errors[$key]) . '</span>';
}

/**
 * ویژگی‌های دسترس‌پذیری یک ورودی: در صورت خطا کلاس و aria-invalid، و در هر حالت
 * aria-describedby شامل شناسه راهنما ($hintId) و پیام خطا (در صورت وجود)
 */
function field_invalid_attr(array $errors, $key, $hintId = '')
{
    $ids = trim($hintId . ' ' . (empty($errors[$key]) ? '' : 'fe-' . $key));
    $describedBy = $ids !== '' ? ' aria-describedby="' . e($ids) . '"' : '';
    if (empty($errors[$key])) {
        return $describedBy;
    }
    return ' class="is-invalid" aria-invalid="true"' . $describedBy;
}

function current_url($withQuery = true)
{
    $path = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    return $withQuery && !empty($_SERVER['QUERY_STRING']) ? $path . '?' . $_SERVER['QUERY_STRING'] : $path;
}

// ------------------------------------------------------- نشست و نقش‌ها
function current_user()
{
    static $user = null;
    global $db;
    if ($user !== null) {
        return $user ?: null;
    }
    if (empty($_SESSION['uid'])) {
        $user = false;
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['uid']]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] !== 'active') {
        unset($_SESSION['uid']);
        $user = false;
        return null;
    }
    $user = $row;
    return $user;
}

function user_id()
{
    $u = current_user();
    return $u ? (int)$u['id'] : 0;
}

function is_logged_in()
{
    return current_user() !== null;
}

function is_admin()
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function is_buyer()
{
    $u = current_user();
    return $u && $u['role'] === 'buyer';
}

function require_login()
{
    if (!is_logged_in()) {
        flash('برای دسترسی به این بخش باید وارد حساب کاربری شوید.', 'error');
        redirect('index.php?page=login&next=' . urlencode(get('page', 'home')));
    }
}

function require_admin()
{
    require_login();
    if (!is_admin()) {
        flash('دسترسی به پنل مدیریت فقط برای مدیران سیستم مجاز است.', 'error');
        redirect('index.php?page=panel');
    }
}

function require_buyer()
{
    require_login();
    if (!is_buyer()) {
        redirect('index.php?page=admin');
    }
}

function initials($name)
{
    $name = trim((string)$name);
    return $name === '' ? '؟' : mb_substr($name, 0, 1, 'UTF-8');
}

// ------------------------------------------------------------ وضعیت‌ها
function order_statuses()
{
    return [
        'pending'   => 'در انتظار تأیید',
        'approved'  => 'تأیید شده',
        'preparing' => 'در حال آماده‌سازی',
        'shipped'   => 'ارسال شده',
        'delivered' => 'تحویل شده',
        'canceled'  => 'لغو شده',
    ];
}

function order_status_label($status)
{
    $all = order_statuses();
    return $all[$status] ?? $status;
}

/** برچسب فارسی رویدادهای سفارش برای نمایش به خریدار (متن توضیح خود رویداد فارسی است) */
function log_action_label($action)
{
    $map = [
        'order_create'  => 'ثبت سفارش',
        'order_update'  => 'تغییر وضعیت سفارش',
        'order_cancel'  => 'لغو سفارش',
        'invoice_issue' => 'صدور صورتحساب',
        'order_delete'  => 'حذف سفارش',
    ];
    return $map[$action] ?? 'به‌روزرسانی';
}

function order_status_class($status)
{
    $map = [
        'pending' => 'warn', 'approved' => 'info', 'preparing' => 'info',
        'shipped' => 'primary', 'delivered' => 'success', 'canceled' => 'danger',
    ];
    return $map[$status] ?? 'muted';
}

function order_status_icon($status)
{
    $map = ['pending' => '⏳', 'approved' => '✅', 'preparing' => '📦', 'shipped' => '🚚', 'delivered' => '🏁', 'canceled' => '⛔'];
    return $map[$status] ?? '•';
}

function payment_statuses()
{
    return ['unpaid' => 'پرداخت نشده', 'paid' => 'پرداخت شده', 'partial' => 'پرداخت جزئی', 'refunded' => 'مرجوع/بازگشت وجه'];
}

function payment_status_label($s)
{
    $all = payment_statuses();
    return $all[$s] ?? $s;
}

function rfq_statuses()
{
    return [
        'new'       => 'جدید',
        'reviewing' => 'در حال بررسی',
        'quoted'    => 'قیمت‌گذاری شده',
        'accepted'  => 'تبدیل به سفارش',
        'rejected'  => 'رد شده',
    ];
}

function rfq_status_label($s)
{
    $all = rfq_statuses();
    return $all[$s] ?? $s;
}

function rfq_status_class($s)
{
    $map = ['new' => 'info', 'reviewing' => 'warn', 'quoted' => 'primary', 'accepted' => 'success', 'rejected' => 'danger'];
    return $map[$s] ?? 'muted';
}

/** ورودی‌های تکرارشوندهٔ اقلام را برای نمایش امن در فرم آماده می‌کند. */
function rfq_form_item_rows($rawItems)
{
    if (!is_array($rawItems)) {
        return [];
    }
    $rows = [];
    foreach (array_slice($rawItems, 0, RFQ_MAX_ITEMS, true) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $clean = [];
        foreach (['description', 'quantity', 'item_code', 'category'] as $key) {
            $value = $row[$key] ?? '';
            $clean[$key] = is_scalar($value) ? trim((string)$value) : '';
        }
        $rows[] = $clean;
    }
    return array_values($rows);
}

/** اقلام ارسالی را اعتبارسنجی و به ساختار قابل ذخیره تبدیل می‌کند. */
function rfq_validate_items($rawItems, array $activeCategories, &$error = null)
{
    $error = null;
    if (!is_array($rawItems)) {
        $error = 'حداقل یک قلم کالا را وارد کنید.';
        return [];
    }
    if (count($rawItems) > RFQ_MAX_ITEMS) {
        $error = 'حداکثر ' . fa_num(RFQ_MAX_ITEMS) . ' قلم در هر استعلام پذیرفته می‌شود.';
        return [];
    }

    $allowedCategories = [];
    foreach ($activeCategories as $category) {
        if (isset($category['slug'])) {
            $allowedCategories[(string)$category['slug']] = true;
        }
    }

    $items = [];
    $messages = [];
    foreach (rfq_form_item_rows($rawItems) as $row) {
        $hasValue = $row['description'] !== '' || $row['quantity'] !== ''
            || $row['item_code'] !== '' || $row['category'] !== '';
        if (!$hasValue) {
            continue;
        }

        $description = $row['description'];
        $quantity = en_digits($row['quantity']);
        $itemCode = $row['item_code'];
        $category = $row['category'];
        $valid = true;

        if (mb_strlen($description, 'UTF-8') < 2 || mb_strlen($description, 'UTF-8') > 500) {
            $messages[] = 'شرح هر قلم را کامل وارد کنید (۲ تا ۵۰۰ نویسه).';
            $valid = false;
        }
        if (!preg_match('/^[0-9]+(?:\.[0-9]{1,3})?$/', $quantity) || (float)$quantity <= 0 || (float)$quantity > 1000000000) {
            $messages[] = 'مقدار هر قلم باید عددی بزرگ‌تر از صفر باشد.';
            $valid = false;
        }
        if (mb_strlen($itemCode, 'UTF-8') > 120) {
            $messages[] = 'شناسه کالا نباید بیش از ۱۲۰ نویسه باشد.';
            $valid = false;
        }
        if ($category === '' || !isset($allowedCategories[$category])) {
            $messages[] = 'دسته‌بندی هر قلم را از فهرست انتخاب کنید.';
            $valid = false;
        }

        if ($valid) {
            $items[] = [
                'description' => $description,
                'quantity' => $quantity,
                'item_code' => $itemCode,
                'category' => $category,
            ];
        }
    }

    if (!$items && !$messages) {
        $messages[] = 'حداقل یک قلم کالا را کامل وارد کنید.';
    }
    if ($messages) {
        $error = implode(' ', array_values(array_unique($messages)));
    }
    return $items;
}

/** ساختار JSON اقلام را بدون تغییر برمی‌گرداند؛ دادهٔ قدیمی خالی می‌ماند. */
function rfq_items_decode($json)
{
    if (!is_string($json) || $json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    $items = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $items[] = [
            'description' => isset($item['description']) && is_scalar($item['description']) ? (string)$item['description'] : '',
            'quantity' => isset($item['quantity']) && is_scalar($item['quantity']) ? (string)$item['quantity'] : '',
            'item_code' => isset($item['item_code']) && is_scalar($item['item_code']) ? (string)$item['item_code'] : '',
            'category' => isset($item['category']) && is_scalar($item['category']) ? (string)$item['category'] : '',
        ];
    }
    return $items;
}

/** متن سازگار با ستون قدیمی description و با گزارش CSV می‌سازد. */
function rfq_items_summary(array $items)
{
    $lines = [];
    foreach ($items as $index => $item) {
        $parts = [fa_num($index + 1) . '- ' . (string)($item['description'] ?? '')];
        if (isset($item['quantity']) && $item['quantity'] !== '') {
            $parts[] = 'مقدار: ' . fa_text($item['quantity']);
        }
        if (!empty($item['item_code'])) {
            $parts[] = 'شناسه کالا: ' . (string)$item['item_code'];
        }
        if (!empty($item['category'])) {
            $parts[] = 'دسته‌بندی: ' . category_title((string)$item['category']);
        }
        $lines[] = implode(' | ', $parts);
    }
    return implode("\n", $lines);
}

function order_timeline_steps()
{
    return ['pending', 'approved', 'preparing', 'shipped', 'delivered'];
}

function order_progress($status)
{
    $steps = order_timeline_steps();
    if ($status === 'canceled') {
        return 0;
    }
    $idx = array_search($status, $steps, true);
    return $idx === false ? 0 : (int)((($idx + 1) / count($steps)) * 100);
}

// --------------------------------------------------------------- کاتالوگ
function categories($onlyActive = true)
{
    global $db;
    $sql = 'SELECT * FROM categories' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id';
    return $db->query($sql)->fetchAll();
}

function category_title($slug, $fallback = 'سایر')
{
    static $map = null;
    global $db;
    if ($map === null) {
        $map = [];
        foreach ($db->query('SELECT slug, title FROM categories') as $r) {
            $map[$r['slug']] = $r['title'];
        }
    }
    return $map[$slug] ?? $fallback;
}

/** نام مرورگر را بدون مسیر و نویسه‌های کنترلی برای نمایش/دانلود نگه می‌دارد. */
function rfq_attachment_safe_name($name)
{
    $name = str_replace('\\', '/', (string)$name);
    $name = basename($name);
    $name = preg_replace('/[\\x00-\\x1F\\x7F]/', '', $name);
    $name = trim((string)$name);
    return mb_substr($name, 0, 180, 'UTF-8');
}

/** MIME واقعی را با fileinfo می‌خواند؛ در نبود افزونه، نوع فایل با امضای باینری هم بررسی می‌شود. */
function rfq_attachment_detect_mime($path)
{
    if (!function_exists('finfo_open')) {
        return '';
    }
    $info = @finfo_open(FILEINFO_MIME_TYPE);
    if (!$info) {
        return '';
    }
    $mime = @finfo_file($info, $path);
    // The local finfo handle is released automatically when this function returns.
    return strtolower(trim((string)$mime));
}

/** اعتبارسنجی محتوا بر اساس امضای فایل، نه نام یا MIME ارسالی مرورگر. */
function rfq_attachment_content_is_valid($path, $extension, $mime)
{
    $allowedMimes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'pdf' => ['application/pdf', 'application/x-pdf'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/cdfv2', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ];
    if (!isset($allowedMimes[$extension]) || ($mime !== '' && !in_array($mime, $allowedMimes[$extension], true))) {
        return false;
    }

    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return false;
    }
    $header = (string)fread($handle, 1024);
    fclose($handle);

    if ($extension === 'jpg' || $extension === 'jpeg') {
        return substr($header, 0, 3) === "\xFF\xD8\xFF";
    }
    if ($extension === 'png') {
        return substr($header, 0, 8) === "\x89PNG\r\n\x1A\n";
    }
    if ($extension === 'pdf') {
        return strpos($header, '%PDF-') !== false;
    }
    if ($extension === 'xls') {
        return substr($header, 0, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    }
    if ($extension === 'xlsx') {
        if (substr($header, 0, 4) !== "PK\x03\x04") {
            return false;
        }
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                return false;
            }
            $valid = $zip->locateName('[Content_Types].xml') !== false
                && $zip->locateName('xl/workbook.xml') !== false;
            $zip->close();
            return $valid;
        }
        $archive = @file_get_contents($path);
        return is_string($archive) && strpos($archive, '[Content_Types].xml') !== false
            && strpos($archive, 'xl/workbook.xml') !== false;
    }
    return false;
}

/** فایل‌های انتخاب‌شده را قبل از ذخیره از نظر تعداد، حجم، پسوند و محتوای واقعی می‌سنجد. */
function rfq_attachment_uploads_inspect($upload)
{
    $none = ['files' => [], 'error' => null];
    if (!is_array($upload) || !isset($upload['error'])) {
        return $none;
    }
    $names = is_array($upload['name'] ?? null) ? $upload['name'] : [0 => ($upload['name'] ?? '')];
    $errors = is_array($upload['error']) ? $upload['error'] : [0 => $upload['error']];
    $indexes = array_values(array_unique(array_merge(array_keys($names), array_keys($errors))));
    $files = [];
    $totalBytes = 0;
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'xls', 'xlsx'];

    foreach ($indexes as $index) {
        $code = isset($errors[$index]) && is_scalar($errors[$index]) ? (int)$errors[$index] : UPLOAD_ERR_NO_FILE;
        if ($code === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (count($files) >= RFQ_MAX_ATTACHMENTS) {
            return ['files' => [], 'error' => 'حداکثر ' . fa_num(RFQ_MAX_ATTACHMENTS) . ' فایل برای هر استعلام پذیرفته می‌شود.'];
        }
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return ['files' => [], 'error' => 'حجم هر پیوست نباید بیش از ' . fa_num(RFQ_ATTACHMENT_MAX_BYTES / 1048576) . ' مگابایت باشد.'];
        }
        if ($code !== UPLOAD_ERR_OK) {
            return ['files' => [], 'error' => 'دریافت یکی از پیوست‌ها ناموفق بود؛ لطفاً فایل را دوباره انتخاب کنید.'];
        }

        $tmp = is_array($upload['tmp_name'] ?? null) && isset($upload['tmp_name'][$index])
            ? (string)$upload['tmp_name'][$index]
            : (string)($upload['tmp_name'] ?? '');
        $size = is_array($upload['size'] ?? null) && isset($upload['size'][$index])
            ? (int)$upload['size'][$index]
            : (int)($upload['size'] ?? 0);
        $originalName = isset($names[$index]) && is_scalar($names[$index]) ? rfq_attachment_safe_name($names[$index]) : '';

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['files' => [], 'error' => 'یکی از پیوست‌ها معتبر نیست؛ لطفاً فایل را دوباره انتخاب کنید.'];
        }
        if ($size <= 0 || $size > RFQ_ATTACHMENT_MAX_BYTES) {
            return ['files' => [], 'error' => 'حجم هر پیوست باید بیشتر از صفر و حداکثر ' . fa_num(RFQ_ATTACHMENT_MAX_BYTES / 1048576) . ' مگابایت باشد.'];
        }
        $totalBytes += $size;
        if ($totalBytes > RFQ_ATTACHMENT_MAX_TOTAL_BYTES) {
            return ['files' => [], 'error' => 'حجم مجموع پیوست‌ها نباید بیش از ' . fa_num(RFQ_ATTACHMENT_MAX_TOTAL_BYTES / 1048576) . ' مگابایت باشد.'];
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return ['files' => [], 'error' => 'فقط فایل‌های JPG، JPEG، PNG، Excel و PDF پذیرفته می‌شوند.'];
        }
        $mime = rfq_attachment_detect_mime($tmp);
        if (!rfq_attachment_content_is_valid($tmp, $extension, $mime)) {
            return ['files' => [], 'error' => 'محتوای یکی از فایل‌ها با قالب اعلام‌شده سازگار نیست.'];
        }
        if ($originalName === '') {
            $originalName = 'attachment.' . $extension;
        }
        $files[] = [
            'tmp' => $tmp,
            'name' => $originalName,
            'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
            'mime' => $mime !== '' ? $mime : 'application/octet-stream',
            'size' => $size,
        ];
    }

    return ['files' => $files, 'error' => null];
}

/** فایل‌های معتبر را با نام تصادفی در پوشهٔ خصوصی APP_TMP ذخیره می‌کند. */
function rfq_attachment_uploads_commit(array $inspected)
{
    if (!$inspected) {
        return ['files' => [], 'error' => null];
    }
    $dir = APP_TMP . DIRECTORY_SEPARATOR . 'rfq-attachments';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return ['files' => [], 'error' => 'پوشهٔ خصوصی پیوست‌ها ساخته نشد؛ دسترسی نوشتن به پوشه tmp را بررسی کنید.'];
    }
    @chmod($dir, 0700);

    $saved = [];
    foreach ($inspected as $file) {
        $token = bin2hex(random_bytes(16));
        $storedName = $token . '.' . $file['extension'];
        $target = $dir . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($file['tmp'], $target)) {
            rfq_attachments_delete($saved);
            return ['files' => [], 'error' => 'ذخیرهٔ پیوست ناموفق بود؛ دسترسی نوشتن به پوشهٔ خصوصی را بررسی کنید.'];
        }
        @chmod($target, 0600);
        $saved[] = [
            'token' => $token,
            'name' => $file['name'],
            'stored_name' => $storedName,
            'extension' => $file['extension'],
            'mime' => $file['mime'],
            'size' => (int)$file['size'],
        ];
    }
    return ['files' => $saved, 'error' => null];
}

/** JSON پیوست‌ها را با الگوی نام و شناسهٔ مورد انتظار فیلتر می‌کند. */
function rfq_attachments_decode($json)
{
    if (!is_string($json) || $json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    $files = [];
    foreach ($decoded as $file) {
        if (!is_array($file)) {
            continue;
        }
        $token = isset($file['token']) && is_scalar($file['token']) ? (string)$file['token'] : '';
        $storedName = isset($file['stored_name']) && is_scalar($file['stored_name']) ? (string)$file['stored_name'] : '';
        if (!preg_match('/^[a-f0-9]{32}$/', $token)
            || !preg_match('/^([a-f0-9]{32})\\.(jpg|png|pdf|xls|xlsx)$/', $storedName, $matches)
            || !hash_equals($token, $matches[1])) {
            continue;
        }
        $files[] = [
            'token' => $token,
            'name' => isset($file['name']) && is_scalar($file['name']) ? rfq_attachment_safe_name($file['name']) : 'پیوست.' . $matches[2],
            'stored_name' => $storedName,
            'extension' => $matches[2],
            'mime' => isset($file['mime']) && is_scalar($file['mime']) ? (string)$file['mime'] : 'application/octet-stream',
            'size' => isset($file['size']) && is_numeric($file['size']) ? (int)$file['size'] : 0,
        ];
    }
    return $files;
}

/** مسیر دیسک فقط از نام تصادفی مجاز ساخته می‌شود و بیرون از پوشهٔ خصوصی پذیرفته نمی‌شود. */
function rfq_attachment_resolve_path(array $attachment)
{
    $token = (string)($attachment['token'] ?? '');
    $storedName = (string)($attachment['stored_name'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $token)
        || !preg_match('/^' . preg_quote($token, '/') . '\\.(?:jpg|png|pdf|xls|xlsx)$/', $storedName)) {
        return null;
    }
    $dir = realpath(APP_TMP . DIRECTORY_SEPARATOR . 'rfq-attachments');
    if ($dir === false) {
        return null;
    }
    $candidate = $dir . DIRECTORY_SEPARATOR . $storedName;
    if (is_link($candidate)) {
        return null;
    }
    $real = realpath($candidate);
    if ($real === false) {
        return null;
    }
    $prefix = rtrim($dir, '/' . '\\') . DIRECTORY_SEPARATOR;
    $inside = DIRECTORY_SEPARATOR === '\\' ? stripos($real, $prefix) === 0 : strpos($real, $prefix) === 0;
    return $inside ? $real : null;
}

/** فایل‌های پیوست یک استعلام یا فهرست پیوست‌های تازه را از دیسک پاک می‌کند. */
function rfq_attachments_delete(array $files)
{
    foreach ($files as $file) {
        if (!is_array($file)) {
            continue;
        }
        $path = rfq_attachment_resolve_path($file);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }
}

function rfq_attachments_delete_for_rfq($json)
{
    rfq_attachments_delete(rfq_attachments_decode($json));
}

function product_url($id)
{
    return 'index.php?page=product&id=' . (int)$id;
}

/** لینک استعلام قیمت با یک قلم ساختاریافته از پیش در فرم استعلام */
function rfq_prefill_url(array $p, $qty = 1)
{
    $description = trim((string)($p['name'] ?? ''));
    if (!empty($p['brand'])) {
        $description .= ' (' . $p['brand'] . ')';
    }
    if (!empty($p['tax_id'])) {
        $description .= ' | شناسه مالیاتی: ' . $p['tax_id'];
    }
    if (!empty($p['unit'])) {
        $description .= ' | واحد: ' . $p['unit'];
    }
    $itemCode = trim((string)($p['sku'] ?? ''));
    if ($itemCode === '') {
        $itemCode = trim((string)($p['tax_id'] ?? ''));
    }
    $query = [
        'page' => 'rfq',
        'items' => [[
            'description' => $description,
            'quantity' => en_digits((string)$qty),
            'item_code' => $itemCode,
            'category' => (string)($p['category'] ?? ''),
        ]],
    ];
    return 'index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

// ------------------------------------------------------------------ سبد
function cart_count()
{
    if (empty($_SESSION['cart'])) {
        return 0;
    }
    return (int)array_sum($_SESSION['cart']);
}

function cart_items(PDO $db)
{
    if (empty($_SESSION['cart'])) {
        return [];
    }
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($in)");
    $stmt->execute($ids);
    $out = [];
    foreach ($stmt->fetchAll() as $p) {
        $qty = max(1, (int)($_SESSION['cart'][$p['id']] ?? 1));
        $p['qty'] = $qty;
        $p['line_total'] = $qty * (int)$p['price'];
        $out[] = $p;
    }
    return $out;
}

/**
 * محاسبه جمع‌های سبد خرید
 * کد تخفیف فقط وقتی اعمال می‌شود که معتبر باشد. در غیر این صورت coupon = null
 * و couponError دلیل نامعتبربودن را دارد (تا کد نامعتبر مصرف یا «فعال» نشان داده نشود).
 * @return array{subtotal:int,discount:int,tax:int,shipping:int,total:int,items:array,coupon:?array,couponError:?string,freeShippingMin:int}
 */
function cart_totals(PDO $db, $couponCode = null)
{
    $items = cart_items($db);
    $subtotal = 0;
    foreach ($items as $it) {
        $subtotal += $it['line_total'];
    }

    $coupon = null;
    $couponError = null;
    $discount = 0;
    if ($couponCode) {
        $found = coupon_find($db, $couponCode);
        $couponError = coupon_error($found, $subtotal);
        if ($couponError === null) {
            $coupon = $found;
            $discount = coupon_discount($found, $subtotal);
        }
    }

    $tax = (int)round(($subtotal - $discount) * vat_rate());
    $freeShippingMin = (int)settings('free_shipping_min', 0);
    $shipping = 0;
    if ($subtotal > 0 && !($freeShippingMin > 0 && $subtotal >= $freeShippingMin)) {
        $shipping = (int)settings('shipping_cost', 0);
    }
    $total = $subtotal - $discount + $tax + $shipping;

    return compact('subtotal', 'discount', 'tax', 'shipping', 'total', 'items', 'coupon', 'couponError', 'freeShippingMin');
}

function coupon_find(PDO $db, $code)
{
    $stmt = $db->prepare('SELECT * FROM coupons WHERE UPPER(code) = UPPER(?)');
    $stmt->execute([en_digits(trim($code))]);
    $c = $stmt->fetch();
    return $c ?: null;
}

function coupon_error($coupon, $subtotal)
{
    if (!$coupon) {
        return 'کد تخفیف یافت نشد.';
    }
    if (!(int)$coupon['is_active']) {
        return 'این کد تخفیف غیرفعال شده است.';
    }
    if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < strtotime(date('Y-m-d'))) {
        return 'مهلت استفاده از این کد تخفیف به پایان رسیده است.';
    }
    if ((int)$coupon['max_uses'] > 0 && (int)$coupon['used'] >= (int)$coupon['max_uses']) {
        return 'ظرفیت استفاده از این کد تخفیف تکمیل شده است.';
    }
    if ((int)$coupon['min_total'] > 0 && $subtotal < (int)$coupon['min_total']) {
        return 'حداقل مبلغ سبد برای این کد ' . money($coupon['min_total']) . ' است.';
    }
    return null;
}

function coupon_discount($coupon, $subtotal)
{
    if (!$coupon || coupon_error($coupon, $subtotal) !== null) {
        return 0;
    }
    if ($coupon['type'] === 'percent') {
        $d = (int)round($subtotal * ((int)$coupon['amount'] / 100));
    } else {
        $d = (int)$coupon['amount'];
    }
    return min($d, $subtotal);
}

// ------------------------------------------------------------- شماره‌گذاری
function next_order_no(PDO $db)
{
    $prefix = 'PSA-' . date('ym') . '-';
    $stmt = $db->prepare("SELECT order_no FROM orders WHERE order_no LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $n = $last ? ((int)substr($last, -4)) + 1 : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function next_invoice_no(PDO $db)
{
    $prefix = 'INV-' . date('ym') . '-';
    $stmt = $db->prepare('SELECT invoice_no FROM invoices WHERE invoice_no LIKE ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $n = $last ? ((int)substr($last, -4)) + 1 : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function gen_tax_unique_id()
{
    return 'A1847-' . random_int(10000000000, 99999999999) . '-0021';
}

function gen_tracking_code()
{
    return 'TRK-' . random_int(100000, 999999);
}

function gen_rfq_code(PDO $db)
{
    $prefix = 'RFQ-' . date('y') . '-';
    $stmt = $db->prepare('SELECT rfq_code FROM rfqs WHERE rfq_code LIKE ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $n = $last ? ((int)substr($last, -3)) + 1 : 100;
    return $prefix . $n;
}

// ------------------------------------------------------ اعلان و گزارش
function notify($userId, $title, $body = '', $link = '')
{
    global $db;
    if (!$userId) {
        return;
    }
    $stmt = $db->prepare('INSERT INTO notifications (user_id, title, body, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)');
    $stmt->execute([(int)$userId, $title, $body, $link, date('Y-m-d H:i:s')]);
}

function notify_admins($title, $body = '', $link = '')
{
    global $db;
    foreach ($db->query("SELECT id FROM users WHERE role = 'admin'") as $a) {
        notify($a['id'], $title, $body, $link);
    }
}

function log_action($action, $entity = null, $entityId = null, $details = '')
{
    global $db;
    $u = current_user();
    $stmt = $db->prepare('INSERT INTO activity_logs (user_id, actor_name, action, entity, entity_id, details, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $u ? (int)$u['id'] : null,
        $u ? $u['name'] : 'مهمان',
        $action,
        $entity,
        $entityId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'cli',
        date('Y-m-d H:i:s'),
    ]);
}

function unread_notifications($userId = null)
{
    global $db;
    $userId = $userId ?: user_id();
    if (!$userId) {
        return 0;
    }
    $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([(int)$userId]);
    return (int)$stmt->fetchColumn();
}

// ---------------------------------------------------------- صفحه‌بندی
function paginate($total, $perPage, $currentPage)
{
    $total = max(0, (int)$total);
    $perPage = max(1, (int)$perPage);
    $pages = max(1, (int)ceil($total / $perPage));
    $current = min(max(1, (int)$currentPage), $pages);
    return [
        'total' => $total,
        'per_page' => $perPage,
        'pages' => $pages,
        'current' => $current,
        'offset' => ($current - 1) * $perPage,
        'from' => $total ? ($current - 1) * $perPage + 1 : 0,
        'to' => min($total, $current * $perPage),
    ];
}

/** لینک صفحه با حفظ پارامترهای فعلی */
function page_link($page, $params = [])
{
    $query = array_merge($_GET, ['page' => $page], $params);
    return 'index.php?' . http_build_query($query);
}

// ------------------------------------------------------------ آمارگیری
function admin_stats(PDO $db)
{
    $stats = [];
    $stats['users'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'buyer'")->fetchColumn();
    $stats['active_users'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'buyer' AND status = 'active'")->fetchColumn();
    $stats['products'] = (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $stats['low_stock'] = (int)$db->query('SELECT COUNT(*) FROM products WHERE stock <= min_stock AND is_active = 1')->fetchColumn();
    $stats['out_stock'] = (int)$db->query('SELECT COUNT(*) FROM products WHERE stock <= 0')->fetchColumn();
    $stats['orders'] = (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $stats['pending_orders'] = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','approved','preparing','shipped')")->fetchColumn();
    $stats['rfqs_open'] = (int)$db->query("SELECT COUNT(*) FROM rfqs WHERE status IN ('new','reviewing')")->fetchColumn();
    $stats['revenue'] = (int)$db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'canceled'")->fetchColumn();
    $stats['paid_revenue'] = (int)$db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
    $stats['month_revenue'] = (int)$db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'canceled' AND strftime('%Y-%m', created_at) = strftime('%Y-%m','now')")->fetchColumn();
    $stats['invoices'] = (int)$db->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
    $stats['avg_order'] = $stats['orders'] ? (int)round($stats['revenue'] / $stats['orders']) : 0;
    $stats['unread_msgs'] = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id IN (SELECT id FROM users WHERE role='admin') AND is_read = 0")->fetchColumn();
    return $stats;
}

/** فروش ۶ ماه اخیر (میلادی) برای نمودار */
function sales_series(PDO $db, $months = 6)
{
    $series = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-$i month"));
        $stmt = $db->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'canceled' AND strftime('%Y-%m', created_at) = ?");
        $stmt->execute([$key]);
        list($jy, $jm) = gregorian_to_jalali((int)date('Y', strtotime($key . '-01')), (int)date('n', strtotime($key . '-01')), 1);
        $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        $series[] = [
            'label' => $months[$jm - 1],
            'full' => $months[$jm - 1] . ' ' . fa_num($jy),
            'value' => (int)$stmt->fetchColumn(),
        ];
    }
    return $series;
}

function top_products(PDO $db, $limit = 5)
{
    $stmt = $db->prepare("SELECT oi.name, oi.brand, SUM(oi.qty) AS qty, SUM(oi.total) AS amount
                          FROM order_items oi JOIN orders o ON o.id = oi.order_id
                          WHERE o.status != 'canceled'
                          GROUP BY oi.name ORDER BY qty DESC LIMIT " . (int)$limit);
    $stmt->execute();
    return $stmt->fetchAll();
}

function status_distribution(PDO $db)
{
    $rows = $db->query('SELECT status, COUNT(*) AS c FROM orders GROUP BY status')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['status']] = (int)$r['c'];
    }
    return $out;
}

function buyer_stats(PDO $db, $userId)
{
    $stmt = $db->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(total),0) AS sum FROM orders WHERE user_id = ? AND status != 'canceled'");
    $stmt->execute([(int)$userId]);
    $row = $stmt->fetch();

    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('pending','approved','preparing','shipped')");
    $stmt->execute([(int)$userId]);

    return [
        'orders' => (int)$row['c'],
        'spent' => (int)$row['sum'],
        'open' => (int)$stmt->fetchColumn(),
    ];
}

// ------------------------------------------------------------- کمکی‌ها
function is_favorite($productId, $userId = null)
{
    global $db;
    $userId = $userId ?: user_id();
    if (!$userId) {
        return false;
    }
    $stmt = $db->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND product_id = ?');
    $stmt->execute([(int)$userId, (int)$productId]);
    return (bool)$stmt->fetchColumn();
}

function stock_badge($product)
{
    if ((int)$product['stock'] <= 0) {
        return ['class' => 'danger', 'text' => 'ناموجود'];
    }
    if ((int)$product['stock'] <= (int)$product['min_stock']) {
        return ['class' => 'warn', 'text' => 'موجودی محدود: ' . fa_num($product['stock']) . ' ' . $product['unit']];
    }
    return ['class' => 'success', 'text' => 'موجود در انبار'];
}

function stars($rating)
{
    $full = (int)round($rating);
    return str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', 5 - max(0, min(5, $full)));
}

function sanitize_int($v, $default = 0)
{
    return (int)en_digits((string)$v) ?: $default;
}

/** سال شمسی جاری */
function jyear()
{
    list($jy) = gregorian_to_jalali((int)date('Y'), (int)date('n'), (int)date('j'));
    return (int)$jy;
}

// ------------------------------------------------------- روش‌های پرداخت
/** نام فارسی روش پرداخت سفارش (همان برچسب‌های پنل مدیریت) */
function payment_method_label($method)
{
    $labels = [
        'transfer' => 'انتقال بانکی',
        'credit'   => 'تسویه اعتباری',
        'wallet'   => 'اعتبار کارپوشه',
    ];
    return $labels[$method] ?? 'انتقال بانکی';
}

// ------------------------------------------------------- تصویر کالا
/** الگوی نشانی تصویر آپلودشده: uploads/products/<32 حرف هگزادسیمال>.<پسوند مجاز> */
const PRODUCT_IMAGE_PATH_PATTERN = '~^uploads/products/[a-f0-9]{32}\.(?:jpg|png|webp)$~';

/**
 * نشانی قابل نمایش تصویر کالا را برمی‌گرداند.
 * فقط تصویر آپلودشده (مسیر نسبی با الگوی مشخص) یا نشانی http(s) پذیرفته می‌شود؛
 * هر مقدار دیگری (مثلاً javascript:) نادیده گرفته و '' برگردانده می‌شود.
 */
function product_image_src($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (preg_match(PRODUCT_IMAGE_PATH_PATTERN, $value)) {
        return $value;
    }
    if (preg_match('~^https?://[^\s"\'<>\\\\]+$~i', $value)) {
        return $value;
    }
    return '';
}

/** فایل تصویر آپلودشدهٔ یک کالا را از دیسک پاک می‌کند (فقط مسیرهای مجاز با الگوی سخت‌گیرانه) */
function product_image_remove($value)
{
    if (preg_match(PRODUCT_IMAGE_PATH_PATTERN, (string)$value)) {
        $file = APP_ROOT . '/' . $value;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/**
 * فایل آپلودشدهٔ تصویر کالا را بدون ذخیره، بررسی می‌کند.
 * نوع تصویر از محتوای واقعی فایل (getimagesize) تعیین می‌شود، نه از نام فایل.
 * @return array{tmp: ?string, ext: ?string, error: ?string} tmp=null یعنی فایلی ارسال نشده است
 */
function product_image_inspect($file)
{
    $none = ['tmp' => null, 'ext' => null, 'error' => null];
    if (!is_array($file) || !isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return $none;
    }
    $code = (int)$file['error'];
    $tooLarge = ['tmp' => null, 'ext' => null, 'error' => 'حجم تصویر بیش از حد مجاز است (حداکثر ' . fa_num(PRODUCT_IMAGE_MAX_BYTES / 1048576) . ' مگابایت).'];
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return $tooLarge;
    }
    $failed = ['tmp' => null, 'ext' => null, 'error' => 'دریافت تصویر ناموفق بود؛ لطفاً دوباره تلاش کنید.'];
    if ($code !== UPLOAD_ERR_OK) {
        return $failed;
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $failed;
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        return ['tmp' => null, 'ext' => null, 'error' => 'فایل تصویر خالی است.'];
    }
    if ($size > PRODUCT_IMAGE_MAX_BYTES) {
        return $tooLarge;
    }
    $info = @getimagesize($tmp);
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($extensions[$info[2]])) {
        return ['tmp' => null, 'ext' => null, 'error' => 'فقط تصویر با قالب JPG، PNG یا WebP پذیرفته می‌شود.'];
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > 4000 || $info[1] > 4000) {
        return ['tmp' => null, 'ext' => null, 'error' => 'ابعاد تصویر بیش از حد است (حداکثر ۴٬۰۰۰ × ۴٬۰۰۰ پیکسل).'];
    }
    return ['tmp' => $tmp, 'ext' => $extensions[$info[2]], 'error' => null];
}

/**
 * تصویر بررسی‌شده را با نام تصادفی در uploads/products ذخیره می‌کند.
 * اگر افزونه GD باشد، تصویر دوباره ساخته می‌شود تا متادادهٔ EXIF (از جمله مکان GPS عکس)
 * و هر داده‌ی اضافی پس از تصویر حذف شود؛ جهت عکس از روی EXIF اعمال می‌شود.
 * در نبود GD، فایل اصلی (پس از اعتبارسنجی) ذخیره می‌شود.
 * فقط پس از موفقیت همهٔ اعتبارسنجی‌های فرم صدا زده شود تا فایل یتیم نماند.
 * @return array{path: ?string, error: ?string} path=null یعنی فایلی برای ذخیره نبود
 */
function product_image_commit(array $inspected)
{
    if ($inspected['tmp'] === null) {
        return ['path' => null, 'error' => null];
    }
    $dir = APP_UPLOADS . '/products';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['path' => null, 'error' => 'پوشه تصاویر ساخته نشد؛ دسترسی نوشتن در پوشه سایت را بررسی کنید.'];
    }
    $name = bin2hex(random_bytes(16)) . '.' . $inspected['ext'];
    $target = $dir . '/' . $name;
    if (!product_image_reencode($inspected['tmp'], $target, $inspected['ext'])) {
        if (is_file($target)) {
            @unlink($target);
        }
        if (!move_uploaded_file($inspected['tmp'], $target)) {
            return ['path' => null, 'error' => 'ذخیره تصویر ناموفق بود؛ دسترسی نوشتن در پوشه uploads را بررسی کنید.'];
        }
    }
    return ['path' => 'uploads/products/' . $name, 'error' => null];
}

/** تصویر را با GD دوباره می‌سازد و در $target می‌نویسد؛ در صورت نبود قابلیت یا خطا false برمی‌گرداند */
function product_image_reencode($tmp, $target, $ext)
{
    if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) {
        return false;
    }
    $raw = @file_get_contents($tmp);
    $img = $raw === false ? false : @imagecreatefromstring($raw);
    if (!$img) {
        return false;
    }
    $saved = false;
    if ($ext === 'jpg' && function_exists('imagejpeg')) {
        $img = product_image_apply_orientation($img, jpeg_exif_orientation($tmp));
        $saved = @imagejpeg($img, $target, 90);
    } elseif ($ext === 'png' && function_exists('imagepng')) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $saved = @imagepng($img, $target, 6);
    } elseif ($ext === 'webp' && function_exists('imagewebp')) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $saved = @imagewebp($img, $target, 88);
    }
    imagedestroy($img);
    return (bool)$saved && is_file($target) && filesize($target) > 0;
}

/**
 * جهت EXIF یک JPEG (۱ تا ۸) را از بخش APP1 می‌خواند؛ در نبود یا خطا ۱ (بدون چرخش) برمی‌گرداند.
 * فقط ۲۵۶ کیلوبایت ابتدای فایل خوانده می‌شود و هیچ خطایی به خروجی نمی‌رود.
 */
function jpeg_exif_orientation($file)
{
    $data = @file_get_contents($file, false, null, 0, 262144);
    if (!is_string($data) || strlen($data) < 4 || ord($data[0]) !== 0xFF || ord($data[1]) !== 0xD8) {
        return 1;
    }
    $len = strlen($data);
    $u16 = function ($o, $le) use ($data, $len) {
        if ($o < 0 || $o + 1 >= $len) {
            return 0;
        }
        return $le ? (ord($data[$o]) | (ord($data[$o + 1]) << 8)) : ((ord($data[$o]) << 8) | ord($data[$o + 1]));
    };
    $u32 = function ($o, $le) use ($u16) {
        return $le ? ($u16($o, true) | ($u16($o + 2, true) << 16)) : (($u16($o, false) << 16) | $u16($o + 2, false));
    };
    $pos = 2;
    while ($pos + 4 <= $len) {
        if (ord($data[$pos]) !== 0xFF) {
            return 1;
        }
        $marker = ord($data[$pos + 1]);
        if ($marker === 0xDA || $marker === 0xD9) {
            return 1;
        }
        $segment = $u16($pos + 2, false);
        if ($segment < 2) {
            return 1;
        }
        if ($marker === 0xE1 && substr($data, $pos + 4, 6) === "Exif\0\0") {
            $tiff = $pos + 10;
            $order = substr($data, $tiff, 2);
            if ($order !== 'II' && $order !== 'MM') {
                return 1;
            }
            $le = $order === 'II';
            $ifd = $tiff + $u32($tiff + 4, $le);
            $count = $u16($ifd, $le);
            for ($i = 0; $i < $count; $i++) {
                $entry = $ifd + 2 + 12 * $i;
                if ($entry + 12 > $len) {
                    return 1;
                }
                if ($u16($entry, $le) === 0x0112) {
                    $value = $u16($entry + 8, $le);
                    return ($value >= 1 && $value <= 8) ? $value : 1;
                }
            }
            return 1;
        }
        $pos += 2 + $segment;
    }
    return 1;
}

/** جهت تصویر را بر اساس مقدار EXIF Orientation اصلاح می‌کند (۱ = بدون تغییر) */
function product_image_apply_orientation($img, $orientation)
{
    switch ((int)$orientation) {
        case 2: imageflip($img, IMG_FLIP_HORIZONTAL); break;
        case 3: $img = imagerotate($img, 180, 0); break;
        case 4: imageflip($img, IMG_FLIP_VERTICAL); break;
        case 5: imageflip($img, IMG_FLIP_HORIZONTAL); $img = imagerotate($img, 90, 0); break;
        case 6: $img = imagerotate($img, -90, 0); break;
        case 7: imageflip($img, IMG_FLIP_HORIZONTAL); $img = imagerotate($img, -90, 0); break;
        case 8: $img = imagerotate($img, 90, 0); break;
    }
    return $img;
}

// ------------------------------------------------------- صورتحساب
/**
 * یک مبلغ صحیح را به نسبت وزن‌ها بین اقلام تقسیم می‌کند؛ جمع نتیجه دقیقاً برابر مبلغ است
 * (روش بزرگ‌ترین باقی‌مانده). به این ترتیب جمع مالیات و تخفیف ردیف‌ها با جمع کل صورتحساب می‌خواند.
 * @return int[] هم‌اندازه با $weights
 */
function allocate_amount($amount, array $weights)
{
    $amount = (int)round((float)$amount);
    $weights = array_values($weights);
    $result = array_fill(0, count($weights), 0);
    $sum = 0.0;
    foreach ($weights as $w) {
        $sum += max(0.0, (float)$w);
    }
    if ($sum <= 0.0 || $amount === 0) {
        return $result;
    }
    $fractions = [];
    $allocated = 0;
    foreach ($weights as $i => $w) {
        $exact = $amount * (max(0.0, (float)$w) / $sum);
        $floor = (int)floor($exact);
        $result[$i] = $floor;
        $fractions[$i] = $exact - $floor;
        $allocated += $floor;
    }
    $left = $amount - $allocated;
    arsort($fractions);
    foreach (array_keys($fractions) as $i) {
        if ($left <= 0) {
            break;
        }
        $result[$i]++;
        $left--;
    }
    return $result;
}

/**
 * ردیف‌های صورتحساب را با تخفیف و ارزش افزوده تفکیک‌شده برمی‌گرداند.
 * هر ردیف: discount (سهم تخفیف)، taxable (مبلغ مشمول مالیات)، vat، final (مبلغ نهایی ردیف).
 * جمع فیلدها دقیقاً با جمع کل صورتحساب (تخفیف و ارزش افزوده ذخیره‌شده) برابر است.
 */
function invoice_line_breakdown(array $items, $discount, $vatAmount)
{
    $totals = [];
    foreach ($items as $it) {
        $totals[] = (int)($it['total'] ?? 0);
    }
    $discountShare = allocate_amount($discount, $totals);
    $taxable = [];
    foreach ($totals as $i => $t) {
        $taxable[$i] = $t - $discountShare[$i];
    }
    $vat = allocate_amount($vatAmount, $taxable);
    $rows = [];
    foreach (array_values($items) as $i => $it) {
        $rows[] = $it + [
            'discount' => $discountShare[$i],
            'taxable'  => $taxable[$i],
            'vat'      => $vat[$i],
            'final'    => $taxable[$i] + $vat[$i],
        ];
    }
    return $rows;
}

/** عدد ۱ تا ۹۹۹ را به حروف فارسی می‌نویسد (بدون صفر) */
function persian_group_words($n)
{
    $ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    $teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    $tens = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    $hundreds = ['', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
    $parts = [];
    $h = intdiv($n, 100);
    $rest = $n % 100;
    if ($h > 0) {
        $parts[] = $hundreds[$h];
    }
    if ($rest >= 10 && $rest <= 19) {
        $parts[] = $teens[$rest - 10];
    } else {
        $t = intdiv($rest, 10);
        $o = $rest % 10;
        if ($t > 0) {
            $parts[] = $tens[$t];
        }
        if ($o > 0) {
            $parts[] = $ones[$o];
        }
    }
    return implode(' و ', $parts);
}

/** مبلغ عددی را به حروف فارسی می‌نویسد، مثلاً 1234000 → «یک میلیون و دویست و سی و چهار هزار» */
function amount_in_words($amount)
{
    $n = (int)round((float)$amount);
    if ($n === 0) {
        return 'صفر';
    }
    $negative = $n < 0;
    $n = abs($n);
    $scales = ['', 'هزار', 'میلیون', 'میلیارد', 'تریلیون', 'کوادریلیون'];
    $groups = [];
    while ($n > 0) {
        $groups[] = $n % 1000;
        $n = intdiv($n, 1000);
    }
    if (count($groups) > count($scales)) {
        return ($negative ? '-' : '') . fa_num(abs((int)round((float)$amount)));
    }
    $words = [];
    for ($i = count($groups) - 1; $i >= 0; $i--) {
        if ($groups[$i] === 0) {
            continue;
        }
        $part = persian_group_words($groups[$i]);
        if ($i > 0) {
            $part .= ' ' . $scales[$i];
        }
        $words[] = $part;
    }
    return ($negative ? 'منفی ' : '') . implode(' و ', $words);
}

/**
 * نشانی تأیید صورتحساب (برای کد QR و چاپ). نشانی کامل است تا با گوشی قابل باز شدن باشد.
 * اگر نام میزبان نامعتبر باشد، نشانی نسبی برمی‌گردد.
 */
function invoice_verify_url($taxUid)
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $path = 'index.php?page=invoice&id=' . rawurlencode((string)$taxUid);
    if (!preg_match('~^[A-Za-z0-9.\-]+(?::\d{1,5})?$~', $host)) {
        return $path;
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/' . $path;
}
