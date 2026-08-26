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
use PHPUnit\Framework\TestCase;

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
            $ct = $e->encrypt(str_repeat('p', $len));
            self::assertSame('v1', substr($ct, 0, 2));
            self::assertSame(16, strlen(substr($ct, 2, 16)));          // IV
            self::assertSame(32, strlen(substr($ct, 18, 32)));         // MAC
            // 密文区为 PKCS5 填充后的 16 字节整数倍：2 + 16 + 32 + 16*ceil((len+1)/16)
            $expectedCt = 16 * intdiv($len, 16) + ($len % 16 === 0 ? 16 : 16);
            self::assertSame(50 + $expectedCt, strlen($ct), "structure mismatch for len {$len}");
        }
    }

    public function testBlobIvDrivesRawSm4Decrypt(): void
    {
        // 从打包载荷提取 IV 与密文，用底层 Sm4 独立解密，验证 IV 确实随密文携带。
        $key = random_bytes(Sm4CbcEncryptor::KEY_LEN);
        $e = new Sm4CbcEncryptor($key);
        $plain = 'cross-check plaintext';
        $blob = $e->encrypt($plain);
        $iv = substr($blob, 2, 16);
        $ct = substr($blob, 50);
        $options = (new Sm4Options())->setMode(Sm4::MODE_CBC)->setIv(bin2hex($iv))->setPadding('pkcs5');
        self::assertSame($plain, Sm4::decrypt(bin2hex($ct), bin2hex($key), $options));
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

    public function testImplementsEncryptorInterface(): void
    {
        self::assertInstanceOf(EncryptorInterface::class, new Sm4CbcEncryptor(random_bytes(16)));
    }

    public function testGetIdentifier(): void
    {
        self::assertSame('sm4-cbc', (new Sm4CbcEncryptor(random_bytes(16)))->getIdentifier());
        self::assertSame('sm4-cbc-v2', (new Sm4CbcEncryptor(random_bytes(16), 'sm4-cbc-v2'))->getIdentifier());
    }
}
