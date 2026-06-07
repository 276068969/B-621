<?php
declare(strict_types=1);

/*
 * 工具函数：
 * - 输出转义、Flash 消息、分页、基础富文本清洗、输入校验
 */

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (!isset($_SESSION['_flash'])) {
        return null;
    }
    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $flash;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function current_url_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return strtok($uri, '?') ?: '/';
}

function paginate(int $total, int $page, int $pageSize): array
{
    $page = max(1, $page);
    $pageSize = max(1, min(50, $pageSize));
    $pages = (int)max(1, (int)ceil($total / $pageSize));
    $page = min($page, $pages);

    return [
        'page' => $page,
        'pageSize' => $pageSize,
        'pages' => $pages,
        'offset' => ($page - 1) * $pageSize,
    ];
}

function is_valid_username(string $username): bool
{
    if (strlen($username) < 3 || strlen($username) > 16) {
        return false;
    }
    return (bool)preg_match('/^[a-zA-Z0-9_]{3,16}$/', $username);
}

function is_valid_password(string $password): bool
{
    $len = strlen($password);
    return $len >= 6 && $len <= 20;
}

function is_valid_mobile(?string $mobile): bool
{
    if ($mobile === null || $mobile === '') {
        return true;
    }
    return (bool)preg_match('/^1\d{10}$/', $mobile);
}

function sanitize_rich_html(string $html): string
{
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><code><pre><a><h1><h2><h3><h4><h5><h6><hr><span>';
    $clean = strip_tags($html, $allowed);

    $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
    $clean = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $clean) ?? $clean;

    $clean = preg_replace_callback(
        '/<a\s+[^>]*href\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)[^>]*>/i',
        function (array $m): string {
            $raw = trim($m[1], " \t\n\r\0\x0B\"\'");
            if (preg_match('/^\s*javascript:/i', $raw)) {
                return '<a href="#">';
            }
            $safe = e($raw);
            return '<a href="' . $safe . '" target="_blank" rel="noopener noreferrer">';
        },
        $clean
    );

    return $clean;
}

