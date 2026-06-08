<?php
declare(strict_types=1);

/*
 * 帖子详情页：
 * - 渲染帖子富文本内容（基础白名单清洗）
 * - 展示评论区
 * - 登录用户可发表评论（自动关联用户）
 */

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '系统错误 - Lite Forum', 'active' => 'home']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    render_header($config, ['title' => '帖子不存在 - Lite Forum', 'active' => 'home']);
    echo '<div class="card card-lite p-4">帖子不存在或参数错误。</div>';
    render_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $content = trim((string)($_POST['content'] ?? ''));
    if ($content === '') {
        flash_set('danger', '评论内容不能为空。');
        redirect('/post.php?id=' . $id);
    }

    $modResult = moderate_comment($content);
    if (!$modResult['passed']) {
        flash_set('danger', $modResult['message']);
        redirect('/post.php?id=' . $id);
    }

    $u = user();
    $stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, content, status) VALUES (?, ?, ?, 1)');
    $stmt->execute([$id, (int)$u['id'], $content]);
    flash_set('success', '评论已发布。');
    redirect('/post.php?id=' . $id);
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.user_id, p.title, p.content, p.create_time, p.update_time, u.username
     FROM posts p
     JOIN users u ON u.id = p.user_id
     WHERE p.id = ? AND p.status = 1
     LIMIT 1'
);
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    render_header($config, ['title' => '帖子不存在 - Lite Forum', 'active' => 'home']);
    echo '<div class="card card-lite p-4">帖子不存在或已被删除。</div>';
    render_footer();
    exit;
}

$stmt = $pdo->prepare(
    'SELECT c.id, c.content, c.create_time, u.username
     FROM comments c
     JOIN users u ON u.id = c.user_id
     WHERE c.post_id = ? AND c.status = 1
     ORDER BY c.create_time ASC'
);
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

$hotPosts = get_hot_posts($pdo, 6, $id);

render_header($config, ['title' => (string)$post['title'] . ' - Lite Forum', 'active' => 'home']);

$canEdit = can_user_edit_post($config, user(), $post, count($comments));

echo '<div class="card card-lite mb-3">';
echo '<div class="card-body">';
echo '<div class="d-flex justify-content-between flex-wrap gap-2">';
echo '<h1 class="h4 mb-0">' . e((string)$post['title']) . '</h1>';
echo '<div class="d-flex gap-2">';
if ($canEdit) {
    echo '<a class="btn btn-sm btn-outline-primary" href="/post_edit.php?id=' . e((string)$id) . '">编辑</a>';
}
echo '<a class="btn btn-sm btn-outline-secondary" href="/index.php">返回列表</a>';
echo '</div>';
echo '</div>';
echo '<div class="text-muted small mt-2">';
echo '作者：' . e((string)$post['username']) . ' · 发布：' . e((string)$post['create_time']);
if (!empty($post['update_time'])) {
    echo ' · 更新：' . e((string)$post['update_time']);
}
echo '</div>';
echo '<hr>';
echo '<article class="post-content">' . sanitize_rich_html((string)$post['content']) . '</article>';
echo '</div>';
echo '</div>';

echo '<div class="card card-lite mb-3">';
echo '<div class="card-body">';
echo '<div class="d-flex align-items-center justify-content-between">';
echo '<div class="fw-semibold">评论</div>';
echo '<div class="text-muted small">' . e((string)count($comments)) . ' 条</div>';
echo '</div>';
echo '<div class="mt-3">';

if (!$comments) {
    echo '<div class="text-muted">暂无评论。</div>';
} else {
    foreach ($comments as $c) {
        echo '<div class="border rounded-3 p-3 mb-2">';
        echo '<div class="d-flex justify-content-between flex-wrap gap-2">';
        echo '<div class="fw-semibold">' . e((string)$c['username']) . '</div>';
        echo '<div class="text-muted small">' . e((string)$c['create_time']) . '</div>';
        echo '</div>';
        echo '<div class="mt-2">' . e((string)$c['content']) . '</div>';
        echo '</div>';
    }
}

