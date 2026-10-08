<?php
/** فرم افزودن / ویرایش کالا */
require_admin();
$id = (int)get('id');
$p = [
    'id' => 0, 'sku' => '', 'name' => '', 'category' => '', 'brand' => '', 'price' => '',
    'old_price' => '', 'tax_id' => '', 'unit' => 'عدد', 'stock' => 0, 'min_stock' => 5,
    'icon' => '📦', 'image' => '', 'description' => '', 'specs' => '', 'is_active' => 1,
];

if ($id) {
    $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        echo '<div class="alert danger">کالای مورد نظر یافت نشد.</div>';
        return;
    }
    $p = $row;
}
$isEdit = (bool)$id;
$icons = ['📦', '📏', '📐', '🔭', '⛑️', '🦺', '🥾', '🖨️', '📜', '🖋️', '📁', '🗂️', '💡', '🔌', '🔧', '🛠️', '🧰', '🧱', '⚙️', '🔩'];
?>

<div class="detail-head">
  <div>
    <h2 class="sec-title"><?= $isEdit ? 'ویرایش کالا' : 'افزودن کالای جدید به کاتالوگ' ?></h2>
    <p class="sec-sub"><?= $isEdit ? 'شناسه کالا: ' . fa_num($p['id']) : 'پس از ذخیره، کالا در کاتالوگ فروشگاه نمایش داده می‌شود.' ?></p>
  </div>
  <a class="btn btn-secondary btn-sm" href="index.php?page=admin_products">← بازگشت به فهرست</a>
</div>

<form method="POST" action="index.php" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="product_save">
  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

  <div class="form-section">
    <h3 class="card-title">اطلاعات پایه</h3>
    <div class="grid-2">
      <div class="input-group">
        <label>نام کامل کالا *</label>
        <input type="text" name="name" required value="<?= e($p['name']) ?>" placeholder="متر لیزری ۱۰۰ متری لایکا Disto D2">
      </div>
      <div class="input-group">
        <label>برند / سازنده *</label>
        <input type="text" name="brand" required value="<?= e($p['brand']) ?>" placeholder="Leica">
      </div>
    </div>
    <div class="grid-3">
      <div class="input-group">
        <label>گروه کالا *</label>
        <select name="category" required>
          <option value="">انتخاب کنید</option>
          <?php foreach (categories(false) as $c): ?>
            <option value="<?= e($c['slug']) ?>" <?= $p['category'] === $c['slug'] ? 'selected' : '' ?>><?= e($c['icon']) ?> <?= e($c['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="input-group">
        <label>کد کالا (SKU)</label>
        <input type="text" name="sku" value="<?= e($p['sku']) ?>" placeholder="SKU-1001 (خالی = تولید خودکار)">
      </div>
      <div class="input-group">
        <label>واحد فروش</label>
        <input type="text" name="unit" value="<?= e($p['unit']) ?>" placeholder="عدد / بسته / حلقه / متر">
      </div>
    </div>
  </div>

  <div class="form-section">
    <h3 class="card-title">قیمت و انبار</h3>
    <div class="grid-3">
      <div class="input-group">
        <label>قیمت فروش (تومان) *</label>
        <input type="number" name="price" required min="0" value="<?= (int)$p['price'] ?>">
      </div>
      <div class="input-group">
        <label>قیمت قبل از تخفیف (اختیاری)</label>
        <input type="number" name="old_price" min="0" value="<?= (int)$p['old_price'] ?>">
      </div>
      <div class="input-group">
        <label>شناسه کالای سامانه مؤدیان (IRK) *</label>
        <input type="text" name="tax_id" required value="<?= e($p['tax_id']) ?>" placeholder="2710000185962" class="mono">
      </div>
    </div>
    <div class="grid-3">
      <div class="input-group">
        <label>موجودی انبار</label>
        <input type="number" name="stock" min="0" value="<?= (int)$p['stock'] ?>">
      </div>
      <div class="input-group">
        <label>حد هشدار موجودی</label>
        <input type="number" name="min_stock" min="0" value="<?= (int)$p['min_stock'] ?>">
      </div>
      <div class="input-group">
        <label>وضعیت نمایش در کاتالوگ</label>
        <select name="is_active">
          <option value="1" <?= (int)$p['is_active'] ? 'selected' : '' ?>>فعال (قابل خرید)</option>
          <option value="0" <?= !(int)$p['is_active'] ? 'selected' : '' ?>>غیرفعال (پنهان از خریدار)</option>
        </select>
      </div>
    </div>
  </div>

  <div class="form-section">
    <h3 class="card-title">تصویر و توضیحات</h3>
    <div class="grid-2">
      <div class="input-group">
        <label>نشانی تصویر کالا (URL — اختیاری)</label>
        <input type="text" name="image" value="<?= e($p['image']) ?>" placeholder="https://example.com/product.jpg">
      </div>
      <div class="input-group">
        <label>آیکن کالا (در صورت نبود تصویر)</label>
        <div class="icon-picker">
          <?php foreach ($icons as $ic): ?>
            <label class="icon-opt">
              <input type="radio" name="icon" value="<?= e($ic) ?>" <?= $p['icon'] === $ic ? 'checked' : '' ?>>
              <span><?= e($ic) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="input-group">
      <label>توضیحات کالا</label>
      <textarea name="description" rows="4" placeholder="توضیح فنی و کاربرد کالا برای خریداران سازمانی…"><?= e($p['description']) ?></textarea>
    </div>
    <div class="input-group">
      <label>مشخصات فنی (هر مشخصه با علامت | جدا شود)</label>
      <textarea name="specs" rows="3" placeholder="برد: ۱۰۰ متر | دقت: ±۱.۵mm | باتری: لیتیوم‌یونی"><?= e($p['specs']) ?></textarea>
      <small class="mini-note">نمونه: «عرض: ۹۰ سانتی‌متر | طول: ۵۰ متر | گراماژ: ۸۰ گرم»</small>
    </div>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary btn-lg" type="submit"><?= $isEdit ? '💾 ذخیره تغییرات' : '➕ افزودن کالا' ?></button>
    <a class="btn btn-secondary" href="index.php?page=admin_products">انصراف</a>
    <?php if ($isEdit): ?>
      <a class="btn btn-outline" href="<?= product_url($p['id']) ?>" target="_blank">مشاهده در فروشگاه</a>
    <?php endif; ?>
  </div>
</form>
