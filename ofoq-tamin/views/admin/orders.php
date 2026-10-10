<?php
/** مدیریت سفارش‌ها با فیلتر پیشرفته و تغییر سریع وضعیت */
require_admin();

$status = get('status');
$payment = get('payment');
$q = get('q');
$from = get('from');
$to = get('to');

$where = [];
$params = [];
if ($status !== '' && isset(order_statuses()[$status])) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($payment !== '' && isset(payment_statuses()[$payment])) {
    $where[] = 'payment_status = ?';
    $params[] = $payment;
}
if ($q !== '') {
    $where[] = '(order_no LIKE ? OR customer_name LIKE ? OR company LIKE ? OR phone LIKE ? OR tracking_code LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($from !== '') {
    $where[] = 'created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'created_at <= ?';
    $params[] = $to . ' 23:59:59';
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$cnt = $db->prepare('SELECT COUNT(*) FROM orders' . $sqlWhere);
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), 12, (int)get('p', 1));

$stmt = $db->prepare('SELECT * FROM orders' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$sumFiltered = $db->prepare('SELECT COALESCE(SUM(total),0) FROM orders' . $sqlWhere);
$sumFiltered->execute($params);
$filteredTotal = (int)$sumFiltered->fetchColumn();
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">📦</span>
    <div><span>سفارش‌های مطابق فیلتر</span><strong><?= fa_num($pg['total']) ?> سفارش</strong><small>مبلغ مجموع: <?= money_short($filteredTotal) ?> تومان</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">⏳</span>
    <div><span>در انتظار تأیید</span><strong><?= fa_num((int)$db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn()) ?> سفارش</strong><small>نیازمند بررسی واحد فروش</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">🚚</span>
    <div><span>ارسال‌شده / تحویل‌شده</span><strong><?= fa_num((int)$db->query("SELECT COUNT(*) FROM orders WHERE status IN ('shipped','delivered')")->fetchColumn()) ?> سفارش</strong><small>در جریان توزیع</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico teal">⬇️</span>
    <div><span>خروجی گزارش</span><strong><a href="export.php?type=orders">دریافت CSV سفارش‌ها</a></strong><small>به‌همراه جزئیات مشتری</small></div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3 class="card-title">فهرست سفارش‌ها</h3></div>

  <form class="filter-bar wide" method="GET" action="index.php">
    <input type="hidden" name="page" value="admin_orders">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="شماره سفارش، مشتری، تلفن یا کد رهگیری…" aria-label="جست‌وجو در سفارش‌ها">
    <select name="status" aria-label="فیلتر وضعیت سفارش">
      <option value="">همه وضعیت‌ها</option>
      <?php foreach (order_statuses() as $k => $label): ?>
        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="payment" aria-label="فیلتر وضعیت پرداخت">
      <option value="">وضعیت پرداخت</option>
      <?php foreach (payment_statuses() as $k => $label): ?>
        <option value="<?= $k ?>" <?= $payment === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= e($from) ?>" title="از تاریخ (میلادی)" aria-label="از تاریخ (میلادی)">
    <input type="date" name="to" value="<?= e($to) ?>" title="تا تاریخ (میلادی)" aria-label="تا تاریخ (میلادی)">
    <button class="btn btn-sm btn-primary" type="submit">فیلتر</button>
    <a class="btn btn-sm btn-secondary" href="index.php?page=admin_orders">بازنشانی</a>
  </form>

  <div class="table-wrap"><table class="data-table">
    <thead>
      <tr><th>شماره سفارش</th><th>مشتری</th><th>مبلغ کل</th><th>پرداخت</th><th>وضعیت سفارش</th><th>تغییر سریع وضعیت</th><th>رهگیری</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o):
          $itemCount = $db->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = ?');
          $itemCount->execute([$o['id']]);
          $hasInvoice = $db->prepare('SELECT tax_unique_id FROM invoices WHERE order_id = ?');
          $hasInvoice->execute([$o['id']]);
          $tid = $hasInvoice->fetchColumn(); ?>
        <tr>
          <td>
            <strong class="mono"><?= e($o['order_no']) ?></strong>
            <div class="mini-note"><?= jdate($o['created_at'], true) ?> • <?= fa_num($itemCount->fetchColumn()) ?> قلم</div>
          </td>
          <td>
            <?= e($o['company'] ?: $o['customer_name']) ?>
            <div class="mini-note"><?= e($o['customer_name']) ?> | <span class="mono"><?= e($o['phone']) ?></span></div>
          </td>
          <td>
            <strong><?= money($o['total']) ?></strong>
            <?php if ($o['discount'] > 0): ?><div class="mini-note">تخفیف: <?= money_short($o['discount']) ?></div><?php endif; ?>
          </td>
          <td>
            <span class="pill <?= $o['payment_status'] === 'paid' ? 'success' : ($o['payment_status'] === 'refunded' ? 'danger' : 'warn') ?>">
              <?= e(payment_status_label($o['payment_status'])) ?>
            </span>
            <div class="mini-note"><?= e(payment_method_label($o['payment_method'])) ?></div>
          </td>
          <td><span class="status <?= order_status_class($o['status']) ?>"><?= order_status_icon($o['status']) ?> <?= e(order_status_label($o['status'])) ?></span></td>
          <td>
            <form class="inline-form" method="POST" action="index.php" data-confirm-canceled>
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="order_update">
              <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <input type="hidden" name="payment_status" value="<?= e($o['payment_status']) ?>">
              <input type="hidden" name="tracking_code" value="<?= e($o['tracking_code']) ?>">
              <select name="status" class="mini-select" aria-label="تغییر وضعیت سفارش <?= e($o['order_no']) ?>" data-original="<?= e($o['status']) ?>">
                <?php foreach (order_statuses() as $k => $label): ?>
                  <option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-icon" type="submit" title="ثبت" aria-label="ثبت وضعیت سفارش <?= e($o['order_no']) ?>">💾</button>
            </form>
          </td>
          <td class="mono small"><?= e($o['tracking_code'] ?: '—') ?></td>
          <td>
            <div class="row-actions">
              <a class="btn btn-sm btn-primary" href="index.php?page=admin_order&id=<?= (int)$o['id'] ?>">پرونده سفارش</a>
              <?php if ($tid): ?>
                <a class="btn btn-icon" href="index.php?page=invoice&id=<?= e($tid) ?>" title="صورتحساب">🧾</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?>
        <tr><td colspan="8" class="empty-mini">سفارشی با این فیلترها یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table></div>

  <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination">
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('admin_orders', ['p' => $i])) ?>"><?= fa_num($i) ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>
