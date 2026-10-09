<?php
/** کارپوشه و پروفایل خریدار: اطلاعات مالیاتی و نشانی، و تغییر گذرواژه (زیر بخش جمع‌شونده) */
require_buyer();
$me = current_user();
$provinces = ['تهران', 'البرز', 'اصفهان', 'خراسان رضوی', 'فارس', 'آذربایجان شرقی', 'گیلان', 'مازندران', 'خوزستان', 'کرمان', 'یزد', 'قم', 'هرمزگان', 'سایر استان‌ها'];
?>

<section class="card">
  <div class="card-head"><h3 class="card-title">اطلاعات کارپوشه و صورتحساب</h3></div>
  <p class="mini-note">نوع حساب: خریدار سازمانی · تاریخ عضویت: <?= jdate($me['created_at']) ?> · آخرین ورود: <?= jdate($me['last_login_at'], true) ?></p>
  <form method="POST" action="index.php">
    <?= csrf_field() ?>
    <input type="hidden" name="auth_action" value="profile_update">
    <div class="grid-2">
      <div class="input-group">
        <label for="pr-name">نام و نام خانوادگی رابط *</label>
        <input id="pr-name" type="text" name="name" required value="<?= e($me['name']) ?>">
      </div>
      <div class="input-group">
        <label for="pr-company">نام شرکت / مؤسسه</label>
        <input id="pr-company" type="text" name="company" value="<?= e($me['company']) ?>">
      </div>
    </div>
    <div class="grid-2">
      <div class="input-group">
        <label for="pr-phone">شماره همراه (تغییرناپذیر)</label>
        <input id="pr-phone" type="text" value="<?= e($me['phone']) ?>" disabled>
      </div>
      <div class="input-group">
        <label for="pr-email">ایمیل سازمانی</label>
        <input id="pr-email" type="email" name="email" value="<?= e($me['email']) ?>">
      </div>
    </div>
    <div class="grid-2">
      <div class="input-group">
        <label for="pr-national">شناسه ملی شرکت</label>
        <input id="pr-national" type="text" name="national_id" value="<?= e($me['national_id']) ?>">
      </div>
      <div class="input-group">
        <label for="pr-economic">کد اقتصادی</label>
        <input id="pr-economic" type="text" name="economic_code" value="<?= e($me['economic_code']) ?>">
      </div>
    </div>
    <div class="grid-3">
      <div class="input-group">
        <label for="pr-postal">کد پستی</label>
        <input id="pr-postal" type="text" name="postal_code" value="<?= e($me['postal_code']) ?>">
      </div>
      <div class="input-group">
        <label for="pr-province">استان</label>
        <select id="pr-province" name="province">
          <option value="">انتخاب کنید</option>
          <?php foreach ($provinces as $pv): ?>
            <option value="<?= e($pv) ?>" <?= $me['province'] === $pv ? 'selected' : '' ?>><?= e($pv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="input-group">
        <label for="pr-city">شهر</label>
        <input id="pr-city" type="text" name="city" value="<?= e($me['city']) ?>">
      </div>
    </div>
    <div class="input-group">
      <label for="pr-address">نشانی کامل (برای درج در صورتحساب)</label>
      <textarea id="pr-address" name="address" rows="3"><?= e($me['address']) ?></textarea>
    </div>
    <button class="btn btn-primary" type="submit">ذخیره تغییرات</button>
  </form>
</section>

<details class="card collapsible">
  <summary>تغییر گذرواژه</summary>
  <form method="POST" action="index.php" class="password-form">
    <?= csrf_field() ?>
    <input type="hidden" name="auth_action" value="password_change">
    <div class="grid-3">
      <div class="input-group">
        <label for="pw-current">گذرواژه فعلی *</label>
        <input id="pw-current" type="password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="input-group">
        <label for="pw-new">گذرواژه جدید *</label>
        <input id="pw-new" type="password" name="new_password" required autocomplete="new-password">
      </div>
      <div class="input-group">
        <label for="pw-repeat">تکرار گذرواژه جدید *</label>
        <input id="pw-repeat" type="password" name="new_password2" required autocomplete="new-password">
      </div>
    </div>
    <button class="btn btn-secondary" type="submit">تغییر گذرواژه</button>
  </form>
</details>
