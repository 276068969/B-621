<?php
declare(strict_types=1);

/*
 * 内容敏感词审核模块：
 * - 统一的敏感词检测能力
 * - 支持标题、正文、评论等多种内容场景
 * - 提供一致的拦截反馈消息
 * - 中英文智能匹配，避免正常英文误判
 */

function get_sensitive_words_config(): array
{
    static $cachedWordConfig = null;
    if ($cachedWordConfig !== null) {
        return $cachedWordConfig;
    }

    global $config;
    $customWords = $config['moderation']['sensitive_words'] ?? [];

    $defaultWords = [
        'violence' => [
            '杀人', '放火', '抢劫', '贩毒', '吸毒', '走私', '绑架', '勒索',
            '暴力', '殴打', '伤害', '自杀', '自残', '爆炸', '枪击', '刀砍',
        ],
        'pornography' => [
            '色情', '黄色', '淫秽', '成人影片', '三级片', '一夜情',
            '嫖娼', '卖淫', '援交', '包养', '性服务', '裸聊', '裸照',
        ],
        'gambling' => [
            '赌博', '博彩', '赌场', '彩票', '开奖', '下注', '赌球', '赌马',
            '老虎机', '百家乐', '二八杠', '炸金花', '斗牛',
        ],
        'drugs' => [
            '毒品', '海洛因', '冰毒', '可卡因', '大麻', '摇头丸', 'K粉',
            '吗啡', '罂粟', '吸毒', '贩毒', '制毒',
        ],
        'fraud' => [
            '诈骗', '传销', '非法集资', '庞氏骗局', '刷单', '刷信誉',
            '代刷', '套现', '洗钱', '假币',
        ],
        'politics' => [
            '反动', '颠覆', '分裂', '独立', '邪教', '法轮功',
        ],
        'hate' => [
            '傻逼', '操你妈', '妈的', '草泥马', '白痴', '脑残',
            '废物', '垃圾', '去死', '滚蛋', '杂种', '畜生', '婊子',
            '贱人', '狗娘养的', '王八', '王八蛋',
        ],
        'advertising' => [
            '加微信', '微信公众号', '扫码关注', '点击领取',
            '免费领取', '限时优惠', '赚钱秘籍', '月入过万', '在家赚钱',
            '兼职日结', '日赚百元', '快速致富', '一手货源', '厂家直销',
        ],
        'spam' => [
            '不看后悔', '不转不是', '转发有礼', '分享有礼', '点赞有礼',
            '人肉搜索', '求转发', '求扩散',
        ],
    ];

    $englishWords = [
        'pornography' => ['porn', 'porno', 'xxx', 'sex', 'sexy', 'nude', 'naked'],
        'drugs' => ['weed', 'coke', 'heroin', 'cocaine', 'marijuana', 'meth', 'lsd'],
        'gambling' => ['casino', 'poker', 'blackjack', 'roulette', 'baccarat', 'slot machine'],
        'hate' => ['fuck', 'shit', 'asshole', 'bitch', 'dick', 'pussy', 'cunt', 'motherfucker', 'bullshit'],
        'advertising' => ['add me', 'click here', 'free money', 'get rich'],
    ];

    $chineseWords = [];
    foreach ($defaultWords as $category => $categoryWords) {
        foreach ($categoryWords as $word) {
            $chineseWords[] = [
                'word' => $word,
                'category' => $category,
                'type' => 'chinese',
            ];
        }
    }

    $englishWordsList = [];
    foreach ($englishWords as $category => $categoryWords) {
        foreach ($categoryWords as $word) {
            $englishWordsList[] = [
                'word' => $word,
                'category' => $category,
                'type' => 'english',
            ];
        }
    }

    if (!empty($customWords)) {
        foreach ($customWords as $word) {
            $isEnglish = preg_match('/^[a-zA-Z\s]+$/', $word);
            $chineseWords[] = [
                'word' => $word,
                'category' => 'custom',
                'type' => $isEnglish ? 'english' : 'chinese',
            ];
        }
    }

    $allWords = array_merge($chineseWords, $englishWordsList);
    usort($allWords, function ($a, $b) {
        return strlen($b['word']) - strlen($a['word']);
    });

    $cachedWordConfig = [
        'all_words' => $allWords,
        'chinese_words' => $chineseWords,
        'english_words' => $englishWordsList,
    ];

    return $cachedWordConfig;
}

function get_sensitive_word_categories(): array
{
    return [
        'violence' => '暴力内容',
        'pornography' => '色情内容',
        'gambling' => '赌博内容',
        'drugs' => '毒品内容',
        'fraud' => '诈骗内容',
        'politics' => '政治敏感',
        'hate' => '辱骂攻击',
        'advertising' => '广告推广',
        'spam' => '垃圾信息',
        'custom' => '自定义',
    ];
}

function is_moderation_enabled(): bool
{
    global $config;

    $enabled = $config['moderation']['enabled'] ?? true;

    if (is_bool($enabled)) {
        return $enabled;
    }

    if (is_string($enabled)) {
        $enabledLower = strtolower(trim($enabled));
        return in_array($enabledLower, ['1', 'true', 'yes', 'on'], true);
    }

    return (bool)$enabled;
}

