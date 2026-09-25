<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Mascot;
use PHPUnit\Framework\TestCase;

final class MascotTest extends TestCase
{
    public function testNameIsStable(): void
    {
        self::assertSame('Locky', Mascot::NAME);
    }

    public function testSvgIsReadableAndWellFormed(): void
    {
        self::assertFileExists(Mascot::path());

        $svg = Mascot::svg();
        self::assertStringStartsWith('<svg', $svg);
        self::assertStringContainsString('viewBox="0 0 240 240"', $svg);
        self::assertStringEndsWith("</svg>\n", $svg);
    }

    public function testAsciiIsPlainTerminalBlock(): void
    {
        $ascii = Mascot::ascii();
        self::assertGreaterThanOrEqual(5, count(explode("\n", rtrim($ascii))));
        // 纯 ASCII 且不含制表符：不依赖字体、编码与终端宽度设置。
        self::assertSame('', preg_replace('/[\x20-\x7e\n]/', '', $ascii));
        self::assertStringNotContainsString("\t", $ascii);
    }
}
