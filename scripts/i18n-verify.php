<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 多语言 README 一致性校验（防止翻译漂移），CI 跑一次即可。
 *
 *   php scripts/i18n-verify.php               校验全部语言 + 英文镜像
 *   php scripts/i18n-verify.php de ja         只校验指定语言（须在 en / LANGS 内）
 *   php scripts/i18n-verify.php --write-en    由根 README.md 重新生成英文镜像后退出
 *
 * 每个 docs/i18n/<lang>/README.md 检查六项：
 *   1. 项目结构树：```text 代码块的“路径列”与英文 README.md 完全一致，不多不少；
 *   2. 结构计数：各级标题数、围栏代码块数、表格行数、分隔线数、图片数与根 README.md 一致
 *      （只比数量不比内容，整节漏译会在这里暴露）；
 *   3. 相对链接与图片目标在磁盘上真实存在；
 *   4. 目录锚点 (#…) 在本文件里有同名标题（GitHub slug 规则）；
 *   5. labels/<lang>.json 的 translate 键集与 keep 列表和 en.json 一致，且无空值；
 *   6. 文件顶部（前 SWITCHER_SCAN_LINES 行）有语言切换行 **Languages:**。
 *
 * docs/i18n/en/README.md 是英文镜像（根 README.md 的副本，仅语言切换行与相对路径按目录层级改写）：
 * 它的结构树与结构计数天然与根 README 同源，故不做第 1、2 项，改为逐行比对
 * “根 README 改写后的期望内容”，多一句少一句都会作为漂移报出来。
 *
 * 全部通过退出码 0；存在问题退出码 1，并按语言列出可读差异。
 * 新增语言时：加进 LANGS，并在 docs/i18n/labels/ 放好词条文件。
 */

// 英文原文（结构与锚点的基准）：docs/i18n/en/README.md 只是它的镜像副本，会滞后，故以根 README.md 为准。
const ROOT = __DIR__ . '/..';
const REFERENCE_README = 'README.md';
/** 译文目录：结构树必须与英文原文逐行一致。 */
const LANGS = ['ko', 'ru', 'de', 'fr', 'es', 'pt', 'hi', 'ar', 'bn', 'id', 'ja'];
/** 英文镜像目录：内容由根 README.md 改写而来，按内容逐行比对。 */
const EN_MIRROR = 'en';
/** 语言切换行需要出现在前多少行内。 */
const SWITCHER_SCAN_LINES = 10;
/** 单条报告最多列几个具体条目，避免刷屏。 */
const MAX_LISTED = 8;

// 锚点比较依赖 Unicode 小写（西里尔、变音符），缺 mbstring 会算错，宁可直接失败也不给出错误结论。
if (!function_exists('mb_strtolower')) {
    fwrite(STDERR, "scripts/i18n-verify.php requires ext-mbstring\n");
    exit(1);
}

/**
 * 逐行扫描，返回 [是否位于代码围栏内, 行文本, 是否为开栏行]。
 * 围栏标记行本身算“围栏内”，便于整块跳过；闭栏只认与开栏同字符、且不短于开栏长度的
 * 标记（CommonMark 规则），所以 ```text 块正文里出现的嵌套围栏不会被误判成新代码块。
 *
 * @return list<array{0: bool, 1: string, 2: bool}>
 */
function scanLines(string $markdown): array
{
    $rows = [];
    $open = null;
    foreach (explode("\n", $markdown) as $line) {
        $isMarker = false;
        $isOpener = false;
        if (preg_match('/^\s*(`{3,}|~{3,})/', $line, $m) === 1) {
            $isMarker = true;
            $marker = $m[1];
            if ($open === null) {
                $open = $marker;
                $isOpener = true;
            } elseif ($marker[0] === $open[0] && strlen($marker) >= strlen($open)
                && preg_match('/^\s*' . preg_quote($marker, '/') . '\s*$/', $line) === 1
            ) {
                $open = null;
            }
        }
        $rows[] = [$open !== null || $isMarker, $line, $isOpener];
    }

    return $rows;
}

