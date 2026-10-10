<?php
/** اقلام تکرارشوندهٔ مشترک در فرم عمومی و پنل خریدار */
$rfqItemRows = isset($rfqItemRows) && is_array($rfqItemRows) ? $rfqItemRows : [];
if (!$rfqItemRows) {
    $rfqItemRows = [['description' => '', 'quantity' => '', 'item_code' => '', 'category' => '']];
}
$rfqCategories = categories();
$nextRfqItemIndex = count($rfqItemRows);
?>
<fieldset class="rfq-items-fieldset<?= empty($errs['items']) ? '' : ' is-invalid' ?>" data-rfq-items data-max-items="<?= (int)RFQ_MAX_ITEMS ?>" aria-describedby="rfq-items-hint<?= empty($errs['items']) ? '' : ' fe-items' ?>"<?= empty($errs['items']) ? '' : ' aria-invalid="true"' ?>>
  <legend>اقلام موردنیاز <span class="req" aria-hidden="true">*</span></legend>
  <p class="field-hint" id="rfq-items-hint">برای هر قلم، شرح، مقدار و دسته‌بندی را تکمیل کنید؛ شناسهٔ کالا اختیاری است.</p>
  <?= field_error($errs, 'items') ?>
  <div class="rfq-item-list" data-rfq-item-list data-next-index="<?= (int)$nextRfqItemIndex ?>">
    <?php foreach ($rfqItemRows as $index => $item): ?>
      <?php $isRfqTemplate = false; require __DIR__ . '/rfq_item_row.php'; ?>
    <?php endforeach; ?>
  </div>
  <template data-rfq-item-template>
    <?php $index = '__INDEX__'; $item = []; $isRfqTemplate = true; require __DIR__ . '/rfq_item_row.php'; ?>
  </template>
  <button class="btn btn-secondary btn-sm rfq-item__add" type="button" data-rfq-add-item>＋ افزودن قلم</button>
</fieldset>
