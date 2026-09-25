<?php
declare(strict_types=1);

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
define('JSON_API_REQUEST', true);
require_once __DIR__ . '/../config/app.php';
date_default_timezone_set('Asia/Manila');
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true) || getenv('APP_ENV') === 'local';
function otp_json(bool $success, string $message, int $status = 200, ?string $otp = null): void {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    global $isLocal;
    echo json_encode(['success' => $success, 'message' => $message, 'debug_otp' => $isLocal ? $otp : null]);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') otp_json(false, 'Invalid request method.', 405);
    $input = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($input)) $input = $_POST;
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $csrf = trim((string) ($input['csrf_token'] ?? ''));
    if ($csrf === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf)) otp_json(false, 'Your session expired. Please reload the page and try again.', 403);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) otp_json(false, 'Please enter a valid email address.', 422);

    $db = get_db();
    $stmt = $db->prepare('SELECT user_id, first_name, email FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email)) AND is_active = TRUE LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if (!$user) otp_json(false, 'Email address not found.', 404);

    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $db->prepare('UPDATE users SET reset_otp_code = ?, reset_otp_created_at = NOW(), reset_otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE user_id = ?')->execute([$otp, $user['user_id']]);
    $name = strtoupper(trim((string) $user['first_name'])) ?: 'TENANT';
    $body = email_template('Your password reset code', "Hi {$name},\n\nUse this code to reset your password:\n\n{$otp}\n\nThis code expires in 10 minutes.");
    if (!send_email_alert($user['email'], $user['first_name'], 'Your password reset code', $body)) {
        $db->prepare('UPDATE users SET reset_otp_code = NULL, reset_otp_created_at = NULL, reset_otp_expires_at = NULL WHERE user_id = ?')->execute([$user['user_id']]);
        otp_json(false, 'Gmail SMTP Error: ' . ($GLOBALS['last_email_error'] ?? 'Email delivery failed.'), 500);
    }
    $_SESSION['reset_email'] = $email;
    otp_json(true, 'Verification code sent successfully.', 200, $otp);
} catch (PDOException $e) {
    error_log('Send OTP database error: ' . $e->getMessage());
    otp_json(false, 'Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    error_log('Send OTP error: ' . $e->getMessage());
    otp_json(false, 'Unable to send the verification code.', 500);
}