/** 文档里第一个 ```text 代码块的内容（项目结构树）。 */
function treeBlock(string $markdown): string
{
    $inside = false;
    $block = [];
    foreach (scanLines($markdown) as [$fenced, $line]) {
        if (!$fenced) {
            continue;
        }
        if (!$inside) {
            // 只认 ```text 围栏，避免误吃其它代码块。
            if (preg_match('/^\s*```\s*text\s*$/', $line) === 1) {
                $inside = true;
            }
            continue;
        }
        if (preg_match('/^\s*```\s*$/', $line) === 1) {
            break;
        }
        $block[] = $line;
    }

    return implode("\n", $block);
}

/**
 * 树块的“路径列”：每行取注解列（连续 2 个以上空格）之前的部分，保留树枝缩进。
 * 只有注解的续行会退化成纯树枝前缀，同样参与比较（防止多写/漏写续行）。
 *
 * @return list<string>
 */
function treePaths(string $block): array
{
    $paths = [];
    foreach (explode("\n", $block) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $parts = preg_split('/\s{2,}/', rtrim($line));
        $paths[] = (string) ($parts[0] ?? '');
    }

    return $paths;
}

/** 报告里去掉树枝前缀，只显示路径本身（纯树枝行原样显示）。 */
function stripTreeGlyphs(string $path): string
{
    $stripped = preg_replace('/^[\s│├└─]+/u', '', $path);

    return ($stripped === null || $stripped === '') ? $path : $stripped;
}

/**
 * 语言无关的结构计数：各级标题数、围栏代码块数、表格行数、分隔线数、图片数。
 * 只数数量、不比内容——标题文字、链接与图片路径、正文长度本来就因语言而异；
 * 但整节漏译会让某一级的标题数或代码块数对不上，这正是要抓的漂移。
 *
 * @return array<string, int>
 */
function structureCounts(string $markdown): array
{
    $counts = [
        'heading level 1' => 0,
        'heading level 2' => 0,
        'heading level 3' => 0,
        'heading level 4' => 0,
        'heading level 5' => 0,
        'heading level 6' => 0,
        'fenced code blocks' => 0,
        'table rows' => 0,
        'horizontal rules' => 0,
        'images' => 0,
    ];
    foreach (scanLines($markdown) as [$inFence, $line, $isOpener]) {
        if ($isOpener) {
            $counts['fenced code blocks']++;
        }
        if ($inFence) {
            continue; // 围栏内的内容不算文档结构
        }
        if (preg_match('/^(#{1,6})\s/', $line, $m) === 1) {
            $counts['heading level ' . strlen($m[1])]++;
        }
        if (str_starts_with($line, '|')) {
            $counts['table rows']++;
        }
        if (preg_match('/^\s*(?:-{3,}|\*{3,}|_{3,})\s*$/', $line) === 1) {
            $counts['horizontal rules']++;
        }
        $counts['images'] += (int) preg_match_all('/!\[[^\]]*\]\(|<img\b/', $line);
    }

    return $counts;
}

/** GitHub 标题锚点：小写、去标点、空格转连字符（保留 Unicode 文字，如 ü、С）。 */
function slug(string $heading): string
{
    // GitHub 的锚点按 Unicode 小写；strtolower() 只认 ASCII（西里尔等大写不动），故用 mbstring。
    $slug = mb_strtolower(trim($heading), 'UTF-8');
    $slug = (string) preg_replace('/[^\p{L}\p{N}\p{M}\s_-]+/u', '', $slug);

    return (string) preg_replace('/\s+/', '-', $slug);
}

/**
 * 文件里定义的全部标题锚点。
 *
 * @return array<string, true>
 */
function headingSlugs(string $markdown): array
{
    $slugs = [];
    foreach (scanLines($markdown) as [$fenced, $line]) {
        if ($fenced) {
            continue;
        }
        if (preg_match('/^#{1,6}\s+(.+)$/', $line, $m) === 1) {
            $slugs[slug(rtrim($m[1]))] = true;
        }
    }

    return $slugs;
}

/**
 * 相对链接与图片目标（跳过纯锚点、绝对 URL、mailto:）。
 *
 * @return list<string>
 */
