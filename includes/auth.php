<?php
declare(strict_types=1);

/*
 * 用户登录态：
 * - 通过 SESSION 记录当前用户信息
 * - 提供登录拦截与当前用户读取
 */

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (user() !== null) {
        return;
    }
    $return = urlencode($_SERVER['REQUEST_URI'] ?? '/');
    redirect('/login.php?return=' . $return);
}

function login_user(int $id, string $username): void
{
    $_SESSION['user'] = ['id' => $id, 'username' => $username];
}

function logout_user(): void
{
    unset($_SESSION['user']);
}

