<?php
declare(strict_types=1);

/*
 * 我的主页：
 * - 用户基础资料展示
 * - 发帖与回帖数量统计
 * - 本人发布的帖子列表
 * - 最近参与的评论记录
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '系统错误 - Lite Forum', 'active' => 'profile']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$u = user();
$userId = (int)$u['id'];

$stmt = $pdo->prepare('SELECT id, username, mobile, create_time, status FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$userInfo = $stmt->fetch();

if (!$userInfo) {
    flash_set('danger', '用户信息不存在。');
    redirect('/index.php');
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ? AND status = 1');
$stmt->execute([$userId]);
$postCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE user_id = ? AND status = 1');
$stmt->execute([$userId]);
$commentCount = (int)$stmt->fetchColumn();

$favoriteCount = get_user_favorite_count($pdo, $userId);

$tab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : 'posts';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = 10;

$posts = [];
$comments = [];
$total = 0;

if ($tab === 'posts') {
    $total = $postCount;
    $pg = paginate($total, $page, $pageSize);

    $stmt = $pdo->prepare(
        'SELECT p.id, p.user_id, p.title, p.content, p.create_time, p.update_time,
         COALESCE(c.cnt, 0) AS comment_count
         FROM posts p
         LEFT JOIN (
             SELECT post_id, COUNT(*) AS cnt
             FROM comments
             WHERE status = 1
             GROUP BY post_id
         ) c ON c.post_id = p.id
         WHERE p.user_id = :user_id AND p.status = 1
         ORDER BY p.create_time DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $pg['pageSize'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll();
} else {
    $total = $commentCount;
    $pg = paginate($total, $page, $pageSize);

    $stmt = $pdo->prepare(
        'SELECT c.id, c.content, c.create_time, p.id AS post_id, p.title AS post_title
         FROM comments c
         JOIN posts p ON p.id = c.post_id
         WHERE c.user_id = :user_id AND c.status = 1 AND p.status = 1
         ORDER BY c.create_time DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $pg['pageSize'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $comments = $stmt->fetchAll();
}

function build_profile_query_string(array $overrides = []): string
{
    $params = $_GET;
    if (isset($params['page'])) {
        unset($params['page']);
    }
    foreach ($overrides as $key => $value) {
        if ($value === '' || $value === null || $value === '0') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    if (empty($params)) {
        return '';
    }
    return '?' . http_build_query($params);
}

render_header($config, ['title' => '我的主页 - Lite Forum', 'active' => 'profile']);

echo '<style>';
echo '.profile-card{border:0;border-radius:.5rem;box-shadow:0 .125rem .25rem rgba(0,0,0,.075);background:#fff;}';
echo '.avatar-circle{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg, #2c3e50, #1abc9c);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem;font-weight:bold;}';
echo '.stat-card{border-radius:.5rem;background:#f8f9fa;padding:1rem;text-align:center;}';
echo '.stat-number{font-size:1.75rem;font-weight:bold;color:var(--bs-primary);}';
echo '.stat-label{font-size:.875rem;color:#6c757d;margin-top:.25rem;}';
echo '.nav-tabs .nav-link{color:#495057;border:0;border-bottom:2px solid transparent;}';
echo '.nav-tabs .nav-link.active{color:var(--bs-primary);border-bottom-color:var(--bs-primary);background:transparent;}';
echo '.nav-tabs .nav-link:hover{border-bottom-color:#dee2e6;}';
echo '.comment-item{border-left:3px solid #e9ecef;padding-left:1rem;}';
echo '</style>';

echo '<div class="row g-3 mb-3">';

echo '<div class="col-lg-4">';
echo '<div class="card profile-card">';
echo '<div class="card-body p-4">';
echo '<div class="d-flex align-items-center gap-3 mb-4">';
echo '<div class="avatar-circle">';
echo mb_strtoupper(mb_substr((string)$userInfo['username'], 0, 1));
echo '</div>';
echo '<div>';
echo '<div class="h5 mb-0">' . e((string)$userInfo['username']) . '</div>';
echo '<div class="text-muted small">论坛成员</div>';
echo '</div>';
echo '</div>';

echo '<hr class="my-3">';

echo '<div class="mb-3">';
echo '<div class="text-muted small mb-1">注册时间</div>';
echo '<div class="fw-medium">' . e((string)$userInfo['create_time']) . '</div>';
echo '</div>';

if ($userInfo['mobile']) {
    echo '<div class="mb-3">';
    echo '<div class="text-muted small mb-1">联系手机</div>';
    $mobile = (string)$userInfo['mobile'];
    echo '<div class="fw-medium">' . e($mobile) . '</div>';
    echo '</div>';
}

echo '<div class="mb-3">';
echo '<div class="text-muted small mb-1">账号状态</div>';
echo '<div class="fw-medium text-success">正常</div>';
echo '</div>';

echo '</div>';
echo '</div>';
echo '</div>';

echo '<div class="col-lg-8">';
echo '<div class="card profile-card">';
echo '<div class="card-body p-4">';
echo '<div class="h5 mb-3">内容统计</div>';
echo '<div class="row g-3">';
echo '<div class="col-3">';
echo '<div class="stat-card">';
echo '<div class="stat-number">' . e((string)$postCount) . '</div>';
echo '<div class="stat-label">发布帖子</div>';
echo '</div>';
echo '</div>';
echo '<div class="col-3">';
echo '<div class="stat-card">';
echo '<div class="stat-number">' . e((string)$commentCount) . '</div>';
echo '<div class="stat-label">发表评论</div>';
echo '</div>';
echo '</div>';
echo '<div class="col-3">';
echo '<a href="/favorites.php" class="text-decoration-none">';
echo '<div class="stat-card" style="cursor:pointer;transition:all .2s;" onmouseover="this.style.background=\'#e9ecef\'" onmouseout="this.style.background=\'#f8f9fa\'">';
echo '<div class="stat-number" style="color:#ffc107;">' . e((string)$favoriteCount) . '</div>';
echo '<div class="stat-label">⭐ 我的收藏</div>';
echo '</div>';
echo '</a>';
echo '</div>';
echo '<div class="col-3">';
$totalInteract = $postCount + $commentCount;
echo '<div class="stat-card">';
echo '<div class="stat-number">' . e((string)$totalInteract) . '</div>';
echo '<div class="stat-label">总互动数</div>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>';

echo '<div class="card card-lite">';
echo '<div class="card-body p-0">';
echo '<ul class="nav nav-tabs px-3 pt-3" role="tablist">';
echo '<li class="nav-item" role="presentation">';
$postsActive = $tab === 'posts' ? ' active' : '';
echo '<a class="nav-link' . $postsActive . '" href="/profile.php?tab=posts">我的帖子</a>';
echo '</li>';
echo '<li class="nav-item" role="presentation">';
$commentsActive = $tab === 'comments' ? ' active' : '';
echo '<a class="nav-link' . $commentsActive . '" href="/profile.php?tab=comments">我的评论</a>';
echo '</li>';
echo '</ul>';

echo '<div class="p-4">';

if ($tab === 'posts') {
    echo '<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">';
    echo '<div class="fw-semibold">我发布的帖子</div>';
    echo '<div class="text-muted small">共 ' . e((string)$postCount) . ' 篇</div>';
    echo '</div>';

    if (!$posts) {
        echo '<div class="text-center py-5">';
        echo '<div style="font-size:3rem;margin-bottom:1rem;">📝</div>';
        echo '<div class="h5 mb-2">还没有发布过帖子</div>';
        echo '<div class="text-muted mb-3">分享你的想法，发布第一篇帖子吧</div>';
        echo '<a class="btn btn-primary" href="/post_add.php">发布帖子</a>';
        echo '</div>';
    } else {
        foreach ($posts as $post) {
            $excerptSource = strip_tags(sanitize_rich_html((string)$post['content']));
            if (function_exists('mb_substr')) {
                $excerpt = mb_substr($excerptSource, 0, 100);
            } else {
                $excerpt = substr($excerptSource, 0, 100);
            }
            if (strlen($excerptSource) > strlen($excerpt)) {
                $excerpt .= '...';
            }

            $postCanEdit = can_user_edit_post($config, $u, $post, (int)$post['comment_count']);

            echo '<div class="border-bottom pb-3 mb-3 last:border-0">';
            echo '<div class="d-flex justify-content-between gap-3">';
            echo '<div class="flex-grow-1">';
            echo '<a class="h6 text-decoration-none mb-1 d-block" href="/post.php?id=' . e((string)$post['id']) . '">' . e((string)$post['title']) . '</a>';
            echo '<div class="text-muted small">' . e((string)$excerpt) . '</div>';
            echo '<div class="text-muted small mt-2">';
            echo '<span class="me-2">📅 ' . e((string)$post['create_time']) . '</span>';
            if (!empty($post['update_time'])) {
                echo '<span class="me-2">✏️ ' . e((string)$post['update_time']) . '</span>';
            }
            echo '<span>💬 ' . e((string)$post['comment_count']) . ' 评论</span>';
            echo '</div>';
            echo '</div>';
            echo '<div class="text-end d-flex flex-column gap-2">';
            echo '<a class="btn btn-sm btn-outline-secondary" href="/post.php?id=' . e((string)$post['id']) . '">查看</a>';
            if ($postCanEdit) {
                echo '<a class="btn btn-sm btn-outline-primary" href="/post_edit.php?id=' . e((string)$post['id']) . '">编辑</a>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        if ($pg['pages'] > 1) {
            echo '<nav aria-label="Page navigation" class="mt-4">';
            echo '<ul class="pagination justify-content-center mb-0">';
            for ($i = 1; $i <= $pg['pages']; $i++) {
                $active = $i === $pg['page'] ? ' active' : '';
                $qs = build_profile_query_string(['page' => $i, 'tab' => 'posts']);
                echo '<li class="page-item' . $active . '"><a class="page-link" href="/profile.php' . e($qs ? $qs : '?tab=posts&page=' . $i) . '">' . e((string)$i) . '</a></li>';
            }
            echo '</ul></nav>';
        }
    }
} else {
    echo '<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">';
    echo '<div class="fw-semibold">我发表的评论</div>';
    echo '<div class="text-muted small">共 ' . e((string)$commentCount) . ' 条</div>';
    echo '</div>';

    if (!$comments) {
        echo '<div class="text-center py-5">';
        echo '<div style="font-size:3rem;margin-bottom:1rem;">💬</div>';
        echo '<div class="h5 mb-2">还没有发表过评论</div>';
        echo '<div class="text-muted mb-3">去帖子下面参与讨论，发表你的看法</div>';
        echo '<a class="btn btn-primary" href="/index.php">浏览帖子</a>';
        echo '</div>';
    } else {
        foreach ($comments as $c) {
            $commentContent = (string)$c['content'];
            if (function_exists('mb_substr') && mb_strlen($commentContent) > 120) {
                $shortContent = mb_substr($commentContent, 0, 120) . '...';
            } else {
                $shortContent = $commentContent;
            }

            echo '<div class="border-bottom pb-3 mb-3 comment-item">';
            echo '<div class="d-flex justify-content-between flex-wrap gap-2 mb-2">';
            echo '<a class="text-decoration-none text-decoration-underline" href="/post.php?id=' . e((string)$c['post_id']) . '">';
            echo '📄 ' . e((string)$c['post_title']);
            echo '</a>';
            echo '<div class="text-muted small">' . e((string)$c['create_time']) . '</div>';
            echo '</div>';
            echo '<div class="text-muted">' . e($shortContent) . '</div>';
            echo '</div>';
        }

        if ($pg['pages'] > 1) {
            echo '<nav aria-label="Page navigation" class="mt-4">';
            echo '<ul class="pagination justify-content-center mb-0">';
            for ($i = 1; $i <= $pg['pages']; $i++) {
                $active = $i === $pg['page'] ? ' active' : '';
                $qs = build_profile_query_string(['page' => $i, 'tab' => 'comments']);
                echo '<li class="page-item' . $active . '"><a class="page-link" href="/profile.php' . e($qs ? $qs : '?tab=comments&page=' . $i) . '">' . e((string)$i) . '</a></li>';
            }
            echo '</ul></nav>';
        }
    }
}

echo '</div>';
echo '</div>';
echo '</div>';

render_footer();
