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
        'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Shanghai',
        'trusted_proxies' => getenv('TRUSTED_PROXIES') ? explode(',', getenv('TRUSTED_PROXIES')) : [],
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
    'rate_limit' => [
        'login' => [
            'ip_max' => (int)(getenv('RATE_LIMIT_LOGIN_IP_MAX') ?: 10),
            'ip_window' => (int)(getenv('RATE_LIMIT_LOGIN_IP_WINDOW') ?: 60),
            'account_max' => (int)(getenv('RATE_LIMIT_LOGIN_ACCOUNT_MAX') ?: 5),
            'account_window' => (int)(getenv('RATE_LIMIT_LOGIN_ACCOUNT_WINDOW') ?: 600),
        ],
        'admin_login' => [
            'ip_max' => (int)(getenv('RATE_LIMIT_ADMIN_IP_MAX') ?: 5),
            'ip_window' => (int)(getenv('RATE_LIMIT_ADMIN_IP_WINDOW') ?: 60),
            'account_max' => (int)(getenv('RATE_LIMIT_ADMIN_ACCOUNT_MAX') ?: 3),
            'account_window' => (int)(getenv('RATE_LIMIT_ADMIN_ACCOUNT_WINDOW') ?: 600),
        ],
        'captcha_store' => [
            'ip_max' => (int)(getenv('RATE_LIMIT_CAPTCHA_IP_MAX') ?: 30),
            'ip_window' => (int)(getenv('RATE_LIMIT_CAPTCHA_IP_WINDOW') ?: 60),
        ],
        'check_username' => [
            'ip_max' => (int)(getenv('RATE_LIMIT_CHECK_USERNAME_IP_MAX') ?: 20),
            'ip_window' => (int)(getenv('RATE_LIMIT_CHECK_USERNAME_IP_WINDOW') ?: 60),
        ],
    ],
    'anti_spam' => [
        'enabled' => (getenv('ANTI_SPAM_ENABLED') !== false)
            ? in_array(strtolower(getenv('ANTI_SPAM_ENABLED')), ['1', 'true', 'yes', 'on'], true)
            : true,
        'post' => [
            'min_interval_seconds' => (int)(getenv('ANTI_SPAM_POST_MIN_INTERVAL') ?: 60),
            'similarity_threshold' => (float)(getenv('ANTI_SPAM_POST_SIMILARITY') ?: 0.85),
            'similarity_check_count' => (int)(getenv('ANTI_SPAM_POST_SIMILARITY_COUNT') ?: 3),
            'min_content_length' => (int)(getenv('ANTI_SPAM_POST_MIN_LENGTH') ?: 10),
        ],
        'comment' => [
            'min_interval_seconds' => (int)(getenv('ANTI_SPAM_COMMENT_MIN_INTERVAL') ?: 30),
            'similarity_threshold' => (float)(getenv('ANTI_SPAM_COMMENT_SIMILARITY') ?: 0.9),
            'similarity_check_count' => (int)(getenv('ANTI_SPAM_COMMENT_SIMILARITY_COUNT') ?: 5),
            'min_content_length' => (int)(getenv('ANTI_SPAM_COMMENT_MIN_LENGTH') ?: 2),
        ],
    ],
];

