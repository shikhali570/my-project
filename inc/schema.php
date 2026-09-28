<?php
/**
 * ساختار پایگاه داده - با قابلیت مهاجرت (Migration) روی نسخه‌های قدیمی
 * تمام جداول با CREATE TABLE IF NOT EXISTS ساخته می‌شوند و ستون‌های جدید
 * در صورت نبود، با ALTER TABLE به جداول موجود اضافه می‌گردند.
 */

function install_schema(PDO $db)
{
    $tables = [];

    // کاربران (مدیر سیستم و خریداران)
    $tables['users'] = "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role TEXT NOT NULL DEFAULT 'buyer',
        name TEXT NOT NULL,
        company TEXT,
        phone TEXT UNIQUE NOT NULL,
        email TEXT,
        password_hash TEXT,
        national_id TEXT,
        economic_code TEXT,
        postal_code TEXT,
        province TEXT,
        city TEXT,
        address TEXT,
        credit INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'active',
        notes TEXT,
        created_at TEXT,
        last_login_at TEXT
    )";

    // گروه‌بندی کالا
    $tables['categories'] = "CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL,
        title TEXT NOT NULL,
        icon TEXT DEFAULT '📦',
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1
    )";

    // کالاها
    $tables['products'] = "CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sku TEXT,
        name TEXT NOT NULL,
        category TEXT NOT NULL,
        brand TEXT NOT NULL,
        price INTEGER NOT NULL DEFAULT 0,
        old_price INTEGER,
        tax_id TEXT NOT NULL,
        unit TEXT DEFAULT 'عدد',
        stock INTEGER NOT NULL DEFAULT 0,
        min_stock INTEGER NOT NULL DEFAULT 5,
        icon TEXT DEFAULT '📦',
        image TEXT,
        description TEXT,
        specs TEXT,
        is_active INTEGER NOT NULL DEFAULT 1,
        sold INTEGER NOT NULL DEFAULT 0,
        views INTEGER NOT NULL DEFAULT 0,
        created_at TEXT
    )";

    // سفارش‌ها
    $tables['orders'] = "CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_no TEXT UNIQUE NOT NULL,
        user_id INTEGER,
        customer_name TEXT NOT NULL,
        company TEXT,
        phone TEXT NOT NULL,
        email TEXT,
        tax_id TEXT,
        province TEXT,
        city TEXT,
        address TEXT,
        note TEXT,
        subtotal INTEGER NOT NULL DEFAULT 0,
        discount INTEGER NOT NULL DEFAULT 0,
        tax_amount INTEGER NOT NULL DEFAULT 0,
        shipping INTEGER NOT NULL DEFAULT 0,
        total INTEGER NOT NULL DEFAULT 0,
        coupon_code TEXT,
        status TEXT NOT NULL DEFAULT 'pending',
        payment_status TEXT NOT NULL DEFAULT 'unpaid',
        payment_method TEXT DEFAULT 'transfer',
        tracking_code TEXT,
        admin_note TEXT,
        created_at TEXT,
        updated_at TEXT
    )";

    // اقلام سفارش
    $tables['order_items'] = "CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        product_id INTEGER,
        name TEXT NOT NULL,
        brand TEXT,
        tax_id TEXT,
        price INTEGER NOT NULL,
        qty INTEGER NOT NULL,
        total INTEGER NOT NULL
    )";

    // صورتحساب‌های الکترونیکی (سامانه مؤدیان)
    $tables['invoices'] = "CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_no TEXT UNIQUE NOT NULL,
        tax_unique_id TEXT UNIQUE NOT NULL,
        order_id INTEGER,
        user_id INTEGER,
        buyer_name TEXT NOT NULL,
        buyer_phone TEXT NOT NULL,
        buyer_tax_id TEXT,
        subtotal INTEGER NOT NULL DEFAULT 0,
        tax_amount INTEGER NOT NULL DEFAULT 0,
        total_amount INTEGER NOT NULL DEFAULT 0,
        items_json TEXT NOT NULL,
        created_at TEXT NOT NULL,
        status TEXT DEFAULT 'ثبت قطعی در سامانه مؤدیان'
    )";

    // استعلام قیمت (RFQ)
    $tables['rfqs'] = "CREATE TABLE IF NOT EXISTS rfqs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rfq_code TEXT UNIQUE NOT NULL,
        user_id INTEGER,
        company TEXT NOT NULL,
        phone TEXT NOT NULL,
        title TEXT,
        description TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'new',
        quote_amount INTEGER,
        admin_reply TEXT,
        quoted_at TEXT,
        created_at TEXT NOT NULL
    )";

    // کدهای تخفیف
    $tables['coupons'] = "CREATE TABLE IF NOT EXISTS coupons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        type TEXT NOT NULL DEFAULT 'percent',
        amount INTEGER NOT NULL DEFAULT 0,
        min_total INTEGER NOT NULL DEFAULT 0,
        max_uses INTEGER NOT NULL DEFAULT 0,
        used INTEGER NOT NULL DEFAULT 0,
        expires_at TEXT,
        is_active INTEGER NOT NULL DEFAULT 1,
        description TEXT,
        created_at TEXT
    )";

    // علاقه‌مندی‌ها
    $tables['favorites'] = "CREATE TABLE IF NOT EXISTS favorites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        created_at TEXT
    )";

    // پیام‌ها و اعلان‌های کاربر
    $tables['notifications'] = "CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        body TEXT,
        link TEXT,
        is_read INTEGER NOT NULL DEFAULT 0,
        created_at TEXT
    )";

    // گزارش رویدادهای سیستم
    $tables['activity_logs'] = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        actor_name TEXT,
        action TEXT NOT NULL,
        entity TEXT,
        entity_id INTEGER,
        details TEXT,
        ip TEXT,
        created_at TEXT
    )";

    // تنظیمات سایت
    $tables['settings'] = "CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )";

    foreach ($tables as $sql) {
        $db->exec($sql);
    }

    // ستون‌هایی که ممکن است در نسخه‌های قبلی نبوده باشند
    $sync = [
        'users'        => ['role' => "TEXT NOT NULL DEFAULT 'buyer'", 'email' => 'TEXT', 'password_hash' => 'TEXT', 'credit' => 'INTEGER NOT NULL DEFAULT 0', 'status' => "TEXT NOT NULL DEFAULT 'active'", 'notes' => 'TEXT', 'province' => 'TEXT', 'city' => 'TEXT', 'created_at' => 'TEXT', 'last_login_at' => 'TEXT'],
        'products'     => ['sku' => 'TEXT', 'unit' => "TEXT DEFAULT 'عدد'", 'min_stock' => 'INTEGER NOT NULL DEFAULT 5', 'old_price' => 'INTEGER', 'image' => 'TEXT', 'description' => 'TEXT', 'specs' => 'TEXT', 'is_active' => 'INTEGER NOT NULL DEFAULT 1', 'sold' => 'INTEGER NOT NULL DEFAULT 0', 'views' => 'INTEGER NOT NULL DEFAULT 0', 'created_at' => 'TEXT'],
        'orders'       => ['user_id' => 'INTEGER', 'company' => 'TEXT', 'email' => 'TEXT', 'province' => 'TEXT', 'city' => 'TEXT', 'address' => 'TEXT', 'note' => 'TEXT', 'discount' => 'INTEGER NOT NULL DEFAULT 0', 'shipping' => 'INTEGER NOT NULL DEFAULT 0', 'coupon_code' => 'TEXT', 'payment_method' => "TEXT DEFAULT 'transfer'", 'tracking_code' => 'TEXT', 'admin_note' => 'TEXT', 'updated_at' => 'TEXT'],
        'invoices'     => ['order_id' => 'INTEGER', 'user_id' => 'INTEGER'],
        'rfqs'         => ['user_id' => 'INTEGER', 'title' => 'TEXT', 'status' => "TEXT NOT NULL DEFAULT 'new'", 'quote_amount' => 'INTEGER', 'admin_reply' => 'TEXT', 'quoted_at' => 'TEXT'],
    ];
    foreach ($sync as $table => $columns) {
        $existing = [];
        foreach ($db->query("PRAGMA table_info($table)") as $col) {
            $existing[] = $col['name'];
        }
        foreach ($columns as $name => $definition) {
            if (!in_array($name, $existing, true)) {
                $db->exec("ALTER TABLE $table ADD COLUMN $name $definition");
            }
        }
    }

    // نمایه‌ها برای سرعت جست‌وجو
    foreach ([
        'CREATE INDEX IF NOT EXISTS idx_products_cat ON products(category)',
        'CREATE INDEX IF NOT EXISTS idx_orders_user ON orders(user_id)',
        'CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)',
        'CREATE INDEX IF NOT EXISTS idx_items_order ON order_items(order_id)',
        'CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id, is_read)',
        'CREATE INDEX IF NOT EXISTS idx_rfq_user ON rfqs(user_id)',
    ] as $idx) {
        $db->exec($idx);
    }

    // مقادیر پیش‌فرض تنظیمات
    $defaults = [
        'site_name'        => 'پارس سازه و آفیس',
        'site_slogan'      => 'مرجع تأمین تجهیزات دفاتر فنی و عمرانی',
        'phone'            => '۰۲۱-۸۸۷۷۶۶۵۵',
        'email'            => 'sales@parssazeh.ir',
        'address'          => 'تهران، خیابان بهشتی، ساختمان پارس، طبقه ۳',
        'vat_rate'         => '10',
        'shipping_cost'    => '250000',
        'free_shipping_min'=> '50000000',
        'company_national_id' => '۱۰۱۰۹۹۸۸۷۷۶',
        'company_economic_code' => '۴۱۱۵۶۷۸۹۰۰۱',
        'bank_info'        => 'بانک ملت | شبا: IR۱۲ ۰۱۲۰ ۰۰۰۰ ۰۰۰۰ ۱۲۳۴ ۵۶۷۸ ۹۰ | به نام شرکت پارس سازه و آفیس',
        'invoice_prefix'   => 'PSA',
        'work_hours'       => 'شنبه تا چهارشنبه ۸ الی ۱۷:۳۰ | پنجشنبه ۸ الی ۱۳',
    ];
    $stmt = $db->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
    }
}
