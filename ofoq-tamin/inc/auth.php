<?php
/**
 * احراز هویت: ورود، ثبت‌نام، خروج، ویرایش پروفایل و تغییر گذرواژه
 * (پیش از هر خروجی HTML اجرا می‌شود)
 */

$authAction = $_POST['auth_action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $authAction !== '') {
    csrf_guard();
}

// ------------------------------------------------------------------ ورود
if ($authAction === 'login') {
    $phone = en_digits(post('phone'));
    $password = (string)($_POST['password'] ?? '');

    // بازگشت به صفحه ورود با حفظ شماره و مقصد (next)
    $nextParam = get('next');
    $loginBack = 'index.php?page=login' . (preg_match('/^[a-z_]+$/', $nextParam) ? '&next=' . $nextParam : '');

    if (!valid_phone($phone) || $password === '') {
        remember_form('login', ['phone' => post('phone')], []);
        flash('شماره همراه یا گذرواژه نامعتبر است.', 'error');
        redirect($loginBack);
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE phone = ?');
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        log_action('login_failed', 'user', null, 'تلاش ناموفق ورود با شماره ' . $phone);
        remember_form('login', ['phone' => post('phone')], []);
        flash('شماره همراه یا گذرواژه اشتباه است.', 'error');
        redirect($loginBack);
    }

    if ($user['status'] !== 'active') {
        remember_form('login', ['phone' => post('phone')], []);
        flash('حساب کاربری شما غیرفعال است. با پشتیبانی تماس بگیرید.', 'error');
        redirect($loginBack);
    }

    // ارتقای خودکار هش در صورت نیاز
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $upd = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    $upd = $db->prepare('UPDATE users SET last_login_at = ? WHERE id = ?');
    $upd->execute([date('Y-m-d H:i:s'), $user['id']]);

    $_SESSION['uid'] = (int)$user['id'];
    log_action('login', 'user', $user['id'], 'ورود موفق به سامانه');

    $next = get('next') ?: ($user['role'] === 'admin' ? 'admin' : 'panel');
    if (!preg_match('/^[a-z_]+$/', $next)) {
        $next = $user['role'] === 'admin' ? 'admin' : 'panel';
    }

    flash('خوش آمدید ' . $user['name'] . ' عزیز!', 'success');
    redirect('index.php?page=' . $next);
}

// -------------------------------------------------------------- ثبت‌نام
if ($authAction === 'register') {
    $entityType = post('entity_type');
    $name = post('name');
    $company = post('company');
    $phone = en_digits(post('phone'));
    $email = post('email');
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');
    $nationalId = en_digits(post('national_id'));
    $economicCode = en_digits(post('economic_code'));
    $postalCode = en_digits(post('postal_code'));
    $city = post('city');
    $province = post('province');
    $address = post('address');

    $errors = [];
    if (!in_array($entityType, ['individual', 'legal'], true)) {
        $errors[] = 'شخص حقیقی یا حقوقی بودن خریدار را انتخاب کنید.';
    }
    if (mb_strlen($name) < 3) {
        $errors[] = 'نام و نام خانوادگی خریدار یا نماینده را کامل وارد کنید.';
    }
    if ($entityType === 'legal' && mb_strlen($company) < 2) {
        $errors[] = 'نام کامل شرکت یا مؤسسه را وارد کنید.';
    }
    if (in_array($entityType, ['individual', 'legal'], true)) {
        $idLength = $entityType === 'individual' ? 10 : 11;
        if (!preg_match('/^[0-9]{' . $idLength . '}$/', $nationalId)) {
            $errors[] = $entityType === 'individual'
                ? 'کد ملی شخص حقیقی باید ۱۰ رقم باشد.'
                : 'شناسه ملی شخص حقوقی باید ۱۱ رقم باشد.';
        }
    }
    if ($entityType === 'legal' && $economicCode !== '' && !preg_match('/^[0-9]{1,20}$/', $economicCode)) {
        $errors[] = 'کد اقتصادی باید فقط شامل رقم باشد.';
    }
    if (!valid_phone($phone)) {
        $errors[] = 'شماره همراه باید ۱۱ رقم و با ۰۹ شروع شود.';
    }
    if (!preg_match('/^[0-9]{10}$/', $postalCode)) {
        $errors[] = 'کد پستی باید ۱۰ رقم باشد.';
    }
    if ($province === '') {
        $errors[] = 'استان را انتخاب کنید.';
    }
    if (mb_strlen($city) < 2) {
        $errors[] = 'نام شهر را وارد کنید.';
    }
    if (mb_strlen($address) < 10) {
        $errors[] = 'نشانی کامل محل صدور صورتحساب را وارد کنید.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'گذرواژه باید حداقل ۶ کاراکتر باشد.';
    }
    if ($password !== $password2) {
        $errors[] = 'تکرار گذرواژه مطابقت ندارد.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'قالب ایمیل صحیح نیست.';
    }

    if ($errors) {
        foreach ($errors as $err) {
            flash($err, 'error');
        }
        $_SESSION['old_register'] = [
            'entity_type' => $entityType,
            'name' => $name,
            'company' => $company,
            'phone' => $phone,
            'email' => $email,
            'national_id' => $nationalId,
            'economic_code' => $economicCode,
            'postal_code' => $postalCode,
            'city' => $city,
            'province' => $province,
            'address' => $address,
        ];
        redirect('index.php?page=register');
    }

    $company = $entityType === 'legal' ? $company : '';
    $economicCode = $entityType === 'legal' ? $economicCode : '';
    $stmt = $db->prepare('SELECT id FROM users WHERE phone = ?');
    $stmt->execute([$phone]);
    if ($stmt->fetchColumn()) {
        flash('این شماره همراه قبلاً ثبت شده است. وارد شوید یا شماره دیگری وارد کنید.', 'error');
        redirect('index.php?page=login');
    }

    $stmt = $db->prepare('INSERT INTO users (role, entity_type, name, company, phone, email, password_hash, national_id, economic_code, postal_code, province, city, address, status, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        'buyer', $entityType, $name, $company ?: null, $phone, $email ?: null, password_hash($password, PASSWORD_DEFAULT),
        $nationalId, $economicCode ?: null, $postalCode, $province, $city, $address, 'active', date('Y-m-d H:i:s'),
    ]);
    $uid = (int)$db->lastInsertId();

    $_SESSION['uid'] = $uid;
    notify($uid, 'حساب کاربری شما ایجاد شد', 'پنل خریدار شامل پیگیری سفارش‌ها، صورتحساب‌های الکترونیکی و استعلام قیمت‌ها فعال شد.', 'index.php?page=panel');
    notify_admins('خریدار جدید ثبت‌نام کرد', ($company ?: $name) . ' با شماره ' . $phone . ' حساب خریدار ایجاد کرد.', 'index.php?page=admin_users&id=' . $uid);
    log_action('register', 'user', $uid, 'ثبت‌نام خریدار جدید');

    flash('ثبت‌نام با موفقیت انجام شد. به پنل خریدار خوش آمدید!', 'success');
    redirect('index.php?page=panel');
}

