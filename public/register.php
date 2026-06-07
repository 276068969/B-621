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

echo '<div class="form-floating mb-3">';
echo '<input class="form-control" name="username" id="username" placeholder="用户名" minlength="3" maxlength="16" required value="' . e($values['username']) . '">';
echo '<label for="username">用户名</label>';
echo '<div class="form-text" id="usernameHint">3-16 位字母/数字/下划线，需唯一。</div>';
echo '<div class="invalid-feedback">请输入 3-16 位用户名。</div>';
if (isset($errors['username'])) {
    echo '<div class="text-danger small mt-1">❌ ' . e($errors['username']) . '</div>';
}
echo '</div>';

echo '<div class="row">';
echo '<div class="col-12 col-md-6 mb-3">';
echo '<div class="form-floating">';
echo '<input type="password" class="form-control" name="password" id="password" placeholder="密码" minlength="6" maxlength="20" required>'; 
echo '<label for="password">密码</label>';
echo '<div class="invalid-feedback">请输入 6-20 位密码。</div>';
if (isset($errors['password'])) {
    echo '<div class="text-danger small mt-1">❌ ' . e($errors['password']) . '</div>';
}
echo '</div></div>';

echo '<div class="col-12 col-md-6 mb-3">';
echo '<div class="form-floating">';
echo '<input type="password" class="form-control" name="password2" id="password2" placeholder="确认密码" minlength="6" maxlength="20" required>';
echo '<label for="password2">确认密码</label>';
echo '<div class="form-text" id="pwdHint"></div>';
echo '<div class="invalid-feedback">请再次输入密码。</div>';
if (isset($errors['password2'])) {
    echo '<div class="text-danger small mt-1">❌ ' . e($errors['password2']) . '</div>';
}
echo '</div></div></div>';

echo '<div class="form-floating mb-3">';
echo '<input class="form-control" name="mobile" id="mobile" placeholder="手机号" inputmode="numeric" value="' . e($values['mobile']) . '">';
echo '<label for="mobile">手机号 (可选)</label>';
if (isset($errors['mobile'])) {
    echo '<div class="text-danger small mt-1">❌ ' . e($errors['mobile']) . '</div>';
}
echo '</div>';

echo '<div class="row align-items-center mb-3">';
echo '<div class="col-7">';
echo '<div class="form-floating">';
echo '<input class="form-control" name="captcha" id="captcha" placeholder="验证码" inputmode="numeric" maxlength="4" required value="' . e($values['captcha']) . '">';
echo '<label for="captcha">验证码</label>';
echo '<div class="invalid-feedback">请输入 4 位验证码。</div>';
echo '</div></div>';
echo '<div class="col-5 text-end">';
echo '<button type="button" class="btn btn-outline-secondary w-100 py-3 fw-bold fs-5" id="captchaBtn" style="letter-spacing:.25rem;">0000</button>';
echo '</div>';
if (isset($errors['captcha'])) {
    echo '<div class="col-12 text-danger small mt-1">❌ ' . e($errors['captcha']) . '</div>';
}
echo '</div>';

echo '<div class="form-check mb-4">';
echo '<input class="form-check-input" type="checkbox" value="1" name="agree" id="agree" required ' . ($values['agree'] === '1' ? 'checked' : '') . '>'; 
echo '<label class="form-check-label" for="agree">我已阅读并同意 <a href="#" data-bs-toggle="modal" data-bs-target="#agreementModal">用户协议</a></label>';
echo '<div class="invalid-feedback">请勾选同意用户协议。</div>';
if (isset($errors['agree'])) {
    echo '<div class="text-danger small mt-1">❌ ' . e($errors['agree']) . '</div>';
}
echo '</div>';

echo '<button class="btn btn-primary w-100 py-3 fw-bold fs-5 shadow-sm mb-3" type="submit" id="submitBtn">立即注册</button>';
echo '<div class="text-center">';
echo '<a class="text-decoration-none text-muted" href="/login.php">已有账号？<span class="text-primary fw-semibold">去登录</span></a>';
echo '</div>';

echo '</form>';

// 前端校验增强
echo '<script>';
echo '(() => {';
echo '  "use strict";';
echo '  const forms = document.querySelectorAll(".needs-validation");';
echo '  Array.from(forms).forEach(form => {';
echo '    form.addEventListener("submit", event => {';
echo '      if (!form.checkValidity()) {';
echo '        event.preventDefault();';
echo '        event.stopPropagation();';
echo '        showModal("注册失败", "请检查表单中标记红色的必填项。");';
echo '      }';
echo '      form.classList.add("was-validated");';
echo '    }, false);';
echo '  });';
echo '})();';
// ... (原有逻辑保持不变)
echo 'const usernameEl=document.getElementById("username");';
echo 'const usernameHint=document.getElementById("usernameHint");';
echo 'const passwordEl=document.getElementById("password");';
echo 'const password2El=document.getElementById("password2");';
echo 'const pwdHint=document.getElementById("pwdHint");';
echo 'const captchaBtn=document.getElementById("captchaBtn");';

echo 'function genCaptcha(){return String(Math.floor(1000+Math.random()*9000));}';
echo 'async function storeCaptcha(code){';
echo '  const body=new URLSearchParams(); body.set("code",code);';
echo '  await fetch("/api/captcha_store.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body});';
echo '}';
echo 'async function refreshCaptcha(){const code=genCaptcha(); captchaBtn.textContent=code; await storeCaptcha(code);}';
echo 'captchaBtn.addEventListener("click",()=>{refreshCaptcha();});';
echo 'refreshCaptcha();';

echo 'let usernameTimer=null;';
echo 'usernameEl.addEventListener("input",()=>{';
echo '  clearTimeout(usernameTimer);';
echo '  const v=usernameEl.value.trim();';
echo '  usernameHint.textContent="3-16 位字母/数字/下划线，需唯一。";';
echo '  if(v.length<3){return;}';
echo '  usernameTimer=setTimeout(async()=>{';
echo '    try{';
echo '      const res=await fetch("/api/check_username.php?username="+encodeURIComponent(v));';
echo '      const data=await res.json();';
echo '      if(!data.ok){return;}';
echo '      if(data.exists){usernameHint.innerHTML="<span class=\"text-danger\">用户名已存在，请更换。</span>";}';
echo '      else{usernameHint.innerHTML="<span class=\"text-success\">用户名可用。</span>";}';
echo '    }catch(e){}';
echo '  },300);';
echo '});';

echo 'function checkPwd(){';
echo '  const a=passwordEl.value; const b=password2El.value;';
echo '  if(!b){pwdHint.textContent="请再次输入密码。"; return;}';
echo '  if(a===b){pwdHint.innerHTML="<span class=\"text-success\">密码一致。</span>";}';
echo '  else{pwdHint.innerHTML="<span class=\"text-danger\">两次输入不一致。</span>";}';
echo '}';
echo 'passwordEl.addEventListener("input",checkPwd);';
echo 'password2El.addEventListener("input",checkPwd);';
echo '</script>';

render_footer();

