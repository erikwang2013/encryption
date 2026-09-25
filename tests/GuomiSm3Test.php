<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use CryptoSm\SM3\Sm3;
use Erikwang2013\Encryption\Contract\HasherInterface;
use Erikwang2013\Encryption\Guomi\Sm3Hasher;
use PHPUnit\Framework\TestCase;

final class GuomiSm3Test extends TestCase
{
    public function testKatEmptyString(): void
    {
        self::assertSame(
            '1ab21d8355cfa17f8e61194831e81a8f22bec8c728fefb747ed035eb5082aa2b',
            (new Sm3Hasher())->digestHex('')
        );
    }

    public function testKatAbcdRepeated16(): void
    {
        // GB/T 32905-2016 附录 A 向量 2：64 字节输入（'abcd' × 16）。
        self::assertSame(
            'debe9ff92275b8a138604889c18e5a4d6fdb70e5387e5765293dcba39c0c5732',
            (new Sm3Hasher())->digestHex(str_repeat('abcd', 16))
        );
    }

    public function testDigestHexEqualsBin2HexOfDigest(): void
    {
        $h = new Sm3Hasher();
        foreach (['', 'abc', random_bytes(100), "中文 UTF-8 输入"] as $input) {
            self::assertSame($h->digestHex($input), bin2hex($h->digest($input)));
        }
    }

    public function testDigestLowercase(): void
    {
        $hex = (new Sm3Hasher())->digestHex('abc');
        self::assertSame(strtolower($hex), $hex);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hex);
    }

    public function testDigestFixedLengthForAnyInput(): void
    {
        $h = new Sm3Hasher();
        foreach (['', 'a', random_bytes(1), random_bytes(64), random_bytes(4096)] as $input) {
            self::assertSame(32, strlen($h->digest($input)));
            self::assertSame(64, strlen($h->digestHex($input)));
        }
    }

    public function testDigestDeterministicForLongBinaryInput(): void
    {
        $input = random_bytes(4096);
        $h = new Sm3Hasher();
        self::assertSame($h->digest($input), $h->digest($input));
        self::assertSame($h->digestHex($input), $h->digestHex($input));
    }

    public function testNativeAndPurePhpPathsAgree(): void
    {
        // 无论当前走 OpenSSL 原生还是 vendor 纯 PHP，输出必须与 vendor 实现逐字节一致——
        // 这是替换实现后「同一输入同一摘要」的回归保障（含空串与分组边界长度）。
        $h = new Sm3Hasher();
        $inputs = ['', 'abc', str_repeat('a', 55), str_repeat('b', 56), str_repeat('c', 64),
            random_bytes(1000), random_bytes(4096)];
        foreach ($inputs as $input) {
            self::assertSame(
                strtolower(Sm3::hash($input)),
                $h->digestHex($input),
                'SM3 mismatch for input length ' . strlen($input)
            );
        }
    }

    public function testUsesOpenSslWhenAvailable(): void
    {
        if (!Sm3Hasher::nativeAvailable()) {
            self::markTestSkipped('OpenSSL has no sm3 digest; the pure-PHP fallback is in use.');
        }
        self::assertSame(
            bin2hex((string) openssl_digest('abc', 'sm3', true)),
            (new Sm3Hasher())->digestHex('abc')
        );
    }

    public function testLargeInputStaysCheapOnTheNativePath(): void
    {
        if (!Sm3Hasher::nativeAvailable()) {
            self::markTestSkipped('pure-PHP fallback is quadratic by nature.');
        }
        // 原生路径 1 MiB 约 4 MB 内存；旧的纯 PHP 实现同样输入要 ~490 MB。
        $data = random_bytes(262144);
        $before = memory_get_usage();
        (new Sm3Hasher())->digest($data);
        self::assertLessThan(32 * 1024 * 1024, memory_get_peak_usage() - $before);
    }

    public function testImplementsHasherInterface(): void
    {
        self::assertInstanceOf(HasherInterface::class, new Sm3Hasher());
    }

    public function testGetIdentifier(): void
    {
        self::assertSame('sm3', (new Sm3Hasher())->getIdentifier());
        self::assertSame('sm3-custom', (new Sm3Hasher('sm3-custom'))->getIdentifier());
    }
}
