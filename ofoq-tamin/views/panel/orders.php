<?php
/** سفارش‌های خریدار: جست‌وجو، فیلتر وضعیت و صفحه‌بندی */
require_buyer();
$me = current_user();
$statuses = order_statuses();
$status = get('status');
if ($status !== '' && !isset($statuses[$status])) {
    $status = '';
}
$q = get('q');

$where = ['user_id = ?'];
$params = [$me['id']];
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(order_no LIKE ? OR tracking_code LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$sqlWhere = ' WHERE ' . implode(' AND ', $where);

$cnt = $db->prepare('SELECT COUNT(*) FROM orders' . $sqlWhere);
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), 10, (int)get('p', 1));

$stmt = $db->prepare(
    'SELECT orders.*, (SELECT tax_unique_id FROM invoices WHERE invoices.order_id = orders.id LIMIT 1) AS tax_uid'
    . ' FROM orders' . $sqlWhere . ' ORDER BY orders.id DESC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$counts = [];
$countStmt = $db->prepare('SELECT status, COUNT(*) AS c FROM orders WHERE user_id = ? GROUP BY status');
$countStmt->execute([$me['id']]);
foreach ($countStmt as $r) {
    $counts[$r['status']] = (int)$r['c'];
}
$allCount = array_sum($counts);
$filtered = $q !== '' || $status !== '';
?>

<section class="card">
  <div class="card-head">
    <h3 class="card-title">سفارش‌ها (<?= fa_num($pg['total']) ?>)</h3>
  </div>

  <form class="filter-bar order-filter" method="GET" action="index.php">
    <input type="hidden" name="page" value="panel_orders">
    <input type="search" class="filter-search" name="q" value="<?= e($q) ?>" placeholder="شماره سفارش یا کد رهگیری" aria-label="جست‌وجوی شماره سفارش یا کد رهگیری">
    <select name="status" aria-label="فیلتر وضعیت سفارش">
      <option value="">همه وضعیت‌ها (<?= fa_num($allCount) ?>)</option>
      <?php foreach ($statuses as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?> (<?= fa_num($counts[$key] ?? 0) ?>)</option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-primary" type="submit">جست‌وجو</button>
    <?php if ($filtered): ?>
      <a class="btn btn-sm btn-secondary" href="index.php?page=panel_orders">پاک‌کردن</a>
    <?php endif; ?>
  </form>

  <?php if (!$orders): ?>
    <?php if ($allCount === 0): ?>
      <div class="empty-state"><span aria-hidden="true">📦</span><h3>هنوز سفارشی ثبت نکرده‌اید</h3><p>کالاها را از کاتالوگ انتخاب کنید یا برای پروژه‌های بزرگ استعلام قیمت بگیرید.</p>
        <div class="flex-gap wrap"><a class="btn btn-primary" href="index.php?page=home">شروع خرید</a><a class="btn btn-secondary" href="index.php?page=rfq">استعلام قیمت پروژه</a></div></div>
    <?php else: ?>
      <div class="empty-mini">سفارشی با این مشخصات پیدا نشد. فیلتر یا عبارت جست‌وجو را تغییر دهید.</div>
    <?php endif; ?>
  <?php else: ?>
    <div class="table-wrap"><table class="data-table">
      <thead>
        <tr><th>شماره سفارش</th><th>مبلغ کل</th><th>وضعیت</th><th>پرداخت</th><th><span class="visually-hidden">عملیات</span></th></tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td>
              <strong class="mono"><?= e($o['order_no']) ?></strong>
              <div class="mini-note"><?= jdate($o['created_at']) ?></div>
            </td>
            <td><strong><?= money($o['total']) ?></strong></td>
            <td><span class="status <?= order_status_class($o['status']) ?>"><?= e(order_status_label($o['status'])) ?></span></td>
            <td><span class="pill <?= $o['payment_status'] === 'paid' ? 'success' : 'warn' ?>"><?= e(payment_status_label($o['payment_status'])) ?></span></td>
            <td class="cell-action">
              <div class="flex-gap cell-actions">
                <a class="btn btn-sm btn-primary" href="index.php?page=panel_order&no=<?= e($o['order_no']) ?>">جزئیات</a>
                <?php if ($o['tax_uid']): ?>
                  <a class="btn btn-sm btn-secondary" href="index.php?page=invoice&id=<?= e($o['tax_uid']) ?>">🧾 فاکتور</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>

    <?php if ($pg['pages'] > 1): ?>
      <nav class="pagination" aria-label="صفحات سفارش‌ها">
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
          <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('panel_orders', ['p' => $i])) ?>"<?= $i === $pg['current'] ? ' aria-current="page"' : '' ?>><?= fa_num($i) ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
