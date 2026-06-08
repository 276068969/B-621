<?php
declare(strict_types=1);

/*
 * 帖子编辑（普通用户）：
 * - 作者可在限定时间内、尚无评论时编辑自己的帖子
 * - 更新 update_time 字段
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

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
    flash_set('danger', '参数错误。');
    redirect('/index.php');
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.user_id, p.title, p.content, p.create_time, p.update_time, p.status, u.username
     FROM posts p
     JOIN users u ON u.id = p.user_id
     WHERE p.id = ?
     LIMIT 1'
);
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post || (int)$post['status'] !== 1) {
    flash_set('danger', '帖子不存在或已被删除。');
    redirect('/index.php');
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 1');
$stmt->execute([$id]);
$commentCount = (int)$stmt->fetchColumn();

$u = user();
$editConfig = $config['post_edit'] ?? [];
$timeLimitMinutes = (int)($editConfig['time_limit_minutes'] ?? 30);
$allowWithComments = (bool)($editConfig['allow_with_comments'] ?? false);

if (!can_user_edit_post($config, $u, $post, $commentCount)) {
    $reason = '无法编辑该帖子。';
    if ((int)$post['user_id'] !== (int)$u['id']) {
        $reason = '只能编辑自己发布的帖子。';
    } elseif (!$allowWithComments && $commentCount > 0) {
        $reason = '帖子已有评论，无法再编辑。';
    } elseif ($timeLimitMinutes > 0) {
        $reason = '已超过编辑时限（发布后 ' . $timeLimitMinutes . ' 分钟内可编辑）。';
    }

    flash_set('danger', $reason);
    redirect('/post.php?id=' . $id);
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
        if (!can_user_edit_post($config, $u, $post, $commentCount)) {
            flash_set('danger', '编辑时限已过或已有评论，无法保存修改。');
            redirect('/post.php?id=' . $id);
        }

        $stmt = $pdo->prepare('UPDATE posts SET title = ?, content = ?, update_time = NOW() WHERE id = ?');
        $stmt->execute([$title, $content, $id]);
        flash_set('success', '帖子已更新。');
        redirect('/post.php?id=' . $id);
    }
}

$remainingMinutes = get_edit_remaining_minutes($config, $post);

render_header($config, ['title' => '编辑帖子 - Lite Forum', 'active' => 'home']);

echo '<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">';
echo '<div class="d-flex align-items-center gap-2">';
echo '<div>';
echo '<h1 class="h4 mb-0">编辑帖子</h1>';
echo '<div class="text-muted small mt-1">';
if ($remainingMinutes > 0) {
    echo '距编辑时限还有约 ' . e((string)$remainingMinutes) . ' 分钟';
} else {
    echo '编辑时限即将截止，请尽快保存';
}
if (!$allowWithComments) {
    echo ' · 评论出现后将无法编辑';
}
echo '</div>';
echo '</div>';
echo '<span class="badge bg-info text-white" id="previewStatusBadge" style="display:none;">预览中</span>';
echo '</div>';
echo '<div class="d-flex gap-2">';
echo '<button class="btn btn-sm btn-outline-primary" id="togglePreviewBtn" type="button">显示预览</button>';
echo '<a class="btn btn-sm btn-outline-secondary" href="/post.php?id=' . e((string)$id) . '">返回帖子</a>';
echo '</div>';
echo '</div>';

echo '<div class="row g-3" id="editorContainer">';

echo '<div class="col-lg-6" id="editPanel">';
echo '<div class="card card-lite h-100">';
echo '<div class="card-body p-4">';
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h2 class="h6 mb-0 text-muted">编辑区</h2>';
echo '</div>';

echo '<form method="post" class="needs-validation" id="postForm" novalidate>'; 

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
echo '<button class="btn btn-primary px-4 py-2 fw-semibold shadow-sm" type="submit" id="submitBtn">保存修改</button>';
echo '<a class="btn btn-outline-secondary px-4 py-2" href="/post.php?id=' . e((string)$id) . '" id="cancelBtn">取消</a>';
echo '</div>';

echo '</form>';
echo '</div></div></div>';

echo '<div class="col-lg-6" id="previewPanel" style="display:none;">';
echo '<div class="card card-lite h-100">';
echo '<div class="card-body p-4">';
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h2 class="h6 mb-0 text-muted">实时预览</h2>';
echo '<span class="text-muted small" id="lastPreviewTime"></span>';
echo '</div>';
echo '<div class="border rounded-3 p-4 bg-light" id="previewContent" style="min-height:400px;">';
echo '<div class="text-center text-muted py-5">';
echo '<div class="fs-1 mb-2">👁️</div>';
echo '<div>开始编辑后，这里将显示实时预览</div>';
echo '</div>';
echo '</div>';
echo '<div class="form-text mt-2 text-center">预览效果与实际发布页面一致</div>';
echo '</div></div></div>';

echo '</div>';

echo '<style>';
echo '.preview-article h1.preview-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 0.75rem; }';
echo '.preview-article .preview-meta { color: #6c757d; font-size: 0.875rem; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #dee2e6; }';
echo '.preview-article .post-content { line-height: 1.75; font-size: 1rem; }';
echo '.preview-article .post-content p { margin-bottom: 1rem; }';
echo '.preview-article .post-content ul, .preview-article .post-content ol { margin-bottom: 1rem; padding-left: 2rem; }';
echo '.preview-article .post-content a { color: #0d6efd; text-decoration: none; }';
echo '.preview-article .post-content a:hover { text-decoration: underline; }';
echo '.preview-article .post-content strong { font-weight: 600; }';
echo '#previewPanel .card { position: sticky; top: 1rem; }';
echo '</style>';

echo '<script>';
echo '(function() {';
echo '  window.PostEditor = {';
echo '    formDirty: false,';
echo '    previewVisible: false,';
echo '    initialTitle: "",';
echo '    initialContent: "",';

echo '    checkDirty: function() {';
echo '      const titleEl = document.getElementById("title");';
echo '      let content = "";';
echo '      if (window.tinymce && tinymce.get("editor")) { content = tinymce.get("editor").getContent(); }';
echo '      else if (document.getElementById("editor")) { content = document.getElementById("editor").value; }';
echo '      const currentTitle = titleEl ? titleEl.value : "";';
echo '      this.formDirty = (currentTitle !== this.initialTitle) || (content !== this.initialContent);';
echo '    },';

echo '    markDirty: function() {';
echo '      this.checkDirty();';
echo '    },';

echo '    updatePreview: function() {';
echo '      const titleEl = document.getElementById("title");';
echo '      const contentEl = document.getElementById("editor");';
echo '      const previewEl = document.getElementById("previewContent");';
echo '      const timeEl = document.getElementById("lastPreviewTime");';
echo '      const title = titleEl.value.trim();';
echo '      let content = "";';
echo '      if (window.tinymce && tinymce.get("editor")) { content = tinymce.get("editor").getContent(); }';
echo '      else { content = contentEl.value; }';

echo '      if (!title && !content.trim()) {';
echo '        previewEl.innerHTML = \'<div class="text-center text-muted py-5"><div class="fs-1 mb-2">👁️</div><div>开始编辑后，这里将显示实时预览</div></div>\';';
echo '        return;';
echo '      }';

echo '      const now = new Date();';
echo '      const timeStr = now.toLocaleTimeString("zh-CN", { hour: "2-digit", minute: "2-digit", second: "2-digit" });';
echo '      timeEl.textContent = "更新于 " + timeStr;';

echo '      const displayTitle = title || "<span class=\'text-muted\'>（未填写标题）</span>";';
echo '      const displayContent = content || "<p class=\'text-muted\'>（未填写内容）</p>";';
echo '      const username = "' . (user() ? e((string)user()['username']) : '匿名用户') . '";';
echo '      const nowStr = now.toLocaleDateString("zh-CN") + " " . timeStr;';

echo '      previewEl.innerHTML = \'<div class="preview-article">\' +';
echo '        \'<h1 class="preview-title">\' + displayTitle + \'</h1>\' +';
echo '        \'<div class="preview-meta">作者：\' + username + \' · 发布：\' + nowStr + \'</div>\' +';
echo '        \'<article class="post-content">\' + displayContent + \'</article>\' +';
echo '        \'</div>\';';
echo '    },';

echo '    markDirty: function() {';
echo '      this.formDirty = true;';
echo '    },';

echo '    togglePreview: function() {';
echo '      const previewPanel = document.getElementById("previewPanel");';
echo '      const editPanel = document.getElementById("editPanel");';
echo '      const toggleBtn = document.getElementById("togglePreviewBtn");';
echo '      const badge = document.getElementById("previewStatusBadge");';
echo '      this.previewVisible = !this.previewVisible;';
echo '      if (this.previewVisible) {';
echo '        previewPanel.style.display = "block";';
echo '        editPanel.classList.remove("col-lg-12");';
echo '        editPanel.classList.add("col-lg-6");';
echo '        toggleBtn.textContent = "隐藏预览";';
echo '        badge.style.display = "inline-block";';
echo '        this.updatePreview();';
echo '        if (window.tinymce && tinymce.get("editor")) { tinymce.get("editor").focus(); }';
echo '      } else {';
echo '        previewPanel.style.display = "none";';
echo '        editPanel.classList.remove("col-lg-6");';
echo '        editPanel.classList.add("col-lg-12");';
echo '        toggleBtn.textContent = "显示预览";';
echo '        badge.style.display = "none";';
echo '      }';
echo '    },';

echo '    init: function() {';
echo '      const self = this;';

echo '      window.addEventListener("beforeunload", function(e) {';
echo '        if (self.formDirty) {';
echo '          e.preventDefault();';
echo '          e.returnValue = "您有未保存的内容，确定要离开吗？";';
echo '          return e.returnValue;';
echo '        }';
echo '      });';

echo '      const form = document.getElementById("postForm");';
echo '      if (form) {';
echo '        form.addEventListener("submit", function(e) {';
echo '          if (window.tinymce && tinymce.get("editor")) { tinymce.get("editor").save(); }';
echo '          const content = document.getElementById("editor").value.trim();';
echo '          if (!content) {';
echo '            e.preventDefault();';
echo '            e.stopPropagation();';
echo '            showModal("保存失败", "帖子内容不能为空。");';
echo '            return;';
echo '          }';
echo '          if (!this.checkValidity()) {';
echo '            e.preventDefault();';
echo '            e.stopPropagation();';
echo '            showModal("保存失败", "请完善标题等必填项。");';
echo '            return;';
echo '          }';
echo '          this.classList.add("was-validated");';
echo '          self.formDirty = false;';
echo '        });';
echo '      }';

echo '      const titleEl = document.getElementById("title");';
echo '      if (titleEl) {';
echo '        self.initialTitle = titleEl.value;';
echo '        titleEl.addEventListener("input", function() {';
echo '          self.markDirty();';
echo '          if (self.previewVisible) { self.updatePreview(); }';
echo '        });';
echo '      }';

echo '      const contentEl = document.getElementById("editor");';
echo '      if (contentEl) {';
echo '        self.initialContent = contentEl.value;';
echo '      }';

echo '      const toggleBtn = document.getElementById("togglePreviewBtn");';
echo '      if (toggleBtn) {';
echo '        toggleBtn.addEventListener("click", function() { self.togglePreview(); });';
echo '      }';
echo '    }';
echo '  };';

echo '  if (document.readyState === "loading") {';
echo '    document.addEventListener("DOMContentLoaded", function() { window.PostEditor.init(); });';
echo '  } else {';
echo '    window.PostEditor.init();';
echo '  }';
echo '})();';
echo '</script>';

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
echo 'content_style:"body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; font-size:16px;}",';
echo 'setup:function(ed) {';
echo '  ed.on("input change keyup", function() {';
echo '    if (window.PostEditor) {';
echo '      window.PostEditor.markDirty();';
echo '      if (window.PostEditor.previewVisible) { window.PostEditor.updatePreview(); }';
echo '    }';
echo '  });';
echo '  ed.on("init", function() {';
echo '    if (window.PostEditor) {';
echo '      window.PostEditor.initialContent = ed.getContent();';
echo '      window.PostEditor.formDirty = false;';
echo '    }';
echo '  });';
echo '}';
echo '});';
echo '</script>';

render_footer();
