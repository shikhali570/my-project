<?php
/** لیست سفارش‌های خریدار با فیلتر وضعیت */
require_buyer();
$me = current_user();
$status = get('status');
$q = get('q');
$where = ['user_id = ?'];
$params = [$me['id']];
if ($status !== '' && isset(order_statuses()[$status])) {
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

$stmt = $db->prepare('SELECT * FROM orders' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$counts = [];
foreach ($db->query("SELECT status, COUNT(*) c FROM orders WHERE user_id = " . (int)$me['id'] . ' GROUP BY status') as $r) {
    $counts[$r['status']] = (int)$r['c'];
}
?>

<div class="tabs">
  <a class="tab <?= $status === '' ? 'active' : '' ?>" href="index.php?page=panel_orders">همه (<?= fa_num(array_sum($counts)) ?>)</a>
  <?php foreach (order_statuses() as $key => $label): ?>
    <a class="tab <?= $status === $key ? 'active' : '' ?>" href="index.php?page=panel_orders&status=<?= $key ?>">
      <?= e(order_status_icon($key)) ?> <?= e($label) ?> (<?= fa_num($counts[$key] ?? 0) ?>)
    </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-head">
    <h3 class="card-title"><?= fa_num($pg['total']) ?> سفارش</h3>
    <form class="inline-search" method="GET" action="index.php">
      <input type="hidden" name="page" value="panel_orders">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="جست‌وجوی شماره سفارش یا کد رهگیری">
      <button class="btn btn-sm btn-primary" type="submit">جست‌وجو</button>
    </form>
  </div>

  <?php if (!$orders): ?>
    <div class="empty-mini">سفارشی با این مشخصات یافت نشد.</div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>شماره سفارش</th><th>تاریخ ثبت</th><th>اقلام</th><th>مبلغ کل</th>
          <th>وضعیت سفارش</th><th>پرداخت</th><th>رهگیری</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o):
            $ic = $db->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = ?');
            $ic->execute([$o['id']]); ?>
          <tr>
            <td class="mono"><?= e($o['order_no']) ?></td>
            <td><?= jdate($o['created_at']) ?></td>
            <td><?= fa_num($ic->fetchColumn()) ?> قلم</td>
            <td><strong><?= money($o['total']) ?></strong></td>
            <td><span class="status <?= order_status_class($o['status']) ?>"><?= e(order_status_label($o['status'])) ?></span></td>
            <td><span class="pill <?= $o['payment_status'] === 'paid' ? 'success' : 'warn' ?>"><?= e(payment_status_label($o['payment_status'])) ?></span></td>
            <td class="mono"><?= e($o['tracking_code'] ?: '—') ?></td>
            <td>
              <div class="flex-gap">
                <a class="btn btn-sm btn-primary" href="index.php?page=panel_order&no=<?= e($o['order_no']) ?>">جزئیات</a>
                <?php
                $inv = $db->prepare('SELECT tax_unique_id FROM invoices WHERE order_id = ?');
                $inv->execute([$o['id']]);
                $tid = $inv->fetchColumn();
                if ($tid): ?>
                  <a class="btn btn-sm btn-secondary" href="index.php?page=invoice&id=<?= e($tid) ?>">🧾 فاکتور</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($pg['pages'] > 1): ?>
      <nav class="pagination">
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
          <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('panel_orders', ['p' => $i])) ?>"><?= fa_num($i) ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
