<?php
declare(strict_types=1);

/*
 * 帖子编辑：
 * - 管理员可修改标题与内容
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

admin_require_login();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '编辑帖子 - Lite Forum', 'active' => 'admin']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    flash_set('danger', '参数错误。');
    redirect('/admin/posts.php');
}

$stmt = $pdo->prepare('SELECT id, title, content, status FROM posts WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    flash_set('danger', '帖子不存在。');
    redirect('/admin/posts.php');
}

$title = (string)$post['title'];
$content = (string)$post['content'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $content = (string)($_POST['content'] ?? '');

    if ($title === '' || strlen($title) > 200) {
        $errors['title'] = '标题为必填，且不超过 200 字。';
    }
    if (trim(strip_tags($content)) === '') {
        $errors['content'] = '帖子内容不能为空。';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE posts SET title = ?, content = ?, update_time = NOW() WHERE id = ?');
        $stmt->execute([$title, $content, $id]);
        flash_set('success', '帖子已更新。');
        redirect('/admin/posts.php');
    }
}

render_header($config, ['title' => '编辑帖子 - Lite Forum', 'active' => 'admin']);

echo '<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">';
echo '<div><h1 class="h4 mb-0">编辑帖子</h1><div class="text-muted small mt-1">ID: ' . e((string)$id) . '</div></div>';
echo '<div class="d-flex gap-2">';
echo '<a class="btn btn-outline-secondary" href="/admin/posts.php">返回列表</a>';
echo '<a class="btn btn-outline-secondary" href="/post.php?id=' . e((string)$id) . '" target="_blank" rel="noopener">前台预览</a>';
echo '</div>';
echo '</div>';

echo '<div class="card card-lite">';
echo '<div class="card-body p-4">';
echo '<form method="post" novalidate>'; 

echo '<div class="mb-3">';
echo '<label class="form-label">标题<span class="required-star">*</span></label>';
echo '<input class="form-control" name="title" maxlength="200" required value="' . e($title) . '">';
if (isset($errors['title'])) {
    echo '<div class="text-danger small mt-1">' . e($errors['title']) . '</div>';
}
echo '</div>';

echo '<div class="mb-3">';
echo '<label class="form-label">内容<span class="required-star">*</span></label>';
echo '<div class="tiny-editor-shell">';
echo '<textarea class="form-control border-0" id="editor" name="content" rows="10">' . e($content) . '</textarea>';
echo '</div>';
if (isset($errors['content'])) {
    echo '<div class="text-danger small mt-1">' . e($errors['content']) . '</div>';
}
echo '</div>';

echo '<div class="d-flex gap-2">';
echo '<button class="btn btn-primary" type="submit">保存</button>';
echo '<a class="btn btn-outline-secondary" href="/admin/posts.php">取消</a>';
echo '</div>';

echo '</form>';
echo '</div></div>';

echo '<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.4/tinymce.min.js"></script>';
echo '<script>';
echo 'tinymce.init({';
echo 'selector:"#editor",';
echo 'menubar:false,';
echo 'branding:false,';
echo 'plugins:"lists link",';
echo 'toolbar:"undo redo | bold italic | bullist numlist | link | removeformat",';
echo 'paste_data_images:false,';
echo 'height:360,';
echo 'content_style:"body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; font-size:16px;}"';
echo '});';
echo '</script>';

render_footer();

