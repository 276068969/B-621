<?php
declare(strict_types=1);

/*
 * 用户退出：清理用户登录态。
 */

require_once __DIR__ . '/../includes/bootstrap.php';

logout_user();
flash_set('success', '已退出登录。');
redirect('/index.php');

