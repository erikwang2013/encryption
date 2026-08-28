<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Internal\EncryptThenMacBlob;
use PHPUnit\Framework\TestCase;

final class EncryptThenMacBlobTest extends TestCase
{
    public function testPackLayoutAndMac(): void
    {
        $blob = $this->double()->pack(str_repeat('i', 16), str_repeat('c', 8));
        // v1(2) + IV(16) + MAC(32) + CT(8)
        self::assertSame(58, strlen($blob));
        self::assertSame('v1', substr($blob, 0, 2));
        self::assertSame(str_repeat('i', 16), substr($blob, 2, 16));
        $expectedMac = hash_hmac('sha256', str_repeat('i', 16) . str_repeat('c', 8), $this->macKey(), true);
        self::assertSame($expectedMac, substr($blob, 18, 32));
        self::assertSame(str_repeat('c', 8), substr($blob, 50));
    }

    public function testUnpackReturnsIvMacCt(): void
    {
        $double = $this->double();
        $blob = $double->pack(str_repeat('i', 16), str_repeat('c', 8));
        [$iv, $mac, $ct] = $double->unpack($blob);
        self::assertSame(str_repeat('i', 16), $iv);
        self::assertSame(32, strlen($mac));
        self::assertSame(str_repeat('c', 8), $ct);
    }

    public function testTamperedCiphertextFailsMac(): void
    {
        $double = $this->double();
        $blob = $double->pack(str_repeat('i', 16), str_repeat('c', 8));
        // 翻转最后一个密文字节。
        $tampered = substr($blob, 0, -1) . chr(ord(substr($blob, -1)) ^ 0x01);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Test MAC verification failed.');
        $double->unpack($tampered);
    }

    public function testTamperedMacFails(): void
    {
        $double = $this->double();
        $blob = $double->pack(str_repeat('i', 16), str_repeat('c', 8));
        $tampered = substr($blob, 0, 18) . chr(ord($blob[18]) ^ 0xff) . substr($blob, 19);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Test MAC verification failed.');
        $double->unpack($tampered);
    }

    public function testWrongPrefixFails(): void
    {
        $double = $this->double();
        $blob = $double->pack(str_repeat('i', 16), str_repeat('c', 8));
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid Test ciphertext prefix.');
        $double->unpack('x2' . substr($blob, 2));
    }

    public function testTooShortBlobFails(): void
    {
        $double = $this->double();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Test ciphertext too short.');
        $double->unpack('v1' . str_repeat('a', 10));
    }

    public function testMacDiffersAcrossKeys(): void
    {
        $a = $this->double(random_bytes(32));
        $b = $this->double(random_bytes(32));
        self::assertNotSame(
            $a->pack(str_repeat('i', 16), 'ct'),
            $b->pack(str_repeat('i', 16), 'ct'),
        );
    }

    private function macKey(): string
    {
        return hash_hmac('sha256', $this->key(), 'dgn:enc:hmac', true);
    }

    private function key(): string
    {
        static $key = null;

        return $key ??= random_bytes(32);
    }

    private function double(?string $key = null): EncryptThenMacBlobTestDouble
    {
        return new EncryptThenMacBlobTestDouble($key ?? $this->key());
    }
}

/**
 * 直接使用 EncryptThenMacBlob 的最小测试替身，暴露 pack/unpack 两个私有方法。
 */
final class EncryptThenMacBlobTestDouble
{
    use EncryptThenMacBlob;

    public const PREFIX = 'v1';
    public const IV_LEN = 16;
    public const MAC_LEN = 32;

    public function __construct(private string $key)
    {
    }

    public function pack(string $iv, string $ct): string
    {
        return $this->packWithMac($iv, $ct);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    public function unpack(string $blob): array
    {
        return $this->unpackAndVerify($blob, self::PREFIX);
    }

    protected function label(): string
    {
        return 'Test';
    }
}
