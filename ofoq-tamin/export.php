<?php
/**
 * خروجی CSV گزارش‌ها (فقط مدیر سیستم)
 * نمونه: export.php?type=orders
 */
require_once __DIR__ . '/config.php';
require_login();
if (!is_admin()) {
    http_response_code(403);
    die('دسترسی غیرمجاز');
}

$type = preg_replace('/[^a-z_]/', '', get('type', 'orders'));
$filename = 'parssaze-' . $type . '-' . date('Ymd-His') . '.csv';

$datasets = [
    'orders' => [
        'title' => 'گزارش سفارش‌ها',
        'sql' => "SELECT o.order_no, o.created_at, o.customer_name, o.company, o.phone, o.tax_id, o.province, o.city,
                         o.status, o.payment_status, o.subtotal, o.discount, o.tax_amount, o.shipping, o.total,
                         (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS items_count,
                         o.tracking_code
                  FROM orders o ORDER BY o.id DESC",
        'headers' => ['شماره سفارش', 'تاریخ ثبت', 'خریدار', 'شرکت', 'تلفن', 'شناسه ملی', 'استان', 'شهر',
            'وضعیت سفارش', 'وضعیت پرداخت', 'جمع اقلام', 'تخفیف', 'ارزش افزوده', 'ارسال', 'مبلغ کل', 'تعداد اقلام', 'کد رهگیری'],
        'map' => function ($r) {
            return [
                $r['order_no'], $r['created_at'], $r['customer_name'], $r['company'], $r['phone'], $r['tax_id'],
                $r['province'], $r['city'], order_status_label($r['status']), payment_status_label($r['payment_status']),
                $r['subtotal'], $r['discount'], $r['tax_amount'], $r['shipping'], $r['total'], $r['items_count'], $r['tracking_code'],
            ];
        },
    ],
    'invoices' => [
        'title' => 'گزارش صورتحساب‌های الکترونیکی',
        'sql' => 'SELECT i.invoice_no, i.tax_unique_id, i.created_at, i.buyer_name, i.buyer_phone, i.buyer_tax_id,
                         i.subtotal, i.tax_amount, i.total_amount, i.status, o.order_no
                  FROM invoices i LEFT JOIN orders o ON o.id = i.order_id ORDER BY i.id DESC',
        'headers' => ['شماره فاکتور', 'شناسه یکتای مالیاتی', 'تاریخ صدور', 'خریدار', 'تلفن', 'شناسه ملی خریدار',
            'خالص اقلام', 'ارزش افزوده', 'مبلغ کل', 'وضعیت سند', 'شماره سفارش'],
        'map' => function ($r) {
            return [$r['invoice_no'], $r['tax_unique_id'], $r['created_at'], $r['buyer_name'], $r['buyer_phone'],
                $r['buyer_tax_id'], $r['subtotal'], $r['tax_amount'], $r['total_amount'], $r['status'], $r['order_no']];
        },
    ],
    'products' => [
        'title' => 'گزارش کالا و موجودی انبار',
        'sql' => 'SELECT id, sku, name, category, brand, price, old_price, tax_id, unit, stock, min_stock, sold, views, is_active
                  FROM products ORDER BY id',
        'headers' => ['شناسه', 'کد کالا', 'نام کالا', 'گروه', 'برند', 'قیمت فروش', 'قیمت قبل از تخفیف',
            'شناسه مالیاتی', 'واحد', 'موجودی', 'حد هشدار', 'تعداد فروش', 'بازدید', 'وضعیت'],
        'map' => function ($r) {
            return [$r['id'], $r['sku'], $r['name'], category_title($r['category']), $r['brand'], $r['price'],
                $r['old_price'], $r['tax_id'], $r['unit'], $r['stock'], $r['min_stock'], $r['sold'], $r['views'],
                (int)$r['is_active'] ? 'فعال' : 'غیرفعال'];
        },
    ],
    'users' => [
        'title' => 'گزارش کاربران و مشتریان',
        'sql' => "SELECT u.id, u.role, u.name, u.company, u.phone, u.email, u.national_id, u.economic_code,
                         u.province, u.city, u.credit, u.status, u.created_at, u.last_login_at,
                         (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders_count,
                         (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.user_id = u.id AND o.status != 'canceled') AS orders_sum
                  FROM users u ORDER BY u.id",
        'headers' => ['شناسه', 'نقش', 'نام رابط', 'شرکت', 'تلفن', 'ایمیل', 'شناسه ملی', 'کد اقتصادی',
            'استان', 'شهر', 'اعتبار', 'وضعیت', 'تاریخ عضویت', 'آخرین ورود', 'تعداد سفارش', 'مجموع خرید'],
        'map' => function ($r) {
            return [$r['id'], $r['role'] === 'admin' ? 'مدیر سیستم' : 'خریدار', $r['name'], $r['company'], $r['phone'],
                $r['email'], $r['national_id'], $r['economic_code'], $r['province'], $r['city'], $r['credit'],
                $r['status'] === 'active' ? 'فعال' : 'غیرفعال', $r['created_at'], $r['last_login_at'], $r['orders_count'], $r['orders_sum']];
        },
    ],
    'rfqs' => [
        'title' => 'گزارش استعلام‌های قیمت',
        'sql' => 'SELECT rfq_code, created_at, company, phone, email, messenger, title, status, quote_amount, quoted_at,
                         description, items_json, attachments_json FROM rfqs ORDER BY id DESC',
        'headers' => ['کد استعلام', 'تاریخ ثبت', 'شرکت', 'شماره همراه', 'ایمیل', 'پیام‌رسان پاسخگو', 'عنوان', 'وضعیت',
            'مبلغ پیشنهادی', 'تاریخ قیمت‌گذاری', 'شرح درخواست', 'اقلام ساختاریافته', 'نام پیوست‌ها'],
        'map' => function ($r) {
            $itemLines = [];
            foreach (rfq_items_decode($r['items_json'] ?? '[]') as $index => $item) {
                $parts = [fa_num($index + 1) . '- ' . $item['description'], 'مقدار: ' . fa_text($item['quantity'])];
                if ($item['item_code'] !== '') {
                    $parts[] = 'شناسه کالا: ' . $item['item_code'];
                }
                if ($item['category'] !== '') {
                    $parts[] = 'دسته‌بندی: ' . category_title($item['category']);
                }
                $itemLines[] = implode(' | ', $parts);
            }
            $attachmentNames = [];
            foreach (rfq_attachments_decode($r['attachments_json'] ?? '[]') as $attachment) {
                $attachmentNames[] = $attachment['name'];
            }
            return [
                $r['rfq_code'], $r['created_at'], $r['company'], $r['phone'], $r['email'], $r['messenger'], $r['title'],
                rfq_status_label($r['status']), $r['quote_amount'], $r['quoted_at'], str_replace("\n", ' / ', $r['description']),
                implode(' / ', $itemLines), implode(' / ', $attachmentNames),
            ];
        },
    ],
];

if (!isset($datasets[$type])) {
    http_response_code(404);
    die('نوع گزارش نامعتبر است. مقادیر مجاز: ' . implode(', ', array_keys($datasets)));
}

$ds = $datasets[$type];
$stmt = $db->query($ds['sql']);
$rows = $stmt->fetchAll();

log_action('export_csv', $type, null, 'دریافت خروجی CSV گزارش ' . $ds['title'] . ' (' . count($rows) . ' رکورد)');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM برای نمایش صحیح فارسی در اکسل
fputcsv($out, [$ds['title'] . ' — ' . settings('site_name') . ' — تاریخ تهیه: ' . date('Y-m-d H:i')], ',', '"', '');
fputcsv($out, [''], ',', '"', '');
fputcsv($out, $ds['headers'], ',', '"', '');
foreach ($rows as $r) {
    fputcsv($out, $ds['map']($r), ',', '"', '');
}
fclose($out);
exit;