function relativeTargets(string $markdown): array
{
    preg_match_all('/\]\(\s*([^)\s]+)|src="([^"]+)"/', $markdown, $m, PREG_SET_ORDER);
    $targets = [];
    foreach ($m as $set) {
        $target = ($set[1] ?? '') !== '' ? $set[1] : (string) ($set[2] ?? '');
        if ($target === '' || $target[0] === '#' || str_contains($target, '://') || str_starts_with($target, 'mailto:')) {
            continue;
        }
        $targets[] = $target;
    }

    return $targets;
}

/**
 * 把条目列表压缩成一行：最多 MAX_LISTED 个，其余以 (+N) 收尾。
 *
 * @param array<int|string, string> $items
 */
function brief(array $items): string
{
    $items = array_values($items);
    $head = array_slice($items, 0, MAX_LISTED);
    $extra = count($items) - count($head);

    return implode(', ', $head) . ($extra > 0 ? sprintf(' (+%d more)', $extra) : '');
}

/** 报告用：截断过长行（图表的 alt 文本极长）。 */
function excerpt(string $line, int $max = 80): string
{
    return mb_strlen($line, 'UTF-8') > $max ? mb_substr($line, 0, $max, 'UTF-8') . '…' : $line;
}

/**
 * 由根 README.md 推导 docs/i18n/en/README.md 的期望内容：镜像比根目录深两级
 * （docs/i18n/en/），故语言切换行改成本地链接、文档内相对路径按层级改写。
 */
function mirrorOf(string $root): string
{
    $lines = [];
    foreach (explode("\n", $root) as $line) {
        $lines[] = preg_match('/^\*\*Languages:\*\*/', $line) === 1 ? mirrorSwitcher($line) : $line;
    }
    $mirror = implode("\n", $lines);
    // [`docs/`](./docs) 指向目录本身；./docs/ 下的资源在镜像里深一层，各多退一级。
    $mirror = (string) preg_replace('/\]\(\.\/docs\)/', '](../../)', $mirror);
    $mirror = (string) preg_replace('/(\]\(|src=")\.\/docs\//', '$1../../', $mirror);
    // 指向仓库根目录的目标（不含 ./ ../ / # 与协议前缀）再多退一级。
    $mirror = (string) preg_replace(
        '/(\]\(|src=")(?!\.{1,2}\/|\/|#|[a-z][a-z0-9+.-]*:)([^)"\s]+)/i',
        '$1../../../$2',
        $mirror
    );

    return $mirror;
}

/** 语言切换行：English 加粗，各语言改指 ../<lang>/README.md；镜像就在 docs/i18n/ 下，不再列“全部语言”。 */
function mirrorSwitcher(string $line): string
{
    $mirror = (string) preg_replace('/\[English\]\(README\.md\)/', '**English**', $line);
    $mirror = (string) preg_replace('/\]\(README\.zh-CN\.md\)/', '](../../../README.zh-CN.md)', $mirror);
    $mirror = (string) preg_replace('#\]\(docs/i18n/([a-z-]+)/README\.md\)#', '](../$1/README.md)', $mirror);

    return (string) preg_replace('/ \| \[All translations[^\]]*\]\(docs\/i18n\/README\.md\)/', '', $mirror);
}

/**
 * 行文本 → 出现的行号列表（忽略空行）。
 *
 * @return array<string, list<int>>
 */
function lineIndex(string $markdown): array
{
    $index = [];
    foreach (explode("\n", $markdown) as $i => $line) {
        if (trim($line) !== '') {
            $index[$line][] = $i + 1;
        }
    }

    return $index;
}

/**
 * 逐行比对镜像与期望内容：按“行文本 → 出现次数”取差集，
 * 这样插入/删除一整节也不会把后面所有行都算成差异。
 *
 * @return list<string>
 */
