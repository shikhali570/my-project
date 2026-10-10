<?php
/**
 * داده‌های اولیه سیستم (فقط زمانی که جدول‌ها خالی باشند درج می‌شوند)
 */
function seed_database(PDO $db)
{
    seed_categories($db);
    seed_products($db);
    seed_users($db);
    seed_coupons($db);
    seed_demo_orders($db);
    seed_demo_rfqs($db);
    seed_notifications($db);
    seed_logs($db);
}

function seed_categories(PDO $db)
{
    if ((int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn() > 0) {
        return;
    }
    $rows = [
        ['surveying', 'ابزار دقیق و نقشه‌برداری', '📏', 1],
        ['hse', 'ایمنی و HSE کارگاهی', '🦺', 2],
        ['plotter', 'رول و چاپ نقشه', '🖨️', 3],
        ['stationery', 'بایگانی و لوازم‌التحریر', '📁', 4],
        ['electrical', 'تجهیزات برقی و روشنایی', '💡', 5],
        ['tools', 'ابزارآلات دستی و برقی', '🔧', 6],
    ];
    $stmt = $db->prepare('INSERT INTO categories (slug, title, icon, sort_order) VALUES (?, ?, ?, ?)');
    foreach ($rows as $r) {
        $stmt->execute($r);
    }
}

function seed_products(PDO $db)
{
    if ((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn() > 0) {
        return;
    }

    $now = date('Y-m-d H:i:s');
    $rows = [
        ['SKU-1001', 'متر لیزری ۱۰۰ متری لایکا Disto D2 بلوتوث‌دار', 'surveying', 'Leica', 9800000, 11200000, '2710000185962', 'دستگاه', 12, 3, '📏', 'متر لیزری حرفه‌ای با برد ۱۰۰ متر، دقت ±۱.۵ میلی‌متر و اتصال بلوتوث به اپلیکیشن DISTO Plan برای انتقال سریع نقشه‌های اندازه‌گیری.', 'برد: ۱۰۰ متر | دقت: ±۱.۵mm | بلوتوث ۴.۰ | باتری: لیتیوم‌یونی | ضدآب: IP54', 1, 24],
        ['SKU-1002', 'تراز لیزری ۳۶۰ درجه سه خط سبز بوش GLL 3-80', 'surveying', 'Bosch', 16500000, null, '2710000185963', 'دستگاه', 6, 2, '📐', 'تراز لیزری سه‌پلین با خطوط سبز، مناسب اجرای پارتیشن، سقف کاذب و کنترل تراز در پروژه‌های عمرانی.', 'برد کار: ۳۰ متر | خطوط: ۳ پلین ۳۶۰° | دقت: ±۰.۲mm/m | رده لیزری: ۲', 1, 31],
        ['SKU-1003', 'تریپاد آلومینیومی دوربین نقشه‌برداری با تراز کروی', 'surveying', 'Leica', 5400000, null, '2710000185964', 'عدد', 9, 2, '🔭', 'سه‌پایه آلومینیومی سبک با قفل‌های پیچی، ارتفاع قابل تنظیم و تراز کروی دقت بالا برای دوربین‌های تئودولیت و توتال استیشن.', 'ارتفاع: ۱۰۵ تا ۱۶۵ سانتی‌متر | وزن: ۴.۲ کیلوگرم | پیچ اتصال: ۵/۸ اینچ', 1, 7],
        ['SKU-2001', 'کلاه ایمنی عایق برق مهندسی JSP کلاس E', 'hse', 'JSP', 680000, 790000, '2710000294110', 'عدد', 45, 10, '⛑️', 'کلاه ایمنی عایق با تست ۲۰۰۰۰ ولت، مناسب کارهای برق‌کاری و پیمانکاری، دارای گیره چانه و قابلیت نصب گوشی و محافظ صورت.', 'استاندارد: EN397 / EN50365 | کلاس عایقی: E | وزن: ۳۸۵ گرم', 1, 130],
        ['SKU-2002', 'جلیقه شبرنگ ۴ جیب زیپی اعلا (بسته ۵ عددی)', 'hse', 'SafeTech', 295000, null, '2710000294111', 'بسته', 80, 20, '🦺', 'جلیقه شبرنگ با نوارهای شب‌نما، پارچه تنفسی و چهار جیب زیپی برای نگهداری دفترچه و ابزار کوچک.', 'سایز: فری‌سایز | جنس: پلی‌استر مش | نوار شب‌نما: ۵ سانتی‌متری', 1, 420],
        ['SKU-2003', 'کفش ایمنی ساق‌بلند کلاس S3 ضدسایش', 'hse', 'Tiger', 1890000, null, '2710000294112', 'جفت', 26, 6, '🥾', 'کفش ایمنی با پنجه فولادی، کف ضدلغزش و ضدسوراخ، مناسب کارگاه‌های ساختمانی و صنایع سنگین.', 'استاندارد: EN ISO 20345 S3 | پنجه: فولاد ۲۰۰ ژول | سایز: ۴۰ تا ۴۵', 1, 64],
        ['SKU-3001', 'رول پلاتر تحریر ۸۰ گرم عرض ۹۰ سانت (۵۰ متری)', 'plotter', 'Double A', 740000, 850000, '2710000389104', 'رول', 30, 8, '🖨️', 'رول کاغذ پلاتر سفید درجه یک با گراماژ ۸۰ گرم، مناسب چاپ نقشه‌های اجرایی، شیت‌های A1 و A0 در دفاتر فنی.', 'عرض: ۹۰ سانتی‌متر | طول: ۵۰ متر | گراماژ: ۸۰ گرم | سفیدی: ۱۵۰ CIE', 1, 210],
        ['SKU-3002', 'رول پلاتر کالک شفاف عرض ۹۱ سانت', 'plotter', 'HP', 2150000, null, '2710000389105', 'رول', 14, 4, '📜', 'کالک شفاف مخصوص پلاترهای رول‌به‌رول، مناسب ارائه نقشه‌ها و رونوشت‌برداری صنعتی.', 'عرض: ۹۱ سانتی‌متر | طول: ۵۰ متر | شفافیت: بالا', 1, 38],
        ['SKU-3003', 'کارتریج جوهر مشکی پلاتر HP DesignJet 712', 'plotter', 'HP', 4650000, null, '2710000389106', 'عدد', 11, 3, '🖋️', 'کارتریج اصلی مشکی ۷۱۲ با حجم ۸۰ میلی‌لیتر مناسب چاپ مستمر نقشه در پلاترهای سری DesignJet T و Z.', 'حجم: ۸۰ml | رنگ: مشکی | سازگار: DesignJet T120/T520/T730', 1, 46],
        ['SKU-4001', 'زونکن عطف ۸ سانت لبه فلزی پاپکو', 'stationery', 'Papco', 185000, null, '2710000512892', 'عدد', 100, 25, '📁', 'زونکن با عطف ۸ سانتی‌متر، لبه فلزی، ارابه مقاوم و قابلیت بازیافت، مناسب آرشیو نقشه و اسناد پروژه.', 'عطف: ۸ سانتی‌متر | جنس: مقوای فشرده | رنگ‌بندی: کدبندی خودکار', 1, 350],
        ['SKU-4002', 'پوشه نقشه A1 با لبه شبرنگ (بسته ۱۰ عددی)', 'stationery', 'Behzad', 320000, null, '2710000512893', 'بسته', 48, 12, '🗂️', 'پوشه نقشه مقاوم با لبه شبرنگ مخصوص نگهداری نقشه‌های A1، ضدآب و مقاوم به پارگی.', 'سایز: A1 | جنس: PVC | لبه: شبرنگ رنگی', 1, 96],
        ['SKU-5001', 'پرژکتور ال‌ای‌دی ۲۰۰ وات برای نورافکن کارگاهی', 'electrical', 'ParsLight', 3450000, 3900000, '2710000611200', 'عدد', 22, 5, '💡', 'پرژکتور ال‌ای‌دی پروژه‌ای با بدنه آلومینیومی، درجه حفاظتی IP66 و امکان نصب روی دکل روشنایی کارگاه.', 'توان: ۲۰۰ وات | شار نوری: ۲۰۰۰۰ لومن | IP66 | گارانتی: ۳ سال', 1, 58],
        ['SKU-5002', 'کابل افشان ۳×۲.۵ برق کارگاهی (حلقه ۱۰۰ متری)', 'electrical', 'Simcat', 5600000, null, '2710000611201', 'حلقه', 17, 5, '🔌', 'کابل افشان مسی با روکش PVC مناسب تغذیه موقت کارگاه و تابلو برق پروژه.', 'سطح مقطع: ۳×۲.۵ | جنس هادی: مس آنیل | طول: ۱۰۰ متر', 1, 40],
        ['SKU-6001', 'دریل چکشی ۱۸ ولت بوش با دو باتری', 'tools', 'Bosch', 8900000, 9600000, '2710000715400', 'دستگاه', 15, 4, '🔧', 'دریل چکشی بی‌سیم حرفه‌ای با دو باتری ۲ آمپرساعت، گیربکس دو سرعته و چراغ کار.', 'ولتاژ: ۱۸V | گشتاور: ۵۰ نیوتون‌متر | دو باتری + شارژر | گارانتی: ۱۲ ماه', 1, 33],
        ['SKU-6002', 'ست آچار بکس ۲۴ پارچه رینگی چرخ‌دنده', 'tools', 'Neiko', 2750000, null, '2710000715401', 'ست', 28, 6, '🛠️', 'ست کامل آچار رینگی چرخ‌دنده با کیف حمل، فولاد کروم وانادیوم و آبکاری مات ضدزنگ.', 'تعداد: ۲۴ عدد | سایز: ۶ تا ۳۲ میلی‌متر | جنس: Cr-V', 1, 74],
        ['SKU-6003', 'شیلنگ تراز ۵۰ متری صنعتی با رزرو لاستیکی', 'tools', 'Ken', 890000, null, '2710000715402', 'عدد', 0, 4, '🧰', 'شیلنگ تراز صنعتی با دو سر شیشه مدرج، مناسب اجرای کرسی‌چینی و کنترل تراز المان‌های سازه‌ای.', 'طول: ۵۰ متر | قطر: ۱۰ میلی‌متر | مقاوم: تا ۶۰ درجه سانتی‌گراد', 1, 18],
    ];

    $stmt = $db->prepare('INSERT INTO products (sku, name, category, brand, price, old_price, tax_id, unit, stock, min_stock, icon, description, specs, is_active, sold, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($rows as $r) {
        $r[] = $now;
        $stmt->execute($r);
    }
}

function seed_users(PDO $db)
{
    if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    $users = [
        ['admin', 'مدیر سیستم', 'پارس سازه و آفیس', '09120000000', 'admin@parssazeh.ir', 'admin1234',
            '۱۰۱۰۹۹۸۸۷۷۶', '۴۱۱۵۶۷۸۹۰۰۱', '1587563124', 'تهران', 'تهران',
            'تهران، خیابان بهشتی، ساختمان پارس، طبقه ۳', 0, 'active'],
        ['buyer', 'مهندس علوی', 'شرکت مهندسی بناسازان', '09121111111', 'alavi@banasazan.ir', 'buyer1234',
            '10103456789', '411589342110', '1587563124', 'تهران', 'تهران',
            'تهران، خیابان گاندی، پلاک ۴۲، دفتر پروژه', 15000000, 'active'],
        ['buyer', 'مهندس رضایی', 'پیمانکاری سازه گستر البرز', '09122222222', 'rezaei@sazegostar.ir', 'buyer1234',
            '10320456781', '411903342117', '3156948721', 'البرز', 'کرج',
            'کرج، بلوار طالقانی، مجتمع اداری آریا، واحد ۷', 4200000, 'active'],
        ['buyer', 'مهندس کریمی', 'شرکت عمرانی راه و ساختمان پارس', '09123333333', 'karimi@rahsaz.ir', 'buyer1234',
            '10861234567', '411772209314', '8145631290', 'اصفهان', 'اصفهان',
            'اصفهان، خیابان چهارباغ بالا، ساختمان نگین، طبقه ۲', 0, 'active'],
        ['buyer', 'خانم مهندس شریفی', 'دفتر فنی مهندسی آریا سازه', '09124444444', 'sharifi@ariasazeh.ir', 'buyer1234',
            '10791345672', '411332210087', '1934512780', 'خراسان رضوی', 'مشهد',
            'مشهد، بلوار وکیل‌آباد، برج آلتون، واحد ۱۲۰۴', 0, 'inactive'],
    ];
    $stmt = $db->prepare('INSERT INTO users (role, name, company, phone, email, password_hash, national_id, economic_code, postal_code, province, city, address, credit, status, created_at, last_login_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($users as $u) {
        $u[5] = password_hash($u[5], PASSWORD_DEFAULT);
        $u[] = $now;
        $u[] = $now;
        $stmt->execute($u);
    }
}

function seed_coupons(PDO $db)
{
    if ((int)$db->query('SELECT COUNT(*) FROM coupons')->fetchColumn() > 0) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    $rows = [
        ['PROJECT10', 'percent', 10, 10000000, 100, 4, date('Y-m-d', strtotime('+90 days')), 1, '۱۰٪ تخفیف پروژه‌ای برای سفارش‌های بالای ۱۰ میلیون تومان'],
        ['HSE15', 'percent', 15, 5000000, 50, 1, date('Y-m-d', strtotime('+45 days')), 1, 'تخفیف ویژه تجهیزات ایمنی'],
        ['WELCOME2M', 'fixed', 2000000, 30000000, 200, 2, date('Y-m-d', strtotime('+120 days')), 1, 'اعتبار خوش‌آمدگویی خرید سازمانی'],
        ['NOWRUZ', 'percent', 12, 8000000, 60, 60, date('Y-m-d', strtotime('-10 days')), 0, 'کمپین نوروزی (منقضی شده)'],
    ];
    $stmt = $db->prepare('INSERT INTO coupons (code, type, amount, min_total, max_uses, used, expires_at, is_active, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($rows as $r) {
        $r[] = $now;
        $stmt->execute($r);
    }
}

function seed_demo_orders(PDO $db)
{
    if ((int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn() > 0) {
        return;
    }

    $products = $db->query('SELECT * FROM products')->fetchAll();
    $bySku = [];
    foreach ($products as $p) {
        $bySku[$p['sku']] = $p;
    }

    $vat = 0.10;
    $shipping = 250000;

    $baskets = [
        // [phone, status, payment, days ago, tracking, [[sku, qty], ...]]
        ['09121111111', 'delivered', 'paid', 46, 'TRK-482913', [['SKU-1001', 2], ['SKU-3001', 6]]],
        ['09121111111', 'shipped', 'paid', 12, 'TRK-613452', [['SKU-2002', 10], ['SKU-2001', 8]]],
        ['09122222222', 'preparing', 'paid', 6, null, [['SKU-5001', 4], ['SKU-5002', 3]]],
        ['09122222222', 'pending', 'unpaid', 2, null, [['SKU-6001', 2]]],
        ['09123333333', 'approved', 'unpaid', 4, null, [['SKU-1002', 1], ['SKU-1003', 1]]],
        ['09123333333', 'delivered', 'paid', 74, 'TRK-330014', [['SKU-4001', 40], ['SKU-4002', 12]]],
        ['09121111111', 'canceled', 'refunded', 30, null, [['SKU-3003', 3]]],
        ['09122222222', 'delivered', 'paid', 95, 'TRK-118227', [['SKU-6002', 4], ['SKU-2003', 6]]],
    ];

    $stmtOrder = $db->prepare('INSERT INTO orders (order_no, user_id, customer_name, company, phone, email, tax_id, province, city, address, note, subtotal, discount, tax_amount, shipping, total, coupon_code, status, payment_status, payment_method, tracking_code, admin_note, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmtItem = $db->prepare('INSERT INTO order_items (order_id, product_id, name, brand, tax_id, price, qty, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmtInv = $db->prepare('INSERT INTO invoices (invoice_no, tax_unique_id, order_id, user_id, buyer_name, buyer_phone, buyer_tax_id, subtotal, tax_amount, total_amount, items_json, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $counter = 1;
    foreach ($baskets as $b) {
        list($phone, $status, $pay, $daysAgo, $tracking, $items) = $b;
        $user = $db->prepare('SELECT * FROM users WHERE phone = ?');
        $user->execute([$phone]);
        $u = $user->fetch();
        if (!$u) {
            continue;
        }

        $subtotal = 0;
        $rows = [];
        foreach ($items as $it) {
            $p = $bySku[$it[0]] ?? null;
            if (!$p) {
                continue;
            }
            $line = $p['price'] * $it[1];
            $subtotal += $line;
            $rows[] = [$p, $it[1], $line];
        }
        if (!$rows) {
            continue;
        }

        $discount = 0;
        $coupon = null;
        if ($daysAgo > 40 && $subtotal > 10000000) {
            $discount = (int)round($subtotal * 0.10);
            $coupon = 'PROJECT10';
        }
        $tax = (int)round(($subtotal - $discount) * $vat);
        $ship = $subtotal >= 50000000 ? 0 : $shipping;
        $total = $subtotal - $discount + $tax + $ship;

        $created = date('Y-m-d H:i:s', strtotime('-' . $daysAgo . ' days'));
        $updated = date('Y-m-d H:i:s', strtotime('-' . max(0, $daysAgo - 3) . ' days'));
        $orderNo = 'PSA-' . date('ym', strtotime($created)) . '-' . str_pad((string)$counter, 4, '0', STR_PAD_LEFT);

        $stmtOrder->execute([
            $orderNo, $u['id'], $u['name'], $u['company'], $u['phone'], $u['email'], $u['national_id'],
            $u['province'], $u['city'], $u['address'], null,
            $subtotal, $discount, $tax, $ship, $total, $coupon, $status, $pay, 'transfer',
            $tracking, $status === 'delivered' ? 'تحویل حضوری در انبار مرکزی با رضایت خریدار' : null,
            $created, $updated,
        ]);
        $orderId = (int)$db->lastInsertId();

        $itemsForInvoice = [];
        foreach ($rows as $r) {
            list($p, $qty, $line) = $r;
            $stmtItem->execute([$orderId, $p['id'], $p['name'], $p['brand'], $p['tax_id'], $p['price'], $qty, $line]);
            $itemsForInvoice[] = ['name' => $p['name'], 'brand' => $p['brand'], 'tax_id' => $p['tax_id'], 'unit' => (string)($p['unit'] ?? ''), 'price' => (int)$p['price'], 'qty' => (int)$qty, 'total' => (int)$line];
        }

        // صورتحساب الکترونیکی برای سفارش‌های پرداخت‌شده
        if ($pay === 'paid') {
            $invNo = 'INV-' . date('ym', strtotime($created)) . '-' . random_int(1000, 9999);
            $taxUid = 'A1847-' . random_int(10000000000, 99999999999) . '-0021';
            $stmtInv->execute([
                $invNo, $taxUid, $orderId, $u['id'], $u['company'] ?: $u['name'], $u['phone'], $u['national_id'],
                $subtotal, $tax, $total, json_encode($itemsForInvoice, JSON_UNESCAPED_UNICODE), $created,
                'ثبت قطعی در سامانه مؤدیان',
            ]);
        }
        $counter++;
    }
}

function seed_demo_rfqs(PDO $db)
{
    if ((int)$db->query('SELECT COUNT(*) FROM rfqs')->fetchColumn() > 0) {
        return;
    }
    $rows = [
        ['RFQ-25-101', '09121111111', 'شرکت مهندسی بناسازان', 'تجهیزات دفتر فنی پروژه مسکونی ۱۲ طبقه',
            "۱- ۱۰ حلقه رول پلاتر عرض ۹۰\n۲- ۵ عدد کارتریج مشکی پلاتر\n۳- ۲ دستگاه متر لیزری ۱۰۰ متری",
            'quoted', 48500000, 'پیش‌فاکتور این استعلام آماده شده است؛ زمان تحویل و روش پرداخت هنگام تأیید هماهنگ می‌شود.', 8],
        ['RFQ-25-102', '09122222222', 'پیمانکاری سازه گستر البرز', 'تأمین پک ایمنی کارگاهی فاز دو',
            "۱- ۵۰ عدد کلاه ایمنی عایق کلاس E\n۲- ۶۰ بسته جلیقه شبرنگ\n۳- ۲۵ جفت کفش ایمنی سایز ۴۲",
            'reviewing', null, null, 3],
        ['RFQ-25-103', '09123333333', 'شرکت عمرانی راه و ساختمان پارس', 'روشنایی محوطه و دکل کارگاهی',
            "۱- ۱۲ عدد پرژکتور ال‌ای‌دی ۲۰۰ وات\n۲- ۶ حلقه کابل افشان ۳×۲.۵\n۳- مشاوره اجرای تابلو برق موقت",
            'new', null, null, 1],
        ['RFQ-25-104', '09121111111', 'شرکت مهندسی بناسازان', 'ابزارآلات دستی کارگاه پروژه بندرعباس',
            "۱- ۵ ست آچار بکس ۲۴ پارچه\n۲- ۴ دستگاه دریل چکشی ۱۸ ولت\n۳- ۶ شیلنگ تراز ۵۰ متری",
            'accepted', 21500000, 'سفارش تبدیل به فاکتور رسمی شد و توسط مسئول خرید تأیید گردید.', 21],
    ];
    $stmt = $db->prepare('INSERT INTO rfqs (rfq_code, user_id, company, phone, title, description, status, quote_amount, admin_reply, quoted_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($rows as $r) {
        $user = $db->prepare('SELECT id FROM users WHERE phone = ?');
        $user->execute([$r[1]]);
        $uid = $user->fetchColumn() ?: null;
        $created = date('Y-m-d H:i:s', strtotime('-' . $r[8] . ' days'));
        $quoted = $r[7] ? date('Y-m-d H:i:s', strtotime('-' . max(0, $r[8] - 1) . ' days')) : null;
        $stmt->execute([$r[0], $uid, $r[2], $r[1], $r[3], $r[4], $r[5], $r[6], $r[7], $quoted, $created]);
    }
}

function seed_notifications(PDO $db)
{
    if ((int)$db->query('SELECT COUNT(*) FROM notifications')->fetchColumn() > 0) {
        return;
    }
    $users = $db->query("SELECT id, role FROM users WHERE role = 'buyer'")->fetchAll();
    $stmt = $db->prepare('INSERT INTO notifications (user_id, title, body, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($users as $i => $u) {
        $stmt->execute([$u['id'], 'به پارس سازه و آفیس خوش آمدید', 'کاتالوگ کامل تجهیزات و پنل پیگیری سفارش‌ها در اختیار شماست. با ثبت سفارش، صورتحساب الکترونیکی معتبر در کارپوشه مؤدیان شما ثبت می‌شود.', 'index.php?page=panel_orders', 0, date('Y-m-d H:i:s', strtotime('-' . (5 + $i) . ' days'))]);
        if ($i < 2) {
            $stmt->execute([$u['id'], 'پاسخ استعلام قیمت آماده است', 'کارشناس فروش پیش‌فاکتور سازمانی استعلام شما را ثبت کرد. برای مشاهده جزئیات به بخش استعلام‌ها مراجعه کنید.', 'index.php?page=panel_rfqs', 0, date('Y-m-d H:i:s', strtotime('-' . (2 + $i) . ' days'))]);
        }
    }
}

function seed_logs(PDO $db)
{
    if ((int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn() > 0) {
        return;
    }
    $stmt = $db->prepare('INSERT INTO activity_logs (user_id, actor_name, action, entity, entity_id, details, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([1, 'مدیر سیستم', 'system_seed', 'system', null, 'راه‌اندازی اولیه سامانه و بارگذاری کاتالوگ کالا', '127.0.0.1', date('Y-m-d H:i:s')]);
}
