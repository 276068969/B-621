<?php
declare(strict_types=1);

/*
 * 用户注册页：
 * - 用户名唯一（3-16）
 * - 密码 6-20（加密存储）
 * - 手机号可选（格式校验）
 * - 验证码：前端生成 + 后端验证（Session）
 * - 协议勾选必填
 * - 注册成功自动登录
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (user() !== null) {
    redirect('/index.php');
}

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '注册 - Lite Forum', 'active' => 'register']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$values = [
    'username' => '',
    'mobile' => '',
    'captcha' => '',
    'agree' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['username'] = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');
    $values['mobile'] = trim((string)($_POST['mobile'] ?? ''));
    $values['captcha'] = trim((string)($_POST['captcha'] ?? ''));
    $values['agree'] = (string)($_POST['agree'] ?? '');

    if (!is_valid_username($values['username'])) {
        $errors['username'] = '用户名需为 3-16 位字母/数字/下划线。';
    }
    if (!is_valid_password($password)) {
        $errors['password'] = '密码需为 6-20 位。';
    }
    if ($password !== $password2) {
        $errors['password2'] = '两次输入的密码不一致。';
    }
    if (!is_valid_mobile($values['mobile'])) {
        $errors['mobile'] = '手机号格式不正确（示例：13800138000）。';
    }
    if (!preg_match('/^\d{4}$/', $values['captcha'])) {
        $errors['captcha'] = '验证码为 4 位数字。';
    }
    if ($values['agree'] !== '1') {
        $errors['agree'] = '请先阅读并同意用户协议。';
    }

    $sessionCaptcha = $_SESSION['captcha'] ?? null;
    if (!$errors && (!$sessionCaptcha || !isset($sessionCaptcha['code'], $sessionCaptcha['ts']))) {
        $errors['captcha'] = '验证码已失效，请刷新后重试。';
    }
    if (!$errors && (time() - (int)$sessionCaptcha['ts'] > 600)) {
        $errors['captcha'] = '验证码已过期，请刷新后重试。';
    }
    if (!$errors && $values['captcha'] !== (string)$sessionCaptcha['code']) {
        $errors['captcha'] = '验证码错误。';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$values['username']]);
        if ($stmt->fetchColumn()) {
            $errors['username'] = '用户名已存在，请更换。';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password, mobile, status) VALUES (?, ?, ?, 1)');
        try {
            $stmt->execute([$values['username'], $hash, $values['mobile'] !== '' ? $values['mobile'] : null]);
        } catch (Throwable $e) {
            $errors['username'] = '用户名已存在，请更换。';
        }
    }

    if (!$errors) {
        unset($_SESSION['captcha']);
        $userId = (int)$pdo->lastInsertId();
        login_user($userId, $values['username']);
        flash_set('success', '注册成功，已自动登录。');
        redirect('/index.php');
    }
}

render_header($config, ['title' => '注册 - Lite Forum', 'active' => 'register']);

echo '<div class="row justify-content-center">';
echo '<div class="col-12 col-lg-7">';
echo '<div class="card card-lite">';
echo '<div class="card-body p-4">';
echo '<h1 class="h4 mb-1">创建账号</h1>';
echo '<div class="text-muted small">轻量论坛 · 账号用于发帖与评论关联</div>';

echo '<form method="post" class="mt-4 needs-validation" id="registerForm" novalidate>'; 

echo '<div class="mb-3" id="usernameFieldWrap">';
echo '<div class="form-floating position-relative">';
echo '<input class="form-control" name="username" id="username" placeholder="用户名" minlength="3" maxlength="16" required value="' . e($values['username']) . '">';
echo '<label for="username">用户名</label>';
echo '<div class="position-absolute end-0 top-50 translate-middle-y me-3" id="usernameStatusIcon" style="display:none;">';
echo '<span class="spinner-border spinner-border-sm text-primary" role="status" id="usernameSpinner"></span>';
echo '</div>';
echo '</div>';
echo '<div class="form-text mt-1" id="usernameHint">';
echo '<span class="me-1">💡</span>3-16 位字母/数字/下划线，需唯一。';
echo '</div>';
echo '<div class="invalid-feedback">请输入 3-16 位用户名。</div>';
if (isset($errors['username'])) {
    echo '<div class="text-danger small mt-1 d-flex align-items-center">';
    echo '<span class="me-1">❌</span>' . e($errors['username']);
    echo '</div>';
}
echo '</div>';

echo '<div class="row">';
echo '<div class="col-12 col-md-6 mb-3">';
echo '<div class="form-floating position-relative">';
echo '<input type="password" class="form-control" name="password" id="password" placeholder="密码" minlength="6" maxlength="20" required>'; 
echo '<label for="password">密码</label>';
echo '</div>';
echo '<div class="invalid-feedback">请输入 6-20 位密码。</div>';
echo '<div class="mt-2" id="pwdStrengthWrap" style="display:none;">';
echo '<div class="d-flex align-items-center gap-2 mb-1">';
echo '<small class="text-muted">密码强度：</small>';
echo '<div class="flex-grow-1 progress" style="height:6px;">';
echo '<div class="progress-bar" id="pwdStrengthBar" role="progressbar" style="width:0%; transition:width .3s ease;"></div>';
echo '</div>';
echo '<small id="pwdStrengthText" class="fw-medium" style="min-width:50px;">--</small>';
echo '</div>';
echo '</div>';
if (isset($errors['password'])) {
    echo '<div class="text-danger small mt-1 d-flex align-items-center">';
    echo '<span class="me-1">❌</span>' . e($errors['password']);
    echo '</div>';
}
echo '</div>';

echo '<div class="col-12 col-md-6 mb-3">';
echo '<div class="form-floating position-relative" id="password2Wrap">';
echo '<input type="password" class="form-control" name="password2" id="password2" placeholder="确认密码" minlength="6" maxlength="20" required>';
echo '<label for="password2">确认密码</label>';
echo '<div class="position-absolute end-0 top-50 translate-middle-y me-3" id="pwdMatchIcon" style="display:none;">';
echo '</div>';
echo '</div>';
echo '<div class="form-text mt-1" id="pwdHint">';
echo '<span class="me-1">💡</span>请再次输入密码进行确认。';
echo '</div>';
echo '<div class="invalid-feedback">请再次输入密码。</div>';
if (isset($errors['password2'])) {
    echo '<div class="text-danger small mt-1 d-flex align-items-center">';
    echo '<span class="me-1">❌</span>' . e($errors['password2']);
    echo '</div>';
}
echo '</div></div>';

echo '<div class="form-floating mb-3">';
echo '<input class="form-control" name="mobile" id="mobile" placeholder="手机号" inputmode="numeric" value="' . e($values['mobile']) . '">';
echo '<label for="mobile">手机号 (可选)</label>';
if (isset($errors['mobile'])) {
    echo '<div class="text-danger small mt-1">❌ ' . e($errors['mobile']) . '</div>';
}
echo '</div>';

echo '<div class="row align-items-center mb-3" id="captchaRow">';
echo '<div class="col-7">';
echo '<div class="form-floating">';
echo '<input class="form-control" name="captcha" id="captcha" placeholder="验证码" inputmode="numeric" maxlength="4" required value="' . e($values['captcha']) . '">';
echo '<label for="captcha">验证码</label>';
echo '<div class="invalid-feedback">请输入 4 位验证码。</div>';
echo '</div></div>';
echo '<div class="col-5 text-end">';
echo '<button type="button" class="btn btn-outline-secondary w-100 py-3 fw-bold fs-5 position-relative overflow-hidden" id="captchaBtn" style="letter-spacing:.25rem; transition:all .2s ease;">';
echo '<span id="captchaCode">0000</span>';
echo '<span class="captcha-refresh-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-secondary bg-opacity-10" id="captchaRefreshOverlay" style="display:none;">';
echo '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>';
echo '</span>';
echo '</button>';
echo '<div class="form-text text-end mt-1">';
echo '<small class="text-muted"><span class="me-1">🔄</span>点击图片可刷新验证码</small>';
echo '</div>';
echo '</div>';
if (isset($errors['captcha'])) {
    echo '<div class="col-12 text-danger small mt-1 d-flex align-items-center">';
    echo '<span class="me-1">❌</span>' . e($errors['captcha']);
    echo '</div>';
}
echo '</div>';

echo '<div class="form-check mb-3" id="agreeWrap">';
echo '<input class="form-check-input" type="checkbox" value="1" name="agree" id="agree" required ' . ($values['agree'] === '1' ? 'checked' : '') . '>'; 
echo '<label class="form-check-label" for="agree">我已阅读并同意 <a href="#" data-bs-toggle="modal" data-bs-target="#agreementModal">用户协议</a></label>';
echo '<div class="invalid-feedback">请勾选同意用户协议。</div>';
if (isset($errors['agree'])) {
    echo '<div class="text-danger small mt-1 d-flex align-items-center">';
    echo '<span class="me-1">❌</span>' . e($errors['agree']);
    echo '</div>';
}
echo '</div>';

echo '<div class="alert alert-danger d-none" id="formErrorSummary" role="alert">';
echo '<div class="d-flex align-items-start gap-2">';
echo '<span class="fs-5">⚠️</span>';
echo '<div>';
echo '<div class="fw-bold mb-1">请完善以下信息后再提交：</div>';
echo '<ul class="mb-0 small" id="formErrorList"></ul>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<button class="btn btn-primary w-100 py-3 fw-bold fs-5 shadow-sm mb-3 position-relative" type="submit" id="submitBtn">';
echo '<span id="submitBtnText">立即注册</span>';
echo '<span class="spinner-border spinner-border-sm me-2 d-none" id="submitBtnSpinner" role="status"></span>';
echo '</button>';
echo '<div class="text-center">';
echo '<a class="text-decoration-none text-muted" href="/login.php">已有账号？<span class="text-primary fw-semibold">去登录</span></a>';
echo '</div>';

echo '</form>';

echo '<div class="modal fade" id="agreementModal" tabindex="-1" aria-labelledby="agreementModalLabel" aria-hidden="true">';
echo '<div class="modal-dialog modal-dialog-centered modal-lg">';
echo '<div class="modal-content border-0 shadow-lg">';
echo '<div class="modal-header border-0">';
echo '<h5 class="modal-title fw-bold" id="agreementModalLabel">用户协议</h5>';
echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
echo '</div>';
echo '<div class="modal-body text-muted small" style="max-height:60vh;overflow-y:auto;">';
echo '<h6 class="fw-bold text-dark">一、账号注册</h6>';
echo '<p>1. 用户在注册账号时，需提供真实、准确、完整的个人信息。</p>';
echo '<p>2. 用户应妥善保管账号和密码，对账号下的所有行为和言论承担责任。</p>';
echo '<p>3. 用户名需符合 3-16 位字母/数字/下划线的格式要求，且具有唯一性。</p>';
echo '';
echo '<h6 class="fw-bold text-dark mt-3">二、用户行为规范</h6>';
echo '<p>1. 用户在使用本论坛服务时，应遵守中华人民共和国相关法律法规。</p>';
echo '<p>2. 禁止发布违法、违规、色情、暴力、恐怖等不良信息。</p>';
echo '<p>3. 禁止发布广告、垃圾信息、恶意刷屏等影响社区秩序的内容。</p>';
echo '<p>4. 尊重他人合法权益，禁止侮辱、诽谤、骚扰他人。</p>';
echo '';
echo '<h6 class="fw-bold text-dark mt-3">三、内容管理</h6>';
echo '<p>1. 论坛管理员有权对违反本协议的用户进行处理，包括但不限于删除内容、禁言、封禁账号等。</p>';
echo '<p>2. 用户发布的内容仅代表个人观点，不代表论坛立场。</p>';
echo '';
echo '<h6 class="fw-bold text-dark mt-3">四、隐私保护</h6>';
echo '<p>1. 我们将采取合理的安全措施保护用户的个人信息安全。</p>';
echo '<p>2. 除法律法规要求外，未经用户许可，我们不会向第三方披露用户个人信息。</p>';
echo '';
echo '<h6 class="fw-bold text-dark mt-3">五、协议修改</h6>';
echo '<p>1. 论坛有权根据需要修改本协议，修改后的协议将在网站上公布。</p>';
echo '<p>2. 用户继续使用本服务即视为同意修改后的协议。</p>';
echo '';
echo '<p class="mt-3 text-end">Lite Forum 团队</p>';
echo '</div>';
echo '<div class="modal-footer border-0">';
echo '<button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">关闭</button>';
echo '<button type="button" class="btn btn-primary px-4 rounded-pill" data-bs-dismiss="modal" id="agreeModalBtn">我已阅读并同意</button>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<style>';
echo '.form-validation-icon{width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;font-size:12px;font-weight:bold;color:#fff;}';
echo '.form-validation-icon.valid{background-color:#1abc9c;}';
echo '.form-validation-icon.invalid{background-color:#dc3545;}';
echo '.input-valid{border-color:#1abc9c !important;box-shadow:0 0 0 .2rem rgba(26,188,156,.15) !important;}';
echo '.input-invalid{border-color:#dc3545 !important;box-shadow:0 0 0 .2rem rgba(220,53,69,.15) !important;}';
echo '.shake-animation{animation:shake .4s ease-in-out;}';
echo '@keyframes shake{0%,100%{transform:translateX(0);}20%,60%{transform:translateX(-5px);}40%,80%{transform:translateX(5px);}}';
echo '.fade-in{animation:fadeIn .3s ease-in-out;}';
echo '@keyframes fadeIn{from{opacity:0;transform:translateY(-5px);}to{opacity:1;transform:translateY(0);}}';
echo '.pwd-strength-weak{background-color:#dc3545 !important;}';
echo '.pwd-strength-medium{background-color:#ffc107 !important;}';
echo '.pwd-strength-strong{background-color:#17a2b8 !important;}';
echo '.pwd-strength-excellent{background-color:#1abc9c !important;}';
echo '.captcha-btn-refreshing{cursor:not-allowed;opacity:.7;}';
echo '</style>';

echo '<script>';
echo '(() => {';
echo '  "use strict";';

echo '  const form = document.getElementById("registerForm");';
echo '  const usernameEl = document.getElementById("username");';
echo '  const usernameHint = document.getElementById("usernameHint");';
echo '  const usernameStatusIcon = document.getElementById("usernameStatusIcon");';
echo '  const usernameSpinner = document.getElementById("usernameSpinner");';
echo '  const passwordEl = document.getElementById("password");';
echo '  const password2El = document.getElementById("password2");';
echo '  const pwdHint = document.getElementById("pwdHint");';
echo '  const pwdMatchIcon = document.getElementById("pwdMatchIcon");';
echo '  const pwdStrengthWrap = document.getElementById("pwdStrengthWrap");';
echo '  const pwdStrengthBar = document.getElementById("pwdStrengthBar");';
echo '  const pwdStrengthText = document.getElementById("pwdStrengthText");';
echo '  const captchaBtn = document.getElementById("captchaBtn");';
echo '  const captchaCode = document.getElementById("captchaCode");';
echo '  const captchaRefreshOverlay = document.getElementById("captchaRefreshOverlay");';
echo '  const captchaEl = document.getElementById("captcha");';
echo '  const agreeEl = document.getElementById("agree");';
echo '  const submitBtn = document.getElementById("submitBtn");';
echo '  const submitBtnText = document.getElementById("submitBtnText");';
echo '  const submitBtnSpinner = document.getElementById("submitBtnSpinner");';
echo '  const formErrorSummary = document.getElementById("formErrorSummary");';
echo '  const formErrorList = document.getElementById("formErrorList");';

echo '  let usernameTimer = null;';
echo '  let isRefreshingCaptcha = false;';

echo '  function showUsernameStatus(type, text) {';
echo '    usernameStatusIcon.style.display = "flex";';
echo '    if (type === "loading") {';
echo '      usernameSpinner.style.display = "inline-block";';
echo '      usernameStatusIcon.innerHTML = \'<span class="spinner-border spinner-border-sm text-primary" role="status"></span>\';';
echo '    } else if (type === "valid") {';
echo '      usernameStatusIcon.innerHTML = \'<span class="form-validation-icon valid">✓</span>\';';
echo '      usernameEl.classList.add("input-valid");';
echo '      usernameEl.classList.remove("input-invalid");';
echo '    } else if (type === "invalid") {';
echo '      usernameStatusIcon.innerHTML = \'<span class="form-validation-icon invalid">✕</span>\';';
echo '      usernameEl.classList.add("input-invalid");';
echo '      usernameEl.classList.remove("input-valid");';
echo '    } else {';
echo '      usernameStatusIcon.style.display = "none";';
echo '      usernameEl.classList.remove("input-valid", "input-invalid");';
echo '    }';
echo '    if (text) {';
echo '      const iconClass = type === "valid" ? "text-success" : (type === "invalid" ? "text-danger" : "");';
echo '      const icon = type === "valid" ? "✅" : (type === "invalid" ? "❌" : "💡");';
echo '      usernameHint.innerHTML = \'<span class="me-1">\' + icon + \'</span><span class="\' + iconClass + \'">\' + text + \'</span>\';';
echo '    }';
echo '  }';

echo '  usernameEl.addEventListener("input", () => {';
echo '    clearTimeout(usernameTimer);';
echo '    const v = usernameEl.value.trim();';
echo '    if (!v) {';
echo '      showUsernameStatus("idle", "3-16 位字母/数字/下划线，需唯一。");';
echo '      return;';
echo '    }';
echo '    if (v.length < 3) {';
echo '      showUsernameStatus("idle", "3-16 位字母/数字/下划线，需唯一。");';
echo '      return;';
echo '    }';
echo '    if (!/^[a-zA-Z0-9_]{3,16}$/.test(v)) {';
echo '      showUsernameStatus("invalid", "用户名格式不正确，只能包含字母、数字和下划线。");';
echo '      return;';
echo '    }';
echo '    showUsernameStatus("loading", "正在检查用户名是否可用...");';
echo '    usernameTimer = setTimeout(async () => {';
echo '      try {';
echo '        const res = await fetch("/api/check_username.php?username=" + encodeURIComponent(v));';
echo '        const data = await res.json();';
echo '        if (!data.ok) { showUsernameStatus("idle", "3-16 位字母/数字/下划线，需唯一。"); return; }';
echo '        if (data.exists) {';
echo '          showUsernameStatus("invalid", "用户名已存在，请更换。");';
echo '        } else {';
echo '          showUsernameStatus("valid", "用户名可用。");';
echo '        }';
echo '      } catch(e) {';
echo '        showUsernameStatus("idle", "3-16 位字母/数字/下划线，需唯一。");';
echo '      }';
echo '    }, 400);';
echo '  });';

echo '  function checkPasswordStrength(pwd) {';
echo '    let score = 0;';
echo '    if (pwd.length >= 6) score++;';
echo '    if (pwd.length >= 10) score++;';
echo '    if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) score++;';
echo '    if (/\d/.test(pwd)) score++;';
echo '    if (/[^a-zA-Z0-9]/.test(pwd)) score++;';
echo '    const levels = [';
echo '      { text: "太弱", width: "20%", class: "pwd-strength-weak", textClass: "text-danger" },';
echo '      { text: "较弱", width: "40%", class: "pwd-strength-weak", textClass: "text-danger" },';
echo '      { text: "中等", width: "60%", class: "pwd-strength-medium", textClass: "text-warning" },';
echo '      { text: "较强", width: "80%", class: "pwd-strength-strong", textClass: "text-info" },';
echo '      { text: "很强", width: "100%", class: "pwd-strength-excellent", textClass: "text-success" }';
echo '    ];';
echo '    const idx = Math.min(score, levels.length - 1);';
echo '    return levels[idx];';
echo '  }';

echo '  passwordEl.addEventListener("input", () => {';
echo '    const pwd = passwordEl.value;';
echo '    if (pwd) {';
echo '      pwdStrengthWrap.style.display = "block";';
echo '      const level = checkPasswordStrength(pwd);';
echo '      pwdStrengthBar.style.width = level.width;';
echo '      pwdStrengthBar.className = "progress-bar " + level.class;';
echo '      pwdStrengthText.textContent = level.text;';
echo '      pwdStrengthText.className = "fw-medium " + level.textClass;';
echo '    } else {';
echo '      pwdStrengthWrap.style.display = "none";';
echo '    }';
echo '    checkPwdMatch();';
echo '  });';

echo '  function checkPwdMatch() {';
echo '    const a = passwordEl.value;';
echo '    const b = password2El.value;';
echo '    if (!b) {';
echo '      pwdHint.innerHTML = \'<span class="me-1">💡</span>请再次输入密码进行确认。\';';
echo '      pwdMatchIcon.style.display = "none";';
echo '      password2El.classList.remove("input-valid", "input-invalid");';
echo '      return;';
echo '    }';
echo '    if (!a) {';
echo '      pwdHint.innerHTML = \'<span class="me-1">💡</span>请先输入密码。\';';
echo '      pwdMatchIcon.style.display = "none";';
echo '      password2El.classList.remove("input-valid", "input-invalid");';
echo '      return;';
echo '    }';
echo '    pwdMatchIcon.style.display = "flex";';
echo '    if (a === b) {';
echo '      pwdHint.innerHTML = \'<span class="me-1">✅</span><span class="text-success">密码一致。</span>\';';
echo '      pwdMatchIcon.innerHTML = \'<span class="form-validation-icon valid">✓</span>\';';
echo '      password2El.classList.add("input-valid");';
echo '      password2El.classList.remove("input-invalid");';
echo '    } else {';
echo '      pwdHint.innerHTML = \'<span class="me-1">❌</span><span class="text-danger">两次输入的密码不一致。</span>\';';
echo '      pwdMatchIcon.innerHTML = \'<span class="form-validation-icon invalid">✕</span>\';';
echo '      password2El.classList.add("input-invalid");';
echo '      password2El.classList.remove("input-valid");';
echo '    }';
echo '  }';

echo '  password2El.addEventListener("input", checkPwdMatch);';

echo '  function genCaptcha() {';
echo '    return String(Math.floor(1000 + Math.random() * 9000));';
echo '  }';

echo '  async function storeCaptcha(code) {';
echo '    const body = new URLSearchParams();';
echo '    body.set("code", code);';
echo '    await fetch("/api/captcha_store.php", {';
echo '      method: "POST",';
echo '      headers: { "Content-Type": "application/x-www-form-urlencoded" },';
echo '      body';
echo '    });';
echo '  }';

echo '  async function refreshCaptcha() {';
echo '    if (isRefreshingCaptcha) return;';
echo '    isRefreshingCaptcha = true;';
echo '    captchaBtn.classList.add("captcha-btn-refreshing");';
echo '    captchaRefreshOverlay.style.display = "flex";';
echo '    captchaCode.style.opacity = "0.3";';
echo '    try {';
echo '      const code = genCaptcha();';
echo '      captchaCode.textContent = code;';
echo '      await storeCaptcha(code);';
echo '      await new Promise(resolve => setTimeout(resolve, 500));';
echo '    } catch(e) {';
echo '      console.error("刷新验证码失败", e);';
echo '    } finally {';
echo '      captchaBtn.classList.remove("captcha-btn-refreshing");';
echo '      captchaRefreshOverlay.style.display = "none";';
echo '      captchaCode.style.opacity = "1";';
echo '      isRefreshingCaptcha = false;';
echo '    }';
echo '  }';

echo '  captchaBtn.addEventListener("click", () => {';
echo '    refreshCaptcha();';
echo '  });';

echo '  refreshCaptcha();';

echo '  function validateForm() {';
echo '    const errors = [];';
echo '    const username = usernameEl.value.trim();';
echo '    const password = passwordEl.value;';
echo '    const password2 = password2El.value;';
echo '    const captcha = captchaEl.value.trim();';
echo '    const agree = agreeEl.checked;';

echo '    if (!username || username.length < 3 || username.length > 16 || !/^[a-zA-Z0-9_]{3,16}$/.test(username)) {';
echo '      errors.push("用户名格式不正确（3-16位字母/数字/下划线）");';
echo '    }';
echo '    if (!password || password.length < 6 || password.length > 20) {';
echo '      errors.push("密码长度需为 6-20 位");';
echo '    }';
echo '    if (!password2 || password !== password2) {';
echo '      errors.push("两次输入的密码不一致");';
echo '    }';
echo '    if (!captcha || !/^\d{4}$/.test(captcha)) {';
echo '      errors.push("请输入 4 位数字验证码");';
echo '    }';
echo '    if (!agree) {';
echo '      errors.push("请阅读并同意用户协议");';
echo '    }';

echo '    return errors;';
echo '  }';

echo '  function showErrorSummary(errors) {';
echo '    if (errors.length === 0) {';
echo '      formErrorSummary.classList.add("d-none");';
echo '      return;';
echo '    }';
echo '    formErrorList.innerHTML = "";';
echo '    errors.forEach(err => {';
echo '      const li = document.createElement("li");';
echo '      li.textContent = err;';
echo '      li.className = "mb-1";';
echo '      formErrorList.appendChild(li);';
echo '    });';
echo '    formErrorSummary.classList.remove("d-none");';
echo '    formErrorSummary.classList.add("fade-in");';
echo '    formErrorSummary.scrollIntoView({ behavior: "smooth", block: "center" });';
echo '  }';

echo '  form.addEventListener("submit", event => {';
echo '    if (!form.checkValidity()) {';
echo '      event.preventDefault();';
echo '      event.stopPropagation();';
echo '      const errors = validateForm();';
echo '      showErrorSummary(errors);';
echo '      form.classList.add("was-validated");';
echo '      return;';
echo '    }';
echo '    const errors = validateForm();';
echo '    if (errors.length > 0) {';
echo '      event.preventDefault();';
echo '      event.stopPropagation();';
echo '      showErrorSummary(errors);';
echo '      form.classList.add("was-validated");';
echo '      return;';
echo '    }';
echo '    submitBtn.disabled = true;';
echo '    submitBtnSpinner.classList.remove("d-none");';
echo '    submitBtnText.textContent = "注册中...";';
echo '  });';

echo '  [usernameEl, passwordEl, password2El, captchaEl, agreeEl].forEach(el => {';
echo '    el.addEventListener("input", () => {';
echo '      if (!formErrorSummary.classList.contains("d-none")) {';
echo '        const errors = validateForm();';
echo '        if (errors.length === 0) {';
echo '          formErrorSummary.classList.add("d-none");';
echo '        }';
echo '      }';
echo '    });';
echo '    if (el.type === "checkbox") {';
echo '      el.addEventListener("change", () => {';
echo '        if (!formErrorSummary.classList.contains("d-none")) {';
echo '          const errors = validateForm();';
echo '          if (errors.length === 0) {';
echo '            formErrorSummary.classList.add("d-none");';
echo '          }';
echo '        }';
echo '      });';
echo '    }';
echo '  });';

echo '  const agreeModalBtn = document.getElementById("agreeModalBtn");';
echo '  if (agreeModalBtn) {';
echo '    agreeModalBtn.addEventListener("click", () => {';
echo '      agreeEl.checked = true;';
echo '      if (!formErrorSummary.classList.contains("d-none")) {';
echo '        const errors = validateForm();';
echo '        if (errors.length === 0) {';
echo '          formErrorSummary.classList.add("d-none");';
echo '        }';
echo '      }';
echo '    });';
echo '  }';

echo '})();';
echo '</script>';

render_footer();

