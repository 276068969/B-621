<?php
declare(strict_types=1);

/*
 * 后台退出：清理后台登录态。
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    $pdo = db($config);
    admin_log_operation($pdo, 'admin_logout', null, null, '管理员退出后台');
} catch (Throwable $e) {
}

admin_logout();
flash_set('success', '已退出后台。');
redirect('/index.php');

