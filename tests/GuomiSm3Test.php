<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

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

    public function testDigestHexMatchesOpenSslWhenAvailable(): void
    {
        if (!in_array('sm3', hash_algos(), true)) {
            self::markTestSkipped('ext-openssl has no sm3 digest');
        }
        $h = new Sm3Hasher();
        foreach (['', 'abc', str_repeat('x', 1000), "国密 密码杂凑\x00\xff"] as $input) {
            self::assertSame(hash('sm3', $input), $h->digestHex($input), "mismatch for input len " . strlen($input));
        }
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
        $input = random_bytes(65536);
        $h = new Sm3Hasher();
        self::assertSame($h->digest($input), $h->digest($input));
        self::assertSame($h->digestHex($input), $h->digestHex($input));
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
