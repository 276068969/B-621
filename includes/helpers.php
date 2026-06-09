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

function can_user_edit_post(array $config, ?array $user, array $post, int $commentCount = 0): bool
{
    if ($user === null) {
        return false;
    }

    if ((int)$post['user_id'] !== (int)$user['id']) {
        return false;
    }

    $editConfig = $config['post_edit'] ?? [];
    $timeLimitMinutes = (int)($editConfig['time_limit_minutes'] ?? 30);

    if ($timeLimitMinutes > 0) {
        $createTime = strtotime((string)$post['create_time']);
        if ($createTime === false) {
            return false;
        }
        $elapsed = time() - $createTime;
        if ($elapsed > $timeLimitMinutes * 60) {
            return false;
        }
    }

    $allowWithComments = (bool)($editConfig['allow_with_comments'] ?? false);
    if (!$allowWithComments && $commentCount > 0) {
        return false;
    }

    return true;
}

function get_edit_remaining_minutes(array $config, array $post): int
{
    $editConfig = $config['post_edit'] ?? [];
    $timeLimitMinutes = (int)($editConfig['time_limit_minutes'] ?? 30);
    if ($timeLimitMinutes <= 0) {
        return -1;
    }

    $createTime = strtotime((string)$post['create_time']);
    if ($createTime === false) {
        return 0;
    }

    $elapsed = time() - $createTime;
    $remaining = (int)ceil(($timeLimitMinutes * 60 - $elapsed) / 60);
    return max(0, $remaining);
}

function get_hot_posts(PDO $pdo, int $limit = 10, int $excludeId = 0): array
{
    $whereConditions = ['p.status = 1'];
    $params = [];

    if ($excludeId > 0) {
        $whereConditions[] = 'p.id != :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $whereSql = implode(' AND ', $whereConditions);

    $sql = 'SELECT p.id, p.title, p.create_time, u.username,
            COALESCE(c_stats.comment_count, 0) AS comment_count,
            COALESCE(c_stats.first_comment_time, p.create_time) AS first_comment_time,
            COALESCE(c_stats.last_comment_time, p.create_time) AS last_comment_time
     FROM posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN (
         SELECT post_id,
                COUNT(*) AS comment_count,
                MIN(create_time) AS first_comment_time,
                MAX(create_time) AS last_comment_time
         FROM comments
         WHERE status = 1
         GROUP BY post_id
     ) c_stats ON c_stats.post_id = p.id
     WHERE ' . $whereSql . '
     ORDER BY (
         LOG(2, COALESCE(c_stats.comment_count, 0) + 1) * 40
         + (1 / (1 + (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(p.create_time)) / 3600 / 24)) * 30
         + (UNIX_TIMESTAMP(COALESCE(c_stats.last_comment_time, p.create_time))
            - UNIX_TIMESTAMP(COALESCE(c_stats.first_comment_time, p.create_time))) / 3600 / 24 * 30
     ) DESC
     LIMIT :limit';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function is_post_favorited(PDO $pdo, int $userId, int $postId): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ? AND post_id = ?');
    $stmt->execute([$userId, $postId]);
    return (int)$stmt->fetchColumn() > 0;
}

function toggle_favorite(PDO $pdo, int $userId, int $postId): array
{
    $stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND post_id = ? LIMIT 1');
    $stmt->execute([$userId, $postId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND post_id = ?');
        $stmt->execute([$userId, $postId]);
        return ['favorited' => false, 'action' => 'unfavorited'];
    } else {
        $stmt = $pdo->prepare('INSERT INTO favorites (user_id, post_id) VALUES (?, ?)');
        $stmt->execute([$userId, $postId]);
        return ['favorited' => true, 'action' => 'favorited'];
    }
}

function get_user_favorites(PDO $pdo, int $userId, int $page, int $pageSize): array
{
    $offset = ($page - 1) * $pageSize;

    $sql = 'SELECT f.id AS favorite_id, f.create_time AS favorite_time,
                   p.id, p.title, p.content, p.create_time, p.update_time, p.status,
                   u.username,
                   COALESCE(c.cnt, 0) AS comment_count
            FROM favorites f
            JOIN posts p ON p.id = f.post_id
            JOIN users u ON u.id = p.user_id
            LEFT JOIN (
                SELECT post_id, COUNT(*) AS cnt
                FROM comments
                WHERE status = 1
                GROUP BY post_id
            ) c ON c.post_id = p.id
            WHERE f.user_id = ?
            ORDER BY f.create_time DESC
            LIMIT ? OFFSET ?';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function get_user_favorite_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