function mirrorProblems(string $mirror, string $expected): array
{
    $expectIndex = lineIndex($expected);
    $mirrorIndex = lineIndex($mirror);
    $problems = [];
    foreach ($expectIndex as $line => $numbers) {
        $surplus = count($numbers) - count($mirrorIndex[$line] ?? []);
        for ($i = 0; $i < $surplus; $i++) {
            $problems[] = sprintf('README.md:%d missing from the mirror: %s', $numbers[$i], excerpt((string) $line));
        }
    }
    foreach ($mirrorIndex as $line => $numbers) {
        $surplus = count($numbers) - count($expectIndex[$line] ?? []);
        for ($i = 0; $i < $surplus; $i++) {
            $problems[] = sprintf('mirror:%d not in README.md: %s', $numbers[$i], excerpt((string) $line));
        }
    }
    if (count($problems) > MAX_LISTED) {
        $more = count($problems) - MAX_LISTED;
        $problems = array_slice($problems, 0, MAX_LISTED);
        $problems[] = sprintf('(+%d more differing lines)', $more);
    }

    return $problems;
}

/**
 * 校验单个语言，返回问题列表（空数组即通过）。
 *
 * @param array<mixed> $enLabels
 * @param string|null $referenceMarkdown 根 README.md 的内容；null 表示不做树/结构比对（英文镜像另按内容逐行比对）
 * @return list<string>
 */
function checkLanguage(string $lang, array $enLabels, ?string $referenceMarkdown): array
{
    $problems = [];
    $file = ROOT . "/docs/i18n/{$lang}/README.md";
    if (!is_file($file)) {
        return ["missing file docs/i18n/{$lang}/README.md"];
    }
    $markdown = (string) file_get_contents($file);

    // 1+2. 结构树与结构计数（英文镜像与根 README 同源，跳过；它按内容逐行比对）
    if ($referenceMarkdown !== null) {
        $expected = treePaths(treeBlock($referenceMarkdown));
        $actual = treePaths(treeBlock($markdown));
        foreach (array_diff($expected, $actual) as $entry) {
            $problems[] = sprintf("missing tree entry '%s'", stripTreeGlyphs($entry));
        }
        foreach (array_diff($actual, $expected) as $entry) {
            $problems[] = sprintf("extra tree entry '%s'", stripTreeGlyphs($entry));
        }

        $rootCounts = structureCounts($referenceMarkdown);
        foreach (structureCounts($markdown) as $what => $count) {
            if ($count !== $rootCounts[$what]) {
                $problems[] = sprintf('%s count %d, README.md has %d', $what, $count, $rootCounts[$what]);
            }
        }
    }

    // 3. 相对链接 / 图片目标
    $broken = [];
    foreach (relativeTargets($markdown) as $target) {
        $path = explode('#', $target)[0];
        if ($path === '' || file_exists(dirname($file) . '/' . $path)) {
            continue;
        }
        $broken[$target] = true;
    }
    if ($broken !== []) {
        $problems[] = 'broken link or image target: ' . brief(array_keys($broken));
    }

    // 4. 锚点
    $slugs = headingSlugs($markdown);
    preg_match_all('/\]\(#([^)\s"]+)/', $markdown, $m);
    $dangling = array_diff(array_unique($m[1]), array_keys($slugs));
    if ($dangling !== []) {
        $problems[] = 'anchor without matching heading: #' . brief($dangling);
    }

    // 5. 词条文件
    $problems = array_merge($problems, checkLabels($lang, $enLabels));

    // 6. 语言切换行
    $head = array_slice(explode("\n", $markdown), 0, SWITCHER_SCAN_LINES);
    $hasSwitcher = false;
    foreach ($head as $line) {
        if (str_contains($line, '**Languages:**')) {
            $hasSwitcher = true;
            break;
        }
    }
    if (!$hasSwitcher) {
        $problems[] = sprintf('no language switcher line (**Languages:**) in the first %d lines', SWITCHER_SCAN_LINES);
    }

    return $problems;
}

/**
 * 校验 docs/i18n/labels/<lang>.json 与 en.json 的一致性。
 *
 * @param array<mixed> $enLabels
 * @return list<string>
 */
