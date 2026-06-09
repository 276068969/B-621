<?php
declare(strict_types=1);

/*
 * 反灌水模块：
 * - 短时间重复发言检测
 * - 近似内容连续提交检测
 * - 无意义占位文本检测
 * - 清晰可理解的拦截提示
 */

function get_anti_spam_config(): array
{
    global $config;
    static $cachedConfig = null;

    if ($cachedConfig !== null) {
        return $cachedConfig;
    }

    $antiSpamConfig = $config['anti_spam'] ?? [];

    $defaultConfig = [
        'enabled' => true,
        'post' => [
            'min_interval_seconds' => 60,
            'similarity_threshold' => 0.85,
            'similarity_check_count' => 3,
            'min_content_length' => 10,
        ],
        'comment' => [
            'min_interval_seconds' => 30,
            'similarity_threshold' => 0.9,
            'similarity_check_count' => 5,
            'min_content_length' => 2,
        ],
    ];

    $cachedConfig = array_replace_recursive($defaultConfig, $antiSpamConfig);

    return $cachedConfig;
}

function is_anti_spam_enabled(): bool
{
    $config = get_anti_spam_config();
    $enabled = $config['enabled'] ?? true;

    if (is_bool($enabled)) {
        return $enabled;
    }

    if (is_string($enabled)) {
        $enabledLower = strtolower(trim($enabled));
        return in_array($enabledLower, ['1', 'true', 'yes', 'on'], true);
    }

    return (bool)$enabled;
}

function normalize_text(string $text): string
{
    $text = strip_tags($text);
    $text = trim($text);
    $text = preg_replace('/\s+/', '', $text) ?? '';
    $text = preg_replace('/[^\p{L}\p{N}\p{P}\p{S}]/u', '', $text) ?? '';
    return $text;
}

function calculate_similarity(string $text1, string $text2): float
{
    $text1 = normalize_text($text1);
    $text2 = normalize_text($text2);

    if ($text1 === '' || $text2 === '') {
        return 0.0;
    }

    if ($text1 === $text2) {
        return 1.0;
    }

    $len1 = mb_strlen($text1, 'UTF-8');
    $len2 = mb_strlen($text2, 'UTF-8');

    if ($len1 === 0 || $len2 === 0) {
        return 0.0;
    }

    $maxLen = max($len1, $len2);
    $distance = levenshtein_distance($text1, $text2);

    return 1.0 - ($distance / $maxLen);
}

function levenshtein_distance(string $str1, string $str2): int
{
    $chars1 = preg_split('//u', $str1, -1, PREG_SPLIT_NO_EMPTY);
    $chars2 = preg_split('//u', $str2, -1, PREG_SPLIT_NO_EMPTY);

    $len1 = count($chars1);
    $len2 = count($chars2);

    if ($len1 === 0) {
        return $len2;
    }
    if ($len2 === 0) {
        return $len1;
    }

    $matrix = [];
    for ($i = 0; $i <= $len1; $i++) {
        $matrix[$i] = [];
        $matrix[$i][0] = $i;
    }
    for ($j = 0; $j <= $len2; $j++) {
        $matrix[0][$j] = $j;
    }

    for ($i = 1; $i <= $len1; $i++) {
        for ($j = 1; $j <= $len2; $j++) {
            $cost = $chars1[$i - 1] === $chars2[$j - 1] ? 0 : 1;
            $matrix[$i][$j] = min(
                $matrix[$i - 1][$j] + 1,
                $matrix[$i][$j - 1] + 1,
                $matrix[$i - 1][$j - 1] + $cost
            );
        }
    }

    return $matrix[$len1][$len2];
}

