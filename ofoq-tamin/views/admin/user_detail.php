<?php
/** پرونده مشتری سازمانی */
require_admin();
$id = (int)get('id');
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$u = $stmt->fetch();

if (!$u) {
    echo '<div class="empty-state"><span>👤</span><h3>کاربر یافت نشد</h3><a class="btn btn-primary" href="index.php?page=admin_users">بازگشت</a></div>';
    return;
}

$orders = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 10');
$orders->execute([$id]);
$orderRows = $orders->fetchAll();

$rfqs = $db->prepare('SELECT * FROM rfqs WHERE user_id = ? ORDER BY id DESC LIMIT 6');
$rfqs->execute([$id]);
$rfqRows = $rfqs->fetchAll();

$invoices = $db->prepare('SELECT * FROM invoices WHERE user_id = ? ORDER BY id DESC LIMIT 6');
$invoices->execute([$id]);
$invoiceRows = $invoices->fetchAll();

$agg = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(total),0) s, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END),0) paid,
                     COALESCE(SUM(CASE WHEN status IN ('pending','approved','preparing','shipped') THEN 1 ELSE 0 END),0) open_orders
                     FROM orders WHERE user_id = ? AND status != 'canceled'");
$agg->execute([$id]);
$stats = $agg->fetch();

$notifyCount = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
$notifyCount->execute([$id]);
?>

<div class="detail-head">
  <div>
    <h2 class="sec-title"><?= e($u['company'] ?: $u['name']) ?></h2>
    <p class="sec-sub">
      <?= e($u['name']) ?> | <span class="mono"><?= e($u['phone']) ?></span> |
      <?= e($u['role'] === 'admin' ? 'مدیر سیستم' : 'خریدار') ?> |
      عضویت از <?= jdate($u['created_at']) ?>
    </p>
  </div>
  <div class="flex-gap">
    <span class="status <?= $u['status'] === 'active' ? 'success' : 'danger' ?>"><?= $u['status'] === 'active' ? 'حساب فعال' : 'غیرفعال' ?></span>
    <a class="btn btn-sm btn-primary" href="index.php?page=admin_user_form&id=<?= (int)$u['id'] ?>">✏️ ویرایش</a>
    <?php if ($u['role'] !== 'admin'): ?>
      <form method="POST" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="user_toggle">
        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <button class="btn btn-sm btn-secondary" type="submit"><?= $u['status'] === 'active' ? '🚫 غیرفعال‌سازی' : '✅ فعال‌سازی' ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">📦</span>
    <div><span>سفارش‌های فعال</span><strong><?= fa_num($stats['c']) ?> سفارش</strong><small><?= fa_num($stats['open_orders']) ?> در جریان</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">💰</span>
    <div><span>مجموع خرید</span><strong><?= money_short($stats['s']) ?> تومان</strong><small>دریافتی تأییدشده: <?= money_short($stats['paid']) ?></small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">🎁</span>
    <div><span>اعتبار فعلی کارپوشه</span><strong><?= money($u['credit']) ?></strong><small>قابل کسر از سفارش‌ها</small></div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico purple">🧾</span>
    <div><span>صورتحساب‌های صادرشده</span><strong><?= fa_num(count($invoiceRows)) ?> سند</strong><small><?= fa_num((int)$notifyCount->fetchColumn()) ?> اعلان ارسال‌شده</small></div>
  </div>
</div>

