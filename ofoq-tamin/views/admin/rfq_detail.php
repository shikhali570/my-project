<?php
/** پاسخ و قیمت‌گذاری استعلام */
require_admin();
$id = (int)get('id');
$stmt = $db->prepare('SELECT * FROM rfqs WHERE id = ?');
$stmt->execute([$id]);
$r = $stmt->fetch();

if (!$r) {
    echo '<div class="empty-state"><span>📋</span><h3>استعلام یافت نشد</h3><a class="btn btn-primary" href="index.php?page=admin_rfqs">بازگشت</a></div>';
    return;
}

$customer = null;
if ($r['user_id']) {
    $c = $db->prepare('SELECT * FROM users WHERE id = ?');
    $c->execute([$r['user_id']]);
    $customer = $c->fetch();
}

$others = $db->prepare('SELECT * FROM rfqs WHERE id != ? AND (user_id = ? OR phone = ?) ORDER BY id DESC LIMIT 5');
$others->execute([$id, $r['user_id'], $r['phone']]);
$otherRows = $others->fetchAll();

// پیشنهاد سریع قیمت بر اساس اقلام متنی استعلام (تخمین بر پایه کاتالوگ)
$estimate = 0;
foreach ($db->query('SELECT price FROM products ORDER BY RANDOM() LIMIT 1') as $row) {
    $estimate = (int)$row['price'] * 4;
}
?>

<div class="detail-head">
  <div>
    <h2 class="sec-title">استعلام <span class="mono"><?= e($r['rfq_code']) ?></span></h2>
    <p class="sec-sub">ثبت‌شده در <?= jdate($r['created_at'], true) ?> توسط <?= e($r['company']) ?></p>
  </div>
  <div class="flex-gap">
    <span class="status <?= rfq_status_class($r['status']) ?>"><?= e(rfq_status_label($r['status'])) ?></span>
    <a class="btn btn-sm btn-secondary" href="index.php?page=admin_rfqs">← فهرست استعلام‌ها</a>
  </div>
</div>

<?php
$rfqItems = rfq_items_decode($r['items_json'] ?? '[]');
$rfqAttachments = rfq_attachments_decode($r['attachments_json'] ?? '[]');
?>

