<?php
declare(strict_types=1);

/*
 * 发帖页：
 * - 必须登录
 * - 帖子内容使用 TinyMCE 富文本编辑器（CDN）
 * - 内容原样入库，详情页渲染时做基础白名单清洗
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '系统错误 - Lite Forum', 'active' => 'post_add']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$title = '';
$content = '';
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
        $u = user();
        $stmt = $pdo->prepare('INSERT INTO posts (user_id, title, content, status) VALUES (?, ?, ?, 1)');
        $stmt->execute([(int)$u['id'], $title, $content]);
        $postId = (int)$pdo->lastInsertId();
        flash_set('success', '帖子已发布。');
        redirect('/post.php?id=' . $postId);
    }
}

render_header($config, ['title' => '发布帖子 - Lite Forum', 'active' => 'post_add']);

echo '<div class="card card-lite">';
echo '<div class="card-body p-4">';
echo '<div class="d-flex justify-content-between flex-wrap gap-2">';
echo '<h1 class="h4 mb-0">发布帖子</h1>';
echo '<a class="btn btn-sm btn-outline-secondary" href="/index.php">返回列表</a>';
echo '</div>';

echo '<form method="post" class="mt-3 needs-validation" id="postForm" novalidate>'; 

echo '<div class="form-floating mb-3">';
echo '<input class="form-control" name="title" id="title" placeholder="标题" maxlength="200" required value="' . e($title) . '">';
echo '<label for="title">标题</label>';
echo '<div class="form-text">建议用一句话概括核心问题/主题。</div>';
echo '<div class="invalid-feedback">请输入标题（不超过 200 字）。</div>';
if (isset($errors['title'])) {
    echo '<div class="text-danger small mt-1">❌ ' . e($errors['title']) . '</div>';
}
echo '</div>';

echo '<div class="mb-4">';
echo '<label class="form-label fw-bold">帖子内容</label>';
echo '<div class="tiny-editor-shell">';
echo '<textarea class="form-control border-0" id="editor" name="content" rows="12">' . e($content) . '</textarea>';
echo '</div>';
echo '<div class="form-text mt-1">支持加粗、列表、链接等基础格式；已禁用图片上传，保持轻量。</div>';
if (isset($errors['content'])) {
    echo '<div class="text-danger small mt-1">❌ ' . e($errors['content']) . '</div>';
}
echo '</div>';

echo '<div class="d-flex gap-2">';
echo '<button class="btn btn-primary px-4 py-2 fw-semibold shadow-sm" type="submit">立即发布</button>';
echo '<a class="btn btn-outline-secondary px-4 py-2" href="/index.php">取消</a>';
echo '</div>';

echo '</form>';

// 提交前同步 TinyMCE 内容到 textarea，并做基础校验
echo '<script>';
echo 'document.getElementById("postForm").addEventListener("submit", function(e) {';
echo '  if (tinymce.get("editor")) { tinymce.get("editor").save(); }';
echo '  const content = document.getElementById("editor").value.trim();';
echo '  if (!content) {';
echo '    e.preventDefault();';
echo '    e.stopPropagation();';
echo '    showModal("发布失败", "帖子内容不能为空。");';
echo '    return;';
echo '  }';
echo '  if (!this.checkValidity()) {';
echo '    e.preventDefault();';
echo '    e.stopPropagation();';
echo '    showModal("发布失败", "请完善标题等必填项。");';
echo '  }';
echo '  this.classList.add("was-validated");';
echo '});';
echo '</script>';
echo '</div>';
echo '</div>';

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

