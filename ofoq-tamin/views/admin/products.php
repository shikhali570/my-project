<?php
/** مدیریت کالاها: فهرست، فیلتر، اصلاح سریع موجودی، فعال/غیرفعال، حذف */
require_admin();

$q = get('q');
$cat = get('cat');
$stockFilter = get('stock');
$activeFilter = get('active');
$sort = get('sort', 'newest');

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE ? OR brand LIKE ? OR tax_id LIKE ? OR sku LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($cat !== '') {
    $where[] = 'category = ?';
    $params[] = $cat;
}
if ($stockFilter === 'low') {
    $where[] = 'stock <= min_stock';
} elseif ($stockFilter === 'out') {
    $where[] = 'stock <= 0';
} elseif ($stockFilter === 'ok') {
    $where[] = 'stock > min_stock';
}
if ($activeFilter === '1' || $activeFilter === '0') {
    $where[] = 'is_active = ?';
    $params[] = (int)$activeFilter;
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$orderBy = ['newest' => 'id DESC', 'price_desc' => 'price DESC', 'price_asc' => 'price ASC', 'stock_asc' => 'stock ASC', 'sold' => 'sold DESC'][$sort] ?? 'id DESC';

$cnt = $db->prepare('SELECT COUNT(*) FROM products' . $sqlWhere);
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), 15, (int)get('p', 1));

$stmt = $db->prepare('SELECT * FROM products' . $sqlWhere . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$products = $stmt->fetchAll();

$totalValue = (int)$db->query('SELECT COALESCE(SUM(price * stock),0) FROM products')->fetchColumn();
$lowCount = (int)$db->query('SELECT COUNT(*) FROM products WHERE stock <= min_stock')->fetchColumn();
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">🏷️</span>
    <div><span>تعداد کل کالاها</span><strong><?= fa_num((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn()) ?> قلم</strong><small><?= fa_num($lowCount) ?> قلم نیازمند تأمین</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">📦</span>
    <div><span>ارزش موجودی انبار</span><strong><?= money_short($totalValue) ?> تومان</strong><small>بر اساس قیمت فروش</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">🔎</span>
    <div><span>نتیجه فیلتر جاری</span><strong><?= fa_num($pg['total']) ?> کالا</strong><small>صفحه <?= fa_num($pg['current']) ?> از <?= fa_num($pg['pages']) ?></small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico teal">⬇️</span>
    <div><span>خروجی گزارش</span><strong><a href="export.php?type=products">دریافت فایل CSV</a></strong><small>قابل باز کردن در اکسل</small></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h3 class="card-title">کاتالوگ کالاها</h3>
    <div class="flex-gap">
      <a class="btn btn-sm btn-secondary" href="index.php?page=admin_categories">🗂️ گروه‌های کالا</a>
      <a class="btn btn-sm btn-primary" href="index.php?page=admin_product_form">➕ کالای جدید</a>
    </div>
  </div>

  <form class="filter-bar wide" method="GET" action="index.php">
    <input type="hidden" name="page" value="admin_products">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="نام، برند، شناسه مالیاتی یا SKU…">
    <select name="cat">
      <option value="">همه گروه‌ها</option>
      <?php foreach (categories(false) as $c): ?>
        <option value="<?= e($c['slug']) ?>" <?= $cat === $c['slug'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="stock">
      <option value="">همه موجودی‌ها</option>
      <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>کم‌موجود (زیر حد هشدار)</option>
      <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>ناموجود</option>
      <option value="ok" <?= $stockFilter === 'ok' ? 'selected' : '' ?>>موجودی مطلوب</option>
    </select>
    <select name="active">
      <option value="">وضعیت نمایش</option>
      <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>>فعال در کاتالوگ</option>
      <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>غیرفعال</option>
    </select>
    <select name="sort">
      <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>جدیدترین</option>
      <option value="sold" <?= $sort === 'sold' ? 'selected' : '' ?>>پرفروش‌ترین</option>
      <option value="stock_asc" <?= $sort === 'stock_asc' ? 'selected' : '' ?>>کم‌ترین موجودی</option>
      <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>گران‌ترین</option>
      <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>ارزان‌ترین</option>
    </select>
    <button class="btn btn-sm btn-primary" type="submit">فیلتر</button>
    <a class="btn btn-sm btn-secondary" href="index.php?page=admin_products">بازنشانی</a>
  </form>

  <table class="data-table">
    <thead>
      <tr>
        <th>#</th><th>کالا</th><th>گروه / برند</th><th>شناسه مالیاتی</th>
        <th>قیمت فروش</th><th>موجودی</th><th>فروش</th><th>نمایش</th><th>عملیات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $p): $b = stock_badge($p); ?>
        <tr class="<?= (int)$p['is_active'] ? '' : 'row-muted' ?>">
          <td><?= fa_num($p['id']) ?></td>
          <td>
            <div class="cell-product">
              <span class="cell-ico"><?= e($p['icon']) ?></span>
              <div>
                <strong><?= e($p['name']) ?></strong>
                <div class="mini-note mono"><?= e($p['sku'] ?: '—') ?></div>
              </div>
            </div>
          </td>
          <td><?= e(category_title($p['category'])) ?><div class="mini-note"><?= e($p['brand']) ?></div></td>
          <td class="mono small"><?= e($p['tax_id']) ?></td>
          <td>
            <strong><?= money_short($p['price']) ?></strong>
            <?php if ($p['old_price']): ?><div class="mini-note"><del><?= fa_num($p['old_price']) ?></del></div><?php endif; ?>
          </td>
          <td>
            <form class="inline-form" method="POST" action="index.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="stock_update">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="redirect" value="<?= e(current_url()) ?>">
              <input type="number" class="qty-input" name="set" value="<?= (int)$p['stock'] ?>" min="0">
              <button class="btn btn-icon" type="submit" title="ثبت موجودی">💾</button>
            </form>
            <div class="mini-note"><span class="status <?= $b['class'] ?>"><?= e($b['text']) ?></span></div>
          </td>
          <td><?= fa_num($p['sold']) ?> <?= e($p['unit']) ?></td>
          <td>
            <form method="POST" action="index.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="product_toggle">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="redirect" value="<?= e(current_url()) ?>">
              <button class="btn btn-sm <?= (int)$p['is_active'] ? 'btn-green' : 'btn-secondary' ?>" type="submit">
                <?= (int)$p['is_active'] ? 'فعال' : 'غیرفعال' ?>
              </button>
            </form>
          </td>
          <td>
            <div class="row-actions">
              <a class="btn btn-icon" href="index.php?page=admin_product_form&id=<?= (int)$p['id'] ?>" title="ویرایش">✏️</a>
              <a class="btn btn-icon" href="<?= product_url($p['id']) ?>" target="_blank" title="مشاهده در فروشگاه">👁️</a>
              <button class="btn btn-icon danger" type="button" title="حذف"
                      data-confirm="کالای «<?= e($p['name']) ?>» حذف یا غیرفعال شود؟"
                      data-form="del-<?= (int)$p['id'] ?>">🗑️</button>
            </div>
            <form id="del-<?= (int)$p['id'] ?>" method="POST" action="index.php" class="hidden">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="product_delete">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?>
        <tr><td colspan="9" class="empty-mini">کالایی با این مشخصات یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination">
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('admin_products', ['p' => $i])) ?>"><?= fa_num($i) ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>
