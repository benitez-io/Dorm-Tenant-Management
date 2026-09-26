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
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
    $role = strtolower((string) ($_SESSION['role'] ?? ''));
    $emptyCounts = ['tenants' => 0, 'payments' => 0, 'maintenance' => 0, 'notifications' => 0, 'announcements' => 0];

    if (!$userId || !in_array($role, ['admin', 'tenant'], true)) {
        notification_json(['status' => 'success', 'counts' => normalize_notification_counts($emptyCounts)]);
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

        // Admin: Pending or active maintenance requests needing attention
        $counts['maintenance'] = notification_count(
            $database,
            "SELECT COUNT(*) FROM maintenance_requests
             WHERE status IN ('Pending', 'Ongoing')
             AND date_submitted > ?",
            [$cutoffs['maintenance']]
        );

        // Informational announcements use the notification dot.
        $counts['announcements'] = notification_count(
            $database,
            "SELECT COUNT(*) FROM notifications WHERE type = 'Announcement' AND date_sent > ?",
            [$cutoffs['notifications']]
        );

        // Direct alerts require review and use the number badge.
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

            // Use status timestamps when supported, while remaining compatible with older schemas.
            $hasUpdatedAt = notification_count(
                $database,
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'maintenance_requests'
                 AND COLUMN_NAME = 'updated_at'",
                []
            ) > 0;

            $maintenanceQuery = $hasUpdatedAt
                ? "SELECT COUNT(*) FROM maintenance_requests
                   WHERE tenant_id = ?
                         AND status IN ('Pending', 'Ongoing', 'Completed')
                   AND (date_submitted > ? OR COALESCE(updated_at, date_submitted) > ?)"
                : "SELECT COUNT(*) FROM maintenance_requests
                   WHERE tenant_id = ?
                         AND status IN ('Pending', 'Ongoing', 'Completed')
                   AND date_submitted > ?";
            $maintenanceParameters = $hasUpdatedAt
                ? [$tenantId, $cutoffs['maintenance'], $cutoffs['maintenance']]
                : [$tenantId, $cutoffs['maintenance']];

            $counts['maintenance'] = notification_count(
                $database,
                $maintenanceQuery,
                $maintenanceParameters
            );

            // Pending or overdue bills need tenant attention.
            $counts['payments'] = notification_count(
                $database,
                "SELECT COUNT(*) FROM payments WHERE tenant_id = ? AND payment_status IN ('Pending', 'Overdue') AND created_at > ?",
                [$tenantId, $cutoffs['payments']]
            );

            // Target notifications to this tenant or their room.
            $targetFilter = "(target_type = 'all' OR (target_type = 'tenant' AND target_value = ?) OR (target_type = 'room' AND FIND_IN_SET(?, REPLACE(target_value, ' ', ''))))";
            $counts['announcements'] = notification_count(
                $database,
                "SELECT COUNT(*) FROM notifications WHERE type = 'Announcement' AND date_sent > ? AND {$targetFilter}",
                [$cutoffs['notifications'], (string) $tenantId, $roomNumber]
            );
            $counts['notifications'] = notification_count(
                $database,
                "SELECT COUNT(*) FROM notifications WHERE type <> 'Announcement' AND date_sent > ? AND {$targetFilter}",
                [$cutoffs['notifications'], $tenantId, $roomNumber]
            );
        }
    }

    notification_json(['status' => 'success', 'counts' => normalize_notification_counts($counts)]);
} catch (Throwable $exception) {
    error_log('Unread notification count error: ' . $exception->getMessage());
    notification_json(['status' => 'success', 'counts' => normalize_notification_counts($emptyCounts)]);
}