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

    /**
     * 逐字路径的参考实现（与 ZucEncryptor::xorKeystream 的取流方式一致）：
     * 每 4 字一次 pack('N4')，末尾不足 4 字节截断补齐。
     */
    private function perWordKeystream(ZucEngine $engine, int $length): string
    {
        $ks = '';
        $full = intdiv($length, 16);
        for ($j = 0; $j < $full; $j++) {
            $ks .= pack('N4', $engine->nextKey(), $engine->nextKey(), $engine->nextKey(), $engine->nextKey());
        }
        for ($j = $full * 16; $j < $length; $j += 4) {
            $ks .= substr(pack('N', $engine->nextKey()), 0, min(4, $length - $j));
        }
        return $ks;
    }

    /** 批量 API 的固定已知答案：key=00×16, iv=00×16 → 前 32 字节密钥流（同官方向量首 8 字）。 */
    public function testEngineKeystreamKatAllZeroKeyAndIv(): void
    {
        $engine = new ZucEngine(str_repeat("\x00", 16), str_repeat("\x00", 16));
        self::assertSame('27bede74018082da87d4e5b69f18bf6632070e0f39b7b692b4673edc3184a48e', bin2hex($engine->keystream(32)));
    }

    /** 批量 API 必须与逐字 nextKey() 路径逐字节一致（含首字丢弃与末字截断）。 */
    public function testEngineKeystreamMatchesPerWordPathForManyLengths(): void
    {
        foreach ([0, 1, 3, 4, 15, 16, 17, 31, 32, 33, 63, 64, 1000, 4096, 65536, 1048576] as $len) {
            $key = random_bytes(16);
            $iv = random_bytes(16);
            $batch = (new ZucEngine($key, $iv))->keystream($len);
            $perWord = $this->perWordKeystream(new ZucEngine($key, $iv), $len);
            self::assertSame($len, strlen($batch), "batch length mismatch for len {$len}");
            self::assertSame(hash('sha256', $perWord), hash('sha256', $batch), "batch keystream diverged at len {$len}");
        }
    }

    /** 批量 API 连续调用（长度均为 4 的倍数）等价于一次生成等长的密钥流。 */
    public function testEngineKeystreamChunkedCallsMatchSingleCall(): void
    {
        $key = random_bytes(16);
        $iv = random_bytes(16);
        $chunked = new ZucEngine($key, $iv);
        $stream = $chunked->keystream(4096) . $chunked->keystream(1000) . $chunked->keystream(4);
        self::assertSame(bin2hex((new ZucEngine($key, $iv))->keystream(5100)), bin2hex($stream));
    }

    /** 批量 API 生成后仍可从同一实例逐字续读（状态回写完整），空长度不消耗密钥流。 */
    public function testEngineKeystreamAndNextKeyInterleaveOnWordBoundary(): void
    {
        $key = random_bytes(16);
        $iv = random_bytes(16);
        $mixed = new ZucEngine($key, $iv);
        self::assertSame('', $mixed->keystream(0));
        $stream = $mixed->keystream(16) . pack('N', $mixed->nextKey()) . pack('N', $mixed->nextKey());
        self::assertSame(bin2hex($this->perWordKeystream(new ZucEngine($key, $iv), 24)), bin2hex($stream));
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

    public function testEncryptorV2MacDerivationRoundTripsAndV1CannotReadIt(): void
    {
        $key = random_bytes(ZucEncryptor::KEY_LEN);
        $v2 = new ZucEncryptor($key, macDerivation: 'v2');
        self::assertSame('zuc v2 payload', $v2->decrypt($v2->encrypt('zuc v2 payload')));
        try {
            (new ZucEncryptor($key))->decrypt($v2->encrypt('payload'));
            self::fail('A v1 ZUC encryptor must not decrypt v2 ciphertext: the MAC keys differ.');
        } catch (EncryptionException $e) {
            self::assertSame('ZUC MAC verification failed.', $e->getMessage());
        }
    }

    public function testEncryptorUnknownMacKeySchemeThrows(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown ZUC MAC key derivation scheme "v3" (expected "v1" or "v2").');
        new ZucEncryptor(random_bytes(ZucEncryptor::KEY_LEN), macDerivation: 'v3');
    }
}
