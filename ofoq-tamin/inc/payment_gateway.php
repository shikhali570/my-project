<?php
/**
 * به‌پرداخت ملت (Mellat BPM) payment gateway.
 * Credentials are encrypted with a per-installation key stored in the protected
 * runtime folder (APP_TMP); the plaintext password is never rendered back.
 */

if (!defined('MELLAT_WSDL_URL')) {
    define('MELLAT_WSDL_URL', 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
}
if (!defined('MELLAT_START_URL')) {
    define('MELLAT_START_URL', 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat');
}

function payment_gateway_key($create = false)
{
    $path = APP_TMP . DIRECTORY_SEPARATOR . 'payment-gateway.key';
    if (is_file($path)) {
        $key = @file_get_contents($path);
        return is_string($key) && strlen($key) === 32 ? $key : false;
    }
    if (!$create || !is_dir(APP_TMP) || !is_writable(APP_TMP)) {
        return false;
    }

    try {
        $key = random_bytes(32);
    } catch (Throwable $e) {
        return false;
    }
    $handle = @fopen($path, 'x+b');
    if (!$handle) {
        // Another request may have created it concurrently.
        if (is_file($path)) {
            $existing = @file_get_contents($path);
            return is_string($existing) && strlen($existing) === 32 ? $existing : false;
        }
        return false;
    }
    $written = fwrite($handle, $key);
    fflush($handle);
    fclose($handle);
    @chmod($path, 0600);
    if ($written !== 32) {
        @unlink($path);
        return false;
    }
    return $key;
}

/** Encrypt a provider secret before it is stored in the settings table. */
function payment_secret_encrypt($plaintext)
{
    if (!function_exists('openssl_encrypt') || $plaintext === '') {
        return false;
    }
    $key = payment_gateway_key(true);
    if ($key === false) {
        return false;
    }
    try {
        $nonce = random_bytes(12);
    } catch (Throwable $e) {
        return false;
    }
    $tag = '';
    $ciphertext = openssl_encrypt((string)$plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'mellat-password-v1', 16);
    if ($ciphertext === false || strlen($tag) !== 16) {
        return false;
    }
    return 'v1:' . base64_encode($nonce . $tag . $ciphertext);
}

/** Decrypt a stored provider secret; returns false if its key is unavailable. */
function payment_secret_decrypt($encrypted)
{
    if (!function_exists('openssl_decrypt') || !is_string($encrypted) || substr($encrypted, 0, 3) !== 'v1:') {
        return false;
    }
    $payload = base64_decode(substr($encrypted, 3), true);
    if (!is_string($payload) || strlen($payload) < 29) {
        return false;
    }
    $key = payment_gateway_key(false);
    if ($key === false) {
        return false;
    }
    $nonce = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $ciphertext = substr($payload, 28);
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'mellat-password-v1');
    return is_string($plaintext) ? $plaintext : false;
}

/** Accept only a public HTTPS origin/path; used as the bank callback base URL. */
function mellat_normalize_site_url($value)
{
    $value = trim((string)$value);
    if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return '';
    }
    $parts = parse_url($value);
    if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])) {
        return '';
    }
    return rtrim($value, '/');
}

function mellat_callback_url()
{
    $base = mellat_normalize_site_url(settings('site_url', ''));
    return $base === '' ? '' : $base . '/index.php?action=mellat_callback';
}

/** Current readiness of the gateway, safe to show to administrators. */
function mellat_gateway_status()
{
    $terminalId = en_digits(settings('mellat_terminal_id', ''));
    $username = trim((string)settings('mellat_username', ''));
    $storedPassword = (string)settings('mellat_password_enc', '');
    $password = $storedPassword !== '' ? payment_secret_decrypt($storedPassword) : false;
    $siteUrl = mellat_normalize_site_url(settings('site_url', ''));
    $enabled = settings('mellat_enabled', '0') === '1';
    $hasCredentials = preg_match('/^[0-9]{1,20}$/', $terminalId) === 1
        && $username !== '' && is_string($password) && $password !== '';
    $soapAvailable = class_exists('SoapClient');
    $opensslAvailable = function_exists('openssl_encrypt') && function_exists('openssl_decrypt');
    $reasons = [];
    if (!$enabled) $reasons[] = 'درگاه غیرفعال است.';
    if (!$hasCredentials) $reasons[] = 'شناسه ترمینال، نام کاربری و رمز درگاه کامل نیست یا کلید رمزگشایی در دسترس نیست.';
    if ($siteUrl === '') $reasons[] = 'نشانی عمومی HTTPS سایت را در همین پنل ثبت کنید.';
    if (!$soapAvailable) $reasons[] = 'افزونه SOAP در PHP فعال نیست؛ از هاست بخواهید php-soap را فعال کند.';
    if (!$opensslAvailable) $reasons[] = 'افزونه OpenSSL برای ذخیره و خواندن امن رمز درگاه فعال نیست.';
    return [
        'enabled' => $enabled,
        'configured' => $hasCredentials,
        'soap_available' => $soapAvailable,
        'openssl_available' => $opensslAvailable,
        'site_url_valid' => $siteUrl !== '',
        'password_saved' => $storedPassword !== '' && is_string($password),
        'ready' => $enabled && $hasCredentials && $siteUrl !== '' && $soapAvailable && $opensslAvailable,
        'reasons' => $reasons,
    ];
}

