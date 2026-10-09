<?php
/** آرشیو صورتحساب‌های الکترونیکی (مدیریت) */
require_admin();

$q = get('q');
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(i.invoice_no LIKE ? OR i.tax_unique_id LIKE ? OR i.buyer_name LIKE ? OR i.buyer_phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$cnt = $db->prepare('SELECT COUNT(*) FROM invoices i' . $sqlWhere);
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), 15, (int)get('p', 1));

$stmt = $db->prepare('SELECT i.*, o.order_no FROM invoices i LEFT JOIN orders o ON o.id = i.order_id' . $sqlWhere . ' ORDER BY i.id DESC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$totals = $db->query('SELECT COUNT(*) c, COALESCE(SUM(subtotal),0) sub, COALESCE(SUM(tax_amount),0) tax, COALESCE(SUM(total_amount),0) tot FROM invoices')->fetch();
$monthTax = (int)$db->query("SELECT COALESCE(SUM(tax_amount),0) FROM invoices WHERE strftime('%Y-%m', created_at) = strftime('%Y-%m','now')")->fetchColumn();
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">🧾</span>
    <div><span>تعداد صورتحساب‌های صادرشده</span><strong><?= fa_num($totals['c']) ?> سند</strong><small>ثبت‌شده در سامانه مؤدیان</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">💰</span>
    <div><span>مجموع مبالغ اسناد</span><strong><?= money_short($totals['tot']) ?> تومان</strong><small>خالص اقلام: <?= money_short($totals['sub']) ?> تومان</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">📊</span>
    <div><span>مجموع ارزش افزوده صادرشده</span><strong><?= money_short($totals['tax']) ?> تومان</strong><small>این ماه: <?= money_short($monthTax) ?> تومان</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico teal">⬇️</span>
    <div><span>خروجی گزارش مالی</span><strong><a href="export.php?type=invoices">دریافت CSV صورتحساب‌ها</a></strong><small>مناسب ارائه به حسابدار</small></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h3 class="card-title">آرشیو اسناد مالیاتی</h3>
    <form class="inline-search" method="GET" action="index.php">
      <input type="hidden" name="page" value="admin_invoices">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="شماره فاکتور، شناسه یکتا، نام خریدار یا تلفن">
      <button class="btn btn-sm btn-primary" type="submit">جست‌وجو</button>
    </form>
  </div>

  <div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>شماره فاکتور</th><th>شناسه یکتای مالیاتی</th><th>خریدار</th><th>سفارش</th><th>تاریخ</th><th>جمع اقلام</th><th>ارزش افزوده</th><th>مبلغ کل</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr>
          <td><strong class="mono"><?= e($inv['invoice_no']) ?></strong></td>
          <td class="mono small"><?= e($inv['tax_unique_id']) ?></td>
          <td><?= e($inv['buyer_name']) ?><div class="mini-note mono"><?= e($inv['buyer_phone']) ?></div></td>
          <td class="mono small"><?= e($inv['order_no'] ?: '—') ?></td>
          <td><?= jdate($inv['created_at']) ?></td>
          <td><?= money_short($inv['subtotal']) ?></td>
          <td><?= money_short($inv['tax_amount']) ?></td>
          <td><strong><?= money($inv['total_amount']) ?></strong></td>
          <td><a class="btn btn-sm btn-primary" href="index.php?page=invoice&id=<?= e($inv['tax_unique_id']) ?>">مشاهده / چاپ</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$invoices): ?>
        <tr><td colspan="9" class="empty-mini">صورتحسابی با این مشخصات یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>

  <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination">
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('admin_invoices', ['p' => $i])) ?>"><?= fa_num($i) ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>
