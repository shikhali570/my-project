<?php
/** مدیریت کاربران و مشتریان سازمانی */
require_admin();

$q = get('q');
$role = get('role');
$status = get('status');

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE ? OR company LIKE ? OR phone LIKE ? OR email LIKE ? OR national_id LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($role === 'admin' || $role === 'buyer') {
    $where[] = 'role = ?';
    $params[] = $role;
}
if ($status === 'active' || $status === 'inactive') {
    $where[] = 'status = ?';
    $params[] = $status;
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$cnt = $db->prepare('SELECT COUNT(*) FROM users' . $sqlWhere);
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), 12, (int)get('p', 1));

$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders_count,
        (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.user_id = u.id AND o.payment_status = 'paid' AND o.status != 'canceled') AS paid_sum
        FROM users u" . $sqlWhere . ' ORDER BY u.id DESC LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset'];
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$roleCounts = $db->query("SELECT role, COUNT(*) c FROM users GROUP BY role")->fetchAll();
$counts = [];
foreach ($roleCounts as $r) {
    $counts[$r['role']] = (int)$r['c'];
}
$credits = (int)$db->query('SELECT COALESCE(SUM(credit),0) FROM users')->fetchColumn();
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">👥</span>
    <div><span>کل کاربران</span><strong><?= fa_num(array_sum($counts)) ?> حساب</strong><small><?= fa_num($counts['buyer'] ?? 0) ?> خریدار + <?= fa_num($counts['admin'] ?? 0) ?> مدیر</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">✅</span>
    <div><span>حساب‌های فعال</span><strong><?= fa_num((int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn()) ?></strong><small>امکان ورود و ثبت سفارش</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">🎁</span>
    <div><span>مجموع اعتبار سازمانی اعطاشده</span><strong><?= money_short($credits) ?> تومان</strong><small>قابل استفاده در سفارش‌ها</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico purple">➕</span>
    <div><span>ایجاد کاربر جدید</span><strong><a href="index.php?page=admin_user_form">افزودن خریدار</a></strong><small>با تعیین گذرواژه اولیه</small></div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3 class="card-title">فهرست کاربران (<?= fa_num($pg['total']) ?>)</h3></div>

  <form class="filter-bar wide" method="GET" action="index.php">
    <input type="hidden" name="page" value="admin_users">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="نام، شرکت، تلفن، ایمیل یا شناسه ملی…">
    <select name="role">
      <option value="">همه نقش‌ها</option>
      <option value="buyer" <?= $role === 'buyer' ? 'selected' : '' ?>>خریدار</option>
      <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>مدیر سیستم</option>
    </select>
    <select name="status">
      <option value="">همه وضعیت‌ها</option>
      <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>فعال</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
    </select>
    <button class="btn btn-sm btn-primary" type="submit">فیلتر</button>
    <a class="btn btn-sm btn-secondary" href="index.php?page=admin_users">بازنشانی</a>
    <a class="btn btn-sm btn-secondary" href="export.php?type=users">⬇️ CSV</a>
  </form>

  <table class="data-table">
    <thead>
      <tr><th>#</th><th>کاربر</th><th>نقش</th><th>تماس</th><th>شناسه ملی / اقتصادی</th><th>سفارش‌ها</th><th>خرید تأییدشده</th><th>اعتبار</th><th>وضعیت</th><th>عملیات</th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= fa_num($u['id']) ?></td>
          <td>
            <div class="cell-product">
              <span class="cell-ico"><?= e(initials($u['company'] ?: $u['name'])) ?></span>
              <div>
                <strong><?= e($u['company'] ?: $u['name']) ?></strong>
                <div class="mini-note"><?= e($u['name']) ?><?= $u['city'] ? ' — ' . e($u['city']) : '' ?></div>
              </div>
            </div>
          </td>
          <td>
            <span class="pill <?= $u['role'] === 'admin' ? 'primary' : 'success' ?>"><?= $u['role'] === 'admin' ? 'مدیر سیستم' : 'خریدار' ?></span>
          </td>
          <td class="mono small"><?= e($u['phone']) ?><div class="mini-note"><?= e($u['email'] ?: '—') ?></div></td>
          <td class="mono small"><?= e($u['national_id'] ?: '—') ?><div class="mini-note"><?= e($u['economic_code'] ?: '—') ?></div></td>
          <td><?= fa_num($u['orders_count']) ?> سفارش</td>
          <td><?= money_short($u['paid_sum']) ?></td>
          <td><?= money_short($u['credit']) ?></td>
          <td><span class="status <?= $u['status'] === 'active' ? 'success' : 'danger' ?>"><?= $u['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></span></td>
          <td>
            <div class="row-actions">
              <a class="btn btn-icon" href="index.php?page=admin_user&id=<?= (int)$u['id'] ?>" title="پرونده مشتری">📂</a>
              <a class="btn btn-icon" href="index.php?page=admin_user_form&id=<?= (int)$u['id'] ?>" title="ویرایش">✏️</a>
              <?php if ($u['role'] !== 'admin'): ?>
                <form class="inline-form" method="POST" action="index.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="user_toggle">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn btn-icon" type="submit" title="<?= $u['status'] === 'active' ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?>">
                    <?= $u['status'] === 'active' ? '🚫' : '✅' ?>
                  </button>
                </form>
                <button class="btn btn-icon danger" type="button" title="حذف"
                        data-confirm="حساب «<?= e($u['name']) ?>» حذف یا غیرفعال شود؟"
                        data-form="delu-<?= (int)$u['id'] ?>">🗑️</button>
                <form id="delu-<?= (int)$u['id'] ?>" method="POST" action="index.php" class="hidden">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="user_delete">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?>
        <tr><td colspan="10" class="empty-mini">کاربری با این مشخصات یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination">
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('admin_users', ['p' => $i])) ?>"><?= fa_num($i) ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>
