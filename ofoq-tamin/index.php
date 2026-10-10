<?php
/**
 * پارس سازه و آفیس | کنترلر اصلی و مسیریاب
 * همه درخواست‌ها از این فایل عبور می‌کنند: index.php?page=...
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/auth.php';    // ورود، ثبت‌نام، پروفایل، گذرواژه
require_once __DIR__ . '/inc/payment_gateway.php'; // درگاه امن به‌پرداخت ملت
require_once __DIR__ . '/inc/actions.php'; // سبد، سفارش، عملیات مدیریت

$page = preg_replace('/[^a-z_]/', '', get('page', 'home'));
if ($page === '') {
    $page = 'home';
}

// ---------------------------------------------------------------- مسیرها
$routeMap = [
    // عمومی
    'home'            => ['view' => 'views/home.php', 'layout' => 'public', 'title' => 'فروشگاه تجهیزات مهندسی'],
    'product'         => ['view' => 'views/product.php', 'layout' => 'public', 'title' => 'جزئیات کالا'],
    'cart'            => ['view' => 'views/cart.php', 'layout' => 'public', 'title' => 'سبد سفارش'],
    'order_success'   => ['view' => 'views/order_success.php', 'layout' => 'public', 'title' => 'ثبت سفارش'],
    'invoice'         => ['view' => 'views/invoice.php', 'layout' => 'public', 'title' => 'صورتحساب الکترونیکی'],
    'track'           => ['view' => 'views/track.php', 'layout' => 'public', 'title' => 'پیگیری سفارش'],
    'rfq'             => ['view' => 'views/rfq.php', 'layout' => 'public', 'title' => 'استعلام قیمت پروژه'],
    'about'           => ['view' => 'views/about.php', 'layout' => 'public', 'title' => 'درباره ما و استانداردهای مالیاتی'],
    'contact'         => ['view' => 'views/contact.php', 'layout' => 'public', 'title' => 'تماس با واحد فروش'],
    'login'           => ['view' => 'views/login.php', 'layout' => 'auth', 'title' => 'ورود به حساب'],
    'register'        => ['view' => 'views/register.php', 'layout' => 'auth', 'title' => 'ثبت‌نام خریدار حقیقی یا حقوقی'],

    // پنل خریدار
    'panel'               => ['view' => 'views/panel/dashboard.php', 'layout' => 'panel', 'title' => 'پیشخوان خریدار', 'role' => 'buyer'],
    'panel_orders'        => ['view' => 'views/panel/orders.php', 'layout' => 'panel', 'title' => 'سفارش‌های من', 'role' => 'buyer'],
    'panel_order'         => ['view' => 'views/panel/order_detail.php', 'layout' => 'panel', 'title' => 'جزئیات سفارش', 'role' => 'buyer'],
    'panel_invoices'      => ['view' => 'views/panel/invoices.php', 'layout' => 'panel', 'title' => 'صورتحساب‌های من', 'role' => 'buyer'],
    'panel_rfqs'          => ['view' => 'views/panel/rfqs.php', 'layout' => 'panel', 'title' => 'استعلام‌های قیمت', 'role' => 'buyer'],
    'panel_favorites'     => ['view' => 'views/panel/favorites.php', 'layout' => 'panel', 'title' => 'علاقه‌مندی‌ها', 'role' => 'buyer'],
    'panel_notifications' => ['view' => 'views/panel/notifications.php', 'layout' => 'panel', 'title' => 'پیام‌ها و اعلان‌ها', 'role' => 'buyer'],
    'panel_profile'       => ['view' => 'views/panel/profile.php', 'layout' => 'panel', 'title' => 'کارپوشه و پروفایل', 'role' => 'buyer'],

    // پنل مدیریت
    'admin'               => ['view' => 'views/admin/dashboard.php', 'layout' => 'admin', 'title' => 'داشبورد مدیریت', 'role' => 'admin'],
    'admin_products'      => ['view' => 'views/admin/products.php', 'layout' => 'admin', 'title' => 'مدیریت کالاها', 'role' => 'admin'],
    'admin_product_form'  => ['view' => 'views/admin/product_form.php', 'layout' => 'admin', 'title' => 'فرم کالا', 'role' => 'admin'],
    'admin_categories'    => ['view' => 'views/admin/categories.php', 'layout' => 'admin', 'title' => 'گروه‌های کالا', 'role' => 'admin'],
    'admin_orders'        => ['view' => 'views/admin/orders.php', 'layout' => 'admin', 'title' => 'مدیریت سفارش‌ها', 'role' => 'admin'],
    'admin_order'         => ['view' => 'views/admin/order_detail.php', 'layout' => 'admin', 'title' => 'جزئیات سفارش', 'role' => 'admin'],
    'admin_invoices'      => ['view' => 'views/admin/invoices.php', 'layout' => 'admin', 'title' => 'صورتحساب‌های الکترونیکی', 'role' => 'admin'],
    'admin_users'         => ['view' => 'views/admin/users.php', 'layout' => 'admin', 'title' => 'مدیریت کاربران', 'role' => 'admin'],
    'admin_user'          => ['view' => 'views/admin/user_detail.php', 'layout' => 'admin', 'title' => 'پرونده مشتری', 'role' => 'admin'],
    'admin_user_form'     => ['view' => 'views/admin/user_form.php', 'layout' => 'admin', 'title' => 'فرم کاربر', 'role' => 'admin'],
    'admin_rfqs'          => ['view' => 'views/admin/rfqs.php', 'layout' => 'admin', 'title' => 'مدیریت استعلام‌ها', 'role' => 'admin'],
    'admin_rfq'           => ['view' => 'views/admin/rfq_detail.php', 'layout' => 'admin', 'title' => 'پاسخ به استعلام', 'role' => 'admin'],
    'admin_coupons'       => ['view' => 'views/admin/coupons.php', 'layout' => 'admin', 'title' => 'کدهای تخفیف', 'role' => 'admin'],
    'admin_reports'       => ['view' => 'views/admin/reports.php', 'layout' => 'admin', 'title' => 'گزارش‌های فروش', 'role' => 'admin'],
    'admin_notifications' => ['view' => 'views/admin/notifications.php', 'layout' => 'admin', 'title' => 'اعلان‌های مدیریت', 'role' => 'admin'],
    'admin_settings'      => ['view' => 'views/admin/settings.php', 'layout' => 'admin', 'title' => 'تنظیمات فروشگاه', 'role' => 'admin'],
    'admin_control'       => ['view' => 'views/admin/control.php', 'layout' => 'admin', 'title' => 'مرکز مدیریت سایت', 'role' => 'admin'],

    'admin_logs'          => ['view' => 'views/admin/logs.php', 'layout' => 'admin', 'title' => 'گزارش رویدادها', 'role' => 'admin'],
    'admin_profile'       => ['view' => 'views/admin/profile.php', 'layout' => 'admin', 'title' => 'پروفایل مدیر', 'role' => 'admin'],
];

if (!isset($routeMap[$page])) {
    http_response_code(404);
    $page = 'home';
    $notFound = true;
}

$route = $routeMap[$page];
$layout = $route['layout'];

// کنترل سطح دسترسی
if (!empty($route['role'])) {
    require_login();
    if ($route['role'] === 'admin' && !is_admin()) {
        flash('دسترسی به پنل مدیریت فقط برای مدیران سیستم مجاز است.', 'error');
        redirect('index.php?page=panel');
    }
    if ($route['role'] === 'buyer' && !is_buyer()) {
        redirect('index.php?page=admin');
    }
}

// کاربران وارد شده نباید صفحه ورود/ثبت‌نام ببینند
if (in_array($page, ['login', 'register'], true) && is_logged_in()) {
    redirect('index.php?page=' . (is_admin() ? 'admin' : 'panel'));
}

$pageTitle = $route['title'];
$editablePageTitles = ['home', 'about', 'contact', 'rfq', 'register'];
if (in_array($page, $editablePageTitles, true)) {
    $pageTitle = site_content('page_title_' . $page, $pageTitle);
}
$currentPage = $page;
$flashes = take_flashes();
$cartCount = cart_count();
$navCategories = categories();

// ------------------------------------------------------------- رندر خروجی
switch ($layout) {
    case 'admin':
        require __DIR__ . '/admin_header.php';
        require __DIR__ . '/' . $route['view'];
        require __DIR__ . '/admin_footer.php';
        break;

    case 'panel':
        require __DIR__ . '/panel_header.php';
        require __DIR__ . '/' . $route['view'];
        require __DIR__ . '/panel_footer.php';
        break;

    default:
        require __DIR__ . '/header.php';
        require __DIR__ . '/' . $route['view'];
        require __DIR__ . '/footer.php';
        break;
}
