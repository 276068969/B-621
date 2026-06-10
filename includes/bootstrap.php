<?php
declare(strict_types=1);

/*
 * 全站引导文件：统一加载配置、启动 Session、引入公共函数。
 */

$config = require __DIR__ . '/../config/config.php';

if (!empty($config['app']['timezone'])) {
    date_default_timezone_set($config['app']['timezone']);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionName = $config['app']['session_name'] ?? 'lite_forum_sess';
    session_name($sessionName);
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/admin_log.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_moderation.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/anti_spam.php';

if (!empty($config['app']['trusted_proxies'])) {
    RateLimiter::setTrustedProxies($config['app']['trusted_proxies']);
}

