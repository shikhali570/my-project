<?php
/** کارپوشه و پروفایل خریدار: اطلاعات مالیاتی، امنیت، نشانی‌ها */
require_buyer();
$me = current_user();
$stats = buyer_stats($db, $me['id']);
$provinces = ['تهران', 'البرز', 'اصفهان', 'خراسان رضوی', 'فارس', 'آذربایجان شرقی', 'گیلان', 'مازندران', 'خوزستان', 'کرمان', 'یزد', 'قم', 'هرمزگان', 'سایر استان‌ها'];

$lastOrders = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 3');
$lastOrders->execute([$me['id']]);
$orders = $lastOrders->fetchAll();
?>

<div class="panel-grid">
  <div class="card">
    <div class="card-head"><h3 class="card-title">اطلاعات کارپوشه و صورتحساب</h3></div>
    <form method="POST" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="auth_action" value="profile_update">
      <div class="grid-2">
        <div class="input-group">
          <label>نام و نام خانوادگی رابط *</label>
          <input type="text" name="name" required value="<?= e($me['name']) ?>">
        </div>
        <div class="input-group">
          <label>نام شرکت / مؤسسه</label>
          <input type="text" name="company" value="<?= e($me['company']) ?>">
        </div>
      </div>
      <div class="grid-2">
        <div class="input-group">
          <label>شماره همراه (تغییرناپذیر)</label>
          <input type="text" value="<?= e($me['phone']) ?>" disabled>
        </div>
        <div class="input-group">
          <label>ایمیل سازمانی</label>
          <input type="email" name="email" value="<?= e($me['email']) ?>">
        </div>
      </div>
      <div class="grid-2">
        <div class="input-group">
          <label>شناسه ملی شرکت</label>
          <input type="text" name="national_id" value="<?= e($me['national_id']) ?>">
        </div>
        <div class="input-group">
          <label>کد اقتصادی</label>
          <input type="text" name="economic_code" value="<?= e($me['economic_code']) ?>">
        </div>
      </div>
      <div class="grid-3">
        <div class="input-group">
          <label>کد پستی</label>
          <input type="text" name="postal_code" value="<?= e($me['postal_code']) ?>">
        </div>
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
        <label>نشانی کامل (برای درج در صورتحساب)</label>
        <textarea name="address" rows="3"><?= e($me['address']) ?></textarea>
      </div>
      <button class="btn btn-primary" type="submit">ذخیره تغییرات کارپوشه</button>
    </form>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h3 class="card-title">تغییر گذرواژه</h3></div>
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
        <button class="btn btn-secondary" type="submit">تغییر گذرواژه</button>
      </form>
    </div>

    <div class="card">
      <div class="card-head"><h3 class="card-title">خلاصه حساب</h3></div>
      <div class="kv"><span>نوع حساب:</span><strong>خریدار سازمانی</strong></div>
      <div class="kv"><span>وضعیت:</span><strong class="status <?= $me['status'] === 'active' ? 'success' : 'danger' ?>"><?= $me['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></strong></div>
      <div class="kv"><span>اعتبار کارپوشه:</span><strong><?= money($me['credit']) ?></strong></div>
      <div class="kv"><span>تعداد سفارش:</span><strong><?= fa_num($stats['orders']) ?></strong></div>
      <div class="kv"><span>مجموع خرید:</span><strong><?= money($stats['spent']) ?></strong></div>
      <div class="kv"><span>تاریخ عضویت:</span><strong><?= jdate($me['created_at']) ?></strong></div>
      <div class="kv"><span>آخرین ورود:</span><strong><?= jdate($me['last_login_at'], true) ?></strong></div>
    </div>

    <div class="card">
      <div class="card-head"><h3 class="card-title">آخرین سفارش‌ها</h3></div>
      <div class="note-list">
        <?php foreach ($orders as $o): ?>
          <a class="note-item" href="index.php?page=panel_order&no=<?= e($o['order_no']) ?>">
            <strong class="mono"><?= e($o['order_no']) ?></strong>
            <p><?= money($o['total']) ?> — <?= e(order_status_label($o['status'])) ?></p>
            <small><?= jdate($o['created_at']) ?></small>
          </a>
        <?php endforeach; ?>
        <?php if (!$orders): ?><div class="empty-mini">سفارشی ثبت نشده است.</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>
