<?php
/** قالب بالای پنل خریدار */
$me = current_user();
require __DIR__ . '/header.php';

$panelMenu = [
    'panel'               => ['📊', 'پیشخوان خریدار'],
    'panel_orders'        => ['📦', 'سفارش‌های من'],
    'panel_invoices'      => ['🧾', 'صورتحساب‌های مالیاتی'],
    'panel_rfqs'          => ['📋', 'استعلام‌های قیمت'],
    'panel_favorites'     => ['⭐', 'علاقه‌مندی‌ها'],
    'panel_notifications' => ['🔔', 'اعلان‌ها'],
    'panel_profile'       => ['👤', 'کارپوشه و پروفایل'],
];
$unreadPanel = unread_notifications($me['id']);
?>

<div class="panel-layout">
  <aside class="panel-side">
    <div class="side-user">
      <div class="side-avatar"><?= e(initials($me['company'] ?: $me['name'])) ?></div>
      <strong><?= e($me['company'] ?: $me['name']) ?></strong>
      <small><?= e($me['name']) ?> | <?= e($me['phone']) ?></small>
      <span class="pill <?= $me['status'] === 'active' ? 'success' : 'danger' ?>">
        <?= $me['status'] === 'active' ? 'حساب فعال' : 'حساب غیرفعال' ?>
      </span>
    </div>

    <div class="side-credit">
      <span>اعتبار سازمانی کارپوشه</span>
      <strong><?= money($me['credit']) ?></strong>
      <small>قابل استفاده در سفارش‌های بعدی</small>
    </div>

    <nav class="panel-menu">
      <?php foreach ($panelMenu as $key => $item): ?>
        <a class="panel-link <?= $currentPage === $key ? 'active' : '' ?>" href="index.php?page=<?= $key ?>">
          <span><?= $item[0] ?></span> <?= $item[1] ?>
          <?php if ($key === 'panel_notifications' && $unreadPanel): ?>
            <span class="count-chip"><?= fa_num($unreadPanel) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a class="panel-link danger" href="index.php?action=logout"><span>🚪</span> خروج از حساب</a>
    </nav>

    <div class="side-help">
      <strong>پشتیبانی تدارکات</strong>
      <p><?= e(settings('phone')) ?></p>
      <a class="btn btn-sm btn-orange" href="index.php?page=rfq">ثبت استعلام جدید</a>
    </div>
  </aside>

  <main class="panel-main">
    <div class="panel-head">
      <div>
        <h1 class="panel-title"><?= e($pageTitle) ?></h1>
        <div class="crumbs">
          <a href="index.php?page=home">فروشگاه</a> <span>/</span>
          <a href="index.php?page=panel">پنل خریدار</a> <span>/</span>
          <span><?= e($pageTitle) ?></span>
        </div>
      </div>
      <div class="panel-head-actions">
        <a class="btn btn-secondary btn-sm" href="index.php?page=home">🏷️ ادامه خرید</a>
        <a class="btn btn-primary btn-sm" href="index.php?page=panel_notifications">
          🔔 اعلان‌ها <?= $unreadPanel ? '<span class="badge-count">' . fa_num($unreadPanel) . '</span>' : '' ?>
        </a>
      </div>
    </div>