function mellat_credentials()
{
    $encrypted = (string)settings('mellat_password_enc', '');
    $password = $encrypted !== '' ? payment_secret_decrypt($encrypted) : false;
    if (!is_string($password) || $password === '') {
        return false;
    }
    return [
        'terminal_id' => en_digits(settings('mellat_terminal_id', '')),
        'username' => trim((string)settings('mellat_username', '')),
        'password' => $password,
    ];
}

/** Credentials captured for this attempt allow safe verification after admin rotation. */
function mellat_attempt_credentials(array $attempt)
{
    $encrypted = (string)($attempt['credential_enc'] ?? '');
    if ($encrypted !== '') {
        $plain = payment_secret_decrypt($encrypted);
        if (!is_string($plain)) return false;
        $snapshot = json_decode($plain, true);
        if (!is_array($snapshot) || empty($snapshot['terminal_id']) || empty($snapshot['username'])
            || !isset($snapshot['password']) || !is_string($snapshot['password'])) {
            return false;
        }
        return [
            'terminal_id' => en_digits($snapshot['terminal_id']),
            'username' => (string)$snapshot['username'],
            'password' => (string)$snapshot['password'],
        ];
    }
    return mellat_credentials();
}

/**
 * Invoke one documented Mellat BPM operation. A test double can be supplied as
 * the third argument; production calls always use PHP's SOAP extension and TLS.
 */
