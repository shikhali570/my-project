<?php
/** داشبورد مدیریت */
require_admin();
$stats = admin_stats($db);

$series = sales_series($db, 6);
$maxSeries = max(1, max(array_column($series, 'value')));

$recentOrders = $db->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
$lowStock = $db->query('SELECT * FROM products WHERE stock <= min_stock ORDER BY stock ASC LIMIT 6')->fetchAll();
$openRfqs = $db->query("SELECT * FROM rfqs WHERE status IN ('new','reviewing') ORDER BY id DESC LIMIT 5")->fetchAll();
$topProducts = top_products($db, 5);
$dist = status_distribution($db);
$totalOrders = max(1, array_sum($dist));
$logs = $db->query('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 6')->fetchAll();
$topCustomers = $db->query("SELECT u.id, u.name, u.company, COUNT(o.id) c, COALESCE(SUM(o.total),0) s
                            FROM users u JOIN orders o ON o.user_id = u.id
                            WHERE o.status != 'canceled' GROUP BY u.id ORDER BY s DESC LIMIT 5")->fetchAll();
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">💰</span>
    <div>
      <span>فروش کل (سفارش‌های فعال)</span>
      <strong><?= money_short($stats['revenue']) ?> تومان</strong>
      <small>فروش این ماه: <?= money_short($stats['month_revenue']) ?> تومان</small>
    </div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">✅</span>
    <div>
      <span>دریافتی تأییدشده</span>
      <strong><?= money_short($stats['paid_revenue']) ?> تومان</strong>
      <small>میانگین هر سفارش: <?= money_short($stats['avg_order']) ?> تومان</small>
    </div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">📦</span>
    <div>
      <span>سفارش‌های در جریان</span>
      <strong><?= fa_num($stats['pending_orders']) ?> سفارش</strong>
      <small>از مجموع <?= fa_num($stats['orders']) ?> سفارش ثبت‌شده</small>
    </div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico purple">👥</span>
    <div>
      <span>خریداران سازمانی</span>
      <strong><?= fa_num($stats['active_users']) ?> فعال</strong>
      <small>از <?= fa_num($stats['users']) ?> حساب ثبت‌شده</small>
    </div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico red">⚠️</span>
    <div>
      <span>کالاهای نیازمند تأمین</span>
      <strong><?= fa_num($stats['low_stock']) ?> قلم</strong>
      <small><?= fa_num($stats['out_stock']) ?> قلم کاملاً ناموجود</small>
    </div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico teal">📋</span>
    <div>
      <span>استعلام‌های باز</span>
      <strong><?= fa_num($stats['rfqs_open']) ?> مورد</strong>
      <small><a href="index.php?page=admin_rfqs">پاسخ‌دهی و قیمت‌گذاری</a></small>
    </div>
  </div>
</div>

<div class="admin-grid-2">
  <div class="card">
    <div class="card-head">
      <h3 class="card-title">روند فروش ۶ ماه اخیر</h3>
      <span class="pill info">مجموع: <?= money_short(array_sum(array_column($series, 'value'))) ?> تومان</span>
    </div>
    <div class="chart">
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

  <div class="card">
    <div class="card-head"><h3 class="card-title">توزیع وضعیت سفارش‌ها</h3></div>
    <div class="dist-list">
      <?php foreach (order_statuses() as $key => $label):
          $c = $dist[$key] ?? 0;
          $pct = (int)round($c / $totalOrders * 100); ?>
        <div class="dist-row">
          <span class="status <?= order_status_class($key) ?>"><?= e($label) ?></span>
          <div class="progress-bar sm"><span style="width: <?= $pct ?>%"></span></div>
          <strong><?= fa_num($c) ?> <small>(<?= fa_num($pct) ?>٪)</small></strong>
        </div>
      <?php endforeach; ?>
    </div>
    <a class="btn btn-sm btn-secondary" href="index.php?page=admin_orders">مدیریت همه سفارش‌ها</a>
  </div>
</div>

<div class="admin-grid-2">
  <div class="card">
    <div class="card-head">
      <h3 class="card-title">آخرین سفارش‌ها</h3>
      <a class="link-more" href="index.php?page=admin_orders">همه ←</a>
    </div>
    <table class="data-table">
      <thead><tr><th>شماره</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td class="mono"><?= e($o['order_no']) ?><div class="mini-note"><?= jdate_short($o['created_at']) ?></div></td>
            <td><?= e($o['company'] ?: $o['customer_name']) ?><div class="mini-note mono"><?= e($o['phone']) ?></div></td>
            <td><?= money_short($o['total']) ?></td>
            <td>
              <span class="status <?= order_status_class($o['status']) ?>"><?= e(order_status_label($o['status'])) ?></span>
              <div class="mini-note"><?= e(payment_status_label($o['payment_status'])) ?></div>
            </td>
            <td><a class="btn btn-sm btn-primary" href="index.php?page=admin_order&id=<?= (int)$o['id'] ?>">بررسی</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-head">
      <h3 class="card-title">هشدار موجودی انبار</h3>
      <a class="link-more" href="index.php?page=admin_products&stock=low">مدیریت انبار ←</a>
    </div>
    <?php if (!$lowStock): ?>
      <div class="alert success">موجودی همه کالاها در سطح مطلوب است ✅</div>
    <?php else: ?>
      <table class="data-table">
        <thead><tr><th>کالا</th><th>موجودی</th><th>حد هشدار</th><th>اصلاح سریع</th></tr></thead>
        <tbody>
          <?php foreach ($lowStock as $p): ?>
            <tr>
              <td><?= e($p['icon']) ?> <?= e($p['name']) ?><div class="mini-note"><?= e($p['brand']) ?></div></td>
              <td><span class="status <?= (int)$p['stock'] <= 0 ? 'danger' : 'warn' ?>"><?= fa_num($p['stock']) ?> <?= e($p['unit']) ?></span></td>
              <td><?= fa_num($p['min_stock']) ?></td>
              <td>
                <form class="inline-form" method="POST" action="index.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="stock_update">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="redirect" value="index.php?page=admin">
                  <input type="number" class="qty-input" name="set" value="<?= (int)$p['stock'] ?>" min="0">
                  <button class="btn btn-sm btn-secondary" type="submit">ثبت</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="admin-grid-3">
  <div class="card">
    <div class="card-head"><h3 class="card-title">استعلام‌های در انتظار پاسخ</h3><a class="link-more" href="index.php?page=admin_rfqs">همه ←</a></div>
    <?php if (!$openRfqs): ?>
      <div class="empty-mini">استعلام بازی وجود ندارد.</div>
    <?php else: foreach ($openRfqs as $r): ?>
      <div class="flow-row">
        <div>
          <strong class="mono"><?= e($r['rfq_code']) ?></strong>
          <small><?= e($r['company']) ?></small>
        </div>
        <span class="status <?= rfq_status_class($r['status']) ?>"><?= e(rfq_status_label($r['status'])) ?></span>
        <a class="btn btn-sm btn-primary" href="index.php?page=admin_rfq&id=<?= (int)$r['id'] ?>">پاسخ</a>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3 class="card-title">پرفروش‌ترین کالاها</h3><a class="link-more" href="index.php?page=admin_reports">گزارش کامل ←</a></div>
    <?php foreach ($topProducts as $i => $t): ?>
      <div class="flow-row">
        <div>
          <strong><?= fa_num($i + 1) ?>. <?= e($t['name']) ?></strong>
          <small><?= e($t['brand']) ?> • <?= fa_num($t['qty']) ?> فروش</small>
        </div>
        <strong><?= money_short($t['amount']) ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (!$topProducts): ?><div class="empty-mini">داده‌ای برای نمایش نیست.</div><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3 class="card-title">مشتریان برتر</h3><a class="link-more" href="index.php?page=admin_users">همه ←</a></div>
    <?php foreach ($topCustomers as $c): ?>
      <div class="flow-row">
        <div>
          <strong><?= e($c['company'] ?: $c['name']) ?></strong>
          <small><?= fa_num($c['c']) ?> سفارش</small>
        </div>
        <a class="btn btn-sm btn-secondary" href="index.php?page=admin_user&id=<?= (int)$c['id'] ?>"><?= money_short($c['s']) ?></a>
      </div>
    <?php endforeach; ?>
    <?php if (!$topCustomers): ?><div class="empty-mini">مشتری با سفارش ثبت‌شده وجود ندارد.</div><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3 class="card-title">آخرین رویدادهای سیستم</h3><a class="link-more" href="index.php?page=admin_logs">گزارش کامل ←</a></div>
  <table class="data-table">
    <thead><tr><th>کاربر</th><th>عملیات</th><th>جزئیات</th><th>زمان</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= e($l['actor_name']) ?></td>
          <td><span class="pill muted mono"><?= e($l['action']) ?></span></td>
          <td><?= e($l['details']) ?></td>
          <td><?= jdate($l['created_at'], true) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
