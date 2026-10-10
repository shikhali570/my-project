<?php
/** فیلدهای تماس مشترک فرم عمومی و فرم پنل خریدار */
$rfqDefaultCompany = !empty($me['company']) ? $me['company'] : ($me['name'] ?? '');
$rfqDefaultPhone = $me['phone'] ?? '';
$rfqDefaultEmail = $me['email'] ?? '';
?>
<div class="form-grid rfq-contact-grid">
  <div class="input-group">
    <label for="r-company">نام شرکت / پیمانکار <span class="req" aria-hidden="true">*</span></label>
    <input id="r-company" type="text" name="company" required maxlength="180" autocomplete="organization"
           placeholder="مثلاً شرکت مهندسی بناسازان"
           value="<?= e($fieldVal('company', $rfqDefaultCompany)) ?>"<?= field_invalid_attr($errs, 'company') ?>>
    <?= field_error($errs, 'company') ?>
  </div>
  <div class="input-group">
    <label for="r-phone">شماره همراه جهت ارتباط <span class="req" aria-hidden="true">*</span></label>
    <input id="r-phone" type="tel" name="phone" required maxlength="11" inputmode="tel" autocomplete="tel" dir="ltr"
           placeholder="09121111111"
           value="<?= e($fieldVal('phone', $rfqDefaultPhone)) ?>"<?= field_invalid_attr($errs, 'phone') ?>>
    <?= field_error($errs, 'phone') ?>
  </div>
  <div class="input-group">
    <label for="r-email">ایمیل <span class="req" aria-hidden="true">*</span></label>
    <input id="r-email" type="email" name="email" required maxlength="254" autocomplete="email" dir="ltr"
           placeholder="name@example.com"
           value="<?= e($fieldVal('email', $rfqDefaultEmail)) ?>"<?= field_invalid_attr($errs, 'email') ?>>
    <?= field_error($errs, 'email') ?>
  </div>
  <div class="input-group">
    <label for="r-messenger">پیام‌رسان پاسخگو <span class="muted">(اختیاری)</span></label>
    <input id="r-messenger" type="text" name="messenger" maxlength="120" autocomplete="off"
           placeholder="نام پیام‌رسان و شناسه یا شماره"
           value="<?= e($fieldVal('messenger', '')) ?>"<?= field_invalid_attr($errs, 'messenger') ?>>
    <?= field_error($errs, 'messenger') ?>
  </div>
</div>
