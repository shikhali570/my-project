<?php
/** ثبت‌نام خریدار سازمانی */
$old = $_SESSION['old_register'] ?? [];
unset($_SESSION['old_register']);
$provinces = ['تهران', 'البرز', 'اصفهان', 'خراسان رضوی', 'فارس', 'آذربایجان شرقی', 'گیلان', 'مازندران', 'خوزستان', 'کرمان', 'یزد', 'قم', 'هرمزگان', 'سایر استان‌ها'];
?>
<div class="auth-layout">
  <div class="auth-card-full">
    <div class="auth-head">
      <div class="logo-icon" aria-hidden="true">📝</div>
      <div>
        <h1>ثبت‌نام خریدار سازمانی</h1>
        <p>با تکمیل کارپوشه، امکان دریافت پیش‌فاکتور رسمی، تسویه اعتباری و پیگیری سفارش فعال می‌شود.</p>
      </div>
    </div>

    <form method="POST" action="index.php" class="auth-form">
      <?= csrf_field() ?>
      <input type="hidden" name="auth_action" value="register">
      <p class="form-legend"><span class="req" aria-hidden="true">*</span> فیلدهای الزامی · گذرواژه حداقل ۶ کاراکتر</p>

      <div class="grid-2">
        <div class="input-group">
          <label for="g-name">نام و نام خانوادگی رابط <span class="req" aria-hidden="true">*</span></label>
          <input id="g-name" type="text" name="name" required autocomplete="name" value="<?= e($old['name'] ?? '') ?>" placeholder="مهندس علوی">
        </div>
        <div class="input-group">
          <label for="g-company">نام شرکت / مؤسسه</label>
          <input id="g-company" type="text" name="company" autocomplete="organization" value="<?= e($old['company'] ?? '') ?>" placeholder="شرکت مهندسی بناسازان">
        </div>
      </div>

      <div class="grid-2">
        <div class="input-group">
          <label for="g-phone">شماره همراه <span class="req" aria-hidden="true">*</span></label>
          <input id="g-phone" type="tel" name="phone" required maxlength="11" inputmode="numeric" autocomplete="tel" dir="ltr" value="<?= e($old['phone'] ?? '') ?>" placeholder="09121111111">
        </div>
        <div class="input-group">
          <label for="g-email">ایمیل سازمانی <span class="muted">(اختیاری)</span></label>
          <input id="g-email" type="email" name="email" autocomplete="email" dir="ltr" value="<?= e($old['email'] ?? '') ?>" placeholder="procurement@company.ir">
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

      <div class="grid-2">
        <div class="input-group">
          <label for="g-nid">شناسه ملی / کد اقتصادی <span class="muted">(اختیاری)</span></label>
          <input id="g-nid" type="text" name="national_id" inputmode="numeric" autocomplete="off" value="<?= e($old['nationalId'] ?? '') ?>" placeholder="10103456789">
        </div>
        <div class="input-group">
          <label for="g-province">استان</label>
          <select id="g-province" name="province" autocomplete="address-level1">
            <?php foreach ($provinces as $pv): ?>
              <option value="<?= e($pv) ?>" <?= ($old['province'] ?? '') === $pv ? 'selected' : '' ?>><?= e($pv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid-2">
        <div class="input-group">
          <label for="g-city">شهر</label>
          <input id="g-city" type="text" name="city" autocomplete="address-level2" value="<?= e($old['city'] ?? '') ?>">
        </div>
        <div class="input-group">
          <label for="g-address">نشانی دفتر / انبار</label>
          <input id="g-address" type="text" name="address" autocomplete="street-address" value="<?= e($old['address'] ?? '') ?>" placeholder="تهران، خیابان گاندی، پلاک ۴۲">
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg btn-block">ایجاد حساب خریدار سازمانی</button>

      <div class="auth-links">
        <span>قبلاً ثبت‌نام کرده‌اید؟ <a href="index.php?page=login">ورود به حساب</a></span>
      </div>
    </form>
  </div>

  <aside class="auth-side">
    <h2>چرا خرید سازمانی از پارس سازه؟</h2>
    <ul class="feature-list">
      <li>🧾 صورتحساب الکترونیکی معتبر و قابل استناد در ممیزی مالیاتی</li>
      <li>🚚 ارسال به کارگاه‌های پروژه در سراسر کشور</li>
      <li>🤝 مشاور فنی تأمین کالا داریم</li>
    </ul>
    <div class="side-note">
      اطلاعات کارپوشه شما فقط برای صدور اسناد رسمی استفاده می‌شود و در اختیار شخص ثالث قرار نمی‌گیرد.
    </div>
  </aside>
</div>
