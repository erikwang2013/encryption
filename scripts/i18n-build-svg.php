<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 由 docs/*.svg 模板 + docs/i18n/labels/<lang>.json 词条，生成 docs/i18n/<lang>/*.svg。
 *
 *   php scripts/i18n-build-svg.php <lang>      生成（并报告未翻译文本）
 *   php scripts/i18n-build-svg.php <lang> --check  只检查，不写文件
 *   php scripts/i18n-build-svg.php --extract   导出各模板的全部文本节点（用于补词条）
 *
 * 词条文件格式：
 *   { "translate": { "English source": "译文字符串" }, "keep": ["aes-256-gcm", "EncryptionManager"] }
 * translate 里的值会替换同名文本；keep 里的字符串原样保留（类名、算法标识等）。
 * 译文比英文长时自动缩小字号，使其不超出英文原文占用的宽度。
 * 若自动缩放过度（如英文原文很短、译文很长的单词），可在 "font_size" 里按英文原文
 * 钉死字号，例如 { "font_size": { "Encrypt": 14.5 } }——钉死后不再参与缩放与过长告警。
 */

const ROOT = __DIR__ . '/..';

const TEMPLATES = [
    'mascot.svg',
    'architecture-design.svg',
    'functional-design.svg',
    'lifecycle.svg',
];

const MIN_FONT_SIZE = 8.5;
const MIN_SHRINK = 0.55;
/** 低于该比例的译文会被报告为“过长”，提示译者改写得更短。 */
const CRAMPED_RATIO = 0.72;

$argvRest = array_slice($argv, 1);

if (in_array('--extract', $argvRest, true)) {
    exit(extractTextNodes());
}

$lang = $argvRest[0] ?? '';
$check = in_array('--check', $argvRest, true);

if ($lang === '' || !preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $lang)) {
    fwrite(STDERR, "Usage: php scripts/i18n-build-svg.php <lang> [--check]\n");
    exit(1);
}

$labelsFile = ROOT . "/docs/i18n/labels/{$lang}.json";
if (!is_file($labelsFile)) {
    fwrite(STDERR, "Missing label file: docs/i18n/labels/{$lang}.json\n");
    exit(1);
}

$labels = json_decode((string) file_get_contents($labelsFile), true);
if (!is_array($labels)) {
    fwrite(STDERR, "labels/{$lang}.json is not valid JSON.\n");
    exit(1);
}

// 版面钉死字号以 en.json 为基准（版面属性与语种无关），语言文件可覆盖。
$baseLabels = json_decode((string) file_get_contents(ROOT . '/docs/i18n/labels/en.json'), true);
$pinnedSizes = array_merge($baseLabels['font_size'] ?? [], $labels['font_size'] ?? []);

/** @var array<string, string> $map */
$map = [];
foreach ($labels['keep'] ?? [] as $literal) {
    $map[$literal] = $literal;
}
$emptyTranslations = [];
foreach ($labels['translate'] ?? [] as $source => $target) {
    if (trim((string) $target) === '') {
        $emptyTranslations[] = $source;
        continue;
    }
    $map[$source] = (string) $target;
}

