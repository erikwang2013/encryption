<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Internal\ZucEngine;
use Erikwang2013\Encryption\Guomi\ZucEncryptor;

final class GuomiZucTest extends TestCase
{
    private function eightWords(ZucEngine $engine): string
    {
        $stream = '';
        for ($i = 0; $i < 8; $i++) {
            $stream .= pack('N', $engine->nextKey());
        }
        return $stream;
    }

    /** 官方/BC 参考向量：key=00×16, iv=00×16 的前 8 个密钥字。 */
    public function testEngineKatAllZeroKeyAndIvEightWords(): void
    {
        $engine = new ZucEngine(str_repeat("\x00", 16), str_repeat("\x00", 16));
        self::assertSame('27bede74018082da87d4e5b69f18bf6632070e0f39b7b692b4673edc3184a48e', bin2hex($this->eightWords($engine)));
    }

    /** 官方/BC 参考向量：key=ff×16, iv=ff×16 的前 8 个密钥字。 */
    public function testEngineKatAllOneKeyAndIvEightWords(): void
    {
        $engine = new ZucEngine(str_repeat("\xff", 16), str_repeat("\xff", 16));
        self::assertSame('0657cfa07096398b734b6cb4883eedf4257a76eb97595208d884adcdb1cbffb8', bin2hex($this->eightWords($engine)));
    }

    public function testEngineDeterministicAcrossInstances(): void
    {
        $key = random_bytes(16);
        $iv = random_bytes(16);
        $a = new ZucEngine($key, $iv);
        $b = new ZucEngine($key, $iv);
        for ($i = 0; $i < 32; $i++) {
            self::assertSame($a->nextKey(), $b->nextKey(), "keystream diverged at word {$i}");
        }
    }

    public function testEngineDifferentIvProducesDifferentStream(): void
    {
        $key = random_bytes(16);
        $a = new ZucEngine($key, str_repeat("\x00", 16));
        $b = new ZucEngine($key, str_repeat("\x01", 16));
        self::assertNotSame($this->eightWords($a), $this->eightWords($b));
    }

    public function testEngineNextKeyIsUint32(): void
    {
        $engine = new ZucEngine(random_bytes(16), random_bytes(16));
        for ($i = 0; $i < 1000; $i++) {
            $word = $engine->nextKey();
            self::assertGreaterThanOrEqual(0, $word);
            self::assertLessThanOrEqual(0xFFFFFFFF, $word);
        }
    }

    public function testEngineRejectsWrongKeyLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ZUC key and IV must be 16 bytes each.');
        new ZucEngine(str_repeat("\x00", 15), str_repeat("\x00", 16));
    }

    public function testEngineRejectsWrongIvLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ZucEngine(str_repeat("\x00", 16), str_repeat("\x00", 17));
    }

    public function testEncryptorRoundTripUtf8AndLongText(): void
    {
        $z = new ZucEncryptor(random_bytes(ZucEncryptor::KEY_LEN));
        foreach (['中文 ZUC 流密码', random_bytes(1024), str_repeat('x', 10000)] as $plain) {
            self::assertSame($plain, $z->decrypt($z->encrypt($plain)));
        }
    }

    public function testEncryptorEmptyPlaintextRoundTrip(): void
    {
        $z = new ZucEncryptor(random_bytes(ZucEncryptor::KEY_LEN));
        self::assertSame('', $z->decrypt($z->encrypt('')));
    }

    public function testEncryptorWrongKeyDecryptFails(): void
    {
        $key = random_bytes(ZucEncryptor::KEY_LEN);
        $ct = (new ZucEncryptor($key))->encrypt('secret');
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('ZUC MAC verification failed');
        (new ZucEncryptor(random_bytes(ZucEncryptor::KEY_LEN)))->decrypt($ct);
    }

    public function testEncryptorTamperedCiphertextByteFails(): void
    {
        $z = new ZucEncryptor(random_bytes(ZucEncryptor::KEY_LEN));
        $ct = $z->encrypt(str_repeat('y', 100));
        $tampered = substr($ct, 0, -1) . chr(ord(substr($ct, -1)) ^ 0x01);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('ZUC MAC verification failed');
        $z->decrypt($tampered);
    }

    public function testEncryptorCiphertextStructure(): void
    {
        $z = new ZucEncryptor(random_bytes(ZucEncryptor::KEY_LEN));
        foreach ([0, 1, 100] as $len) {
            $parts = $this->splitBlob($z->encrypt(str_repeat('p', $len)));
            self::assertSame('v1', $parts['prefix']);
            self::assertSame(16, strlen($parts['iv']));   // IV
            self::assertSame(32, strlen($parts['mac']));  // MAC
            // 流密码：密文区与明文等长
            self::assertSame($len, strlen($parts['ct']), "structure mismatch for len {$len}");
        }
    }

    public function testEncryptorRandomIvProducesDifferentCiphertext(): void
    {
        $z = new ZucEncryptor(random_bytes(ZucEncryptor::KEY_LEN));
        self::assertNotSame($z->encrypt('same'), $z->encrypt('same'));
    }

    public function testEncryptorImplementsEncryptorInterface(): void
    {
        self::assertInstanceOf(EncryptorInterface::class, new ZucEncryptor(random_bytes(16)));
    }

    public function testEncryptorGetIdentifier(): void
    {
        self::assertSame('zuc-128', (new ZucEncryptor(random_bytes(16)))->getIdentifier());
        self::assertSame('zuc-128-v2', (new ZucEncryptor(random_bytes(16), 'zuc-128-v2'))->getIdentifier());
    }
}
