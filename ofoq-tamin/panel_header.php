<?php
/** قالب کارپوشه خریدار */
$me = current_user();
require __DIR__ . '/header.php';

$panelMenu = [
    'panel'               => ['▦', 'پیشخوان'],
    'panel_orders'        => ['□', 'سفارش‌های من'],
    'panel_invoices'      => ['▤', 'صورتحساب‌ها'],
    'panel_rfqs'          => ['☷', 'استعلام‌های قیمت'],
    'panel_favorites'     => ['☆', 'علاقه‌مندی‌ها'],
    'panel_notifications' => ['◉', 'اعلان‌ها'],
    'panel_profile'       => ['◎', 'کارپوشه و پروفایل'],
];
$menuParent = ['panel_order' => 'panel_orders'];
$menuActive = $menuParent[$currentPage] ?? $currentPage;
$unreadPanel = unread_notifications($me['id']);
?>

<div class="panel-layout">
  <aside class="panel-side" aria-label="ناوبری کارپوشه خریدار">
    <a class="panel-store-link" href="index.php?page=home"><span aria-hidden="true">←</span> بازگشت به فروشگاه</a>

    <div class="side-user">
      <div class="side-avatar" aria-hidden="true"><?= e(initials($me['company'] ?: $me['name'])) ?></div>
      <div class="side-user-text">
        <strong><?= e($me['company'] ?: $me['name']) ?></strong>
        <small><?= e($me['name']) ?> <span aria-hidden="true">·</span> <span class="mono"><?= e($me['phone']) ?></span></small>
        <?php if ($me['status'] !== 'active'): ?>
          <span class="pill danger">حساب غیرفعال است</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel-menu-wrap">
      <p class="panel-menu-caption">کارپوشه خرید</p>
      <nav class="panel-menu" aria-label="بخش‌های پنل خریدار">
        <?php foreach ($panelMenu as $key => $item): ?>
          <a class="panel-link <?= $menuActive === $key ? 'active' : '' ?>" href="index.php?page=<?= $key ?>"<?= $menuActive === $key ? ' aria-current="page"' : '' ?>>
            <span class="panel-link__ico" aria-hidden="true"><?= $item[0] ?></span>
            <span class="panel-link__label"><?= $item[1] ?></span>
            <?php if ($key === 'panel_notifications' && $unreadPanel): ?>
              <span class="count-chip" aria-label="<?= fa_num($unreadPanel) ?> پیام خوانده‌نشده"><?= fa_num($unreadPanel) ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
        <a class="panel-link danger" href="index.php?action=logout"><span class="panel-link__ico" aria-hidden="true">⇥</span> <span class="panel-link__label">خروج از حساب</span></a>
      </nav>
    </div>

    <div class="panel-side-note">
      <span class="panel-side-note__mark" aria-hidden="true">✓</span>
      <span>اطلاعات سفارش‌ها و صورتحساب‌ها در همین کارپوشه در دسترس است.</span>
    </div>
  </aside>

  <section class="panel-main" aria-label="محتوای کارپوشه خریدار">
    <header class="panel-head">
      <div class="panel-head__title">
        <span class="panel-eyebrow"><?= ($me['entity_type'] ?? '') === 'individual' ? 'فضای خریدار حقیقی' : ((($me['entity_type'] ?? '') === 'legal') ? 'فضای خریدار حقوقی' : 'کارپوشه خریدار') ?></span>
        <h1 class="panel-title"><?= e($pageTitle) ?></h1>
      </div>
      <a class="btn btn-secondary panel-cart-link" href="index.php?page=cart"><span aria-hidden="true">▣</span> سبد سفارش <span class="badge-count"><?= fa_num($cartCount) ?></span></a>
    </header>
