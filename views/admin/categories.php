<?php
/** مدیریت گروه‌های کالا */
require_admin();
$editId = (int)get('edit');
$edit = ['id' => 0, 'slug' => '', 'title' => '', 'icon' => '📦', 'sort_order' => 0, 'is_active' => 1];
if ($editId) {
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: $edit;
}
$rows = $db->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category = c.slug) AS product_count
                    FROM categories c ORDER BY c.sort_order, c.id')->fetchAll();
?>

<div class="admin-grid-2">
  <div class="card">
    <div class="card-head">
      <h3 class="card-title"><?= $editId ? 'ویرایش گروه کالا' : 'افزودن گروه کالای جدید' ?></h3>
      <?php if ($editId): ?><a class="link-more" href="index.php?page=admin_categories">لغو ویرایش</a><?php endif; ?>
    </div>
    <form method="POST" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="category_save">
      <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
      <div class="input-group">
        <label>عنوان گروه (فارسی) *</label>
        <input type="text" name="title" required value="<?= e($edit['title']) ?>" placeholder="تجهیزات برقی و روشنایی">
      </div>
      <div class="grid-3">
        <div class="input-group">
          <label>شناسه لاتین (slug) *</label>
          <input type="text" name="slug" required value="<?= e($edit['slug']) ?>" placeholder="electrical" class="mono">
        </div>
        <div class="input-group">
          <label>آیکن</label>
          <input type="text" name="icon" value="<?= e($edit['icon']) ?>" placeholder="💡">
        </div>
        <div class="input-group">
          <label>ترتیب نمایش</label>
          <input type="number" name="sort_order" value="<?= (int)$edit['sort_order'] ?>">
        </div>
      </div>
      <div class="input-group">
        <label>وضعیت</label>
        <select name="is_active">
          <option value="1" <?= (int)$edit['is_active'] ? 'selected' : '' ?>>فعال در منوی فروشگاه</option>
          <option value="0" <?= !(int)$edit['is_active'] ? 'selected' : '' ?>>غیرفعال</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit"><?= $editId ? 'ذخیره تغییرات' : 'ایجاد گروه کالا' ?></button>
    </form>
  </div>

  <div class="card">
    <h3 class="card-title">راهنمای گروه‌بندی</h3>
    <ul class="feature-list">
      <li>شناسه لاتین در نشانی صفحه استفاده می‌شود؛ فقط حروف انگلیسی، عدد، خط تیره و زیرخط.</li>
      <li>گروه دارای کالا قابل حذف نیست؛ ابتدا کالاها را به گروه دیگر منتقل کنید.</li>
      <li>گروه‌های غیرفعال در منوی فروشگاه نمایش داده نمی‌شوند اما کالاهای آن‌ها در جست‌وجو باقی می‌مانند.</li>
      <li>ترتیب نمایش با اعداد کوچک‌تر بالاتر قرار می‌گیرد.</li>
    </ul>
    <div class="alert info">تعداد گروه‌های ثبت‌شده: <strong><?= fa_num(count($rows)) ?></strong></div>
  </div>
</div>

<div class="card">
  <h3 class="card-title">گروه‌های کالا</h3>
  <table class="data-table">
    <thead><tr><th>#</th><th>عنوان</th><th>شناسه لاتین</th><th>تعداد کالا</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $c): ?>
        <tr>
          <td><?= fa_num($c['id']) ?></td>
          <td><?= e($c['icon']) ?> <strong><?= e($c['title']) ?></strong></td>
          <td class="mono"><?= e($c['slug']) ?></td>
          <td><a href="index.php?page=admin_products&cat=<?= e($c['slug']) ?>"><?= fa_num($c['product_count']) ?> کالا</a></td>
          <td><?= fa_num($c['sort_order']) ?></td>
          <td><span class="status <?= (int)$c['is_active'] ? 'success' : 'muted' ?>"><?= (int)$c['is_active'] ? 'فعال' : 'غیرفعال' ?></span></td>
          <td>
            <div class="row-actions">
              <a class="btn btn-icon" href="index.php?page=admin_categories&edit=<?= (int)$c['id'] ?>" title="ویرایش">✏️</a>
              <button class="btn btn-icon danger" type="button" title="حذف"
                      data-confirm="گروه «<?= e($c['title']) ?>» حذف شود؟"
                      data-form="delcat-<?= (int)$c['id'] ?>">🗑️</button>
              <form id="delcat-<?= (int)$c['id'] ?>" method="POST" action="index.php" class="hidden">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="category_delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