function mellat_soap_call($method, array $arguments, $client = null)
{
    if (!in_array($method, ['bpPayRequest', 'bpVerifyRequest', 'bpSettleRequest'], true)) {
        return ['ok' => false, 'value' => '', 'error' => 'unsupported_method'];
    }
    if ($client === null) {
        if (!class_exists('SoapClient')) {
            return ['ok' => false, 'value' => '', 'error' => 'soap_extension_missing'];
        }
        try {
            $sslContext = stream_context_create(['ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ]]);
            $client = new SoapClient(MELLAT_WSDL_URL, [
                'exceptions' => true,
                'trace' => false,
                'connection_timeout' => 20,
                'cache_wsdl' => defined('WSDL_CACHE_NONE') ? WSDL_CACHE_NONE : 0,
                'stream_context' => $sslContext,
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'value' => '', 'error' => 'soap_connection_failed'];
        }
    }
    try {
        // BPM exposes each operation as one request object; preserve the WSDL field names.
        $response = $client->__soapCall($method, [$arguments]);
        if (is_object($response)) {
            $properties = get_object_vars($response);
            if (array_key_exists('return', $properties)) {
                $response = $properties['return'];
            } elseif ($properties) {
                $response = reset($properties);
            }
        } elseif (is_array($response)) {
            $response = reset($response);
        }
        if (!is_scalar($response)) {
            return ['ok' => false, 'value' => '', 'error' => 'invalid_soap_response'];
        }
        return ['ok' => true, 'value' => trim((string)$response), 'error' => ''];
    } catch (Throwable $e) {
        return ['ok' => false, 'value' => '', 'error' => 'soap_operation_failed'];
    }
}

function mellat_attempt_fail(PDO $db, $attemptId, $status, $code = '')
{
    $final = in_array($status, ['request_failed', 'declined', 'verify_failed'], true);
    $sql = $final
        ? 'UPDATE payment_attempts SET status = ?, response_code = ?, credential_enc = NULL, updated_at = ? WHERE id = ? AND status != ?'
        : 'UPDATE payment_attempts SET status = ?, response_code = ?, updated_at = ? WHERE id = ? AND status != ?';
    $db->prepare($sql)->execute([$status, (string)$code, date('Y-m-d H:i:s'), (int)$attemptId, 'paid']);
}

/** Start a fresh BPM payment attempt for an already-created order. */
function mellat_start_order_payment(PDO $db, array $order, $client = null)
{
    $gatewayStatus = mellat_gateway_status();
    if (empty($gatewayStatus['ready'])) {
        return ['ok' => false, 'message' => 'پرداخت آنلاین در حال حاضر آماده نیست. از مدیر بخواهید تنظیمات درگاه را بررسی کند.'];
    }
    $credentials = mellat_credentials();
    if (!$credentials || (int)$order['total'] < 1 || (int)$order['total'] > intdiv(PHP_INT_MAX, 10)) {
        return ['ok' => false, 'message' => 'مبلغ سفارش یا تنظیمات درگاه معتبر نیست.'];
    }
    $callback = mellat_callback_url();
    if ($callback === '') {
        return ['ok' => false, 'message' => 'نشانی HTTPS بازگشت از درگاه تنظیم نشده است.'];
    }

    $credentialJson = json_encode($credentials, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $credentialSnapshot = is_string($credentialJson) ? payment_secret_encrypt($credentialJson) : false;
    if (!is_string($credentialSnapshot)) {
        return ['ok' => false, 'message' => 'ذخیرهٔ امن اعتبارنامه‌های درگاه ممکن نشد؛ تنظیمات کلید رمزنگاری هاست را بررسی کنید.'];
    }
    $now = date('Y-m-d H:i:s');
    $db->prepare("INSERT INTO payment_attempts (order_id, provider, credential_enc, status, created_at, updated_at) VALUES (?, 'mellat', ?, 'initiating', ?, ?)")
        ->execute([(int)$order['id'], $credentialSnapshot, $now, $now]);
    $attemptId = (int)$db->lastInsertId();
    if ($attemptId < 1) {
        return ['ok' => false, 'message' => 'ایجاد شناسه پرداخت ناموفق بود.'];
    }
    $db->prepare('UPDATE payment_attempts SET gateway_order_id = ? WHERE id = ?')->execute([$attemptId, $attemptId]);

    // مبالغ برنامه تومان است؛ سرویس به‌پرداخت ملت مبلغ را به ریال می‌خواهد.
    $amountRial = (int)$order['total'] * 10;
    $arguments = [
        'terminalId' => (int)$credentials['terminal_id'],
        'userName' => $credentials['username'],
        'userPassword' => $credentials['password'],
        'orderId' => $attemptId,
        'amount' => $amountRial,
        'localDate' => date('Ymd'),
        'localTime' => date('His'),
        'additionalData' => (string)$order['order_no'],
        'callBackUrl' => $callback,
        'payerId' => 0,
    ];
    $response = mellat_soap_call('bpPayRequest', $arguments, $client);
    if (empty($response['ok'])) {
        mellat_attempt_fail($db, $attemptId, 'request_failed', $response['error'] ?? '');
        return ['ok' => false, 'message' => 'ارتباط با درگاه برقرار نشد؛ سفارش ثبت شده و می‌توانید پرداخت را دوباره آغاز کنید.'];
    }
    $parts = explode(',', (string)$response['value'], 2);
    $code = trim($parts[0] ?? '');
    $refId = trim($parts[1] ?? '');
    if ($code !== '0' || !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $refId)) {
        mellat_attempt_fail($db, $attemptId, 'request_failed', $code);
        return ['ok' => false, 'message' => 'درگاه درخواست پرداخت را نپذیرفت؛ سفارش ثبت شده و می‌توانید دوباره تلاش کنید.'];
    }
    $db->prepare("UPDATE payment_attempts SET ref_id = ?, status = 'redirected', response_code = ?, updated_at = ? WHERE id = ?")
        ->execute([$refId, $code, date('Y-m-d H:i:s'), $attemptId]);
    return ['ok' => true, 'attempt_id' => $attemptId, 'ref_id' => $refId];
}

function mellat_latest_attempt(PDO $db, $orderId)
{
    $stmt = $db->prepare("SELECT * FROM payment_attempts WHERE order_id = ? AND provider = 'mellat' ORDER BY id DESC LIMIT 1");
    $stmt->execute([(int)$orderId]);
    return $stmt->fetch() ?: null;
}

/** Return the browser to the bank with the one-time RefId (BPM requires POST). */
function mellat_redirect_to_bank($refId)
{
    $refId = trim((string)$refId);
    if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $refId)) {
        http_response_code(400);
        exit('شناسه بازگشت به درگاه معتبر نیست.');
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');
    $safeRef = e($refId);
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>انتقال به درگاه پرداخت</title><body style="font-family: sans-serif; padding: 2rem; text-align: center">';
    echo '<p>در حال انتقال امن به درگاه به‌پرداخت ملت…</p>';
    echo '<form id="mellat-pay-form" method="post" action="' . e(MELLAT_START_URL) . '"><input type="hidden" name="RefId" value="' . $safeRef . '"><button type="submit">ادامه به درگاه</button></form>';
    echo '<script>document.getElementById("mellat-pay-form").submit();</script></body></html>';
    exit;
}
