<?php
/** پیشخوان خریدار: خلاصه حساب، هشدار کارپوشه (فقط در صورت نقص اطلاعات) و سفارش‌های اخیر */
require_buyer();
$me = current_user();
$stats = buyer_stats($db, $me['id']);

$recent = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5');
$recent->execute([$me['id']]);
$recentOrders = $recent->fetchAll();

$missing = [];
foreach (['national_id' => 'شناسه ملی', 'economic_code' => 'کد اقتصادی', 'postal_code' => 'کد پستی', 'address' => 'نشانی'] as $key => $label) {
    if (empty($me[$key])) {
        $missing[] = $label;
    }
}
?>

<div class="panel-quick">
  <a class="btn btn-primary" href="index.php?page=home">🛒 شروع خرید</a>
  <a class="btn btn-orange" href="index.php?page=rfq">📋 استعلام قیمت پروژه</a>
</div>

<section class="card summary-card" aria-label="خلاصه حساب">
  <div class="summary-item">
    <span>سفارش‌های ثبت‌شده</span>
    <strong><?= fa_num($stats['orders']) ?></strong>
    <small><?= fa_num($stats['open']) ?> سفارش در جریان</small>
  </div>
  <div class="summary-item">
    <span>مجموع خرید</span>
    <strong><?= money($stats['spent']) ?></strong>
    <small>بدون سفارش‌های لغوشده</small>
  </div>
  <div class="summary-item">
    <span>اعتبار کارپوشه</span>
    <strong><?= money($me['credit']) ?></strong>
    <small>قابل استفاده در سفارش‌های بعدی</small>
  </div>
</section>

<?php if ($missing): ?>
  <div class="alert warn alert-row">
    <span>موارد زیر در کارپوشه شما تکمیل نشده است: <strong><?= e(implode('، ', $missing)) ?></strong></span>
    <a class="btn btn-sm btn-primary" href="index.php?page=panel_profile">تکمیل اطلاعات</a>
  </div>
<?php endif; ?>

<section class="card">
  <div class="card-head">
    <h3 class="card-title">سفارش‌های اخیر</h3>
    <a class="link-more" href="index.php?page=panel_orders">همه سفارش‌ها ←</a>
  </div>
  <?php if (!$recentOrders): ?>
    <div class="empty-mini">هنوز سفارشی ثبت نکرده‌اید. <a href="index.php?page=home">شروع خرید</a></div>
  <?php else: ?>
    <div class="table-wrap"><table class="data-table">
      <thead>
        <tr><th>شماره سفارش</th><th>تاریخ</th><th>مبلغ کل</th><th>وضعیت</th><th><span class="visually-hidden">عملیات</span></th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td class="mono"><?= e($o['order_no']) ?></td>
            <td><?= jdate_short($o['created_at']) ?></td>
            <td><?= money($o['total']) ?></td>
            <td><span class="status <?= order_status_class($o['status']) ?>"><?= e(order_status_label($o['status'])) ?></span></td>
            <td class="cell-action"><a class="btn btn-sm btn-secondary" href="index.php?page=panel_order&no=<?= e($o['order_no']) ?>">مشاهده</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