function checkLabels(string $lang, array $enLabels): array
{
    $file = ROOT . "/docs/i18n/labels/{$lang}.json";
    if (!is_file($file)) {
        return ["missing file docs/i18n/labels/{$lang}.json"];
    }
    $labels = json_decode((string) file_get_contents($file), true);
    if (!is_array($labels)) {
        return ["docs/i18n/labels/{$lang}.json is not valid JSON"];
    }

    $problems = [];
    $translate = is_array($labels['translate'] ?? null) ? $labels['translate'] : [];
    $enTranslate = is_array($enLabels['translate'] ?? null) ? $enLabels['translate'] : [];

    $missing = array_diff(array_keys($enTranslate), array_keys($translate));
    if ($missing !== []) {
        $problems[] = sprintf('labels missing %d translate keys: %s', count($missing), brief(array_values($missing)));
    }
    $unknown = array_diff(array_keys($translate), array_keys($enTranslate));
    if ($unknown !== []) {
        $problems[] = sprintf('labels have %d unknown translate keys: %s', count($unknown), brief(array_values($unknown)));
    }
    if (($labels['keep'] ?? null) !== ($enLabels['keep'] ?? null)) {
        $problems[] = "labels 'keep' list differs from en.json (must be copied verbatim)";
    }
    $empty = [];
    foreach ($translate as $source => $target) {
        // 非字符串（数字、布尔、null）同样算无效翻译。
        if (!is_string($target) || trim($target) === '') {
            $empty[] = (string) $source;
        }
    }
    if ($empty !== []) {
        $problems[] = sprintf('%d empty translations: %s', count($empty), brief($empty));
    }

    return $problems;
}

$args = array_slice($argv, 1);
foreach ($args as $arg) {
    if (str_starts_with($arg, '--') && $arg !== '--write-en') {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        fwrite(STDERR, "Usage: php scripts/i18n-verify.php [lang …] [--write-en]\n");
        exit(1);
    }
}
$requested = array_values(array_filter($args, static fn (string $arg): bool => !str_starts_with($arg, '--')));

$rootMarkdown = (string) file_get_contents(ROOT . '/' . REFERENCE_README);
if (treeBlock($rootMarkdown) === '') {
    fwrite(STDERR, 'No ```text tree block found in ' . REFERENCE_README . "\n");
    exit(1);
}

if (in_array('--write-en', $args, true)) {
    $mirror = mirrorOf($rootMarkdown);
    file_put_contents(ROOT . '/docs/i18n/' . EN_MIRROR . '/README.md', $mirror);
    printf("regenerated docs/i18n/%s/README.md from %s (%d lines)\n", EN_MIRROR, REFERENCE_README, substr_count($mirror, "\n") + 1);
    exit(0);
}

$known = array_merge([EN_MIRROR], LANGS);
$langs = $requested === [] ? $known : $requested;
$invalid = array_diff($langs, $known);
if ($invalid !== []) {
    fwrite(STDERR, 'Unknown language: ' . implode(', ', $invalid) . "\n");
    fwrite(STDERR, 'Known: ' . implode(' ', $known) . "\n");
    exit(1);
}

$enLabels = json_decode((string) file_get_contents(ROOT . '/docs/i18n/labels/en.json'), true);
if (!is_array($enLabels)) {
    fwrite(STDERR, "docs/i18n/labels/en.json is not valid JSON\n");
    exit(1);
}

$failed = 0;
foreach ($langs as $lang) {
    $isMirror = $lang === EN_MIRROR;
    $problems = checkLanguage($lang, $enLabels, $isMirror ? null : $rootMarkdown);
    if ($isMirror) {
        $mirrorFile = ROOT . '/docs/i18n/' . EN_MIRROR . '/README.md';
        if (is_file($mirrorFile)) {
            $problems = array_merge($problems, mirrorProblems((string) file_get_contents($mirrorFile), mirrorOf($rootMarkdown)));
        }
    }
    if ($problems === []) {
        printf("[OK]   %s\n", $lang);
        continue;
    }
    $failed++;
    printf("[FAIL] %s\n", $lang);
    foreach ($problems as $problem) {
        printf("       - %s\n", $problem);
    }
}

printf("%d README(s) checked, %d failed\n", count($langs), $failed);
exit($failed === 0 ? 0 : 1);
