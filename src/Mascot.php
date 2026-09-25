<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 项目宠物 Locky（小锁）。库自身不依赖它，仅供宿主项目在自己的“关于”页面、
 * CLI 横幅或健康检查里展示；形象源文件为 docs/mascot.svg。
 */
final class Mascot
{
    /** 宠物名 */
    public const NAME = 'Locky';

    /** 相对库根目录的 SVG 位置 */
    private const SVG_FILE = '/docs/mascot.svg';

    private function __construct()
    {
    }

    /**
     * 终端横幅用的 ASCII 版本：纯 ASCII、等宽 17 列，不依赖字体或颜色。
     */
    public static function ascii(): string
    {
        return <<<'ASCII'
             .-------.
             /       \
          .-------------.
          |  o       o  |
          |             |
          |     ,-.     |
          |    (   )    |
          |     '-'     |
          |      |      |
          '-------------'
        ASCII;
    }

    /**
     * SVG 源文件绝对路径。
     */
    public static function path(): string
    {
        return dirname(__DIR__) . self::SVG_FILE;
    }

    /**
     * SVG 内容，可直接内联进 HTML，或自行转成 data URI 使用。
     *
     * @throws EncryptionException 源文件缺失或不可读时
     */
    public static function svg(): string
    {
        $file = self::path();
        if (!is_readable($file)) {
            throw new EncryptionException(sprintf('Mascot SVG is not readable: %s', $file));
        }

        $svg = file_get_contents($file);
        if ($svg === false) {
            throw new EncryptionException(sprintf('Mascot SVG could not be read: %s', $file));
        }

        return $svg;
    }
}
