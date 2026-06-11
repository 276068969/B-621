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

$stmt = $pdo->prepare('SELECT id, board_id, title, content, status FROM posts WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    flash_set('danger', '帖子不存在。');
    redirect('/admin/posts.php');
}

$boards = get_boards($pdo, false);

$title = (string)$post['title'];
$content = (string)$post['content'];
$boardId = (int)$post['board_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $content = (string)($_POST['content'] ?? '');
    $boardId = isset($_POST['board_id']) ? (int)$_POST['board_id'] : 0;

    if ($title === '' || strlen($title) > 200) {
        $errors['title'] = '标题为必填，且不超过 200 字。';
    }
    if (trim(strip_tags($content)) === '') {
        $errors['content'] = '帖子内容不能为空。';
    }
    if ($boardId <= 0 || !is_valid_board_id($pdo, $boardId, false)) {
        $errors['board_id'] = '请选择有效的版块。';
    }

    if (!$errors) {
        $modResult = moderate_post($title, $content);
        if (!$modResult['passed']) {
            if (!$modResult['title_passed']) {
                $errors['title'] = $modResult['title_result']['message'];
            }
            if (!$modResult['content_passed']) {
                $errors['content'] = $modResult['content_result']['message'];
            }
        }
    }

    if (!$errors) {
        $oldTitle = (string)$post['title'];
        $oldContent = (string)$post['content'];
        $oldBoardId = (int)$post['board_id'];

        $content = normalize_rich_html($content);

        $changes = [];
        if ($oldTitle !== $title) {
            $changes[] = '标题: "' . mb_substr($oldTitle, 0, 50) . '" → "' . mb_substr($title, 0, 50) . '"';
        }
        if ($oldContent !== $content) {
            $changes[] = '内容已更新';
        }
        if ($oldBoardId !== $boardId) {
            $oldBoard = get_board_by_id($pdo, $oldBoardId);
            $newBoard = get_board_by_id($pdo, $boardId);
            $oldBoardName = $oldBoard ? $oldBoard['name'] : '未分类';
            $newBoardName = $newBoard ? $newBoard['name'] : '未分类';
            $changes[] = '版块: ' . $oldBoardName . ' → ' . $newBoardName;
        }
        $detail = !empty($changes) ? implode('; ', $changes) : '帖子内容无变化';

        $stmt = $pdo->prepare('UPDATE posts SET board_id = ?, title = ?, content = ?, update_time = NOW() WHERE id = ?');
        $stmt->execute([$boardId, $title, $content, $id]);
        admin_log_operation($pdo, 'post_edit', 'post', $id, $detail);
        flash_set('success', '帖子已更新。');
        redirect('/admin/posts.php');
    }
}

render_header($config, ['title' => '编辑帖子 - Lite Forum', 'active' => 'admin']);

echo '<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">';
echo '<div class="d-flex align-items-center gap-2">';
echo '<div><h1 class="h4 mb-0">编辑帖子</h1><div class="text-muted small mt-1">ID: ' . e((string)$id) . '</div></div>';
echo '<span class="badge bg-info text-white" id="previewStatusBadge" style="display:none;">预览中</span>';
echo '</div>';
echo '<div class="d-flex gap-2">';
echo '<button class="btn btn-outline-primary btn-sm" id="togglePreviewBtn" type="button">显示预览</button>';
echo '<a class="btn btn-outline-secondary" href="/admin/posts.php">返回列表</a>';
echo '<a class="btn btn-outline-secondary" href="/post.php?id=' . e((string)$id) . '" target="_blank" rel="noopener">前台预览</a>';
echo '</div>';
echo '</div>';

echo '<div class="row g-3" id="editorContainer">';

echo '<div class="col-lg-6" id="editPanel">';
echo '<div class="card card-lite h-100">';
echo '<div class="card-body p-4">';
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h2 class="h6 mb-0 text-muted">编辑区</h2>';
echo '</div>';

echo '<form method="post" novalidate id="postForm">'; 

echo '<div class="mb-3">';
echo '<label class="form-label">版块<span class="required-star">*</span></label>';
echo '<select class="form-select" name="board_id" id="board_id" required>';
echo '<option value="">请选择版块</option>';
foreach ($boards as $b) {
    $selected = $boardId === (int)$b['id'] ? ' selected' : '';
    echo '<option value="' . e((string)$b['id']) . '"' . $selected . '>' . e((string)$b['name']) . '</option>';
}
echo '</select>';
if (isset($errors['board_id'])) {
    echo '<div class="text-danger small mt-1">' . e($errors['board_id']) . '</div>';
}
echo '</div>';

echo '<div class="mb-3">';
echo '<label class="form-label">标题<span class="required-star">*</span></label>';
echo '<input class="form-control" name="title" id="title" maxlength="200" required value="' . e($title) . '">';
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
echo '<button class="btn btn-primary" type="submit" id="submitBtn">保存</button>';
echo '<a class="btn btn-outline-secondary" href="/admin/posts.php" id="cancelBtn">取消</a>';
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
echo '      let content = ""';
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
echo '      const username = "管理员";';
echo '      const nowStr = now.toLocaleDateString("zh-CN") + " " + timeStr;';

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
echo '            alert("帖子内容不能为空。");';
echo '            return;';
echo '          }';
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