<div class="detail-grid wide-left">
  <div>
    <div class="card">
      <div class="card-head"><h3 class="card-title">سفارش‌های مشتری</h3><span class="pill muted">۱۰ سفارش آخر</span></div>
      <?php if (!$orderRows): ?>
        <div class="empty-mini">این مشتری هنوز سفارشی ثبت نکرده است.</div>
      <?php else: ?>
        <table class="data-table">
          <thead><tr><th>شماره</th><th>تاریخ</th><th>اقلام</th><th>مبلغ</th><th>وضعیت</th><th>پرداخت</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($orderRows as $o):
                $ic = $db->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = ?');
                $ic->execute([$o['id']]); ?>
              <tr>
                <td class="mono"><?= e($o['order_no']) ?></td>
                <td><?= jdate($o['created_at']) ?></td>
                <td><?= fa_num($ic->fetchColumn()) ?> قلم</td>
                <td><?= money($o['total']) ?></td>
                <td><span class="status <?= order_status_class($o['status']) ?>"><?= e(order_status_label($o['status'])) ?></span></td>
                <td><span class="pill <?= $o['payment_status'] === 'paid' ? 'success' : 'warn' ?>"><?= e(payment_status_label($o['payment_status'])) ?></span></td>
                <td><a class="btn btn-sm btn-secondary" href="index.php?page=admin_order&id=<?= (int)$o['id'] ?>">پرونده</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head"><h3 class="card-title">استعلام‌های قیمت مشتری</h3></div>
      <?php if (!$rfqRows): ?>
        <div class="empty-mini">استعلامی ثبت نشده است.</div>
      <?php else: ?>
        <table class="data-table">
          <thead><tr><th>کد</th><th>عنوان</th><th>تاریخ</th><th>وضعیت</th><th>مبلغ پیشنهادی</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($rfqRows as $r): ?>
              <tr>
                <td class="mono"><?= e($r['rfq_code']) ?></td>
                <td><?= e($r['title'] ?: mb_substr($r['description'], 0, 40) . '…') ?></td>
                <td><?= jdate($r['created_at']) ?></td>
                <td><span class="status <?= rfq_status_class($r['status']) ?>"><?= e(rfq_status_label($r['status'])) ?></span></td>
                <td><?= $r['quote_amount'] ? money_short($r['quote_amount']) : '—' ?></td>
                <td><a class="btn btn-sm btn-secondary" href="index.php?page=admin_rfq&id=<?= (int)$r['id'] ?>">پاسخ</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head"><h3 class="card-title">صورتحساب‌های الکترونیکی</h3></div>
      <?php if (!$invoiceRows): ?>
        <div class="empty-mini">صورتحسابی صادر نشده است.</div>
      <?php else: ?>
        <table class="data-table">
          <thead><tr><th>شماره</th><th>شناسه یکتا</th><th>تاریخ</th><th>مبلغ کل</th><th>ارزش افزوده</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($invoiceRows as $inv): ?>
              <tr>
                <td class="mono"><?= e($inv['invoice_no']) ?></td>
                <td class="mono small"><?= e($inv['tax_unique_id']) ?></td>
                <td><?= jdate($inv['created_at']) ?></td>
                <td><?= money($inv['total_amount']) ?></td>
                <td><?= money($inv['tax_amount']) ?></td>
                <td><a class="btn btn-sm btn-secondary" href="index.php?page=invoice&id=<?= e($inv['tax_unique_id']) ?>">چاپ</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <div class="card">
      <h3 class="card-title">اطلاعات کارپوشه</h3>
      <div class="kv"><span>نوع خریدار:</span><strong><?= ($u['entity_type'] ?? '') === 'legal' ? 'شخص حقوقی' : (($u['entity_type'] ?? '') === 'individual' ? 'شخص حقیقی' : 'ثبت‌نشده') ?></strong></div>
      <div class="kv"><span>رابط:</span><strong><?= e($u['name']) ?></strong></div>
      <div class="kv"><span>شرکت:</span><strong><?= e($u['company'] ?: '—') ?></strong></div>
      <div class="kv"><span>تلفن:</span><strong class="mono"><?= e($u['phone']) ?></strong></div>
      <div class="kv"><span>ایمیل:</span><strong><?= e($u['email'] ?: '—') ?></strong></div>
      <div class="kv"><span>شناسه ملی:</span><strong class="mono"><?= e($u['national_id'] ?: '—') ?></strong></div>
      <div class="kv"><span>کد اقتصادی:</span><strong class="mono"><?= e($u['economic_code'] ?: '—') ?></strong></div>
      <div class="kv"><span>کد پستی:</span><strong class="mono"><?= e($u['postal_code'] ?: '—') ?></strong></div>
      <div class="kv"><span>استان / شهر:</span><strong><?= e(($u['province'] ?: '—') . ' / ' . ($u['city'] ?: '—')) ?></strong></div>
      <div class="kv"><span>نشانی:</span><strong><?= e($u['address'] ?: '—') ?></strong></div>
      <div class="kv"><span>آخرین ورود:</span><strong><?= jdate($u['last_login_at'], true) ?></strong></div>
      <?php if ($u['notes']): ?><div class="alert info">یادداشت داخلی: <?= e($u['notes']) ?></div><?php endif; ?>
    </div>

    <div class="card">
      <h3 class="card-title">تنظیم اعتبار سازمانی</h3>
      <form method="POST" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="user_credit">
        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <div class="input-group">
          <label>مبلغ (تومان) — عدد منفی برای کاهش اعتبار</label>
          <input type="number" name="amount" value="1000000" step="100000">
        </div>
        <div class="input-group">
          <label>دلیل / توضیح</label>
          <input type="text" name="reason" placeholder="مثلاً: اعتبار تسویه مرحله‌ای پروژه">
        </div>
        <button class="btn btn-green" type="submit">ثبت تغییر اعتبار</button>
      </form>
      <p class="mini-note">با هر تغییر اعتبار، اعلان برای خریدار ارسال می‌شود.</p>
    </div>

    <div class="card">
      <h3 class="card-title">ارسال پیام به خریدار</h3>
      <form method="POST" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="notify_send">
        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
        <div class="input-group">
          <label>عنوان پیام</label>
          <input type="text" name="title" required placeholder="مثلاً: به‌روزرسانی شرایط تسویه">
        </div>
        <div class="input-group">
          <label>متن پیام</label>
          <textarea name="body" rows="3" required></textarea>
        </div>
        <button class="btn btn-primary" type="submit">ارسال پیام</button>
      </form>
    </div>
  </aside>
</div>
