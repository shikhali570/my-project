<div class="input-group rfq-attachment-field">
  <label for="rfq-attachments">پیوست مدارک <span class="muted">(اختیاری)</span></label>
  <input id="rfq-attachments" type="file" name="attachments[]" multiple
         accept=".jpg,.jpeg,.png,.xls,.xlsx,.pdf,image/jpeg,image/png,application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"<?= field_invalid_attr($errs, 'attachments', 'rfq-attachments-hint') ?>>
  <?= field_error($errs, 'attachments') ?>
  <small class="field-hint" id="rfq-attachments-hint">JPEG/JPG، PNG، Excel یا PDF؛ حداکثر ۵ فایل، هر فایل تا ۵ مگابایت و مجموع تا ۱۰ مگابایت. پیوست‌ها خصوصی هستند؛ مدیر یا صاحب استعلام می‌تواند دریافتشان کند. برای استعلام مهمان، دریافت از همان نشست مرورگر امکان‌پذیر است.</small>
</div>
