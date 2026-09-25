<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use PHPUnit\Framework\TestCase;

/**
 * 保证 examples/plain-php 的纯 PHP 接入示例始终可运行（无框架、无容器）。
 */
final class PlainPhpExampleTest extends TestCase
{
    private const EXAMPLE_DIR = __DIR__ . '/../examples/plain-php';

    public static function setUpBeforeClass(): void
    {
        putenv('ENCRYPTION_MASTER_KEY=' . base64_encode(random_bytes(32)));
        require_once self::EXAMPLE_DIR . '/bootstrap.php';
    }

    public function testBootstrapExposesHelpers(): void
    {
        self::assertFileExists(self::EXAMPLE_DIR . '/bootstrap.php');
        self::assertTrue(function_exists('encryption_manager'));
        self::assertTrue(function_exists('encrypt_field'));
        self::assertTrue(function_exists('decrypt_field'));
    }

    public function testFieldRoundTripWithBase64Storage(): void
    {
        $stored = encrypt_field('13800000000');

        self::assertNotSame('13800000000', $stored, '字段必须真的被加密');
        self::assertNotFalse(base64_decode($stored, true), '存库值应为合法 base64');
        self::assertSame('13800000000', decrypt_field($stored));
    }

    public function testAnotherAlgorithmThroughTheSameManager(): void
    {
        self::assertSame('13800000000', decrypt_field(encrypt_field('13800000000', 'sm4-cbc'), 'sm4-cbc'));
    }

    public function testTamperedValueIsRejected(): void
    {
        $binary = base64_decode(encrypt_field('13800000000'), true);
        $last = strlen($binary) - 1;
        $binary[$last] = chr(ord($binary[$last]) ^ 0xFF);

        $this->expectException(EncryptionException::class);
        decrypt_field(base64_encode($binary));
    }

    public function testStoredValueThatIsNotBase64IsRejected(): void
    {
        $this->expectException(EncryptionException::class);
        decrypt_field('not base64 ###');
    }

    public function testDemoScriptRunsClean(): void
    {
        if (!function_exists('exec')
            || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)
        ) {
            self::markTestSkipped('exec() is unavailable.');
        }

        $output = [];
        $exitCode = 1;
        exec('php ' . escapeshellarg(self::EXAMPLE_DIR . '/demo.php') . ' 2>&1', $output, $exitCode);

        $text = implode("\n", $output);
        self::assertSame(0, $exitCode, "demo.php 退出码非 0：\n{$text}");
        self::assertStringContainsString('解密还原       : 13800000000', $text);
        self::assertStringContainsString('篡改检测       : 已被拒绝', $text);
    }
}
