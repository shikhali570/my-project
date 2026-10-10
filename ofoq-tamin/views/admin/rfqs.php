<?php
/** مدیریت استعلام‌های قیمت */
require_admin();
$status = get('status');
$q = get('q');

$where = [];
$params = [];
if ($status !== '' && isset(rfq_statuses()[$status])) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(rfq_code LIKE ? OR company LIKE ? OR phone LIKE ? OR email LIKE ? OR messenger LIKE ? OR title LIKE ? OR description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$cnt = $db->prepare('SELECT COUNT(*) FROM rfqs' . $sqlWhere);
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), 12, (int)get('p', 1));

$stmt = $db->prepare('SELECT * FROM rfqs' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$statusCounts = [];
foreach ($db->query('SELECT status, COUNT(*) c FROM rfqs GROUP BY status') as $r) {
    $statusCounts[$r['status']] = (int)$r['c'];
}
$quotedSum = (int)$db->query('SELECT COALESCE(SUM(quote_amount),0) FROM rfqs')->fetchColumn();
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">📋</span>
    <div><span>کل استعلام‌ها</span><strong><?= fa_num(array_sum($statusCounts)) ?> درخواست</strong><small><?= fa_num($statusCounts['new'] ?? 0) ?> پاسخ‌نداده</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">⏳</span>
    <div><span>در حال بررسی</span><strong><?= fa_num($statusCounts['reviewing'] ?? 0) ?> مورد</strong><small>نیازمند اعلام قیمت</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">✅</span>
    <div><span>قیمت‌گذاری‌شده / تبدیل به سفارش</span><strong><?= fa_num(($statusCounts['quoted'] ?? 0) + ($statusCounts['accepted'] ?? 0)) ?> مورد</strong><small><a href="index.php?page=admin_rfqs&status=quoted">مشاهده لیست</a></small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico purple">💰</span>
    <div><span>مجموع مبالغ پیشنهادی</span><strong><?= money_short($quotedSum) ?> تومان</strong><small>در انتظار تأیید خریدار</small></div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3 class="card-title">استعلام‌های قیمت پروژه (<?= fa_num($pg['total']) ?>)</h3></div>

  <form class="filter-bar wide" method="GET" action="index.php">
    <input type="hidden" name="page" value="admin_rfqs">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="کد استعلام، شرکت، تلفن، ایمیل یا شرح درخواست…">
    <select name="status">
      <option value="">همه وضعیت‌ها</option>
      <?php foreach (rfq_statuses() as $k => $label): ?>
        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($label) ?> (<?= fa_num($statusCounts[$k] ?? 0) ?>)</option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-primary" type="submit">فیلتر</button>
    <a class="btn btn-sm btn-secondary" href="index.php?page=admin_rfqs">بازنشانی</a>
    <a class="btn btn-sm btn-secondary" href="export.php?type=rfqs">⬇️ CSV</a>
  </form>

  <table class="data-table">
    <thead><tr><th>کد استعلام</th><th>شرکت / تلفن</th><th>عنوان درخواست</th><th>تاریخ</th><th>وضعیت</th><th>مبلغ پیشنهادی</th><th>عملیات</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong class="mono"><?= e($r['rfq_code']) ?></strong></td>
          <td>
            <?= e($r['company']) ?>
            <div class="mini-note mono"><?= e($r['phone']) ?></div>
            <?php if (!empty($r['email'])): ?><div class="mini-note"><?= e($r['email']) ?></div><?php endif; ?>
            <?php if (!empty($r['messenger'])): ?><div class="mini-note">پیام‌رسان: <?= e($r['messenger']) ?></div><?php endif; ?>
          </td>
          <td>
            <?= e($r['title'] ?: '—') ?>
            <div class="mini-note"><?= e(mb_substr($r['description'], 0, 70)) ?>…</div>
          </td>
          <td><?= jdate($r['created_at']) ?><div class="mini-note"><?= time_ago($r['created_at']) ?></div></td>
          <td><span class="status <?= rfq_status_class($r['status']) ?>"><?= e(rfq_status_label($r['status'])) ?></span></td>
          <td><?= $r['quote_amount'] ? money_short($r['quote_amount']) : '—' ?></td>
          <td>
            <div class="row-actions">
              <a class="btn btn-sm btn-primary" href="index.php?page=admin_rfq&id=<?= (int)$r['id'] ?>">پاسخ / قیمت‌گذاری</a>
              <button class="btn btn-icon danger" type="button" title="حذف"
                      data-confirm="استعلام <?= e($r['rfq_code']) ?> حذف شود؟"
                      data-form="delr-<?= (int)$r['id'] ?>">🗑️</button>
              <form id="delr-<?= (int)$r['id'] ?>" method="POST" action="index.php" class="hidden">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rfq_delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="empty-mini">استعلامی با این فیلترها یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination">
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('admin_rfqs', ['p' => $i])) ?>"><?= fa_num($i) ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>