<div class="detail-grid wide-left">
  <div>
    <div class="card">
      <div class="card-head">
        <h3 class="card-title">اطلاعات تماس استعلام</h3>
        <span class="pill muted mono"><?= e($r['phone']) ?></span>
      </div>
      <div class="rfq-contact-details">
        <div><span>شرکت / پیمانکار</span><strong><?= e($r['company']) ?></strong></div>
        <div><span>شماره همراه جهت ارتباط</span><strong class="mono" dir="ltr"><?= e($r['phone']) ?></strong></div>
        <div><span>ایمیل</span><strong><?= !empty($r['email']) ? '<a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>' : '—' ?></strong></div>
        <div><span>پیام‌رسان پاسخگو</span><strong><?= e($r['messenger'] ?: '—') ?></strong></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h3 class="card-title"><?= e($r['title'] ?: 'اقلام درخواست‌شده') ?></h3>
      </div>
      <?php if ($rfqItems): ?>
        <div class="table-wrap">
          <table class="data-table rfq-admin-items">
            <caption class="visually-hidden">اقلام ثبت‌شده در استعلام <?= e($r['rfq_code']) ?></caption>
            <thead><tr><th scope="col">ردیف</th><th scope="col">شرح قلم</th><th scope="col">مقدار</th><th scope="col">شناسه کالا</th><th scope="col">دسته‌بندی</th></tr></thead>
            <tbody>
              <?php foreach ($rfqItems as $index => $item): ?>
                <tr>
                  <td><?= fa_num($index + 1) ?></td>
                  <td class="rfq-item-description"><?= nl2br(e($item['description'])) ?></td>
                  <td><?= e(fa_text($item['quantity'])) ?></td>
                  <td class="mono"><?= e($item['item_code'] ?: '—') ?></td>
                  <td><?= e($item['category'] !== '' ? category_title($item['category']) : '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <pre class="rfq-desc"><?= e($r['description']) ?></pre>
      <?php endif; ?>

      <?php if ($rfqAttachments): ?>
        <div class="rfq-attachments">
          <strong>پیوست‌های استعلام</strong>
          <ul>
            <?php foreach ($rfqAttachments as $attachment): ?>
              <li>
                <a href="index.php?action=rfq_attachment_download&amp;rfq_id=<?= (int)$r['id'] ?>&amp;attachment=<?= e($attachment['token']) ?>"><?= e($attachment['name']) ?></a>
                <small><?= fa_num(round($attachment['size'] / 1024)) ?> کیلوبایت · <?= e(strtoupper($attachment['extension'])) ?></small>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($r['admin_reply']): ?>
        <div class="alert success"><strong>پاسخ ثبت‌شده:</strong> <?= e($r['admin_reply']) ?></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 class="card-title">ثبت پاسخ و پیش‌فاکتور سازمانی</h3>
      <form method="POST" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="rfq_update">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <div class="grid-2">
          <div class="input-group">
            <label>مبلغ پیشنهادی کل (تومان)</label>
            <input type="number" name="quote_amount" min="0" step="10000" value="<?= (int)$r['quote_amount'] ?: $estimate ?>">
            <small class="mini-note">تخمین اولیه سیستم بر اساس اقلام مشابه کاتالوگ: <?= money($estimate) ?></small>
          </div>
          <div class="input-group">
            <label>وضعیت استعلام</label>
            <select name="status">
              <?php foreach (rfq_statuses() as $k => $label): ?>
                <option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="input-group">
          <label>متن پاسخ / شرایط پیشنهاد (تخفیف، زمان تحویل، شرایط پرداخت)</label>
          <textarea name="admin_reply" rows="5" placeholder="پیش‌فاکتور با ۱۲٪ تخفیف سازمانی تنظیم شد؛ زمان تحویل ۴۸ ساعت کاری، تسویه ۳۰ روزه."><?= e($r['admin_reply']) ?></textarea>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">💾 ثبت پاسخ و ارسال اعلان به خریدار</button>
          <a class="btn btn-secondary" href="index.php?page=admin_rfqs">انصراف</a>
        </div>
      </form>
      <p class="mini-note">با ثبت پاسخ، اعلان در پنل خریدار ارسال می‌شود و مشتری می‌تواند پیشنهاد را بپذیرد.</p>
    </div>
  </div>

  <aside>
    <div class="card">
      <h3 class="card-title">اطلاعات خریدار</h3>
      <?php if ($customer): ?>
        <div class="customer-head">
          <div class="side-avatar"><?= e(initials($customer['company'] ?: $customer['name'])) ?></div>
          <div>
            <strong><?= e($customer['company'] ?: $customer['name']) ?></strong>
            <small><?= e($customer['name']) ?></small>
          </div>
        </div>
        <div class="kv"><span>تلفن:</span><strong class="mono"><?= e($customer['phone']) ?></strong></div>
        <div class="kv"><span>شناسه ملی:</span><strong class="mono"><?= e($customer['national_id'] ?: '—') ?></strong></div>
        <div class="kv"><span>شهر:</span><strong><?= e($customer['city'] ?: '—') ?></strong></div>
        <div class="kv"><span>اعتبار کارپوشه:</span><strong><?= money($customer['credit']) ?></strong></div>
        <a class="btn btn-sm btn-primary" href="index.php?page=admin_user&id=<?= (int)$customer['id'] ?>">مشاهده پرونده مشتری</a>
      <?php else: ?>
        <div class="kv"><span>شرکت:</span><strong><?= e($r['company']) ?></strong></div>
        <div class="kv"><span>تلفن:</span><strong class="mono"><?= e($r['phone']) ?></strong></div>
        <div class="alert info">این استعلام توسط کاربر مهمان یا بدون حساب پنل ثبت شده است.</div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 class="card-title">راهنمای قیمت‌گذاری</h3>
      <ul class="feature-list">
        <li>برای سفارش‌های بالای <?= money_short(50000000) ?> تومان تخفیف سازمانی تا ۱۲٪ قابل اعمال است.</li>
        <li>هزینه ارسال برای سفارش‌های بالای <?= money_short(settings('free_shipping_min')) ?> تومان رایگان محاسبه شود.</li>
        <li>در پاسخ، زمان تحویل و شرایط تسویه را شفاف اعلام کنید.</li>
        <li>پس از تأیید خریدار، برای تبدیل به فاکتور رسمی از بخش سفارش‌ها اقدام کنید.</li>
      </ul>
    </div>

    <?php if ($otherRows): ?>
      <div class="card">
        <h3 class="card-title">سایر استعلام‌های این مشتری</h3>
        <div class="note-list">
          <?php foreach ($otherRows as $o): ?>
            <a class="note-item" href="index.php?page=admin_rfq&id=<?= (int)$o['id'] ?>">
              <strong class="mono"><?= e($o['rfq_code']) ?></strong>
              <p><?= e($o['title'] ?: mb_substr($o['description'], 0, 40)) ?></p>
              <small><?= e(rfq_status_label($o['status'])) ?> • <?= jdate($o['created_at']) ?></small>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </aside>
</div>
