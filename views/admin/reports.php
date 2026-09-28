<?php
/** گزارش‌های فروش و تحلیل عملکرد */
require_admin();
$stats = admin_stats($db);
$series = sales_series($db, 6);
$maxSeries = max(1, max(array_column($series, 'value')));

$topProducts = top_products($db, 10);
$byCategory = $db->query("SELECT p.category, SUM(oi.qty) qty, SUM(oi.total) amount
                          FROM order_items oi
                          JOIN orders o ON o.id = oi.order_id
                          JOIN products p ON p.id = oi.product_id
                          WHERE o.status != 'canceled'
                          GROUP BY p.category ORDER BY amount DESC")->fetchAll();
$catTotal = 0;
foreach ($byCategory as $c) {
    $catTotal += (int)$c['amount'];
}

$topCustomers = $db->query("SELECT u.id, u.name, u.company, u.city, COUNT(o.id) c, COALESCE(SUM(o.total),0) s
                            FROM users u JOIN orders o ON o.user_id = u.id
                            WHERE o.status != 'canceled' GROUP BY u.id ORDER BY s DESC LIMIT 8")->fetchAll();

$paymentSplit = $db->query('SELECT payment_status, COUNT(*) c, COALESCE(SUM(total),0) s FROM orders GROUP BY payment_status')->fetchAll();
$couponUsage = $db->query('SELECT code, used, type, amount FROM coupons WHERE used > 0 ORDER BY used DESC')->fetchAll();

$avgBasket = (int)$db->query("SELECT COALESCE(AVG(total),0) FROM orders WHERE status != 'canceled'")->fetchColumn();
$returnRate = $db->query("SELECT
    ROUND(100.0 * (SELECT COUNT(*) FROM orders WHERE status = 'canceled') / MAX(1, (SELECT COUNT(*) FROM orders)), 1)")->fetchColumn();
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">📈</span>
    <div><span>فروش کل (بدون لغوی)</span><strong><?= money_short($stats['revenue']) ?> تومان</strong><small>از <?= fa_num($stats['orders']) ?> سفارش</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">🛒</span>
    <div><span>میانگین ارزش سفارش</span><strong><?= money_short($avgBasket) ?> تومان</strong><small>سبد میانگین خریداران سازمانی</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">🔁</span>
    <div><span>نرخ لغو سفارش</span><strong><?= fa_num((float)$returnRate, 1) ?>٪</strong><small>شاخص رضایت از فرایند تأمین</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico teal">⬇️</span>
    <div>
      <span>خروجی گزارش‌ها (CSV)</span>
      <strong class="flex-gap wrap">
        <a href="export.php?type=orders">سفارش‌ها</a>
        <a href="export.php?type=invoices">صورتحساب‌ها</a>
        <a href="export.php?type=products">کالاها</a>
        <a href="export.php?type=users">کاربران</a>
      </strong>
      <small>مناسب ارائه به حسابدار و مدیریت</small>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h3 class="card-title">روند فروش ۶ ماه اخیر</h3>
    <span class="pill info">میانگین ماهانه: <?= money_short((int)round(array_sum(array_column($series, 'value')) / 6)) ?> تومان</span>
  </div>
  <div class="chart tall">
    <?php foreach ($series as $s): ?>
      <div class="chart-col" title="<?= e($s['full'] ?? $s['label']) ?> — <?= money($s['value']) ?>">
        <div class="chart-bar" style="height: <?= max(3, (int)round($s['value'] / $maxSeries * 100)) ?>%">
          <span><?= money_short($s['value']) ?></span>
        </div>
        <div class="chart-label"><?= e($s['label']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="admin-grid-2">
  <div class="card">
    <div class="card-head"><h3 class="card-title">سهم گروه‌های کالایی از فروش</h3></div>
    <?php foreach ($byCategory as $c):
        $pct = $catTotal ? (int)round($c['amount'] / $catTotal * 100) : 0; ?>
      <div class="dist-row">
        <span><?= e(category_title($c['category'])) ?></span>
        <div class="progress-bar sm"><span style="width: <?= $pct ?>%"></span></div>
        <strong><?= money_short($c['amount']) ?> <small>(<?= fa_num($pct) ?>٪)</small></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$byCategory): ?><div class="empty-mini">داده‌ای برای تحلیل وجود ندارد.</div><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3 class="card-title">وضعیت تسویه مالی</h3><a class="link-more" href="index.php?page=admin_orders&payment=unpaid">پیگیری مطالبات ←</a></div>
    <table class="data-table">
      <thead><tr><th>وضعیت پرداخت</th><th>تعداد سفارش</th><th>مبلغ</th><th>سهم</th></tr></thead>
      <tbody>
        <?php foreach ($paymentSplit as $p): ?>
          <tr>
            <td><?= e(payment_status_label($p['payment_status'])) ?></td>
            <td><?= fa_num($p['c']) ?></td>
            <td><?= money_short($p['s']) ?></td>
            <td><?= fa_num($stats['revenue'] ? round($p['s'] / max(1, $stats['revenue']) * 100) : 0) ?>٪</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <h4 class="sub-title">عملکرد کدهای تخفیف</h4>
    <?php if (!$couponUsage): ?>
      <div class="empty-mini">هنوز کد تخفیفی استفاده نشده است.</div>
    <?php else: ?>
      <table class="data-table">
        <thead><tr><th>کد</th><th>نوع</th><th>مقدار</th><th>تعداد استفاده</th></tr></thead>
        <tbody>
          <?php foreach ($couponUsage as $c): ?>
            <tr>
              <td class="mono"><?= e($c['code']) ?></td>
              <td><?= $c['type'] === 'percent' ? 'درصدی' : 'مبلغ ثابت' ?></td>
              <td><?= $c['type'] === 'percent' ? fa_num($c['amount']) . '٪' : money_short($c['amount']) ?></td>
              <td><?= fa_num($c['used']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="admin-grid-2">
  <div class="card">
    <div class="card-head"><h3 class="card-title">پرفروش‌ترین کالاها</h3></div>
    <table class="data-table">
      <thead><tr><th>#</th><th>کالا</th><th>تعداد فروش</th><th>مبلغ فروش</th></tr></thead>
      <tbody>
        <?php foreach ($topProducts as $i => $t): ?>
          <tr>
            <td><?= fa_num($i + 1) ?></td>
            <td><strong><?= e($t['name']) ?></strong><div class="mini-note"><?= e($t['brand']) ?></div></td>
            <td><?= fa_num($t['qty']) ?></td>
            <td><?= money($t['amount']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$topProducts): ?><tr><td colspan="4" class="empty-mini">فروشی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-head"><h3 class="card-title">رتبه‌بندی مشتریان سازمانی</h3></div>
    <table class="data-table">
      <thead><tr><th>#</th><th>مشتری</th><th>تعداد سفارش</th><th>مجموع خرید</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($topCustomers as $i => $c): ?>
          <tr>
            <td><?= fa_num($i + 1) ?></td>
            <td><strong><?= e($c['company'] ?: $c['name']) ?></strong><div class="mini-note"><?= e($c['city'] ?: '—') ?></div></td>
            <td><?= fa_num($c['c']) ?></td>
            <td><?= money_short($c['s']) ?></td>
            <td><a class="btn btn-sm btn-secondary" href="index.php?page=admin_user&id=<?= (int)$c['id'] ?>">پرونده</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$topCustomers): ?><tr><td colspan="5" class="empty-mini">داده‌ای برای نمایش نیست.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
