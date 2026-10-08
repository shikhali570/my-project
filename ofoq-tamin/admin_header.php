<?php
/** قالب بالای پنل مدیریت */
$me = current_user();
$stats = admin_stats($db);
$adminUnread = unread_notifications($me['id']);
$pageTitle = $pageTitle ?? 'داشبورد مدیریت';

$menuGroups = [
    'عملیات فروش' => [
        'admin'          => ['📊', 'داشبورد مدیریت'],
        'admin_orders'   => ['📦', 'سفارش‌ها', $stats['pending_orders']],
        'admin_invoices' => ['🧾', 'صورتحساب‌های الکترونیکی'],
        'admin_rfqs'     => ['📋', 'استعلام‌های قیمت', $stats['rfqs_open']],
    ],
    'کاتالوگ و انبار' => [
        'admin_products'   => ['🏷️', 'کالاها و موجودی'],
        'admin_categories' => ['🗂️', 'گروه‌های کالا'],
        'admin_coupons'    => ['🎟️', 'کدهای تخفیف'],
    ],
    'مشتریان و تحلیل' => [
        'admin_users'   => ['👥', 'کاربران و مشتریان'],
        'admin_reports' => ['📈', 'گزارش‌های فروش'],
        'admin_notifications' => ['🔔', 'اعلان‌ها', $adminUnread],
    ],
    'سیستم' => [
        'admin_logs'     => ['🛡️', 'گزارش رویدادها'],
        'admin_settings' => ['⚙️', 'تنظیمات فروشگاه'],
        'admin_profile'  => ['👤', 'پروفایل مدیر'],
    ],
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | پنل مدیریت <?= e(settings('site_name')) ?></title>
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=<?= APP_VERSION ?>">
</head>
<body class="admin-body">

<div class="admin-shell">
  <aside class="admin-side" id="adminSide">
    <div class="admin-brand">
      <div class="logo-icon">🏗️</div>
      <div>
        <strong><?= e(settings('site_name')) ?></strong>
        <small>پنل مدیریت و تدارکات</small>
      </div>
    </div>

    <div class="admin-user">
      <div class="side-avatar"><?= e(initials($me['name'])) ?></div>
      <div>
        <strong><?= e($me['name']) ?></strong>
        <small><?= e($me['phone']) ?></small>
      </div>
      <span class="pill primary">مدیر سیستم</span>
    </div>

    <nav class="admin-menu">
      <?php foreach ($menuGroups as $groupTitle => $items): ?>
        <div class="menu-group-title"><?= e($groupTitle) ?></div>
        <?php foreach ($items as $key => $item): ?>
          <a class="admin-link <?= $currentPage === $key ? 'active' : '' ?>" href="index.php?page=<?= $key ?>">
            <span class="ico"><?= $item[0] ?></span>
            <span class="txt"><?= $item[1] ?></span>
            <?php if (!empty($item[2])): ?><span class="count-chip"><?= fa_num($item[2]) ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="admin-side-foot">
      <a class="btn btn-secondary btn-sm" href="index.php?page=home">🏬 مشاهده فروشگاه</a>
      <a class="btn btn-sm btn-danger" href="index.php?action=logout">🚪 خروج</a>
    </div>
  </aside>

  <div class="admin-content">
    <header class="admin-topbar">
      <button class="burger" type="button" aria-label="باز و بسته کردن منوی مدیریت" aria-controls="adminSide" aria-expanded="false" onclick="var s=document.getElementById('adminSide');s.classList.toggle('open');this.setAttribute('aria-expanded',s.classList.contains('open')?'true':'false')">☰</button>
      <div>
        <h1 class="panel-title"><?= e($pageTitle) ?></h1>
        <div class="crumbs">
          <a href="index.php?page=admin">پنل مدیریت</a> <span>/</span> <span><?= e($pageTitle) ?></span>
        </div>
      </div>
      <div class="topbar-stats">
        <div class="mini-stat" title="سفارش‌های در جریان">
          <span>سفارش‌های در جریان</span>
          <strong><?= fa_num($stats['pending_orders']) ?></strong>
        </div>
        <div class="mini-stat" title="فروش این ماه">
          <span>فروش این ماه</span>
          <strong><?= money_short($stats['month_revenue']) ?></strong>
        </div>
        <div class="mini-stat warn" title="کالاهای نیازمند تأمین">
          <span>کالاهای کم‌موجود</span>
          <strong><?= fa_num($stats['low_stock']) ?></strong>
        </div>
      </div>
      <div class="topbar-actions">
        <a class="btn btn-secondary btn-sm" href="index.php?page=admin_notifications">
          🔔 <?= $adminUnread ? '<span class="badge-count">' . fa_num($adminUnread) . '</span>' : 'اعلان‌ها' ?>
        </a>
        <a class="btn btn-primary btn-sm" href="index.php?page=admin_product_form">➕ کالای جدید</a>
      </div>
    </header>

    <?php if (!empty($flashes)): ?>
      <div class="toast-wrap no-print">
        <?php foreach ($flashes as $f): ?>
          <div class="toast-bar <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="admin-page">
