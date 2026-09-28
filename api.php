<?php
/**
 * نقاط پایانی JSON برای تعامل لحظه‌ای (AJAX)
 * نمونه فراخوانی: api.php?do=cart_count  |  api.php?do=product_search&q=متر
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_out($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$do = preg_replace('/[^a-z_]/', '', get('do', get('action', '')));
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
}

switch ($do) {
    // -------------------------------------------------- تعداد اقلام سبد
    case 'cart_count':
        json_out(['ok' => true, 'count' => cart_count(), 'total' => money(cart_totals($db, $_SESSION['coupon'] ?? null)['total'])]);

    // -------------------------------------------------- افزودن به سبد
    case 'add_cart': {
        if (!csrf_verify($input['csrf_token'] ?? '')) {
            json_out(['ok' => false, 'error' => 'توکن امنیتی نامعتبر است. صفحه را دوباره بارگذاری کنید.'], 403);
        }
        $pid = (int)($input['id'] ?? 0);
        $qty = max(1, (int)en_digits((string)($input['qty'] ?? 1)));
        $stmt = $db->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
        $stmt->execute([$pid]);
        $p = $stmt->fetch();
        if (!$p) {
            json_out(['ok' => false, 'error' => 'کالا یافت نشد.'], 404);
        }
        if ((int)$p['stock'] <= 0) {
            json_out(['ok' => false, 'error' => 'این کالا موجود نیست.']);
        }
        $inCart = (int)($_SESSION['cart'][$pid] ?? 0);
        if ($inCart + $qty > (int)$p['stock']) {
            $qty = max(1, (int)$p['stock'] - $inCart);
        }
        $_SESSION['cart'][$pid] = $inCart + $qty;
        json_out([
            'ok' => true,
            'message' => 'به سبد سفارش اضافه شد.',
            'count' => cart_count(),
            'total' => money(cart_totals($db, $_SESSION['coupon'] ?? null)['total']),
        ]);
    }

    // -------------------------------------------------- جست‌وجوی سریع کالا
    case 'product_search': {
        $q = trim((string)get('q'));
        if (mb_strlen($q) < 2) {
            json_out(['ok' => true, 'items' => []]);
        }
        $like = '%' . $q . '%';
        $stmt = $db->prepare('SELECT id, name, brand, price, icon, stock, unit FROM products
                              WHERE is_active = 1 AND (name LIKE ? OR brand LIKE ? OR tax_id LIKE ? OR sku LIKE ?)
                              ORDER BY sold DESC LIMIT 8');
        $stmt->execute([$like, $like, $like, $like]);
        $items = [];
        foreach ($stmt->fetchAll() as $p) {
            $items[] = [
                'id' => (int)$p['id'],
                'name' => $p['name'],
                'brand' => $p['brand'],
                'price' => money($p['price']),
                'icon' => $p['icon'],
                'available' => (int)$p['stock'] > 0,
                'url' => product_url($p['id']),
            ];
        }
        json_out(['ok' => true, 'items' => $items]);
    }

    // -------------------------------------------------- علاقه‌مندی
    case 'favorite': {
        if (!csrf_verify($input['csrf_token'] ?? '')) {
            json_out(['ok' => false, 'error' => 'توکن امنیتی نامعتبر است.'], 403);
        }
        if (!is_buyer()) {
            json_out(['ok' => false, 'error' => 'برای ذخیره علاقه‌مندی‌ها وارد حساب خریدار شوید.'], 401);
        }
        $pid = (int)($input['id'] ?? 0);
        if (is_favorite($pid)) {
            $db->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?')->execute([user_id(), $pid]);
            json_out(['ok' => true, 'active' => false, 'message' => 'از علاقه‌مندی‌ها حذف شد.']);
        }
        $db->prepare('INSERT INTO favorites (user_id, product_id, created_at) VALUES (?, ?, ?)')->execute([user_id(), $pid, date('Y-m-d H:i:s')]);
        json_out(['ok' => true, 'active' => true, 'message' => 'به علاقه‌مندی‌ها اضافه شد.']);
    }

    // -------------------------------------------------- تعداد اعلان‌ها
    case 'notifications': {
        if (!is_logged_in()) {
            json_out(['ok' => true, 'unread' => 0, 'items' => []]);
        }
        $stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 6');
        $stmt->execute([user_id()]);
        $items = [];
        foreach ($stmt->fetchAll() as $n) {
            $items[] = [
                'id' => (int)$n['id'],
                'title' => $n['title'],
                'body' => $n['body'],
                'link' => $n['link'],
                'read' => (bool)$n['is_read'],
                'time' => time_ago($n['created_at']),
            ];
        }
        json_out(['ok' => true, 'unread' => unread_notifications(), 'items' => $items]);
    }

    // -------------------------------------------------- خلاصه موجودی یک کالا
    case 'stock': {
        $pid = (int)get('id');
        $stmt = $db->prepare('SELECT stock, unit, is_active FROM products WHERE id = ?');
        $stmt->execute([$pid]);
        $p = $stmt->fetch();
        if (!$p) {
            json_out(['ok' => false, 'error' => 'کالا یافت نشد.'], 404);
        }
        json_out(['ok' => true, 'stock' => (int)$p['stock'], 'unit' => $p['unit'], 'active' => (bool)$p['is_active']]);
    }

    default:
        json_out(['ok' => false, 'error' => 'درخواست نامعتبر'], 400);
}
