<?php
/** گزارش رویدادهای سیستم (Audit Log) */
require_admin();
$q = get('q');
$action = get('action_filter');
$userId = (int)get('user');

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(details LIKE ? OR actor_name LIKE ? OR entity LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($action !== '') {
    $where[] = 'action = ?';
    $params[] = $action;
}
if ($userId) {
    $where[] = 'user_id = ?';
    $params[] = $userId;
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$cnt = $db->prepare('SELECT COUNT(*) FROM activity_logs' . $sqlWhere);
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), 25, (int)get('p', 1));

$stmt = $db->prepare('SELECT * FROM activity_logs' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$actions = $db->query('SELECT action, COUNT(*) c FROM activity_logs GROUP BY action ORDER BY c DESC LIMIT 20')->fetchAll();
$todayCount = (int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE date(created_at) = date('now')")->fetchColumn();
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">🛡️</span>
    <div><span>کل رویدادهای ثبت‌شده</span><strong><?= fa_num((int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()) ?> رویداد</strong><small>امروز: <?= fa_num($todayCount) ?> رویداد</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">⚠️</span>
    <div><span>تلاش‌های ناموفق ورود</span><strong><?= fa_num((int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login_failed'")->fetchColumn()) ?></strong><small>پایش امنیت حساب‌ها</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">🧾</span>
    <div><span>اسناد صادرشده در گزارش</span><strong><?= fa_num((int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE action IN ('invoice_issue','order_create')")->fetchColumn()) ?></strong><small>ثبت سفارش و صورتحساب</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico red">🧹</span>
    <div>
      <span>پاک‌سازی گزارش‌های قدیمی</span>
      <form class="inline-form" method="POST" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="logs_clear">
        <input type="number" name="days" value="90" min="1" style="width:80px">
        <button class="btn btn-sm btn-secondary" type="submit">روز قبل حذف شود</button>
      </form>
      <small>برای سبک‌سازی پایگاه داده</small>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3 class="card-title">گزارش رویدادها (<?= fa_num($pg['total']) ?>)</h3></div>

  <form class="filter-bar wide" method="GET" action="index.php">
    <input type="hidden" name="page" value="admin_logs">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="جست‌وجو در جزئیات، کاربر یا موجودیت…">
    <select name="action_filter">
      <option value="">همه عملیات‌ها</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= e($a['action']) ?>" <?= $action === $a['action'] ? 'selected' : '' ?>><?= e($a['action']) ?> (<?= fa_num($a['c']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-primary" type="submit">فیلتر</button>
    <a class="btn btn-sm btn-secondary" href="index.php?page=admin_logs">بازنشانی</a>
  </form>

  <table class="data-table">
    <thead><tr><th>#</th><th>کاربر</th><th>عملیات</th><th>موجودیت</th><th>جزئیات</th><th>IP</th><th>زمان</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $l): ?>
        <tr>
          <td><?= fa_num($l['id']) ?></td>
          <td><?= e($l['actor_name']) ?><?= $l['user_id'] ? '<div class="mini-note">#' . fa_num($l['user_id']) . '</div>' : '' ?></td>
          <td><span class="pill muted mono"><?= e($l['action']) ?></span></td>
          <td class="small"><?= e($l['entity'] ?: '—') ?><?= $l['entity_id'] ? ' #' . fa_num($l['entity_id']) : '' ?></td>
          <td><?= e($l['details']) ?></td>
          <td class="mono small"><?= e($l['ip']) ?></td>
          <td><?= jdate($l['created_at'], true) ?><div class="mini-note"><?= time_ago($l['created_at']) ?></div></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="empty-mini">رویدادی با این فیلترها یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination">
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('admin_logs', ['p' => $i])) ?>"><?= fa_num($i) ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>