function is_meaningless_text(string $text, string $scene = 'comment'): bool
{
    $normalized = normalize_text($text);

    if ($normalized === '') {
        return true;
    }

    $config = get_anti_spam_config();
    $sceneConfig = $config[$scene] ?? $config['comment'];
    $minLength = $sceneConfig['min_content_length'] ?? 2;

    if (mb_strlen($normalized, 'UTF-8') < $minLength) {
        return true;
    }

    if (preg_match('/^[\p{P}\p{S}\s]+$/u', $normalized)) {
        return true;
    }

    $chars = preg_split('//u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
    $charCount = count($chars);
    if ($charCount > 0) {
        $uniqueChars = array_unique($chars);
        $uniqueRatio = count($uniqueChars) / $charCount;
        if ($uniqueRatio < 0.2 && $charCount >= 5) {
            return true;
        }
    }

    if ($charCount >= 6) {
        $repeatPatterns = [
            '/^(.)\1{3,}$/u',
            '/^(..)\1{2,}$/u',
            '/^(...)\1{1,}$/u',
        ];
        foreach ($repeatPatterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }
    }

    $placeholderPatterns = [
        '/^(占位|测试|试试|看看|顶|赞|踩|沙发|板凳|地板|mark|马克|留名|签到)$/iu',
        '/^(?:666|哈哈哈|嘿嘿嘿|呵呵呵|嘻嘻嘻|啊啊啊|哦哦哦|嗯嗯嗯)+$/iu',
        '/^(?:好|行|对|是|嗯|哦|啊|哈|嘿)+$/u',
    ];
    foreach ($placeholderPatterns as $pattern) {
        if (preg_match($pattern, $normalized)) {
            return true;
        }
    }

    $specialChars = preg_replace('/[\p{L}\p{N}]/u', '', $normalized);
    if ($specialChars !== '' && mb_strlen($specialChars, 'UTF-8') / mb_strlen($normalized, 'UTF-8') > 0.7) {
        return true;
    }

    return false;
}

function check_post_time_interval(PDO $pdo, int $userId): array
{
    $config = get_anti_spam_config();
    $postConfig = $config['post'] ?? [];
    $minInterval = (int)($postConfig['min_interval_seconds'] ?? 60);

    if ($minInterval <= 0) {
        return ['passed' => true, 'message' => '', 'remaining' => 0];
    }

    $stmt = $pdo->prepare(
        'SELECT create_time FROM posts 
         WHERE user_id = ? AND status = 1 
         ORDER BY create_time DESC 
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['passed' => true, 'message' => '', 'remaining' => 0];
    }

    $lastTime = strtotime((string)$row['create_time']);
    if ($lastTime === false) {
        return ['passed' => true, 'message' => '', 'remaining' => 0];
    }

    $elapsed = time() - $lastTime;
    $remaining = $minInterval - $elapsed;

    if ($remaining > 0) {
        $minutes = (int)ceil($remaining / 60);
        $seconds = $remaining % 60;

        if ($minutes > 0) {
            $timeStr = $minutes . ' 分钟';
            if ($seconds > 0) {
                $timeStr .= $seconds . ' 秒';
            }
        } else {
            $timeStr = $seconds . ' 秒';
        }

        return [
            'passed' => false,
            'message' => sprintf('发帖过于频繁，请等待 %s 后再试。', $timeStr),
            'remaining' => $remaining,
        ];
    }

    return ['passed' => true, 'message' => '', 'remaining' => 0];
}

function check_comment_time_interval(PDO $pdo, int $userId): array
{
    $config = get_anti_spam_config();
    $commentConfig = $config['comment'] ?? [];
    $minInterval = (int)($commentConfig['min_interval_seconds'] ?? 30);

    if ($minInterval <= 0) {
        return ['passed' => true, 'message' => '', 'remaining' => 0];
    }

    $stmt = $pdo->prepare(
        'SELECT create_time FROM comments 
         WHERE user_id = ? AND status = 1 
         ORDER BY create_time DESC 
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['passed' => true, 'message' => '', 'remaining' => 0];
    }

    $lastTime = strtotime((string)$row['create_time']);
    if ($lastTime === false) {
        return ['passed' => true, 'message' => '', 'remaining' => 0];
    }

    $elapsed = time() - $lastTime;
    $remaining = $minInterval - $elapsed;

    if ($remaining > 0) {
        return [
            'passed' => false,
            'message' => sprintf('评论过于频繁，请等待 %d 秒后再试。', $remaining),
            'remaining' => $remaining,
        ];
    }

    return ['passed' => true, 'message' => '', 'remaining' => 0];
}

function check_post_similarity(PDO $pdo, int $userId, string $title, string $content): array
{
    $config = get_anti_spam_config();
    $postConfig = $config['post'] ?? [];
    $threshold = (float)($postConfig['similarity_threshold'] ?? 0.85);
    $checkCount = (int)($postConfig['similarity_check_count'] ?? 3);

    if ($checkCount <= 0 || $threshold >= 1.0) {
        return ['passed' => true, 'message' => '', 'similarity' => 0.0, 'matched_post' => null];
    }

    $stmt = $pdo->prepare(
        'SELECT id, title, content, create_time FROM posts 
         WHERE user_id = ? AND status = 1 
         ORDER BY create_time DESC 
         LIMIT ?'
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $checkCount, PDO::PARAM_INT);
    $stmt->execute();
    $recentPosts = $stmt->fetchAll();

    if (empty($recentPosts)) {
        return ['passed' => true, 'message' => '', 'similarity' => 0.0, 'matched_post' => null];
    }

    $newCombined = $title . ' ' . $content;

    $maxSimilarity = 0.0;
    $matchedPost = null;

    foreach ($recentPosts as $post) {
        $oldCombined = $post['title'] . ' ' . $post['content'];
        $similarity = calculate_similarity($newCombined, $oldCombined);

        if ($similarity > $maxSimilarity) {
            $maxSimilarity = $similarity;
            $matchedPost = $post;
        }
    }

    if ($maxSimilarity >= $threshold) {
        $percent = round($maxSimilarity * 100);
        return [
            'passed' => false,
            'message' => sprintf('您发布的内容与之前的帖子相似度达 %d%%，请避免发布重复内容。', $percent),
            'similarity' => $maxSimilarity,
            'matched_post' => $matchedPost,
        ];
    }

    return ['passed' => true, 'message' => '', 'similarity' => $maxSimilarity, 'matched_post' => null];
}

function check_comment_similarity(PDO $pdo, int $userId, string $content, ?int $postId = null): array
{
    $config = get_anti_spam_config();
    $commentConfig = $config['comment'] ?? [];
    $threshold = (float)($commentConfig['similarity_threshold'] ?? 0.9);
    $checkCount = (int)($commentConfig['similarity_check_count'] ?? 5);

    if ($checkCount <= 0 || $threshold >= 1.0) {
        return ['passed' => true, 'message' => '', 'similarity' => 0.0, 'matched_comment' => null];
    }

    $sql = 'SELECT id, content, create_time FROM comments 
            WHERE user_id = ? AND status = 1 ';
    $params = [$userId];

    if ($postId !== null) {
        $sql .= 'AND post_id = ? ';
        $params[] = $postId;
    }

    $sql .= 'ORDER BY create_time DESC LIMIT ?';
    $params[] = $checkCount;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $recentComments = $stmt->fetchAll();

    if (empty($recentComments)) {
        return ['passed' => true, 'message' => '', 'similarity' => 0.0, 'matched_comment' => null];
    }

    $maxSimilarity = 0.0;
    $matchedComment = null;

    foreach ($recentComments as $comment) {
        $similarity = calculate_similarity($content, (string)$comment['content']);

        if ($similarity > $maxSimilarity) {
            $maxSimilarity = $similarity;
            $matchedComment = $comment;
        }
    }

    if ($maxSimilarity >= $threshold) {
        $percent = round($maxSimilarity * 100);
        return [
            'passed' => false,
            'message' => sprintf('您的评论与之前的评论相似度达 %d%%，请避免发布重复内容。', $percent),
            'similarity' => $maxSimilarity,
            'matched_comment' => $matchedComment,
        ];
    }

    return ['passed' => true, 'message' => '', 'similarity' => $maxSimilarity, 'matched_comment' => null];
}

function anti_spam_check_post(PDO $pdo, int $userId, string $title, string $content): array
{
    if (!is_anti_spam_enabled()) {
        return [
            'passed' => true,
            'reason' => '',
            'message' => '',
            'details' => [],
        ];
    }

    $fullContent = $title . ' ' . $content;
    if (is_meaningless_text($fullContent, 'post')) {
        return [
            'passed' => false,
            'reason' => 'meaningless',
            'message' => '帖子内容过于简单或无实际意义，请补充详细内容后再发布。',
            'details' => ['check' => 'meaningless'],
        ];
    }

    $timeResult = check_post_time_interval($pdo, $userId);
    if (!$timeResult['passed']) {
        return [
            'passed' => false,
            'reason' => 'time_interval',
            'message' => $timeResult['message'],
            'details' => ['check' => 'time_interval', 'remaining' => $timeResult['remaining']],
        ];
    }

    $similarityResult = check_post_similarity($pdo, $userId, $title, $content);
    if (!$similarityResult['passed']) {
        return [
            'passed' => false,
            'reason' => 'similarity',
            'message' => $similarityResult['message'],
            'details' => [
                'check' => 'similarity',
                'similarity' => $similarityResult['similarity'],
                'matched_post_id' => $similarityResult['matched_post']['id'] ?? null,
            ],
        ];
    }

    return [
        'passed' => true,
        'reason' => '',
        'message' => '',
        'details' => [],
    ];
}

function anti_spam_check_comment(PDO $pdo, int $userId, string $content, ?int $postId = null): array
{
    if (!is_anti_spam_enabled()) {
        return [
            'passed' => true,
            'reason' => '',
            'message' => '',
            'details' => [],
        ];
    }

    if (is_meaningless_text($content, 'comment')) {
        return [
            'passed' => false,
            'reason' => 'meaningless',
            'message' => '评论内容过于简单或无实际意义，请发表有价值的评论。',
            'details' => ['check' => 'meaningless'],
        ];
    }

    $timeResult = check_comment_time_interval($pdo, $userId);
    if (!$timeResult['passed']) {
        return [
            'passed' => false,
            'reason' => 'time_interval',
            'message' => $timeResult['message'],
            'details' => ['check' => 'time_interval', 'remaining' => $timeResult['remaining']],
        ];
    }

    $similarityResult = check_comment_similarity($pdo, $userId, $content, $postId);
    if (!$similarityResult['passed']) {
        return [
            'passed' => false,
            'reason' => 'similarity',
            'message' => $similarityResult['message'],
            'details' => [
                'check' => 'similarity',
                'similarity' => $similarityResult['similarity'],
                'matched_comment_id' => $similarityResult['matched_comment']['id'] ?? null,
            ],
        ];
    }

    return [
        'passed' => true,
        'reason' => '',
        'message' => '',
        'details' => [],
    ];
}