echo '</div>';
echo '</div>';
echo '</div>';

echo '<div class="card card-lite mb-3 border-0 shadow-sm">';
echo '<div class="card-body p-4">';
echo '<div class="fw-semibold mb-3 fs-5">发表评论</div>';
if (user() === null) {
    echo '<div class="alert alert-light border d-flex align-items-center" role="alert">';
    echo '<span class="me-2">🔒</span>';
    echo '<div>未登录用户仅可查看。请先 <a href="/login.php?return=' . urlencode('/post.php?id=' . $id) . '" class="fw-bold text-decoration-none">登录</a> 后参与讨论。</div>';
    echo '</div>';
} else {
    echo '<form method="post" class="needs-validation" novalidate>'; 
    echo '<div class="mb-3 position-relative">';
    echo '<textarea class="form-control" name="content" rows="4" placeholder="写下你的看法..." required style="resize:none;"></textarea>';
    echo '<div class="invalid-feedback">评论内容不能为空。</div>';
    echo '<div class="form-text text-end mt-1"><small>支持纯文本，发布后自动关联账号</small></div>';
    echo '</div>';
    echo '<div class="text-end">';
    echo '<button class="btn btn-primary px-4 rounded-pill fw-semibold" type="submit">发布评论</button>';
    echo '</div>';
    echo '</form>';
}
echo '</div>';
echo '</div>';

if ($hotPosts) {
    echo '<div class="card card-lite mb-3 border-0 shadow-sm">';
    echo '<div class="card-body p-4">';
    echo '<div class="d-flex align-items-center gap-2 mb-3">';
    echo '<span style="font-size:1.1rem;">🔥</span>';
    echo '<div class="fw-semibold fs-5">热门讨论</div>';
    echo '</div>';
    echo '<div class="row g-3">';
    foreach ($hotPosts as $hp) {
        echo '<div class="col-md-6 col-12">';
        echo '<a href="/post.php?id=' . e((string)$hp['id']) . '" class="text-decoration-none">';
        echo '<div class="hot-recommend-item">';
        echo '<div class="hot-recommend-title">' . e((string)$hp['title']) . '</div>';
        echo '<div class="hot-recommend-meta text-muted small">';
        echo '<span class="me-2">' . e((string)$hp['username']) . '</span>';
        echo '<span class="hot-comment-badge">' . e((string)$hp['comment_count']) . ' 评论</span>';
        echo '</div>';
        echo '</div>';
        echo '</a>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

// 前端校验
echo '<script>';
echo '(() => {';
echo '  "use strict";';
echo '  const forms = document.querySelectorAll(".needs-validation");';
echo '  Array.from(forms).forEach(form => {';
echo '    form.addEventListener("submit", event => {';
echo '      if (!form.checkValidity()) {';
echo '        event.preventDefault();';
echo '        event.stopPropagation();';
echo '        showModal("提示", "请输入评论内容。");';
echo '      }';
echo '      form.classList.add("was-validated");';
echo '    }, false);';
echo '  });';
echo '})();';
echo '</script>';

echo '<style>';
echo '.hot-recommend-item{padding:.75rem 1rem;border-radius:.5rem;background:#f8f9fa;transition:all .2s;height:100%;}';
echo '.hot-recommend-item:hover{background:#e9ecef;transform:translateY(-2px);}';
echo '.hot-recommend-title{color:#212529;font-size:.95rem;font-weight:500;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.4;}';
echo '.hot-recommend-meta{margin-top:.5rem;display:flex;align-items:center;}';
echo '.hot-comment-badge{font-size:.75rem;color:#495057;background:#dee2e6;padding:.15rem .5rem;border-radius:1rem;}';
echo '</style>';

render_footer();