// ----------------------------------------------------------------- خروج
if (($authAction === 'logout') || get('action') === 'logout') {
    log_action('logout', 'user', user_id(), 'خروج از حساب کاربری');
    unset($_SESSION['uid']);
    flash('با موفقیت از حساب خارج شدید.', 'info');
    redirect('index.php?page=home');
}

// ----------------------------------------------------- ویرایش پروفایل و رمز
if ($authAction === 'profile_update' && is_logged_in()) {
    $u = current_user();
    $name = post('name');
    $company = post('company');
    $email = post('email');
    $nationalId = en_digits(post('national_id'));
    $economicCode = en_digits(post('economic_code'));
    $postalCode = en_digits(post('postal_code'));
    $province = post('province');
    $city = post('city');
    $address = post('address');
    $phone = $u['role'] === 'admin' ? en_digits(post('phone')) : $u['phone'];

    if (mb_strlen($name) < 3) {
        flash('نام وارد شده معتبر نیست.', 'error');
        redirect('index.php?page=' . (is_admin() ? 'admin_profile' : 'panel_profile'));
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('قالب ایمیل صحیح نیست.', 'error');
        redirect('index.php?page=' . (is_admin() ? 'admin_profile' : 'panel_profile'));
    }
    if (in_array(($u['entity_type'] ?? ''), ['individual', 'legal'], true)) {
        $idLength = $u['entity_type'] === 'individual' ? 10 : 11;
        $profileIncomplete = !preg_match('/^[0-9]{' . $idLength . '}$/', $nationalId)
            || !preg_match('/^[0-9]{10}$/', $postalCode)
            || $province === ''
            || mb_strlen($city) < 2
            || mb_strlen($address) < 10
            || ($u['entity_type'] === 'legal' && mb_strlen($company) < 2)
            || ($economicCode !== '' && !preg_match('/^[0-9]{1,20}$/', $economicCode));
        if ($profileIncomplete) {
            flash('اطلاعات صورتحساب را کامل کنید: شناسه هویتی متناسب با نوع خریدار، کد پستی ۱۰ رقمی، استان، شهر و نشانی کامل لازم است.', 'error');
            redirect('index.php?page=panel_profile');
        }
    }
    if (is_admin() && $phone !== $u['phone'] && valid_phone($phone)) {
        $chk = $db->prepare('SELECT COUNT(*) FROM users WHERE phone = ? AND id != ?');
        $chk->execute([$phone, $u['id']]);
        if (!$chk->fetchColumn()) {
            $db->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$phone, $u['id']]);
        }
    }

    $stmt = $db->prepare('UPDATE users SET name = ?, company = ?, email = ?, national_id = ?, economic_code = ?, postal_code = ?, province = ?, city = ?, address = ? WHERE id = ?');
    $stmt->execute([$name, $company ?: null, $email ?: null, $nationalId ?: null, $economicCode ?: null, $postalCode ?: null, $province ?: null, $city ?: null, $address ?: null, $u['id']]);

    log_action('profile_update', 'user', $u['id'], 'ویرایش اطلاعات کارپوشه');
    flash('اطلاعات کارپوشه با موفقیت به‌روزرسانی شد.', 'success');
    redirect('index.php?page=' . (is_admin() ? 'admin_profile' : 'panel_profile'));
}

if ($authAction === 'password_change' && is_logged_in()) {
    $u = current_user();
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $repeat = (string)($_POST['new_password2'] ?? '');
    $back = 'index.php?page=' . (is_admin() ? 'admin_profile' : 'panel_profile');

    if (!password_verify($current, $u['password_hash'])) {
        flash('گذرواژه فعلی صحیح نیست.', 'error');
        redirect($back);
    }
    if (strlen($new) < 6) {
        flash('گذرواژه جدید باید حداقل ۶ کاراکتر باشد.', 'error');
        redirect($back);
    }
    if ($new !== $repeat) {
        flash('تکرار گذرواژه جدید مطابقت ندارد.', 'error');
        redirect($back);
    }

    $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
    log_action('password_change', 'user', $u['id'], 'تغییر گذرواژه حساب');
    flash('گذرواژه با موفقیت تغییر یافت.', 'success');
    redirect($back);
}
