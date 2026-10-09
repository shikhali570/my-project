<?php
/** علاقه‌مندی‌های خریدار */
require_buyer();
$me = current_user();

$stmt = $db->prepare('SELECT p.*, f.created_at AS fav_at FROM favorites f JOIN products p ON p.id = f.product_id WHERE f.user_id = ? ORDER BY f.id DESC');
$stmt->execute([$me['id']]);
$products = $stmt->fetchAll();
$names = array_map(function ($p) {
    return $p['name'];
}, $products);
?>

<section class="card">
  <div class="card-head">
    <h3 class="card-title">علاقه‌مندی‌ها (<?= fa_num(count($products)) ?> کالا)</h3>
    <?php if ($products): ?>
      <div class="flex-gap wrap">
        <a class="btn btn-sm btn-secondary" href="index.php?page=home">➕ افزودن کالا</a>
        <a class="btn btn-sm btn-primary" href="index.php?page=rfq&items=<?= urlencode(implode('، ', $names)) ?>">📋 درخواست پیش‌فاکتور برای همه</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$products): ?>
    <div class="empty-state">
      <span aria-hidden="true">⭐</span>
      <h3>لیست علاقه‌مندی شما خالی است</h3>
      <p>با زدن دکمه ستاره روی کارت کالاها، اقلام پرتکرار پروژه را برای تأمین سریع ذخیره کنید.</p>
      <a class="btn btn-primary" href="index.php?page=home">مشاهده کاتالوگ</a>
    </div>
  <?php else: ?>
    <div class="pro-grid">
      <?php foreach ($products as $p) {
          require APP_ROOT . '/views/partials/product_card.php';
      } ?>
    </div>
  <?php endif; ?>
</section>
