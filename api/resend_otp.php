<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function otp_response(bool $success, string $message, int $status = 200, ?string $debugCode = null): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'debug_code' => getenv('APP_ENV') === 'local' ? $debugCode : null
    ]);
    exit;
}

try {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput ?: '', true);
    $input = is_array($jsonInput) ? $jsonInput : $_POST;

    $email = trim((string) ($input['email'] ?? ''));
    $csrfToken = trim((string) ($input['csrf_token'] ?? ''));

    if ($csrfToken === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
        otp_response(false, 'Your session expired. Please reload the page and try again.', 403);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        otp_response(false, 'Please enter a valid email address.', 422);
    }

    $database = get_db();
    $statement = $database->prepare('SELECT user_id, first_name, email FROM users WHERE email = ? AND is_active = TRUE');
    $statement->execute([$email]);
    $user = $statement->fetch();

    if (!$user) {
        otp_response(false, 'We could not find an active account for that email address.', 404);
    }

    $now = time();
    $lastSentAt = (int) ($_SESSION['otp_last_sent_at'] ?? 0);
    if ($lastSentAt > 0 && $now - $lastSentAt < 60) {
        otp_response(false, 'Please wait before requesting another verification code.', 429);
    }

    $otp = (string) random_int(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', $now + 10 * 60);
    $update = $database->prepare('UPDATE users SET reset_otp = ?, reset_otp_expires = ? WHERE user_id = ?');

    if (!$update->execute([$otp, $expiresAt, $user['user_id']])) {
        otp_response(false, 'The verification code could not be saved. Please try again.', 500);
    }

    $emailBody = "Hi {$user['first_name']}, use this verification code to reset your password:\n\n{$otp}\n\nThis code expires in 10 minutes. If you did not request this, you can safely ignore this email.";
    $sent = send_email_alert(
        $user['email'],
        $user['first_name'],
        'Your password reset code',
        email_template('Reset your password', $emailBody)
    );

    if (!$sent) {
        $database->prepare('UPDATE users SET reset_otp = NULL, reset_otp_expires = NULL WHERE user_id = ?')->execute([$user['user_id']]);
        otp_response(false, 'We could not send the verification email right now. Please try again later.', 502);
    }

    $_SESSION['reset_email'] = $email;
    $_SESSION['otp_last_sent_at'] = $now;
    $_SESSION['reset_otp_debug'] = getenv('APP_ENV') === 'local' ? $otp : null;

    otp_response(true, 'Code sent successfully.', 200, $otp);
} catch (Throwable $exception) {
    error_log('Password reset OTP error: ' . $exception->getMessage());
    otp_response(false, 'We could not send a verification code right now. Please try again later.', 500);
}
