<?php
declare(strict_types=1);

/*
 * 评论管理：
 * - 列表（分页）
 * - 删除（软删）
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

admin_require_login();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '评论管理 - Lite Forum', 'active' => 'admin']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = 10;

$total = (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
$pg = paginate($total, $page, $pageSize);

$stmt = $pdo->prepare(
    'SELECT c.id, c.content, c.create_time, c.status,
            u.username,
            p.id AS post_id, p.title AS post_title
     FROM comments c
     JOIN users u ON u.id = c.user_id
     JOIN posts p ON p.id = c.post_id
     ORDER BY c.create_time DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':limit', $pg['pageSize'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

render_header($config, ['title' => '评论管理 - Lite Forum', 'active' => 'admin']);

echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<div>';
echo '<h1 class="h4 mb-0">评论管理</h1>';
echo '<div class="text-muted small mt-1">共 ' . e((string)$total) . ' 条（含已删除）</div>';
echo '</div>';
echo '<div class="d-flex gap-2">';
echo '<a class="btn btn-outline-secondary" href="/admin/index.php">返回概览</a>';
echo '<a class="btn btn-outline-secondary" href="/admin/logout.php">退出后台</a>';
echo '</div>';
echo '</div>';

echo '<div class="card card-lite">';
echo '<div class="card-body p-0">';
echo '<div class="table-responsive">';
echo '<table class="table table-hover mb-0">';
echo '<thead class="table-light"><tr>'; 
echo '<th class="ps-3">内容</th><th>作者</th><th>所属帖子</th><th>时间</th><th>状态</th><th class="text-end pe-3">操作</th>';
echo '</tr></thead><tbody>';

if (!$rows) {
    echo '<tr><td class="ps-3 py-4 text-muted" colspan="6">暂无数据</td></tr>';
} else {
    foreach ($rows as $r) {
        $statusBadge = ((int)$r['status'] === 1)
            ? '<span class="badge text-bg-success">正常</span>'
            : '<span class="badge text-bg-secondary">已删除</span>';
        $content = (string)$r['content'];
        if (function_exists('mb_substr')) {
            $contentShort = mb_substr($content, 0, 80);
        } else {
            $contentShort = substr($content, 0, 80);
        }
        if (strlen($content) > strlen($contentShort)) {
            $contentShort .= '...';
        }

        echo '<tr>'; 
        echo '<td class="ps-3">' . e($contentShort) . '</td>';
        echo '<td>' . e((string)$r['username']) . '</td>';
        echo '<td><a class="text-decoration-none" href="/post.php?id=' . e((string)$r['post_id']) . '" target="_blank" rel="noopener">' . e((string)$r['post_title']) . '</a></td>';
        echo '<td class="text-muted small">' . e((string)$r['create_time']) . '</td>';
        echo '<td>' . $statusBadge . '</td>';
        echo '<td class="text-end pe-3">';
        if ((int)$r['status'] === 1) {
            $delUrl = '/admin/comment_delete.php?id=' . e((string)$r['id']);
            echo '<button class="btn btn-sm btn-outline-danger" onclick="showConfirmModal(\'删除确认\', \'确定要删除该条评论吗？\', \'' . $delUrl . '\')">删除</button>';
        }
        echo '</td>';
        echo '</tr>';
    }
}

echo '</tbody></table></div></div></div>';

if ($pg['pages'] > 1) {
    echo '<nav class="mt-3" aria-label="Page navigation">';
    echo '<ul class="pagination justify-content-center">';
    for ($i = 1; $i <= $pg['pages']; $i++) {
        $active = $i === $pg['page'] ? ' active' : '';
        echo '<li class="page-item' . $active . '"><a class="page-link" href="/admin/comments.php?page=' . e((string)$i) . '">' . e((string)$i) . '</a></li>';
    }
    echo '</ul></nav>';
}

render_footer();

