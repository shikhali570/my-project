<?php
/** صورتحساب‌های الکترونیکی خریدار */
require_buyer();
$me = current_user();
$q = get('q');

$where = ['user_id = ?'];
$params = [$me['id']];
if ($q !== '') {
    $where[] = '(invoice_no LIKE ? OR tax_unique_id LIKE ? OR buyer_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$sqlWhere = ' WHERE ' . implode(' AND ', $where);

$cnt = $db->prepare('SELECT COUNT(*) FROM invoices' . $sqlWhere);
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), 10, (int)get('p', 1));

$stmt = $db->prepare('SELECT * FROM invoices' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$sum = $db->prepare('SELECT COALESCE(SUM(total_amount),0), COALESCE(SUM(tax_amount),0) FROM invoices WHERE user_id = ?');
$sum->execute([$me['id']]);
list($totalSum, $taxSum) = $sum->fetch(PDO::FETCH_NUM);
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">🧾</span>
    <div><span>تعداد صورتحساب صادرشده</span><strong><?= fa_num($pg['total']) ?></strong><small>ثبت‌شده در سامانه مؤدیان</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">💰</span>
    <div><span>مجموع مبالغ صورتحساب‌ها</span><strong><?= money_short($totalSum) ?> تومان</strong><small>شامل ارزش افزوده</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">📊</span>
    <div><span>اعتبار ارزش افزوده قابل استناد</span><strong><?= money_short($taxSum) ?> تومان</strong><small>منتقل‌شده به کارپوشه شما</small></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h3 class="card-title">آرشیو صورتحساب‌های الکترونیکی</h3>
    <form class="inline-search" method="GET" action="index.php">
      <input type="hidden" name="page" value="panel_invoices">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="جست‌وجوی شماره فاکتور یا شناسه یکتا">
      <button class="btn btn-sm btn-primary" type="submit">جست‌وجو</button>
    </form>
  </div>

  <?php if (!$invoices): ?>
    <div class="empty-mini">صورتحسابی صادر نشده است. با ثبت سفارش، صورتحساب به صورت خودکار صادر می‌شود.</div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th>شماره فاکتور</th><th>شناسه یکتای مالیاتی</th><th>تاریخ صدور</th><th>مبلغ اقلام</th><th>ارزش افزوده</th><th>مبلغ کل</th><th>وضعیت</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $inv): ?>
          <tr>
            <td><strong class="mono"><?= e($inv['invoice_no']) ?></strong></td>
            <td class="mono small"><?= e($inv['tax_unique_id']) ?></td>
            <td><?= jdate($inv['created_at']) ?></td>
            <td><?= money($inv['subtotal']) ?></td>
            <td><?= money($inv['tax_amount']) ?></td>
            <td><strong><?= money($inv['total_amount']) ?></strong></td>
            <td><span class="status success">ثبت قطعی</span></td>
            <td><a class="btn btn-sm btn-primary" href="index.php?page=invoice&id=<?= e($inv['tax_unique_id']) ?>">مشاهده / چاپ</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($pg['pages'] > 1): ?>
      <nav class="pagination">
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
          <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('panel_invoices', ['p' => $i])) ?>"><?= fa_num($i) ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
