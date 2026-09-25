<?php
declare(strict_types=1);

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
define('JSON_API_REQUEST', true);
require_once __DIR__ . '/../config/app.php';
date_default_timezone_set('Asia/Manila');
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function verify_json(bool $success, string $message, int $status = 200): void {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') verify_json(false, 'Invalid request method.', 405);
    $input = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($input)) $input = $_POST;
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $otp = trim((string) ($input['otp_code'] ?? $input['otp'] ?? ''));
    $newPassword = (string) ($input['new_password'] ?? '');
    $confirmPassword = (string) ($input['confirm_password'] ?? '');
    $csrf = trim((string) ($input['csrf_token'] ?? ''));
    if ($csrf === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf)) verify_json(false, 'Your session expired. Please reload the page and try again.', 403);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) verify_json(false, 'Please enter a valid email address.', 422);
    if (!preg_match('/^\d{6}$/', $otp)) verify_json(false, 'Enter the 6-digit verification code.', 422);

    $db = get_db();
    $stmt = $db->prepare('SELECT user_id, reset_otp_code, reset_otp_expires_at FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email)) AND is_active = TRUE LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if (!$user || $user['reset_otp_code'] === null) verify_json(false, 'Invalid verification code.', 422);
    if (strtolower(trim((string) ($_SESSION['reset_email'] ?? ''))) !== $email) verify_json(false, 'Invalid verification session.', 422);
    if (empty($user['reset_otp_expires_at']) || strtotime((string) $user['reset_otp_expires_at']) <= time()) verify_json(false, 'Verification code has expired.', 422);
    if (!hash_equals((string) $user['reset_otp_code'], $otp)) verify_json(false, 'Invalid verification code.', 422);

    $passwordError = password_policy_error($newPassword);
    if ($passwordError !== null) verify_json(false, $passwordError, 422);
    if (!hash_equals($newPassword, $confirmPassword)) verify_json(false, 'Passwords do not match.', 422);

    $update = $db->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW(), reset_otp_code = NULL, reset_otp_created_at = NULL, reset_otp_expires_at = NULL WHERE user_id = ?');
    $update->execute([password_hash($newPassword, PASSWORD_BCRYPT), $user['user_id']]);
    unset($_SESSION['reset_email'], $_SESSION['demo_otp'], $_SESSION['otp_last_sent_at']);
    verify_json(true, 'Password reset successful.');
} catch (PDOException $e) {
    error_log('Verify OTP database error: ' . $e->getMessage());
    verify_json(false, 'Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    error_log('Verify OTP error: ' . $e->getMessage());
    verify_json(false, 'Unable to verify the code.', 500);
}
