<?php
declare(strict_types=1);

/*
 * 帖子列表页：
 * - 分页展示帖子
 * - 显示作者、时间、评论数
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = null;
try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '系统错误 - Lite Forum', 'active' => 'home']);
    echo '<div class="card card-lite p-4">';
    echo '<div class="fw-semibold mb-1">数据库连接失败</div>';
    echo '<div class="text-muted">请检查数据库配置或稍后重试。</div>';
    echo '</div>';
    render_footer();
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = 10;

$total = (int)$pdo->query('SELECT COUNT(*) FROM posts WHERE status = 1')->fetchColumn();
$pg = paginate($total, $page, $pageSize);

$stmt = $pdo->prepare(
    'SELECT p.id, p.title, p.content, p.create_time, u.username,
            COALESCE(c.cnt, 0) AS comment_count
     FROM posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN (
         SELECT post_id, COUNT(*) AS cnt
         FROM comments
         WHERE status = 1
         GROUP BY post_id
     ) c ON c.post_id = p.id
     WHERE p.status = 1
     ORDER BY p.create_time DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':limit', $pg['pageSize'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

render_header($config, ['title' => '帖子列表 - Lite Forum', 'active' => 'home']);

echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<div>';
echo '<h1 class="h4 mb-0">帖子</h1>';
echo '<div class="text-muted small mt-1">共 ' . e((string)$total) . ' 篇</div>';
echo '</div>';
if (user() !== null) {
    echo '<a class="btn btn-primary" href="/post_add.php">发布帖子</a>';
} else {
    echo '<a class="btn btn-outline-secondary" href="/login.php">登录后发帖</a>';
}
echo '</div>';

if (!$posts) {
    echo '<div class="card card-lite p-4">';
    echo '<div class="text-muted">暂无帖子，欢迎先注册/登录发布第一篇。</div>';
    echo '</div>';
    render_footer();
    exit;
}

foreach ($posts as $post) {
    $excerptSource = strip_tags(sanitize_rich_html((string)$post['content']));
    if (function_exists('mb_substr')) {
        $excerpt = mb_substr($excerptSource, 0, 120);
    } else {
        $excerpt = substr($excerptSource, 0, 120);
    }
    if (strlen($excerptSource) > strlen($excerpt)) {
        $excerpt .= '...';
    }

    echo '<div class="card card-lite mb-3">';
    echo '<div class="card-body">';
    echo '<div class="d-flex justify-content-between gap-3">';
    echo '<div class="flex-grow-1">';
    echo '<a class="h5 text-decoration-none" href="/post.php?id=' . e((string)$post['id']) . '">' . e((string)$post['title']) . '</a>';
    echo '<div class="text-muted small mt-2">' . e((string)$excerpt) . '</div>';
    echo '<div class="text-muted small mt-3">';
    echo '<span class="me-2">作者：' . e((string)$post['username']) . '</span>';
    echo '<span class="me-2">时间：' . e((string)$post['create_time']) . '</span>';
    echo '<span>评论：' . e((string)$post['comment_count']) . '</span>';
    echo '</div>';
    echo '</div>';
    echo '<div class="text-end">';
    echo '<a class="btn btn-sm btn-outline-secondary" href="/post.php?id=' . e((string)$post['id']) . '">查看</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

if ($pg['pages'] > 1) {
    echo '<nav aria-label="Page navigation">';
    echo '<ul class="pagination justify-content-center">';
    for ($i = 1; $i <= $pg['pages']; $i++) {
        $active = $i === $pg['page'] ? ' active' : '';
        echo '<li class="page-item' . $active . '"><a class="page-link" href="/index.php?page=' . e((string)$i) . '">' . e((string)$i) . '</a></li>';
    }
    echo '</ul></nav>';
}

render_footer();
