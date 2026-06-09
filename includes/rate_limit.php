<?php
declare(strict_types=1);

/*
 * 访问频率限制：
 * - 基于 IP 的全局限流
 * - 基于 IP + 业务标识 的组合限流（如登录防撞库）
 * - 支持自定义时间窗口和最大请求次数
 * - 使用 MySQL 存储限流记录
 */

class RateLimiter
{
    private PDO $pdo;
    private string $action;
    private int $maxRequests;
    private int $windowSeconds;
    private string $ip;

    public function __construct(PDO $pdo, string $action, int $maxRequests, int $windowSeconds)
    {
        $this->pdo = $pdo;
        $this->action = $action;
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->ip = self::getClientIp();
    }

    public static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        return $ip;
    }

    public function isLimited(): bool
    {
        return $this->checkLimit($this->ip);
    }

    public function isLimitedByIdentifier(string $identifier): bool
    {
        $key = $this->ip . ':' . $identifier;
        return $this->checkLimit($key);
    }

    public function increment(): void
    {
        $this->addRecord($this->ip);
    }

    public function incrementByIdentifier(string $identifier): void
    {
        $key = $this->ip . ':' . $identifier;
        $this->addRecord($key);
    }

    public function getRemainingAttempts(): int
    {
        return $this->calcRemaining($this->ip);
    }

    public function getRemainingAttemptsByIdentifier(string $identifier): int
    {
        $key = $this->ip . ':' . $identifier;
        return $this->calcRemaining($key);
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->calcRetryAfter($this->ip);
    }

    public function getRetryAfterSecondsByIdentifier(string $identifier): int
    {
        $key = $this->ip . ':' . $identifier;
        return $this->calcRetryAfter($key);
    }

    private function checkLimit(string $key): bool
    {
        $this->cleanupExpired();

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rate_limit_logs WHERE action = ? AND req_key = ? AND create_time > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$this->action, $key, $this->windowSeconds]);
        $count = (int)$stmt->fetchColumn();

        return $count >= $this->maxRequests;
    }

    private function addRecord(string $key): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limit_logs (action, req_key, ip, create_time) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$this->action, $key, $this->ip]);
    }

    private function calcRemaining(string $key): int
    {
        $this->cleanupExpired();

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rate_limit_logs WHERE action = ? AND req_key = ? AND create_time > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$this->action, $key, $this->windowSeconds]);
        $count = (int)$stmt->fetchColumn();

        return max(0, $this->maxRequests - $count);
    }

    private function calcRetryAfter(string $key): int
    {
        $this->cleanupExpired();

        $stmt = $this->pdo->prepare(
            'SELECT create_time FROM rate_limit_logs 
             WHERE action = ? AND req_key = ? AND create_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
             ORDER BY create_time ASC LIMIT 1'
        );
        $stmt->execute([$this->action, $key, $this->windowSeconds]);
        $row = $stmt->fetchColumn();
        if (!$row) {
            return 0;
        }

        $firstTime = strtotime((string)$row);
        if ($firstTime === false) {
            return 0;
        }

        $retryAfter = $firstTime + $this->windowSeconds - time();
        return max(0, $retryAfter);
    }

    private function cleanupExpired(): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM rate_limit_logs WHERE action = ? AND create_time < DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$this->action, $this->windowSeconds * 2]);
    }
}

function rate_limit_check(PDO $pdo, string $action, int $maxRequests, int $windowSeconds): bool
{
    $limiter = new RateLimiter($pdo, $action, $maxRequests, $windowSeconds);
    if ($limiter->isLimited()) {
        return false;
    }
    $limiter->increment();
    return true;
}

function rate_limit_check_with_identifier(
    PDO $pdo,
    string $action,
    int $maxRequests,
    int $windowSeconds,
    string $identifier
): bool {
    $limiter = new RateLimiter($pdo, $action, $maxRequests, $windowSeconds);
    if ($limiter->isLimitedByIdentifier($identifier)) {
        return false;
    }
    $limiter->incrementByIdentifier($identifier);
    return true;
}

function rate_limit_get_remaining(PDO $pdo, string $action, int $maxRequests, int $windowSeconds): int
{
    $limiter = new RateLimiter($pdo, $action, $maxRequests, $windowSeconds);
    return $limiter->getRemainingAttempts();
}

function rate_limit_get_retry_after(PDO $pdo, string $action, int $maxRequests, int $windowSeconds): int
{
    $limiter = new RateLimiter($pdo, $action, $maxRequests, $windowSeconds);
    return $limiter->getRetryAfterSeconds();
}
