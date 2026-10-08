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

function product_url($id)
{
    return 'index.php?page=product&id=' . (int)$id;
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
 * @return array{subtotal:int,discount:int,tax:int,shipping:int,total:int,items:array,coupon:?array}
 */
function cart_totals(PDO $db, $couponCode = null)
{
    $items = cart_items($db);
    $subtotal = 0;
    foreach ($items as $it) {
        $subtotal += $it['line_total'];
    }

    $coupon = null;
    $discount = 0;
    if ($couponCode) {
        $coupon = coupon_find($db, $couponCode);
        $discount = coupon_discount($coupon, $subtotal);
    }

    $tax = (int)round(($subtotal - $discount) * vat_rate());
    $shipping = 0;
    if ($subtotal > 0) {
        $freeMin = (int)settings('free_shipping_min', 0);
        $shipping = ($freeMin > 0 && $subtotal >= $freeMin) ? 0 : (int)settings('shipping_cost', 0);
    }
    $total = $subtotal - $discount + $tax + $shipping;

    return compact('subtotal', 'discount', 'tax', 'shipping', 'total', 'items', 'coupon');
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
