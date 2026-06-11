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
    $spamResult = anti_spam_check_comment($pdo, (int)$u['id'], $content);
    if (!$spamResult['passed']) {
        flash_set('danger', $spamResult['message']);
        redirect('/post.php?id=' . $id);
    }

    $stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, content, status) VALUES (?, ?, ?, 1)');
    $stmt->execute([$id, (int)$u['id'], $content]);
    flash_set('success', '评论已发布。');
    redirect('/post.php?id=' . $id);
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.board_id, p.user_id, p.title, p.content, p.create_time, p.update_time, u.username,
            b.name AS board_name
     FROM posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN boards b ON b.id = p.board_id
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

$currentUser = user();
if ($currentUser !== null) {
    record_read_history($pdo, (int)$currentUser['id'], $id);
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

$isFavorited = false;
if ($currentUser !== null) {
    $isFavorited = is_post_favorited($pdo, (int)$currentUser['id'], $id);
}

render_header($config, ['title' => (string)$post['title'] . ' - Lite Forum', 'active' => 'home']);

$canEdit = can_user_edit_post($config, user(), $post, count($comments));

echo '<div class="card card-lite mb-3" id="post-top">';
echo '<div class="card-body">';
$favIcon = $isFavorited ? '★' : '☆';
$favClass = $isFavorited ? 'btn-favorite active' : 'btn-favorite';
$favTitle = $isFavorited ? '取消收藏' : '收藏';

echo '<div class="d-flex justify-content-between flex-wrap gap-2">';
echo '<div>';
echo '<h1 class="h4 mb-0">' . e((string)$post['title']) . '</h1>';
if (!empty($post['board_name'])) {
    echo '<span class="badge bg-info bg-opacity-10 text-info mt-2" style="font-size:.8rem;">' . e((string)$post['board_name']) . '</span>';
}
echo '</div>';
echo '<div class="d-flex gap-2">';
if ($currentUser !== null) {
    echo '<form method="post" action="/favorite_toggle.php" class="favorite-form" data-post-id="' . $id . '">';
    echo '<input type="hidden" name="post_id" value="' . $id . '">';
    echo '<button type="submit" class="btn btn-sm ' . $favClass . '" title="' . e($favTitle) . '">';
    echo '<span class="favorite-icon">' . $favIcon . '</span>';
    echo '<span class="favorite-text ms-1">' . e($favTitle) . '</span>';
    echo '</button>';
    echo '</form>';
}
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
echo '<article class="post-content" id="post-content">' . sanitize_rich_html((string)$post['content']) . '</article>';
echo '</div>';
echo '</div>';

echo '<div class="card card-lite mb-3" id="comments-section">';
echo '<div class="card-body">';
echo '<div class="d-flex align-items-center justify-content-between">';
echo '<div class="fw-semibold">评论</div>';
echo '<div class="d-flex align-items-center gap-3">';
echo '<div class="form-check form-switch mb-0">';
echo '<input class="form-check-input" type="checkbox" id="author-only-toggle">';
echo '<label class="form-check-label text-muted small" for="author-only-toggle">只看作者</label>';
echo '</div>';
echo '<div class="text-muted small">' . e((string)count($comments)) . ' 条</div>';
echo '</div>';
echo '</div>';
echo '<div class="mt-3" id="comments-list">';

if (!$comments) {
    echo '<div class="text-muted">暂无评论。</div>';
} else {
    $authorUsername = (string)$post['username'];
    foreach ($comments as $idx => $c) {
        $isAuthor = $c['username'] === $authorUsername;
        $commentClass = 'border rounded-3 p-3 mb-2 comment-item';
        if ($isAuthor) {
            $commentClass .= ' comment-is-author';
        }
        $commentContent = (string)$c['content'];
        $commentNum = (int)$idx + 1;
        echo '<div class="' . $commentClass . '" id="comment-' . $commentNum . '" data-username="' . e((string)$c['username']) . '" data-is-author="' . ($isAuthor ? '1' : '0') . '" data-content="' . e($commentContent) . '" data-comment-id="' . $commentNum . '">';
        echo '<div class="d-flex justify-content-between flex-wrap gap-2">';
        echo '<div class="d-flex align-items-center gap-2">';
        echo '<div class="fw-semibold">' . e((string)$c['username']) . '</div>';
        if ($isAuthor) {
            echo '<span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:.7rem;">作者</span>';
        }
        echo '</div>';
        echo '<div class="d-flex align-items-center gap-2">';
        echo '<a href="#comment-' . $commentNum . '" class="text-muted small text-decoration-none comment-anchor" title="复制此评论链接">#' . $commentNum . '</a>';
        echo '<div class="text-muted small">' . e((string)$c['create_time']) . '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="mt-2 comment-content-text">' . e($commentContent) . '</div>';
        echo '<div class="comment-toolbar mt-2 d-flex gap-1">';
        echo '<button type="button" class="comment-tool-btn" data-action="quote" data-comment-id="' . $commentNum . '" title="引用回复">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
        echo '<span>引用</span>';
        echo '</button>';
        echo '<button type="button" class="comment-tool-btn" data-action="copy" data-comment-id="' . $commentNum . '" title="复制评论文本">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
        echo '<span>复制</span>';
        echo '</button>';
        echo '<button type="button" class="comment-tool-btn" data-action="share" data-comment-id="' . $commentNum . '" title="复制评论链接">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>';
        echo '<span>分享</span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
    }
}

echo '</div>';
echo '</div>';
if ($comments && count($comments) >= 5) {
    echo '<div class="text-center py-2">';
    echo '<button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="btn-back-to-reply-bottom">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>';
    echo '返回发表评论';
    echo '</button>';
    echo '</div>';
}
echo '</div>';

echo '<button type="button" class="floating-reply-btn" id="floating-reply-btn" style="display:none;" title="回到评论区">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
echo '<span>写评论</span>';
echo '</button>';

echo '<div class="card card-lite mb-3 border-0 shadow-sm" id="reply-section">';
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
    echo '<div id="quote-indicator" class="quote-indicator" style="display:none;">';
    echo '<div class="d-flex justify-content-between align-items-center mb-2">';
    echo '<div class="d-flex align-items-center gap-2">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
    echo '<span class="small fw-semibold text-primary">正在回复 <span id="quote-username"></span> 的评论</span>';
    echo '</div>';
    echo '<button type="button" id="cancel-quote" class="btn-close btn-close-sm" aria-label="取消引用"></button>';
    echo '</div>';
    echo '<div id="quote-content" class="quote-content small text-muted"></div>';
    echo '</div>';
    echo '<input type="hidden" name="reply_to" id="reply-to-input" value="">';
    echo '<textarea class="form-control" name="content" id="comment-textarea" rows="4" placeholder="写下你的看法..." required style="resize:none;"></textarea>';
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

// 阅读辅助工具栏
echo '<div class="reading-toolbar" id="readingToolbar">';
echo '<div class="reading-toolbar-progress" id="readingProgressBar"></div>';
echo '<button class="reading-toolbar-btn" id="btnBackToTop" title="回到顶部">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>';
echo '</button>';
if ($currentUser !== null) {
    $favToolbarClass = $isFavorited ? 'favorite-btn active' : 'favorite-btn';
    $favToolbarTitle = $isFavorited ? '取消收藏' : '收藏';
    $favToolbarIcon = $isFavorited ? '#ffc107' : 'currentColor';
    echo '<button class="reading-toolbar-btn ' . $favToolbarClass . '" id="btnFavorite" title="' . e($favToolbarTitle) . '" data-post-id="' . $id . '" data-favorited="' . ($isFavorited ? '1' : '0') . '">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="' . ($isFavorited ? 'currentColor' : 'none') . '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
    echo '</button>';
}
echo '<button class="reading-toolbar-btn" id="btnCopyLink" title="复制链接">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>';
echo '</button>';
echo '<button class="reading-toolbar-btn" id="btnGoReply" title="去评论">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
echo '</button>';
echo '<button class="reading-toolbar-btn" id="btnAuthorOnly" title="只看作者">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
echo '</button>';
echo '<div class="reading-toolbar-percent" id="readingPercent">0%</div>';
echo '</div>';

echo '<script>';
echo '(function() {';
echo '"use strict";';
echo 'var forms = document.querySelectorAll(".needs-validation");';
echo 'Array.prototype.forEach.call(forms, function(form) {';
echo '  form.addEventListener("submit", function(event) {';
echo '    if (!form.checkValidity()) {';
echo '      event.preventDefault();';
echo '      event.stopPropagation();';
echo '      showModal("提示", "请输入评论内容。");';
echo '    }';
echo '    form.classList.add("was-validated");';
echo '  }, false);';
echo '});';
echo 'var progressBar = document.getElementById("readingProgressBar");';
echo 'var progressPercent = document.getElementById("readingPercent");';
echo 'var toolbar = document.getElementById("readingToolbar");';
echo 'function updateReadingProgress() {';
echo '  var postContent = document.getElementById("post-content");';
echo '  if (!postContent) return;';
echo '  var windowHeight = window.innerHeight;';
echo '  var docHeight = document.documentElement.scrollHeight;';
echo '  var scrollTop = window.scrollY || document.documentElement.scrollTop;';
echo '  var scrollPercent = Math.min(100, Math.max(0, Math.round((scrollTop / (docHeight - windowHeight)) * 100)));';
echo '  if (progressBar) progressBar.style.height = scrollPercent + "%";';
echo '  if (progressPercent) progressPercent.textContent = scrollPercent + "%";';
echo '  if (toolbar) {';
echo '    if (scrollTop > 200) {';
echo '      toolbar.style.opacity = "1";';
echo '      toolbar.style.pointerEvents = "auto";';
echo '    } else {';
echo '      toolbar.style.opacity = "0";';
echo '      toolbar.style.pointerEvents = "none";';
echo '    }';
echo '  }';
echo '}';
echo 'window.addEventListener("scroll", updateReadingProgress);';
echo 'updateReadingProgress();';
echo 'var btnBackToTop = document.getElementById("btnBackToTop");';
echo 'if (btnBackToTop) {';
echo '  btnBackToTop.addEventListener("click", function() {';
echo '    window.scrollTo({ top: 0, behavior: "smooth" });';
echo '  });';
echo '}';
echo 'var btnCopyLink = document.getElementById("btnCopyLink");';
echo 'if (btnCopyLink) {';
echo '  btnCopyLink.addEventListener("click", function() {';
echo '    var url = window.location.href;';
echo '    var input = document.createElement("input");';
echo '    input.value = url;';
echo '    document.body.appendChild(input);';
echo '    input.select();';
echo '    try { document.execCommand("copy"); showToast("链接已复制到剪贴板"); }';
echo '    catch (e) { showToast("复制失败，请手动复制"); }';
echo '    document.body.removeChild(input);';
echo '  });';
echo '}';
echo 'var btnGoReply = document.getElementById("btnGoReply");';
echo 'if (btnGoReply) {';
echo '  btnGoReply.addEventListener("click", function() {';
echo '    var replySection = document.getElementById("reply-section");';
echo '    if (replySection) {';
echo '      replySection.scrollIntoView({ behavior: "smooth", block: "center" });';
echo '      var textarea = replySection.querySelector("textarea");';
echo '      if (textarea) textarea.focus();';
echo '    }';
echo '  });';
echo '}';
echo 'var authorOnlyToggle = document.getElementById("author-only-toggle");';
echo 'var btnAuthorOnly = document.getElementById("btnAuthorOnly");';
echo 'var authorOnlyMode = false;';

echo 'var btnFavorite = document.getElementById("btnFavorite");';
echo 'function updateFavoriteUI(isFavorited) {';
echo '  var headerForm = document.querySelector(".favorite-form");';
echo '  var headerBtn = headerForm ? headerForm.querySelector("button[type=submit]") : null;';
echo '  var headerIcon = headerForm ? headerForm.querySelector(".favorite-icon") : null;';
echo '  var headerText = headerForm ? headerForm.querySelector(".favorite-text") : null;';
echo '  if (headerBtn) {';
echo '    if (isFavorited) { headerBtn.classList.add("active"); headerBtn.setAttribute("title", "取消收藏"); }';
echo '    else { headerBtn.classList.remove("active"); headerBtn.setAttribute("title", "收藏"); }';
echo '  }';
echo '  if (headerIcon) headerIcon.textContent = isFavorited ? "★" : "☆";';
echo '  if (headerText) headerText.textContent = isFavorited ? "取消收藏" : "收藏";';
echo '  if (btnFavorite) {';
echo '    if (isFavorited) { btnFavorite.classList.add("active"); btnFavorite.setAttribute("title", "取消收藏"); btnFavorite.setAttribute("data-favorited", "1"); }';
echo '    else { btnFavorite.classList.remove("active"); btnFavorite.setAttribute("title", "收藏"); btnFavorite.setAttribute("data-favorited", "0"); }';
echo '    var svg = btnFavorite.querySelector("svg");';
echo '    if (svg) svg.setAttribute("fill", isFavorited ? "currentColor" : "none");';
echo '  }';
echo '}';
echo 'function toggleFavorite(postId) {';
echo '  if (!postId || btnFavorite && btnFavorite.disabled) return;';
echo '  if (btnFavorite) btnFavorite.disabled = true;';
echo '  var formData = new FormData();';
echo '  formData.append("post_id", postId);';
echo '  var xhr = new XMLHttpRequest();';
echo '  xhr.open("POST", "/favorite_toggle.php", true);';
echo '  xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");';
echo '  xhr.onload = function() {';
echo '    if (btnFavorite) btnFavorite.disabled = false;';
echo '    try {';
echo '      var data = JSON.parse(xhr.responseText);';
echo '      if (data.success) { updateFavoriteUI(data.favorited); showToast(data.message); }';
echo '    } catch (err) { console.error("收藏操作失败", err); }';
echo '  };';
echo '  xhr.onerror = function() { if (btnFavorite) btnFavorite.disabled = false; console.error("网络错误"); };';
echo '  xhr.send(formData);';
echo '}';
echo 'if (btnFavorite) {';
echo '  btnFavorite.addEventListener("click", function() {';
echo '    var postId = btnFavorite.getAttribute("data-post-id");';
echo '    toggleFavorite(postId);';
echo '  });';
echo '}';
echo 'var favForm = document.querySelector(".favorite-form");';
echo 'if (favForm) {';
echo '  favForm.addEventListener("submit", function(e) {';
echo '    e.preventDefault();';
echo '    var postId = favForm.getAttribute("data-post-id");';
echo '    toggleFavorite(postId);';
echo '  });';
echo '}';

echo 'function toggleAuthorOnly() {';
echo '  authorOnlyMode = !authorOnlyMode;';
echo '  var commentItems = document.querySelectorAll(".comment-item");';
echo '  for (var i = 0; i < commentItems.length; i++) {';
echo '    var item = commentItems[i];';
echo '    if (authorOnlyMode) {';
echo '      if (item.getAttribute("data-is-author") === "1") {';
echo '        item.style.display = "";';
echo '      } else {';
echo '        item.style.display = "none";';
echo '      }';
echo '    } else {';
echo '      item.style.display = "";';
echo '    }';
echo '  }';
echo '  if (btnAuthorOnly) {';
echo '    if (authorOnlyMode) btnAuthorOnly.classList.add("active");';
echo '    else btnAuthorOnly.classList.remove("active");';
echo '  }';
echo '  if (authorOnlyToggle) authorOnlyToggle.checked = authorOnlyMode;';
echo '}';
echo 'if (authorOnlyToggle) {';
echo '  authorOnlyToggle.addEventListener("change", toggleAuthorOnly);';
echo '}';
echo 'if (btnAuthorOnly) {';
echo '  btnAuthorOnly.addEventListener("click", toggleAuthorOnly);';
echo '}';
echo 'var commentAnchors = document.querySelectorAll(".comment-anchor");';
echo 'for (var j = 0; j < commentAnchors.length; j++) {';
echo '  (function(anchor) {';
echo '    anchor.addEventListener("click", function(e) {';
echo '      e.preventDefault();';
echo '      var href = anchor.getAttribute("href");';
echo '      var fullUrl = window.location.origin + window.location.pathname + window.location.search + href;';
echo '      var input = document.createElement("input");';
echo '      input.value = fullUrl;';
echo '      document.body.appendChild(input);';
echo '      input.select();';
echo '      try { document.execCommand("copy"); showToast("评论链接已复制"); }';
echo '      catch (err) { showToast("复制失败"); }';
echo '      document.body.removeChild(input);';
echo '      if (history.pushState) history.pushState(null, "", href);';
echo '      highlightComment(href.substring(1));';
echo '      var target = document.querySelector(href);';
echo '      if (target) target.scrollIntoView({ behavior: "smooth", block: "center" });';
echo '    });';
echo '  })(commentAnchors[j]);';
echo '}';
echo '';
echo 'var currentQuote = null;';
echo 'var quoteIndicator = document.getElementById("quote-indicator");';
echo 'var quoteUsername = document.getElementById("quote-username");';
echo 'var quoteContent = document.getElementById("quote-content");';
echo 'var replyToInput = document.getElementById("reply-to-input");';
echo 'var commentTextarea = document.getElementById("comment-textarea");';
echo 'var cancelQuoteBtn = document.getElementById("cancel-quote");';
echo '';
echo 'function getCommentById(id) {';
echo '  return document.querySelector(\'.comment-item[data-comment-id="\' + id + \'"]\');';
echo '}';
echo '';
echo 'function highlightComment(commentId) {';
echo '  document.querySelectorAll(".comment-item").forEach(function(item) {';
echo '    item.classList.remove("comment-highlighted");';
echo '  });';
echo '  var targetId = typeof commentId === "number" ? commentId : commentId.replace("comment-", "");';
echo '  var target = getCommentById(targetId);';
echo '  if (target) {';
echo '    target.classList.add("comment-highlighted");';
echo '    setTimeout(function() { target.classList.remove("comment-highlighted"); }, 3000);';
echo '  }';
echo '}';
echo '';
echo 'function setQuote(commentId) {';
echo '  var comment = getCommentById(commentId);';
echo '  if (!comment) return;';
echo '  var username = comment.getAttribute("data-username");';
echo '  var content = comment.getAttribute("data-content");';
echo '  currentQuote = { id: commentId, username: username, content: content };';
echo '  if (quoteIndicator) quoteIndicator.style.display = "block";';
echo '  if (quoteUsername) quoteUsername.textContent = "@" + username;';
echo '  if (quoteContent) {';
echo '    var displayContent = content.length > 100 ? content.substring(0, 100) + "..." : content;';
echo '    quoteContent.textContent = displayContent;';
echo '  }';
echo '  if (replyToInput) replyToInput.value = commentId;';
echo '  if (commentTextarea) {';
echo '    commentTextarea.focus();';
echo '    commentTextarea.setSelectionRange(0, 0);';
echo '  }';
echo '  var replySection = document.getElementById("reply-section");';
echo '  if (replySection) {';
echo '    replySection.scrollIntoView({ behavior: "smooth", block: "center" });';
echo '  }';
echo '}';
echo '';
echo 'function clearQuote() {';
echo '  currentQuote = null;';
echo '  if (quoteIndicator) quoteIndicator.style.display = "none";';
echo '  if (replyToInput) replyToInput.value = "";';
echo '}';
echo '';
echo 'if (cancelQuoteBtn) {';
echo '  cancelQuoteBtn.addEventListener("click", clearQuote);';
echo '}';
echo '';
echo 'var commentToolBtns = document.querySelectorAll(".comment-tool-btn");';
echo 'for (var k = 0; k < commentToolBtns.length; k++) {';
echo '  (function(btn) {';
echo '    btn.addEventListener("click", function(e) {';
echo '      e.preventDefault();';
echo '      e.stopPropagation();';
echo '      var action = btn.getAttribute("data-action");';
echo '      var commentId = btn.getAttribute("data-comment-id");';
echo '      var comment = getCommentById(commentId);';
echo '      if (!comment) return;';
echo '      if (action === "quote") {';
echo '        setQuote(commentId);';
echo '        showToast("已引用 #" + commentId + " 评论");';
echo '      } else if (action === "copy") {';
echo '        var content = comment.getAttribute("data-content");';
echo '        var textarea = document.createElement("textarea");';
echo '        textarea.value = content;';
echo '        document.body.appendChild(textarea);';
echo '        textarea.select();';
echo '        try {';
echo '          document.execCommand("copy");';
echo '          showToast("评论文本已复制");';
echo '          btn.classList.add("copied");';
echo '          setTimeout(function() { btn.classList.remove("copied"); }, 1500);';
echo '        } catch (err) {';
echo '          showToast("复制失败");';
echo '        }';
echo '        document.body.removeChild(textarea);';
echo '      } else if (action === "share") {';
echo '        var fullUrl = window.location.origin + window.location.pathname + window.location.search + "#comment-" + commentId;';
echo '        var input = document.createElement("input");';
echo '        input.value = fullUrl;';
echo '        document.body.appendChild(input);';
echo '        input.select();';
echo '        try {';
echo '          document.execCommand("copy");';
echo '          showToast("评论链接已复制");';
echo '        } catch (err) {';
echo '          showToast("复制失败");';
echo '        }';
echo '        document.body.removeChild(input);';
echo '        if (history.pushState) history.pushState(null, "", "#comment-" + commentId);';
echo '        highlightComment(commentId);';
echo '      }';
echo '    });';
echo '  })(commentToolBtns[k]);';
echo '}';
echo '';
echo 'var floatingReplyBtn = document.getElementById("floating-reply-btn");';
echo 'var btnBackToReplyBottom = document.getElementById("btn-back-to-reply-bottom");';
echo '';
echo 'function scrollToReply() {';
echo '  var replySection = document.getElementById("reply-section");';
echo '  if (replySection) {';
echo '    replySection.scrollIntoView({ behavior: "smooth", block: "center" });';
echo '    if (commentTextarea) commentTextarea.focus();';
echo '  }';
echo '}';
echo '';
echo 'if (floatingReplyBtn) {';
echo '  floatingReplyBtn.addEventListener("click", scrollToReply);';
echo '}';
echo 'if (btnBackToReplyBottom) {';
echo '  btnBackToReplyBottom.addEventListener("click", scrollToReply);';
echo '}';
echo '';
echo 'var originalUpdateProgress = updateReadingProgress;';
echo 'function updateReadingProgress() {';
echo '  originalUpdateProgress();';
echo '  var replySection = document.getElementById("reply-section");';
echo '  var commentsSection = document.getElementById("comments-section");';
echo '  if (floatingReplyBtn && replySection && commentsSection) {';
echo '    var replyRect = replySection.getBoundingClientRect();';
echo '    var commentsRect = commentsSection.getBoundingClientRect();';
echo '    var scrollTop = window.scrollY || document.documentElement.scrollTop;';
echo '    if (scrollTop > commentsRect.top + 300 && replyRect.top > window.innerHeight) {';
echo '      floatingReplyBtn.style.display = "flex";';
echo '      setTimeout(function() { floatingReplyBtn.style.opacity = "1"; }, 10);';
echo '    } else {';
echo '      floatingReplyBtn.style.opacity = "0";';
echo '      setTimeout(function() { floatingReplyBtn.style.display = "none"; }, 300);';
echo '    }';
echo '  }';
echo '}';
echo 'window.addEventListener("scroll", updateReadingProgress);';
echo '';
echo 'var commentsList = document.getElementById("comments-list");';
echo 'if (commentsList) {';
echo '  commentsList.addEventListener("click", function(e) {';
echo '    var commentItem = e.target.closest(".comment-item");';
echo '    if (commentItem) {';
echo '      var commentId = commentItem.getAttribute("data-comment-id");';
echo '      if (history.pushState && window.location.hash !== "#comment-" + commentId) {';
echo '        history.pushState(null, "", "#comment-" + commentId);';
echo '      }';
echo '    }';
echo '  });';
echo '}';
echo 'function showToast(msg) {';
echo '  var toast = document.createElement("div");';
echo '  toast.className = "reading-toast";';
echo '  toast.textContent = msg;';
echo '  document.body.appendChild(toast);';
echo '  setTimeout(function() { toast.classList.add("show"); }, 10);';
echo '  setTimeout(function() {';
echo '    toast.classList.remove("show");';
echo '    setTimeout(function() { toast.remove(); }, 300);';
echo '  }, 2000);';
echo '}';
echo 'window.showToast = showToast;';
echo 'if (window.location.hash) {';
echo '  setTimeout(function() {';
echo '    var hash = window.location.hash;';
echo '    highlightComment(hash.substring(1));';
echo '    var target = document.querySelector(hash);';
echo '    if (target) target.scrollIntoView({ behavior: "smooth", block: "center" });';
echo '  }, 100);';
echo '}';
echo '';
echo 'window.addEventListener("hashchange", function() {';
echo '  if (window.location.hash) {';
echo '    highlightComment(window.location.hash.substring(1));';
echo '  }';
echo '});';
echo '})();';
echo '</script>';

echo '<style>';
echo '.hot-recommend-item{padding:.75rem 1rem;border-radius:.5rem;background:#f8f9fa;transition:all .2s;height:100%;}';
echo '.hot-recommend-item:hover{background:#e9ecef;transform:translateY(-2px);}';
echo '.hot-recommend-title{color:#212529;font-size:.95rem;font-weight:500;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.4;}';
echo '.hot-recommend-meta{margin-top:.5rem;display:flex;align-items:center;}';
echo '.hot-comment-badge{font-size:.75rem;color:#495057;background:#dee2e6;padding:.15rem .5rem;border-radius:1rem;}';
echo '';
echo '.btn-favorite{color:#ffc107;border-color:#ffc107;background:transparent;transition:all .2s;}';
echo '.btn-favorite:hover{background:#fff3cd;border-color:#ffc107;color:#ffc107;}';
echo '.btn-favorite.active{background:#ffc107;border-color:#ffc107;color:#fff;}';
echo '.btn-favorite.active:hover{background:#ffb300;border-color:#ffb300;color:#fff;}';
echo '.favorite-icon{font-size:1rem;line-height:1;}';
echo '';
echo '.comment-item{transition:all .2s ease;position:relative;}';
echo '.comment-item:hover{box-shadow:0 4px 12px rgba(0,0,0,.08);}';
echo '.comment-is-author{background:linear-gradient(135deg, rgba(44,62,80,.03) 0%, rgba(26,188,156,.03) 100%);border-color:rgba(44,62,80,.15)!important;}';
echo '.comment-highlighted{animation:commentPulse 1s ease-in-out infinite alternate;}';
echo '@keyframes commentPulse{';
echo '  0%{box-shadow:0 0 0 0 rgba(44,62,80,.3);border-color:rgba(44,62,80,.4);}';
echo '  100%{box-shadow:0 0 0 8px rgba(44,62,80,0);border-color:var(--bs-primary);}';
echo '}';
echo '.comment-anchor{transition:color .2s;}';
echo '.comment-anchor:hover{color:var(--bs-primary)!important;}';
echo '';
echo '.comment-toolbar{opacity:0;transform:translateY(-4px);transition:all .2s ease;padding-top:8px;border-top:1px dashed #e9ecef;}';
echo '.comment-item:hover .comment-toolbar{opacity:1;transform:translateY(0);}';
echo '.comment-tool-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border:1px solid #e9ecef;background:#fff;border-radius:999px;font-size:.75rem;color:#6c757d;cursor:pointer;transition:all .2s ease;}';
echo '.comment-tool-btn:hover{background:var(--bs-primary);color:#fff;border-color:var(--bs-primary);transform:translateY(-1px);}';
echo '.comment-tool-btn.copied{background:#198754;color:#fff;border-color:#198754;}';
echo '.comment-tool-btn svg{flex-shrink:0;}';
echo '';
echo '.quote-indicator{background:linear-gradient(135deg, rgba(44,62,80,.05) 0%, rgba(26,188,156,.05) 100%);border:1px solid rgba(44,62,80,.1);border-radius:.5rem;padding:.75rem 1rem;margin-bottom:.75rem;animation:quoteSlideIn .3s ease;}';
echo '@keyframes quoteSlideIn{';
echo '  from{opacity:0;transform:translateY(-10px);}';
echo '  to{opacity:1;transform:translateY(0);}';
echo '}';
echo '.quote-content{background:rgba(255,255,255,.6);padding:.5rem .75rem;border-radius:.375rem;border-left:3px solid var(--bs-primary);font-style:italic;line-height:1.5;max-height:80px;overflow:hidden;position:relative;}';
echo '.quote-content::after{content:"";position:absolute;bottom:0;left:0;right:0;height:20px;background:linear-gradient(transparent, rgba(248,249,250,.9));}';
echo '.btn-close-sm{width:.5rem;height:.5rem;padding:0;background-size:.5rem .5rem;}';
echo '';
echo '.floating-reply-btn{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(20px);z-index:1040;display:flex;align-items:center;gap:8px;padding:12px 24px;background:var(--bs-primary);color:#fff;border:none;border-radius:999px;box-shadow:0 8px 24px rgba(44,62,80,.3);font-size:.9rem;font-weight:500;cursor:pointer;opacity:0;transition:all .3s ease;}';
echo '.floating-reply-btn:hover{background:#1a252f;transform:translateX(-50%) translateY(-2px);box-shadow:0 12px 32px rgba(44,62,80,.4);}';
echo '.floating-reply-btn svg{flex-shrink:0;}';
echo '';
echo '.reading-toolbar{position:fixed;right:24px;top:50%;transform:translateY(-50%);z-index:1050;display:flex;flex-direction:column;align-items:center;gap:8px;padding:12px 8px;background:#fff;border-radius:999px;box-shadow:0 8px 24px rgba(0,0,0,.12);border:1px solid rgba(0,0,0,.06);opacity:0;pointer-events:none;transition:opacity .3s ease;}';
echo '.reading-toolbar-progress{position:absolute;left:0;top:0;width:3px;background:linear-gradient(180deg,var(--bs-primary),var(--bs-success));border-radius:3px 0 0 3px;transition:height .1s linear;}';
echo '.reading-toolbar-btn{width:36px;height:36px;border:none;background:transparent;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#6c757d;cursor:pointer;transition:all .2s ease;}';
echo '.reading-toolbar-btn:hover{background:rgba(44,62,80,.08);color:var(--bs-primary);transform:scale(1.1);}';
echo '.reading-toolbar-btn.active{background:var(--bs-primary);color:#fff;}';
echo '.reading-toolbar-btn.active:hover{background:var(--bs-primary);color:#fff;transform:scale(1.1);}';
echo '.reading-toolbar-btn.favorite-btn.active{background:#ffc107;color:#fff;}';
echo '.reading-toolbar-btn.favorite-btn.active:hover{background:#ffb300;color:#fff;}';
echo '.reading-toolbar-percent{font-size:.65rem;color:#adb5bd;font-weight:500;line-height:1;}';
echo '';
echo '.reading-toast{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) scale(.9);background:rgba(0,0,0,.8);color:#fff;padding:12px 24px;border-radius:8px;font-size:.9rem;z-index:9999;opacity:0;transition:all .3s ease;pointer-events:none;}';
echo '.reading-toast.show{opacity:1;transform:translate(-50%,-50%) scale(1);}';
echo '';
echo '@media (max-width:768px){';
echo '  .reading-toolbar{right:12px;padding:10px 6px;gap:6px;}';
echo '  .reading-toolbar-btn{width:32px;height:32px;}';
echo '  .reading-toolbar-percent{display:none;}';
echo '  .comment-toolbar{opacity:1;transform:none;}';
echo '  .comment-tool-btn span{display:none;}';
echo '  .comment-tool-btn{padding:6px 8px;}';
echo '  .floating-reply-btn{bottom:16px;padding:10px 20px;font-size:.85rem;}';
echo '}';
echo '</style>';

render_footer();

