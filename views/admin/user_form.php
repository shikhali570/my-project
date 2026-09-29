<?php
/** فرم ایجاد / ویرایش کاربر */
require_admin();
$id = (int)get('id');
$u = [
    'id' => 0, 'role' => 'buyer', 'name' => '', 'company' => '', 'phone' => '', 'email' => '',
    'national_id' => '', 'economic_code' => '', 'postal_code' => '', 'province' => '', 'city' => '',
    'address' => '', 'credit' => 0, 'status' => 'active', 'notes' => '',
];
if ($id) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        echo '<div class="alert danger">کاربر یافت نشد.</div>';
        return;
    }
    $u = $row;
}
$isEdit = (bool)$id;
$provinces = ['تهران', 'البرز', 'اصفهان', 'خراسان رضوی', 'فارس', 'آذربایجان شرقی', 'گیلان', 'مازندران', 'خوزستان', 'کرمان', 'یزد', 'قم', 'هرمزگان', 'سایر استان‌ها'];
?>

<div class="detail-head">
  <div>
    <h2 class="sec-title"><?= $isEdit ? 'ویرایش حساب کاربری' : 'ایجاد حساب کاربری جدید' ?></h2>
    <p class="sec-sub"><?= $isEdit ? 'کاربر: ' . e($u['name']) . ' | شناسه: ' . fa_num($u['id']) : 'می‌توانید حساب خریدار سازمانی یا مدیر سیستم ایجاد کنید.' ?></p>
  </div>
  <a class="btn btn-secondary btn-sm" href="index.php?page=admin_users">← بازگشت به فهرست کاربران</a>
</div>

<form method="POST" action="index.php" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="user_save">
  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">

  <div class="form-section">
    <h3 class="card-title">هویت حساب</h3>
    <div class="grid-2">
      <div class="input-group">
        <label>نام و نام خانوادگی *</label>
        <input type="text" name="name" required value="<?= e($u['name']) ?>">
      </div>
      <div class="input-group">
        <label>نام شرکت / مؤسسه</label>
        <input type="text" name="company" value="<?= e($u['company']) ?>">
      </div>
    </div>
    <div class="grid-2">
      <div class="input-group">
        <label>شماره همراه (نام کاربری) *</label>
        <input type="text" name="phone" required maxlength="11" value="<?= e($u['phone']) ?>" class="mono">
      </div>
      <div class="input-group">
        <label>ایمیل</label>
        <input type="email" name="email" value="<?= e($u['email']) ?>">
      </div>
    </div>
    <div class="grid-3">
      <div class="input-group">
        <label>نقش کاربر *</label>
        <select name="role">
          <option value="buyer" <?= $u['role'] === 'buyer' ? 'selected' : '' ?>>خریدار سازمانی</option>
          <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>مدیر سیستم</option>
        </select>
      </div>
      <div class="input-group">
        <label>وضعیت حساب</label>
        <select name="status">
          <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>فعال</option>
          <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
        </select>
      </div>
      <div class="input-group">
        <label><?= $isEdit ? 'گذرواژه جدید (خالی = بدون تغییر)' : 'گذرواژه اولیه *' ?></label>
        <input type="text" name="password" <?= $isEdit ? '' : 'required' ?> placeholder="حداقل ۶ کاراکتر">
        <small class="mini-note">گذرواژه به صورت هش‌شده ذخیره می‌شود.</small>
      </div>
    </div>
  </div>

  <div class="form-section">
    <h3 class="card-title">اطلاعات مالی و کارپوشه</h3>
    <div class="grid-3">
      <div class="input-group">
        <label>شناسه ملی</label>
        <input type="text" name="national_id" value="<?= e($u['national_id']) ?>" class="mono">
      </div>
      <div class="input-group">
        <label>کد اقتصادی</label>
        <input type="text" name="economic_code" value="<?= e($u['economic_code']) ?>" class="mono">
      </div>
      <div class="input-group">
        <label>کد پستی</label>
        <input type="text" name="postal_code" value="<?= e($u['postal_code']) ?>" class="mono">
      </div>
    </div>
    <div class="grid-3">
      <div class="input-group">
        <label>اعتبار سازمانی (تومان)</label>
        <input type="number" name="credit" value="<?= (int)$u['credit'] ?>" min="0">
      </div>
      <div class="input-group">
        <label>استان</label>
        <select name="province">
          <option value="">انتخاب کنید</option>
          <?php foreach ($provinces as $pv): ?>
            <option value="<?= e($pv) ?>" <?= $u['province'] === $pv ? 'selected' : '' ?>><?= e($pv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="input-group">
        <label>شهر</label>
        <input type="text" name="city" value="<?= e($u['city']) ?>">
      </div>
    </div>
    <div class="input-group">
      <label>نشانی کامل</label>
      <textarea name="address" rows="3"><?= e($u['address']) ?></textarea>
    </div>
    <div class="input-group">
      <label>یادداشت داخلی (فقط برای مدیران)</label>
      <textarea name="notes" rows="2" placeholder="مثلاً: مشتری با تسویه اعتباری ۳۰ روزه و تخفیف سازمانی ۸٪"><?= e($u['notes']) ?></textarea>
    </div>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary btn-lg" type="submit"><?= $isEdit ? '💾 ذخیره تغییرات' : '➕ ایجاد حساب کاربری' ?></button>
    <a class="btn btn-secondary" href="index.php?page=admin_users">انصراف</a>
    <?php if ($isEdit): ?>
      <a class="btn btn-outline" href="index.php?page=admin_user&id=<?= (int)$u['id'] ?>">مشاهده پرونده مشتری</a>
    <?php endif; ?>
  </div>
</form>
