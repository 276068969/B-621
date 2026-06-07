<?php
declare(strict_types=1);

/*
 * 后台退出：清理后台登录态。
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

admin_logout();
flash_set('success', '已退出后台。');
redirect('/index.php');

