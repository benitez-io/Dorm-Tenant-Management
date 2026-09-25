<?php
declare(strict_types=1);

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';

function notification_json(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function notification_count(PDO $database, string $query, array $parameters): int
{
    try {
        $statement = $database->prepare($query);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    } catch (Throwable $exception) {
        error_log('Notification count query failed: ' . $exception->getMessage());
        return 0;
    }
}

function normalize_notification_counts(array $counts): array
{
    return [
        'notifications' => (int) ($counts['notifications'] ?? 0),
        'payments' => (int) ($counts['payments'] ?? 0),
        'maintenance' => (int) ($counts['maintenance'] ?? 0),
        'tenants' => (int) ($counts['tenants'] ?? 0),
        'announcements' => (int) ($counts['announcements'] ?? 0),
    ];
}

try {
    $sessionUser = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
    $userId = $_SESSION['user_id'] ?? $sessionUser['id'] ?? $_SESSION['id'] ?? 0;
    $tenantId = $_SESSION['tenant_id'] ?? $sessionUser['tenant_id'] ?? 0;
    $role = strtolower((string) ($_SESSION['role'] ?? $sessionUser['role'] ?? $_SESSION['user_type'] ?? 'tenant'));
    $emptyCounts = ['tenants' => 0, 'payments' => 0, 'maintenance' => 0, 'notifications' => 0, 'announcements' => 0];

    if (!$userId) {
        notification_json(['status' => 'success', 'counts' => normalize_notification_counts($emptyCounts), 'type' => 'actionable']);
    }

    $database = get_db();

    $getCutoff = function (string $key): string {
        $value = $_GET[$key] ?? 0;
        $timestamp = is_numeric($value) ? max(0, (int) floor((float) $value / 1000)) : 0;
        return date('Y-m-d H:i:s', $timestamp);
    };

    $cutoffs = [
        'tenants' => $getCutoff('last_seen_tenants'),
        'payments' => $getCutoff('last_seen_payments'),
        'maintenance' => $getCutoff('last_seen_maintenance'),
        'notifications' => $getCutoff('last_seen_notifications'),
    ];

    $counts = $emptyCounts;

    if ($role === 'admin') {
        // Admin: Pending tenant approvals
        $counts['tenants'] = notification_count(
            $database,
            "SELECT COUNT(*) FROM tenants WHERE approval_status = 'Pending' AND date_registered > ?",
            [$cutoffs['tenants']]
        );

        // Admin: Pending payments to review
        $counts['payments'] = notification_count(
            $database,
            "SELECT COUNT(*) FROM payments WHERE payment_status = 'Pending' AND created_at > ?",
            [$cutoffs['payments']]
        );

        // Admin: New/Unassigned maintenance requests needing attention
        $counts['maintenance'] = notification_count(
            $database,
            "SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('Pending', 'Ongoing') AND date_submitted > ?",
            [$cutoffs['maintenance']]
        );

        // Admin: System notifications
        $counts['announcements'] = notification_count(
            $database,
            "SELECT COUNT(*) FROM notifications WHERE type = 'Announcement' AND date_sent > ?",
            [$cutoffs['notifications']]
        );
        $counts['notifications'] = notification_count(
            $database,
            "SELECT COUNT(*) FROM notifications WHERE type <> 'Announcement' AND date_sent > ?",
            [$cutoffs['notifications']]
        );

    } elseif ($role === 'tenant') {
        // Tenant Identity Resolution
        $tenant = null;
        $sessionTenantId = $tenantId ?: null;

        if ($sessionTenantId !== null && is_numeric($sessionTenantId)) {
            $stmt = $database->prepare('SELECT * FROM tenants WHERE tenant_id = ?');
            $stmt->execute([(int)$sessionTenantId]);
            $tenant = $stmt->fetch() ?: null;
        }

        if (!$tenant && $userId) {
            $stmt = $database->prepare('SELECT * FROM tenants WHERE user_id = ? OR tenant_id = ?');
            $stmt->execute([(int)$userId, (int)$userId]);
            $tenant = $stmt->fetch() ?: null;
        }

        if ($tenant) {
            $tenantId = (int) $tenant['tenant_id'];

            $roomNumber = '__none__';
            if (!empty($tenant['room_id'])) {
                try {
                    $roomStatement = $database->prepare('SELECT room_number FROM dorm_rooms WHERE room_id = ?');
                    $roomStatement->execute([$tenant['room_id']]);
                    $roomNumber = (string) ($roomStatement->fetchColumn() ?: '__none__');
                } catch (Throwable $e) {
                    // Fail silently
                }
            }

            // Tenant Maintenance: active tickets submitted after the last visit.
            $counts['maintenance'] = notification_count(
                $database,
                "SELECT COUNT(*) FROM maintenance_requests 
                 WHERE tenant_id = ? 
                 AND status IN ('Pending', 'Ongoing')
                 AND date_submitted > ?",
                [$tenantId, $cutoffs['maintenance']]
            );

            // Tenant Payments: Unpaid/Pending payment records or recently uploaded receipts
            $counts['payments'] = notification_count(
                $database,
                "SELECT COUNT(*) FROM payments WHERE tenant_id = ? AND payment_status = 'Pending' AND created_at > ?",
                [$tenantId, $cutoffs['payments']]
            );

            // Tenant Notifications & Announcements
            $targetFilter = "(target_type = 'all' OR (target_type = 'tenant' AND target_value = ?) OR (target_type = 'room' AND FIND_IN_SET(?, REPLACE(target_value, ' ', ''))))";
            $counts['announcements'] = notification_count(
                $database,
                "SELECT COUNT(*) FROM notifications WHERE type = 'Announcement' AND date_sent > ? AND {$targetFilter}",
                [$cutoffs['notifications'], $tenantId, $roomNumber]
            );
            $counts['notifications'] = notification_count(
                $database,
                "SELECT COUNT(*) FROM notifications WHERE type <> 'Announcement' AND date_sent > ? AND {$targetFilter}",
                [$cutoffs['notifications'], $tenantId, $roomNumber]
            );
        }
    }

    notification_json(['status' => 'success', 'counts' => normalize_notification_counts($counts), 'type' => 'actionable']);
} catch (Throwable $exception) {
    error_log('Unread notification count error: ' . $exception->getMessage());
    notification_json(['status' => 'success', 'counts' => normalize_notification_counts($emptyCounts), 'type' => 'actionable']);
}