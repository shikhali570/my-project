<?php
/** صورتحساب‌های الکترونیکی خریدار: جمع‌بندی، جست‌وجو و فهرست */
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

$sum = $db->prepare('SELECT COUNT(*), COALESCE(SUM(total_amount),0), COALESCE(SUM(tax_amount),0) FROM invoices WHERE user_id = ?');
$sum->execute([$me['id']]);
list($allCount, $totalSum, $taxSum) = $sum->fetch(PDO::FETCH_NUM);
?>

<section class="card">
  <div class="card-head">
    <h3 class="card-title">صورتحساب‌ها (<?= fa_num($allCount) ?>)</h3>
    <form class="inline-search" method="GET" action="index.php">
      <input type="hidden" name="page" value="panel_invoices">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="شماره فاکتور یا شناسه یکتا" aria-label="جست‌وجوی صورتحساب">
      <button class="btn btn-sm btn-primary" type="submit">جست‌وجو</button>
    </form>
  </div>

  <p class="table-note">
    مجموع مبالغ (شامل ارزش افزوده): <strong><?= money($totalSum) ?></strong>
    · ارزش افزوده قابل استناد: <strong><?= money($taxSum) ?></strong>
  </p>

  <?php if (!$invoices): ?>
    <div class="empty-mini"><?= $q !== '' ? 'صورتحسابی با این مشخصات پیدا نشد.' : 'صورتحسابی صادر نشده است. با ثبت سفارش، صورتحساب به‌صورت خودکار صادر می‌شود.' ?></div>
  <?php else: ?>
    <div class="table-wrap"><table class="data-table">
      <thead>
        <tr><th>شماره فاکتور</th><th>تاریخ صدور</th><th>مبلغ کل</th><th>شناسه یکتای مالیاتی</th><th><span class="visually-hidden">عملیات</span></th></tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $inv): ?>
          <tr>
            <td><strong class="mono"><?= e($inv['invoice_no']) ?></strong></td>
            <td><?= jdate($inv['created_at']) ?></td>
            <td><strong><?= money($inv['total_amount']) ?></strong></td>
            <td class="mono small"><?= e($inv['tax_unique_id']) ?></td>
            <td class="cell-action"><a class="btn btn-sm btn-primary" href="index.php?page=invoice&id=<?= e($inv['tax_unique_id']) ?>">مشاهده / چاپ</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>

    <?php if ($pg['pages'] > 1): ?>
      <nav class="pagination" aria-label="صفحات صورتحساب‌ها">
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
          <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('panel_invoices', ['p' => $i])) ?>"<?= $i === $pg['current'] ? ' aria-current="page"' : '' ?>><?= fa_num($i) ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
