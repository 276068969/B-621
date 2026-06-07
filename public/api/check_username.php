<?php
declare(strict_types=1);

/*
 * 用户名查重接口（前端实时校验使用）
 * - GET: ?username=xxx
 * - Response: { ok: true, exists: bool }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$username = trim((string)($_GET['username'] ?? ''));
if ($username === '' || !is_valid_username($username)) {
    echo json_encode(['ok' => true, 'exists' => false]);
    exit;
}

try {
    $pdo = db($config);
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $exists = (bool)$stmt->fetchColumn();
    echo json_encode(['ok' => true, 'exists' => $exists]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'server_error']);
}