function detect_sensitive_words(string $content): array
{
    if ($content === '') {
        return [
            'has_sensitive' => false,
            'matched_words' => [],
            'category_map' => [],
        ];
    }

    $wordConfig = get_sensitive_words_config();
    $allWords = $wordConfig['all_words'];
    $matched = [];
    $matchedWords = [];

    $contentLower = mb_strtolower($content, 'UTF-8');

    foreach ($allWords as $wordItem) {
        $word = $wordItem['word'];
        $wordLower = mb_strtolower($word, 'UTF-8');
        $type = $wordItem['type'];
        $category = $wordItem['category'];

        $isMatched = false;

        if ($type === 'english') {
            $pattern = '/\b' . preg_quote($wordLower, '/') . '\b/i';
            if (preg_match($pattern, $contentLower)) {
                $isMatched = true;
            }
        } else {
            if (mb_strpos($contentLower, $wordLower) !== false) {
                $isMatched = true;
            }
        }

        if ($isMatched && !in_array($word, $matchedWords)) {
            $matched[] = [
                'word' => $word,
                'category' => $category,
                'type' => $type,
            ];
            $matchedWords[] = $word;
        }
    }

    $categoryMap = [];
    $categories = get_sensitive_word_categories();

    foreach ($matched as $item) {
        $catKey = $item['category'];
        if (!isset($categoryMap[$catKey])) {
            $categoryMap[$catKey] = [
                'name' => $categories[$catKey] ?? $catKey,
                'words' => [],
            ];
        }
        if (!in_array($item['word'], $categoryMap[$catKey]['words'])) {
            $categoryMap[$catKey]['words'][] = $item['word'];
        }
    }

    $matchedWordList = array_column($matched, 'word');

    return [
        'has_sensitive' => !empty($matchedWordList),
        'matched_words' => $matchedWordList,
        'category_map' => $categoryMap,
    ];
}

function get_moderation_error_message(array $result, string $scene = 'content'): string
{
    if (!$result['has_sensitive']) {
        return '';
    }

    $sceneLabels = [
        'title' => '标题',
        'content' => '内容',
        'comment' => '评论',
        'post' => '帖子',
    ];
    $sceneLabel = $sceneLabels[$scene] ?? '内容';

    if (count($result['matched_words']) <= 2) {
        $displayWords = array_slice($result['matched_words'], 0, 2);
        $wordStr = implode('、', $displayWords);
        return sprintf(
            '%s包含违规内容「%s」，请修改后重新发布。',
            $sceneLabel,
            $wordStr
        );
    }

    $categoryNames = [];
    foreach ($result['category_map'] as $cat) {
        $categoryNames[] = $cat['name'];
    }

    if (!empty($categoryNames)) {
        $categoryStr = implode('、', array_slice($categoryNames, 0, 3));
        return sprintf(
            '%s包含违规内容（%s等），请修改后重新发布。',
            $sceneLabel,
            $categoryStr
        );
    }

    return sprintf('%s包含违规内容，请修改后重新发布。', $sceneLabel);
}

function moderate_content(string $content, string $scene = 'content'): array
{
    if (!is_moderation_enabled()) {
        return [
            'passed' => true,
            'has_sensitive' => false,
            'matched_words' => [],
            'category_map' => [],
            'message' => '',
        ];
    }

    $plainContent = trim(strip_tags($content));

    $result = detect_sensitive_words($plainContent);

    if (!$result['has_sensitive']) {
        return [
            'passed' => true,
            'has_sensitive' => false,
            'matched_words' => [],
            'category_map' => [],
            'message' => '',
        ];
    }

    $message = get_moderation_error_message($result, $scene);

    return [
        'passed' => false,
        'has_sensitive' => true,
        'matched_words' => $result['matched_words'],
        'category_map' => $result['category_map'],
        'message' => $message,
    ];
}

function moderate_post(string $title, string $content): array
{
    $titleResult = moderate_content($title, 'title');
    $contentResult = moderate_content($content, 'content');

    $allMatched = array_merge($titleResult['matched_words'], $contentResult['matched_words']);
    $allMatched = array_values(array_unique($allMatched));

    $allCategoryMap = array_merge_recursive(
        $titleResult['category_map'],
        $contentResult['category_map']
    );

    $mergedCategories = [];
    foreach ($allCategoryMap as $key => $value) {
        if (isset($value['name']) && isset($value['words'])) {
            $mergedCategories[$key] = [
                'name' => $value['name'],
                'words' => array_values(array_unique($value['words'])),
            ];
        }
    }

    $passed = $titleResult['passed'] && $contentResult['passed'];

    $message = '';
    if (!$titleResult['passed']) {
        $message = $titleResult['message'];
    } elseif (!$contentResult['passed']) {
        $message = $contentResult['message'];
    }

    if (!$titleResult['passed'] && !$contentResult['passed']) {
        $message = '帖子标题和内容均包含违规内容，请修改后重新发布。';
    }

    return [
        'passed' => $passed,
        'title_passed' => $titleResult['passed'],
        'content_passed' => $contentResult['passed'],
        'has_sensitive' => !$passed,
        'matched_words' => $allMatched,
        'category_map' => $mergedCategories,
        'message' => $message,
        'title_result' => $titleResult,
        'content_result' => $contentResult,
    ];
}

function moderate_comment(string $content): array
{
    return moderate_content($content, 'comment');
}
