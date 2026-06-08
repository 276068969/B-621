<?php
declare(strict_types=1);

/*
 * 配置入口：数据库、会话、后台管理员。
 * - 生产环境优先读取环境变量（Docker Compose 已注入）。
 */

return [
    'app' => [
        'env' => getenv('APP_ENV') ?: 'development',
        'session_name' => getenv('SESSION_NAME') ?: 'lite_forum_sess',
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'forum',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: 'root',
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        'username' => getenv('ADMIN_USER') ?: 'admin',
        'password' => getenv('ADMIN_PASS') ?: '123456',
    ],
    'post_edit' => [
        'time_limit_minutes' => (int)(getenv('POST_EDIT_TIME_LIMIT_MINUTES') ?: 30),
        'allow_with_comments' => (getenv('POST_EDIT_ALLOW_WITH_COMMENTS') !== false)
            ? in_array(strtolower(getenv('POST_EDIT_ALLOW_WITH_COMMENTS')), ['1', 'true', 'yes', 'on'], true)
            : false,
    ],
    'ui' => [
        'primary' => '#2c3e50',
        'success' => '#1abc9c',
        'danger' => '#dc3545',
        'max_width' => '1200px',
    ],
    'moderation' => [
        'enabled' => (getenv('MODERATION_ENABLED') !== false)
            ? in_array(strtolower(getenv('MODERATION_ENABLED')), ['1', 'true', 'yes', 'on'], true)
            : true,
        'sensitive_words' => [],
    ],
];

