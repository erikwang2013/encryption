<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Kdf\HkdfSha256;
use PHPUnit\Framework\TestCase;

final class HkdfSha256Test extends TestCase
{
    private const MAX_LEN = 8160;

    public function testMultipleOutputLengths(): void
    {
        $kdf = new HkdfSha256();
        $ikm = random_bytes(32);
        $salt = random_bytes(16);
        foreach ([1, 16, 32, 64, 255] as $len) {
            self::assertSame($len, strlen($kdf->derive($ikm, $salt, $len, 'ctx')), "HKDF length {$len}");
        }
    }

    public function testDeterministic(): void
    {
        $kdf = new HkdfSha256();
        $ikm = random_bytes(32);
        $salt = random_bytes(16);
        self::assertSame($kdf->derive($ikm, $salt, 32, 'ctx'), $kdf->derive($ikm, $salt, 32, 'ctx'));
        // 不同 info / salt / ikm 必须产出不同密钥。
        self::assertNotSame(
            $kdf->derive($ikm, $salt, 32, 'ctx'),
            $kdf->derive($ikm, $salt, 32, 'other-ctx')
        );
    }

    public function testMatchesNativeHashHkdf(): void
    {
        $kdf = new HkdfSha256();
        $ikm = random_bytes(32);
        $salt = random_bytes(16);
        $out = $kdf->derive($ikm, $salt, 48, 'info');
        self::assertSame(hash_hkdf('sha256', $ikm, 48, 'info', $salt), $out);
    }

    public function testNegativeLengthRejected(): void
    {
        $kdf = new HkdfSha256();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('HKDF output length must be positive');
        $kdf->derive(random_bytes(32), random_bytes(16), -1);
    }

    public function testOversizeLengthRejected(): void
    {
        $kdf = new HkdfSha256();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('HKDF failed');
        $kdf->derive(random_bytes(32), random_bytes(16), self::MAX_LEN + 1);
    }

    public function testMaxLengthAccepted(): void
    {
        $kdf = new HkdfSha256();
        self::assertSame(self::MAX_LEN, strlen($kdf->derive(random_bytes(32), random_bytes(16), self::MAX_LEN)));
    }

    public function testCustomIdentifier(): void
    {
        self::assertSame('custom-hkdf', (new HkdfSha256('custom-hkdf'))->getIdentifier());
    }
}
