<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;
use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Sm4CbcEncryptor;

final class GuomiSm4Test extends TestCase
{
    public function testRoundTripUtf8AndLongText(): void
    {
        $e = new Sm4CbcEncryptor(random_bytes(Sm4CbcEncryptor::KEY_LEN));
        foreach (['中文国密 SM4 加密', random_bytes(1024), str_repeat('a', 10000)] as $plain) {
            self::assertSame($plain, $e->decrypt($e->encrypt($plain)));
        }
    }

    public function testWrongKeyDecryptFails(): void
    {
        $key = random_bytes(Sm4CbcEncryptor::KEY_LEN);
        $ct = (new Sm4CbcEncryptor($key))->encrypt('secret payload');
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('SM4 MAC verification failed');
        (new Sm4CbcEncryptor(random_bytes(Sm4CbcEncryptor::KEY_LEN)))->decrypt($ct);
    }

    public function testTamperedCiphertextByteFails(): void
    {
        $e = new Sm4CbcEncryptor(random_bytes(Sm4CbcEncryptor::KEY_LEN));
        $ct = $e->encrypt(str_repeat('x', 64));
        // 翻转密文区最后一个字节（v1|IV|MAC 之后），MAC 校验必须失败。
        $tampered = substr($ct, 0, -1) . chr(ord(substr($ct, -1)) ^ 0x01);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('SM4 MAC verification failed');
        $e->decrypt($tampered);
    }

    public function testCiphertextStructure(): void
    {
        $e = new Sm4CbcEncryptor(random_bytes(Sm4CbcEncryptor::KEY_LEN));
        foreach ([0, 16, 17] as $len) {
            $parts = $this->splitBlob($e->encrypt(str_repeat('p', $len)));
            self::assertSame('v1', $parts['prefix']);
            self::assertSame(16, strlen($parts['iv']));          // IV
            self::assertSame(32, strlen($parts['mac']));         // MAC
            // 密文区为 PKCS5 填充后的 16 字节整数倍
            $expectedCt = 16 * intdiv($len, 16) + 16;
            self::assertSame($expectedCt, strlen($parts['ct']), "structure mismatch for len {$len}");
        }
    }

    public function testBlobIvDrivesRawSm4Decrypt(): void
    {
        // 从打包载荷提取 IV 与密文，用底层 Sm4 独立解密，验证 IV 确实随密文携带。
        $key = random_bytes(Sm4CbcEncryptor::KEY_LEN);
        $e = new Sm4CbcEncryptor($key);
        $plain = 'cross-check plaintext';
        $parts = $this->splitBlob($e->encrypt($plain));
        $options = (new Sm4Options())->setMode(Sm4::MODE_CBC)->setIv(bin2hex($parts['iv']))->setPadding('pkcs5');
        self::assertSame($plain, Sm4::decrypt(bin2hex($parts['ct']), bin2hex($key), $options));
    }

    public function testRandomIvProducesDifferentCiphertext(): void
    {
        $e = new Sm4CbcEncryptor(random_bytes(Sm4CbcEncryptor::KEY_LEN));
        self::assertNotSame($e->encrypt('same plaintext'), $e->encrypt('same plaintext'));
    }

    public function testEmptyPlaintextRoundTrip(): void
    {
        $e = new Sm4CbcEncryptor(random_bytes(Sm4CbcEncryptor::KEY_LEN));
        self::assertSame('', $e->decrypt($e->encrypt('')));
    }

    public function testNativeAndVendorPathsProduceIdenticalCiphertext(): void
    {
        if (!Sm4CbcEncryptor::nativeAvailable()) {
            self::markTestSkipped('OpenSSL has no sm4-cbc; the vendor path is in use.');
        }
        $key = (string) hex2bin('0123456789abcdeffedcba9876543210');
        $iv = (string) hex2bin('000102030405060708090a0b0c0d0e0f');
        $options = (new Sm4Options())
            ->setMode(Sm4::MODE_CBC)
            ->setIv(bin2hex($iv))
            ->setPadding('pkcs5');

        foreach (['', '13800000000', random_bytes(32), random_bytes(1000)] as $plain) {
            $native = openssl_encrypt($plain, 'sm4-cbc', $key, OPENSSL_RAW_DATA, $iv);
            $vendor = hex2bin((string) Sm4::encrypt($plain, bin2hex($key), $options));
            self::assertSame(
                bin2hex((string) $vendor),
                bin2hex((string) $native),
                'SM4 ciphertext differs for input length ' . strlen($plain)
            );
        }
    }

    public function testCiphertextFromTheVendorImplementationStillDecrypts(): void
    {
        // 固化向量：由旧实现（vendor Sm4，CBC/pkcs5）产出的完整载荷 v1 | IV | MAC | 密文，
        // 用来保证切换到 OpenSSL 后历史数据仍可解密。原文 "13800000000"。
        $key = (string) hex2bin('0123456789abcdeffedcba9876543210');
        $blob = (string) hex2bin(
            '7631000102030405060708090a0b0c0d0e0fdc7564cd06143f25df8649f637ff05f6'
            . '41b08e1c3bc975fd68e666ac91df818f9a6beb43162615929289bcc39bc61ce7'
        );

        self::assertSame('13800000000', (new Sm4CbcEncryptor($key))->decrypt($blob));
    }

    public function testImplementsEncryptorInterface(): void
    {
        self::assertInstanceOf(EncryptorInterface::class, new Sm4CbcEncryptor(random_bytes(16)));
    }

    public function testGetIdentifier(): void
    {
        self::assertSame('sm4-cbc', (new Sm4CbcEncryptor(random_bytes(16)))->getIdentifier());
        self::assertSame('sm4-cbc-v2', (new Sm4CbcEncryptor(random_bytes(16), 'sm4-cbc-v2'))->getIdentifier());
    }

    public function testV2MacDerivationRoundTripsAndV1CannotReadIt(): void
    {
        $key = random_bytes(Sm4CbcEncryptor::KEY_LEN);
        $v2 = new Sm4CbcEncryptor($key, macDerivation: 'v2');
        foreach (['中文 v2 迁移', random_bytes(300)] as $plain) {
            self::assertSame($plain, $v2->decrypt($v2->encrypt($plain)));
        }
        try {
            (new Sm4CbcEncryptor($key))->decrypt($v2->encrypt('payload'));
            self::fail('A v1 SM4 encryptor must not decrypt v2 ciphertext: the MAC keys differ.');
        } catch (EncryptionException $e) {
            self::assertSame('SM4 MAC verification failed.', $e->getMessage());
        }
    }

    public function testUnknownMacKeySchemeThrows(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown SM4 MAC key derivation scheme "v3" (expected "v1" or "v2").');
        new Sm4CbcEncryptor(random_bytes(Sm4CbcEncryptor::KEY_LEN), macDerivation: 'v3');
    }
}
