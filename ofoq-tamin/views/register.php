<?php
/** ثبت‌نام خریدار حقیقی و حقوقی */
$old = $_SESSION['old_register'] ?? [];
unset($_SESSION['old_register']);
$entityType = in_array(($old['entity_type'] ?? ''), ['individual', 'legal'], true) ? $old['entity_type'] : '';
$provinces = ['تهران', 'البرز', 'اصفهان', 'خراسان رضوی', 'فارس', 'آذربایجان شرقی', 'گیلان', 'مازندران', 'خوزستان', 'کرمان', 'یزد', 'قم', 'هرمزگان', 'سایر استان‌ها'];
$registerBenefits = site_content_items('register_benefits', [
    '🧾 صورتحساب الکترونیکی معتبر و قابل استناد در ممیزی مالیاتی',
    '🚚 ارسال به کارگاه‌های پروژه در سراسر کشور',
    '🤝 مشاور فنی تأمین کالا داریم',
]);
?>
<div class="auth-layout">
  <div class="auth-card-full">
    <div class="auth-head">
      <div class="logo-icon" aria-hidden="true">📝</div>
      <div>
        <h1><?= e(site_content('register_heading', 'ثبت‌نام خریدار حقیقی یا حقوقی')) ?></h1>
        <p><?= nl2br(e(site_content('register_intro', 'اطلاعات هویتی و نشانی لازم برای ثبت خریدار و صدور صورتحساب الکترونیکی را وارد کنید.'))) ?></p>
      </div>
    </div>

    <form method="POST" action="index.php" class="auth-form" data-register-form>
      <?= csrf_field() ?>
      <input type="hidden" name="auth_action" value="register">
      <p class="form-legend"><span class="req" aria-hidden="true">*</span> فیلدهای الزامی · گذرواژه حداقل ۶ کاراکتر</p>

      <fieldset class="buyer-type-select">
        <legend>نوع خریدار برای صورتحساب <span class="req" aria-hidden="true">*</span></legend>
        <div class="buyer-type-options">
          <label class="buyer-type-option<?= $entityType === 'individual' ? ' selected' : '' ?>" for="g-type-individual">
            <input id="g-type-individual" type="radio" name="entity_type" value="individual" required<?= $entityType === 'individual' ? ' checked' : '' ?>>
            <span class="buyer-type-option__icon" aria-hidden="true">👤</span>
            <span class="buyer-type-option__text"><strong>شخص حقیقی</strong><small>صورتحساب به نام شخص</small></span>
          </label>
          <label class="buyer-type-option<?= $entityType === 'legal' ? ' selected' : '' ?>" for="g-type-legal">
            <input id="g-type-legal" type="radio" name="entity_type" value="legal" required<?= $entityType === 'legal' ? ' checked' : '' ?>>
            <span class="buyer-type-option__icon" aria-hidden="true">🏢</span>
            <span class="buyer-type-option__text"><strong>شخص حقوقی</strong><small>صورتحساب به نام شرکت یا مؤسسه</small></span>
          </label>
        </div>
        <small class="field-hint" id="g-type-hint" data-register-type-hint<?= $entityType !== '' ? ' hidden' : '' ?>>برای نمایش فیلدهای متناسب با صورتحساب، نوع خریدار را انتخاب کنید.</small>
      </fieldset>

      <div class="grid-2 buyer-type-fields" data-register-entity-fields="legal"<?= $entityType !== 'legal' ? ' hidden' : '' ?>>
        <div class="input-group">
          <label for="g-company">نام کامل شرکت / مؤسسه <span class="req" aria-hidden="true">*</span></label>
          <input id="g-company" type="text" name="company" autocomplete="organization" value="<?= e($old['company'] ?? '') ?>" placeholder="نام ثبت‌شده شرکت" data-required-for="legal"<?= $entityType === 'legal' ? ' required' : '' ?>>
        </div>
        <div class="input-group">
          <label for="g-economic">کد اقتصادی <span class="muted">(در صورت وجود)</span></label>
          <input id="g-economic" type="text" name="economic_code" inputmode="numeric" autocomplete="off" maxlength="20" dir="ltr" value="<?= e($old['economic_code'] ?? '') ?>" placeholder="کد اقتصادی شرکت">
        </div>
      </div>

      <div class="grid-2">
        <div class="input-group">
          <label for="g-name"><span data-register-contact-label><?= $entityType === 'legal' ? 'نام و نام خانوادگی رابط / نماینده' : 'نام و نام خانوادگی' ?></span> <span class="req" aria-hidden="true">*</span></label>
          <input id="g-name" type="text" name="name" required autocomplete="name" value="<?= e($old['name'] ?? '') ?>" placeholder="نام و نام خانوادگی">
        </div>
        <div class="input-group">
          <label for="g-national">کد ملی / شناسه ملی <span class="req" aria-hidden="true">*</span></label>
          <input id="g-national" type="text" name="national_id" required maxlength="11" inputmode="numeric" autocomplete="off" data-register-national-id value="<?= e($old['national_id'] ?? '') ?>" placeholder="طبق نوع خریدار">
          <small class="field-hint" id="g-national-hint" data-register-national-hint>شخص حقیقی: کد ملی ۱۰ رقم؛ شخص حقوقی: شناسه ملی ۱۱ رقم.</small>
        </div>
      </div>

      <div class="grid-2">
        <div class="input-group">
          <label for="g-phone">شماره همراه <span class="req" aria-hidden="true">*</span></label>
          <input id="g-phone" type="tel" name="phone" required maxlength="11" inputmode="numeric" autocomplete="tel" dir="ltr" value="<?= e($old['phone'] ?? '') ?>" placeholder="09121111111">
        </div>
        <div class="input-group">
          <label for="g-email">ایمیل <span class="muted">(اختیاری)</span></label>
          <input id="g-email" type="email" name="email" autocomplete="email" dir="ltr" value="<?= e($old['email'] ?? '') ?>" placeholder="name@example.ir">
        </div>
      </div>

      <div class="grid-2">
        <div class="input-group">
          <label for="g-postal">کد پستی محل صدور صورتحساب <span class="req" aria-hidden="true">*</span></label>
          <input id="g-postal" type="text" name="postal_code" required maxlength="10" inputmode="numeric" autocomplete="postal-code" dir="ltr" value="<?= e($old['postal_code'] ?? '') ?>" placeholder="۱۰ رقم">
        </div>
        <div class="input-group">
          <label for="g-province">استان <span class="req" aria-hidden="true">*</span></label>
          <select id="g-province" name="province" required autocomplete="address-level1">
            <option value="" <?= empty($old['province']) ? 'selected' : '' ?>>انتخاب کنید</option>
            <?php foreach ($provinces as $pv): ?>
              <option value="<?= e($pv) ?>" <?= ($old['province'] ?? '') === $pv ? 'selected' : '' ?>><?= e($pv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid-2">
        <div class="input-group">
          <label for="g-city">شهر <span class="req" aria-hidden="true">*</span></label>
          <input id="g-city" type="text" name="city" required autocomplete="address-level2" value="<?= e($old['city'] ?? '') ?>">
        </div>
        <div class="input-group">
          <label for="g-address">نشانی کامل محل صدور صورتحساب <span class="req" aria-hidden="true">*</span></label>
          <input id="g-address" type="text" name="address" required autocomplete="street-address" value="<?= e($old['address'] ?? '') ?>" placeholder="خیابان، کوچه، پلاک و واحد">
        </div>
      </div>

      <div class="grid-2">
        <div class="input-group">
          <label for="g-pass">گذرواژه <span class="req" aria-hidden="true">*</span></label>
          <input id="g-pass" type="password" name="password" required minlength="6" autocomplete="new-password">
        </div>
        <div class="input-group">
          <label for="g-pass2">تکرار گذرواژه <span class="req" aria-hidden="true">*</span></label>
          <input id="g-pass2" type="password" name="password2" required minlength="6" autocomplete="new-password">
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg btn-block">ایجاد حساب خریدار</button>

      <div class="auth-links">
        <span>قبلاً ثبت‌نام کرده‌اید؟ <a href="index.php?page=login">ورود به حساب</a></span>
      </div>
    </form>
  </div>

  <aside class="auth-side">
    <h2><?= e(site_content('register_side_title', 'چرا از ' . settings('site_name') . ' خرید کنید؟')) ?></h2>
    <ul class="feature-list">
      <?php foreach ($registerBenefits as $benefit): ?><li><?= e($benefit) ?></li><?php endforeach; ?>
    </ul>
    <div class="side-note">
      <?= nl2br(e(site_content('register_privacy_note', 'اطلاعات کارپوشه شما فقط برای صدور اسناد رسمی استفاده می‌شود و در اختیار شخص ثالث قرار نمی‌گیرد.'))) ?>
    </div>
  </aside>
</div>
