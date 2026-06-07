<?php
declare(strict_types=1);

/*
 * 后台登录态：
 * - 与用户登录态分离
 */

function admin_is_logged_in(): bool
{
    return (bool)($_SESSION['admin_logged_in'] ?? false);
}

function admin_require_login(): void
{
    if (admin_is_logged_in()) {
        return;
    }
    redirect('/admin/login.php');
}

function admin_login(): void
{
    $_SESSION['admin_logged_in'] = true;
}

function admin_logout(): void
{
    unset($_SESSION['admin_logged_in']);
}

