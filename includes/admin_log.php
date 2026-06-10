<?php
declare(strict_types=1);

/*
 * 管理员操作日志：
 * - 记录登录、编辑、删除、恢复等关键操作
 * - 提供日志查询与展示
 */

function admin_get_client_ip(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    return $ip;
}

function admin_log_operation(
    PDO $pdo,
    string $action,
    ?string $targetType = null,
    ?int $targetId = null,
    ?string $detail = null
): void {
    try {
        $adminUsername = 'system';
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
            global $config;
            $adminUsername = $config['admin']['username'] ?? 'admin';
        }

        $ip = admin_get_client_ip();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if ($userAgent !== null && strlen($userAgent) > 512) {
            $userAgent = substr($userAgent, 0, 512);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO admin_operation_logs (admin_username, action, target_type, target_id, detail, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$adminUsername, $action, $targetType, $targetId, $detail, $ip, $userAgent]);
    } catch (Throwable $e) {
        error_log('admin_log_operation failed: ' . $e->getMessage());
    }
}

function admin_get_recent_logs(PDO $pdo, int $limit = 10): array
{
    if ($limit <= 0) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT id, admin_username, action, target_type, target_id, detail, ip, create_time
         FROM admin_operation_logs
         ORDER BY create_time DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function admin_get_action_label(string $action): string
{
    $labels = [
        'admin_login' => '后台登录',
        'admin_logout' => '退出后台',
        'admin_login_failed' => '登录失败',
        'post_edit' => '编辑帖子',
        'post_delete' => '删除帖子',
        'post_restore' => '恢复帖子',
        'comment_delete' => '删除评论',
        'comment_restore' => '恢复评论',
    ];
    return $labels[$action] ?? $action;
}

function admin_get_action_badge_class(string $action): string
{
    $classes = [
        'admin_login' => 'bg-success',
        'admin_logout' => 'bg-secondary',
        'admin_login_failed' => 'bg-danger',
        'post_edit' => 'bg-warning text-dark',
        'post_delete' => 'bg-danger',
        'post_restore' => 'bg-info',
        'comment_delete' => 'bg-danger',
        'comment_restore' => 'bg-info',
    ];
    return $classes[$action] ?? 'bg-secondary';
}
