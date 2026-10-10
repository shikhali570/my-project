<?php
$item = is_array($item ?? null) ? $item : [];
$index = $index ?? 0;
$isRfqTemplate = !empty($isRfqTemplate);
$descriptionId = 'rfq-item-' . $index . '-description';
$quantityId = 'rfq-item-' . $index . '-quantity';
$itemCodeId = 'rfq-item-' . $index . '-code';
$categoryId = 'rfq-item-' . $index . '-category';
?>
<article class="rfq-item" data-rfq-item>
  <div class="rfq-item__head">
    <strong>قلم <span data-rfq-item-number><?= $isRfqTemplate ? '' : fa_num((int)$index + 1) ?></span></strong>
    <button class="rfq-item__remove" type="button" data-rfq-remove-item hidden aria-label="حذف این قلم">حذف قلم ×</button>
  </div>
  <div class="rfq-item__grid">
    <div class="input-group rfq-item__description">
      <label for="<?= e($descriptionId) ?>">شرح قلم <span class="req" aria-hidden="true">*</span></label>
      <textarea id="<?= e($descriptionId) ?>" name="items[<?= e($index) ?>][description]" rows="2" required maxlength="500"
                placeholder="نام کالا و مشخصات موردنیاز"><?= e($item['description'] ?? '') ?></textarea>
    </div>
    <div class="input-group">
      <label for="<?= e($quantityId) ?>">مقدار <span class="req" aria-hidden="true">*</span></label>
      <input id="<?= e($quantityId) ?>" type="number" name="items[<?= e($index) ?>][quantity]" required min="0.001" max="1000000000" step="0.001" inputmode="decimal"
             placeholder="مثلاً ۱۰" value="<?= e($item['quantity'] ?? '') ?>">
    </div>
    <div class="input-group">
      <label for="<?= e($itemCodeId) ?>">شناسه کالا <span class="muted">(اختیاری)</span></label>
      <input id="<?= e($itemCodeId) ?>" type="text" name="items[<?= e($index) ?>][item_code]" maxlength="120"
             placeholder="کد یا شناسه درج‌شده روی کالا"
             value="<?= e($item['item_code'] ?? '') ?>">
    </div>
    <div class="input-group">
      <label for="<?= e($categoryId) ?>">دسته‌بندی <span class="req" aria-hidden="true">*</span></label>
      <select id="<?= e($categoryId) ?>" name="items[<?= e($index) ?>][category]" required>
        <option value="">انتخاب دسته‌بندی</option>
        <?php foreach ($rfqCategories as $category): ?>
          <option value="<?= e($category['slug']) ?>" <?= (string)($item['category'] ?? '') === (string)$category['slug'] ? 'selected' : '' ?>><?= e($category['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</article>
