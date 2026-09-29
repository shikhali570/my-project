<?php
/** مدیریت کدهای تخفیف */
require_admin();
$editId = (int)get('edit');
$edit = ['id' => 0, 'code' => '', 'type' => 'percent', 'amount' => 10, 'min_total' => 0, 'max_uses' => 0, 'used' => 0, 'expires_at' => '', 'is_active' => 1, 'description' => ''];
if ($editId) {
    $stmt = $db->prepare('SELECT * FROM coupons WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: $edit;
}
$rows = $db->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll();
$usedTotal = 0;
foreach ($rows as $r) {
    $usedTotal += (int)$r['used'];
}
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">🎟️</span>
    <div><span>تعداد کدهای تخفیف</span><strong><?= fa_num(count($rows)) ?> کد</strong><small><?= fa_num((int)$db->query('SELECT COUNT(*) FROM coupons WHERE is_active = 1')->fetchColumn()) ?> کد فعال</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">✅</span>
    <div><span>مجموع استفاده‌شده</span><strong><?= fa_num($usedTotal) ?> بار</strong><small>در سفارش‌های ثبت‌شده</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">⏳</span>
    <div><span>کدهای منقضی‌شده</span><strong><?= fa_num((int)$db->query("SELECT COUNT(*) FROM coupons WHERE expires_at IS NOT NULL AND expires_at < date('now')")->fetchColumn()) ?> کد</strong><small>غیرقابل استفاده برای خریداران</small></div>
  </div>
</div>

<div class="admin-grid-2">
  <div class="card">
    <div class="card-head">
      <h3 class="card-title"><?= $editId ? 'ویرایش کد تخفیف' : 'ایجاد کد تخفیف جدید' ?></h3>
      <?php if ($editId): ?><a class="link-more" href="index.php?page=admin_coupons">لغو ویرایش</a><?php endif; ?>
    </div>
    <form method="POST" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="coupon_save">
      <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
      <div class="grid-2">
        <div class="input-group">
          <label>کد تخفیف (لاتین) *</label>
          <input type="text" name="code" required value="<?= e($edit['code']) ?>" placeholder="PROJECT10" class="mono">
        </div>
        <div class="input-group">
          <label>نوع تخفیف *</label>
          <select name="type">
            <option value="percent" <?= $edit['type'] === 'percent' ? 'selected' : '' ?>>درصدی</option>
            <option value="fixed" <?= $edit['type'] === 'fixed' ? 'selected' : '' ?>>مبلغ ثابت (تومان)</option>
          </select>
        </div>
      </div>
      <div class="grid-2">
        <div class="input-group">
          <label>مقدار تخفیف * (درصد یا تومان)</label>
          <input type="number" name="amount" required min="1" value="<?= (int)$edit['amount'] ?>">
        </div>
        <div class="input-group">
          <label>حداقل مبلغ سبد (تومان)</label>
          <input type="number" name="min_total" min="0" step="100000" value="<?= (int)$edit['min_total'] ?>">
        </div>
      </div>
      <div class="grid-3">
        <div class="input-group">
          <label>حداکثر تعداد استفاده (۰ = نامحدود)</label>
          <input type="number" name="max_uses" min="0" value="<?= (int)$edit['max_uses'] ?>">
        </div>
        <div class="input-group">
          <label>تاریخ انقضا</label>
          <input type="date" name="expires_at" value="<?= e(substr((string)$edit['expires_at'], 0, 10)) ?>">
        </div>
        <div class="input-group">
          <label>وضعیت</label>
          <select name="is_active">
            <option value="1" <?= (int)$edit['is_active'] ? 'selected' : '' ?>>فعال</option>
            <option value="0" <?= !(int)$edit['is_active'] ? 'selected' : '' ?>>غیرفعال</option>
          </select>
        </div>
      </div>
      <div class="input-group">
        <label>توضیح داخلی / کمپین</label>
        <input type="text" name="description" value="<?= e($edit['description']) ?>" placeholder="تخفیف پروژه‌ای برای پیمانکاران">
      </div>
      <button class="btn btn-primary" type="submit"><?= $editId ? '💾 ذخیره تغییرات' : '➕ ایجاد کد تخفیف' ?></button>
    </form>
  </div>

  <div class="card">
    <h3 class="card-title">کدهای آماده برای تست</h3>
    <div class="table-note">
      این کدها در فایل داده‌های اولیه ایجاد شده‌اند و می‌توانید در سبد خرید امتحان کنید:
    </div>
    <div class="chip-row">
      <span class="chip mono">PROJECT10 — ۱۰٪ بالای ۱۰ میلیون</span>
      <span class="chip mono">HSE15 — ۱۵٪ تجهیزات ایمنی</span>
      <span class="chip mono">WELCOME2M — ۲ میلیون تومان بالای ۳۰ میلیون</span>
      <span class="chip mono">NOWRUZ — منقضی‌شده</span>
    </div>
    <div class="alert info">کدهای تخفیف روی جمع خالص اقلام اعمال می‌شوند و مالیات ارزش افزوده پس از تخفیف محاسبه می‌گردد.</div>
  </div>
</div>

<div class="card">
  <h3 class="card-title">فهرست کدهای تخفیف</h3>
  <table class="data-table">
    <thead><tr><th>کد</th><th>نوع</th><th>مقدار</th><th>حداقل سبد</th><th>استفاده</th><th>انقضا</th><th>وضعیت</th><th>توضیح</th><th>عملیات</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $c):
          $expired = $c['expires_at'] && strtotime($c['expires_at']) < strtotime(date('Y-m-d'));
          $full = (int)$c['max_uses'] > 0 && (int)$c['used'] >= (int)$c['max_uses']; ?>
        <tr class="<?= (!$c['is_active'] || $expired) ? 'row-muted' : '' ?>">
          <td><strong class="mono"><?= e($c['code']) ?></strong></td>
          <td><?= $c['type'] === 'percent' ? 'درصدی' : 'مبلغ ثابت' ?></td>
          <td><?= $c['type'] === 'percent' ? fa_num($c['amount']) . '٪' : money($c['amount']) ?></td>
          <td><?= $c['min_total'] ? money_short($c['min_total']) : '—' ?></td>
          <td><?= fa_num($c['used']) ?> / <?= (int)$c['max_uses'] ? fa_num($c['max_uses']) : 'نامحدود' ?></td>
          <td><?= $c['expires_at'] ? jdate_short($c['expires_at']) : '—' ?></td>
          <td>
            <?php if ($expired): ?><span class="status muted">منقضی</span>
            <?php elseif ($full): ?><span class="status warn">ظرفیت تکمیل</span>
            <?php elseif ((int)$c['is_active']): ?><span class="status success">فعال</span>
            <?php else: ?><span class="status danger">غیرفعال</span><?php endif; ?>
          </td>
          <td class="small"><?= e($c['description']) ?></td>
          <td>
            <div class="row-actions">
              <a class="btn btn-icon" href="index.php?page=admin_coupons&edit=<?= (int)$c['id'] ?>" title="ویرایش">✏️</a>
              <form class="inline-form" method="POST" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="coupon_toggle">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn btn-icon" type="submit" title="تغییر وضعیت"><?= (int)$c['is_active'] ? '🚫' : '✅' ?></button>
              </form>
              <button class="btn btn-icon danger" type="button" title="حذف"
                      data-confirm="کد تخفیف <?= e($c['code']) ?> حذف شود؟" data-form="delc-<?= (int)$c['id'] ?>">🗑️</button>
              <form id="delc-<?= (int)$c['id'] ?>" method="POST" action="index.php" class="hidden">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="coupon_delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
