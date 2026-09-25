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
    /** 固化向量用的固定密文密钥（32 字节） */
    private const PINNED_KEY = 'kdf-v2-test-cipher-key-32-bytes!';
    private const PINNED_IV = 'iiiiiiiiiiiiiiii';
    private const PINNED_CT = 'cccccccc';
    /** 固定向量：v1/v2 的 MAC 密钥与载荷 MAC（由当前实现一次性计算后固化） */
    private const V1_MAC_KEY_HEX = 'da2314cd159503521e0e8d17b8d13ba4fccc07e3c787e197c3f3462a0349e0fc';
    private const V2_MAC_KEY_HEX = 'a91d08204cafcdc6e0b96d4776f9a3d01ef6f0300c75023c387c75895a1cf6ec';
    private const V1_BLOB_MAC_HEX = '8456f0974a90cd6d56939eb5031d126e6841fba88add7f114d843b063c3a12ba';
    private const V2_BLOB_MAC_HEX = 'd9dcaaea1e3aa48e862b20046f048857f8a4efa0d31f63feeca4af13f7992f75';

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

    public function testV1MacKeyIsPinnedAndIsTheDefault(): void
    {
        // 不传方案 = v1；固定向量保证默认派生方案永不静默漂移。
        $v1 = $this->double(self::PINNED_KEY);
        self::assertSame(self::V1_MAC_KEY_HEX, $v1->macKeyHex());
        self::assertSame(
            self::V1_BLOB_MAC_HEX,
            bin2hex(substr($v1->pack(self::PINNED_IV, self::PINNED_CT), 18, 32))
        );
    }

    public function testV2MacKeyIsPinnedAndDiffersFromV1(): void
    {
        $v2 = $this->double(self::PINNED_KEY, 'v2');
        self::assertSame(self::V2_MAC_KEY_HEX, $v2->macKeyHex());
        self::assertNotSame(self::V1_MAC_KEY_HEX, $v2->macKeyHex());
        self::assertSame(
            self::V2_BLOB_MAC_HEX,
            bin2hex(substr($v2->pack(self::PINNED_IV, self::PINNED_CT), 18, 32))
        );
        // 确定性：同一密钥 + 同一 (IV, 密文) 两次打包结果一致。
        self::assertSame(
            $v2->pack(self::PINNED_IV, self::PINNED_CT),
            $this->double(self::PINNED_KEY, 'v2')->pack(self::PINNED_IV, self::PINNED_CT)
        );
    }

    public function testV1AndV2BlobsAreMutuallyUnverifiable(): void
    {
        $v1 = $this->double(self::PINNED_KEY);
        $v2 = $this->double(self::PINNED_KEY, 'v2');
        try {
            $v1->unpack($v2->pack(self::PINNED_IV, self::PINNED_CT));
            self::fail('v1 must not verify a v2 blob: the MAC keys differ.');
        } catch (EncryptionException $e) {
            self::assertSame('Test MAC verification failed.', $e->getMessage());
        }
        try {
            $v2->unpack($v1->pack(self::PINNED_IV, self::PINNED_CT));
            self::fail('v2 must not verify a v1 blob: the MAC keys differ.');
        } catch (EncryptionException $e) {
            self::assertSame('Test MAC verification failed.', $e->getMessage());
        }
    }

    public function testUnknownMacKeySchemeThrows(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown Test MAC key derivation scheme "v3" (expected "v1" or "v2").');
        $this->double(self::PINNED_KEY, 'v3');
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

    private function double(?string $key = null, string $macDerivation = 'v1'): EncryptThenMacBlobTestDouble
    {
        return new EncryptThenMacBlobTestDouble($key ?? $this->key(), $macDerivation);
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

    public function __construct(private string $key, private string $macDerivation = 'v1')
    {
        $this->assertMacKeyScheme($this->macDerivation);
    }

    protected function macKeyScheme(): string
    {
        return $this->macDerivation;
    }

    public function macKeyHex(): string
    {
        return bin2hex($this->macKey());
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