$outDir = ROOT . "/docs/i18n/{$lang}";
if (!$check && !is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

$totalReplaced = 0;
$totalMissing = 0;
$totalCramped = 0;
foreach (TEMPLATES as $template) {
    $source = ROOT . "/docs/{$template}";
    [$svg, $replaced, $missing, $cramped] = localize($source, $map, $lang, $pinnedSizes);
    $totalReplaced += $replaced;
    $totalMissing += count($missing);
    $totalCramped += count($cramped);

    if (!$check) {
        file_put_contents("{$outDir}/{$template}", $svg);
    }

    printf(
        "%s %-26s replaced=%d untranslated=%d cramped=%d%s\n",
        $check ? '[check]' : '[write]',
        $template,
        $replaced,
        count($missing),
        count($cramped),
        $check ? '' : " -> docs/i18n/{$lang}/{$template}"
    );
    foreach ($missing as $text) {
        printf("        untranslated · %s\n", mb_strimwidth($text, 0, 90, '…'));
    }
    foreach ($cramped as [$text, $ratio]) {
        printf(
            "        too long · %s -> %.0f%% of the English width, rewrite it shorter\n",
            mb_strimwidth($text, 0, 70, '…'),
            $ratio * 100
        );
    }
}

if ($emptyTranslations !== []) {
    printf("Empty translations in labels/%s.json: %d\n", $lang, count($emptyTranslations));
    foreach (array_slice($emptyTranslations, 0, 10) as $source) {
        printf("        · %s\n", mb_strimwidth($source, 0, 90, '…'));
    }
}

printf("Done: %d nodes replaced, %d untranslated, %d too long.\n", $totalReplaced, $totalMissing, $totalCramped);
exit(0);

/**
 * 返回 [svg 内容, 替换数, 未翻译文本列表]。
 *
 * @param array<string, string> $map
 * @return array{0: string, 1: int, 2: list<string>}
 */
function localize(string $file, array $map, string $lang, array $pinnedSizes = []): array
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = true;
    $dom->formatOutput = false;
    $dom->loadXML((string) file_get_contents($file));
    $dom->encoding = 'UTF-8';

    $xpath = new DOMXPath($dom);
    $replaced = 0;
    $missing = [];

    /** @var array<int, string> $before */
    $before = [];
    /** @var array<int, DOMElement> $parents */
    $parents = [];

    foreach ($xpath->query('//text()') as $node) {
        /** @var DOMText $node */
        $raw = $node->nodeValue;
        $trimmed = trim($raw);
        if ($trimmed === '') {
            continue;
        }
        if (!isset($map[$trimmed])) {
            $missing[] = $trimmed;
            continue;
        }

        /** @var DOMElement $textElement */
        $textElement = $node->parentNode;
        if (!isset($before[spl_object_id($textElement)])) {
            $before[spl_object_id($textElement)] = $textElement->textContent;
            $parents[spl_object_id($textElement)] = $textElement;
        }

        preg_match('/^(\s*)(.*?)(\s*)$/su', $raw, $m);
        $node->nodeValue = $m[1] . $map[$trimmed] . $m[3];
        $replaced++;
    }

    $cramped = [];
    foreach ($parents as $id => $textElement) {
        $ratio = fitFontSize($textElement, $before[$id], $pinnedSizes);
        if ($ratio !== null && $ratio < CRAMPED_RATIO) {
            $cramped[] = [$before[$id], $ratio];
        }
    }

    $svg = $dom->saveXML();
    $note = "<!-- Generated from docs/" . basename($file) . " by scripts/i18n-build-svg.php"
        . " ({$lang}) — edit docs/i18n/labels/{$lang}.json instead. -->\n";
    $svg = preg_replace('/>\n/', ">\n{$note}", $svg, 1);

    return [$svg, $replaced, $missing, $cramped];
}

/**
 * 译文比英文原文宽时，按比例缩小该 <text> 的字号（上限 MIN_SHRINK，下限 MIN_FONT_SIZE）。
 * 返回实际采用的缩放比例；未缩放时返回 null。
 */
function fitFontSize(DOMElement $textElement, string $englishText, array $pinnedSizes = []): ?float
{
    $size = fontSizeOf($textElement);
    if ($size === null) {
        return null;
    }

    // 显式钉死字号：不缩放、不告警（用于英文原文短、译文天然更长的单词标签）。
    if (isset($pinnedSizes[$englishText])) {
        $textElement->setAttribute('font-size', (string) $pinnedSizes[$englishText]);

        return null;
    }

    $englishWidth = estimateWidth($englishText, $size);
    $translatedWidth = estimateWidth($textElement->textContent, $size);
    if ($englishWidth <= 0.0 || $translatedWidth <= $englishWidth) {
        return null;
    }

    $ratio = max(MIN_SHRINK, $englishWidth / $translatedWidth);
    $newSize = max(MIN_FONT_SIZE, round($size * $ratio, 1));
    $textElement->setAttribute('font-size', (string) $newSize);

    return $ratio;
}

function fontSizeOf(DOMElement $element): ?float
{
    for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
        if ($node->hasAttribute('font-size')) {
            return (float) $node->getAttribute('font-size');
        }
    }

    return null;
}

function estimateWidth(string $text, float $fontSize): float
{
    $units = 0.0;
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
        $units += charFactor($char);
    }

    return $units * $fontSize;
}

function charFactor(string $char): float
{
    // 全宽字符：CJK、假名、谚文、全角标点
    if (preg_match(
        '/[\x{1100}-\x{115F}\x{2E80}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}'
        . '\x{FE30}-\x{FE4F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}\x{20000}-\x{2FA1F}]/u',
        $char
    ) === 1) {
        return 1.0;
    }

    return ord($char) > 0x7F ? 0.56 : 0.52;
}

function extractTextNodes(): int
{
    $all = [];
    foreach (TEMPLATES as $template) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->loadXML((string) file_get_contents(ROOT . "/docs/{$template}"));
        $texts = [];
        foreach ((new DOMXPath($dom))->query('//text()') as $node) {
            $trimmed = trim($node->nodeValue);
            if ($trimmed !== '') {
                $texts[] = $trimmed;
            }
        }
        $all[$template] = array_values(array_unique($texts));
    }

    echo json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";

    return 0;
}
