<?php
/** پروفایل مدیر سیستم */
require_admin();
$me = current_user();
$logs = $db->prepare('SELECT * FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 10');
$logs->execute([$me['id']]);
$rows = $logs->fetchAll();
$provinces = ['تهران', 'البرز', 'اصفهان', 'خراسان رضوی', 'فارس', 'آذربایجان شرقی', 'گیلان', 'مازندران', 'خوزستان', 'کرمان', 'یزد', 'قم', 'هرمزگان', 'سایر استان‌ها'];
?>

<div class="panel-grid">
  <div class="card">
    <div class="card-head"><h3 class="card-title">اطلاعات حساب مدیر</h3></div>
    <form method="POST" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="auth_action" value="profile_update">
      <div class="grid-2">
        <div class="input-group">
          <label>نام و نام خانوادگی *</label>
          <input type="text" name="name" required value="<?= e($me['name']) ?>">
        </div>
        <div class="input-group">
          <label>سمت / واحد سازمانی</label>
          <input type="text" name="company" value="<?= e($me['company']) ?>">
        </div>
      </div>
      <div class="grid-2">
        <div class="input-group">
          <label>شماره همراه (نام کاربری)</label>
          <input type="text" name="phone" maxlength="11" value="<?= e($me['phone']) ?>" class="mono">
        </div>
        <div class="input-group">
          <label>ایمیل</label>
          <input type="email" name="email" value="<?= e($me['email']) ?>">
        </div>
      </div>
      <div class="grid-3">
        <div class="input-group">
          <label>شناسه ملی</label>
          <input type="text" name="national_id" value="<?= e($me['national_id']) ?>" class="mono">
        </div>
        <div class="input-group">
          <label>کد اقتصادی</label>
          <input type="text" name="economic_code" value="<?= e($me['economic_code']) ?>" class="mono">
        </div>
        <div class="input-group">
          <label>کد پستی</label>
          <input type="text" name="postal_code" value="<?= e($me['postal_code']) ?>" class="mono">
        </div>
      </div>
      <div class="grid-2">
        <div class="input-group">
          <label>استان</label>
          <select name="province">
            <option value="">انتخاب کنید</option>
            <?php foreach ($provinces as $pv): ?>
              <option value="<?= e($pv) ?>" <?= $me['province'] === $pv ? 'selected' : '' ?>><?= e($pv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="input-group">
          <label>شهر</label>
          <input type="text" name="city" value="<?= e($me['city']) ?>">
        </div>
      </div>
      <div class="input-group">
        <label>نشانی</label>
        <textarea name="address" rows="2"><?= e($me['address']) ?></textarea>
      </div>
      <button class="btn btn-primary" type="submit">💾 ذخیره اطلاعات</button>
    </form>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h3 class="card-title">امنیت حساب</h3></div>
      <form method="POST" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="auth_action" value="password_change">
        <div class="input-group">
          <label>گذرواژه فعلی *</label>
          <input type="password" name="current_password" required>
        </div>
        <div class="input-group">
          <label>گذرواژه جدید *</label>
          <input type="password" name="new_password" required>
        </div>
        <div class="input-group">
          <label>تکرار گذرواژه جدید *</label>
          <input type="password" name="new_password2" required>
        </div>
        <button class="btn btn-secondary" type="submit">🔄 تغییر گذرواژه</button>
      </form>
      <div class="alert info" style="margin-top:12px">
        آخرین ورود شما: <strong><?= jdate($me['last_login_at'], true) ?></strong><br>
        سطح دسترسی: <strong>مدیریت کامل سامانه</strong>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3 class="card-title">آخرین اقدامات من</h3><a class="link-more" href="index.php?page=admin_logs&user=<?= (int)$me['id'] ?>">همه ←</a></div>
      <ul class="log-list">
        <?php foreach ($rows as $l): ?>
          <li>
            <strong><?= e($l['action']) ?></strong>
            <span><?= e($l['details']) ?></span>
            <small><?= jdate($l['created_at'], true) ?></small>
          </li>
        <?php endforeach; ?>
        <?php if (!$rows): ?><li class="empty-mini">اقدامی ثبت نشده است.</li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>
