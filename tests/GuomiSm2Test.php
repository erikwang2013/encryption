<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use CryptoSm\Exception\CryptoException;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Sm2EncryptionService;
use PHPUnit\Framework\TestCase;

final class GuomiSm2Test extends TestCase
{
    public function testRequireGmpThrowsWhenMissing(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp loaded, cannot test missing-gmp path.');
        }
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('SM2 requires ext-gmp.');
        Sm2EncryptionService::requireGmp();
    }

    public function testEncryptThrowsWhenGmpMissing(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp loaded, cannot test missing-gmp path.');
        }
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('SM2 requires ext-gmp.');
        Sm2EncryptionService::encrypt('x', str_repeat('0', 128));
    }

    public function testDecryptThrowsWhenGmpMissing(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp loaded, cannot test missing-gmp path.');
        }
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('SM2 requires ext-gmp.');
        Sm2EncryptionService::decrypt(str_repeat('0', 192), str_repeat('1', 64));
    }

    public function testGenerateKeyPairThrowsWhenGmpMissing(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp loaded, cannot test missing-gmp path.');
        }
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('SM2 requires ext-gmp.');
        Sm2EncryptionService::generateKeyPairHex();
    }

    public function testRoundTripAndKeyStructureWhenGmpAvailable(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded.');
        }
        $pair = Sm2EncryptionService::generateKeyPairHex();
        $priv = $pair->getPrivateKey();
        $pub = $pair->getPublicKey();
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $priv);
        self::assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $pub);

        $plain = 'SM2 国密公钥加密';
        $ct = Sm2EncryptionService::encrypt($plain, $pub);
        // 默认 C1C3C2：C1(128 hex) + C3(64 hex) + C2(2×明文字节数)
        self::assertSame(192 + 2 * strlen($plain), strlen($ct));
        self::assertSame($plain, Sm2EncryptionService::decrypt($ct, $priv));
    }

    public function testGenerateKeyPairDistinctWhenGmpAvailable(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded.');
        }
        $a = Sm2EncryptionService::generateKeyPairHex();
        $b = Sm2EncryptionService::generateKeyPairHex();
        self::assertNotSame($a->getPrivateKey(), $b->getPrivateKey());
        self::assertNotSame($a->getPublicKey(), $b->getPublicKey());
    }

    public function testWrongPrivateKeyNeverYieldsPlaintextWhenGmpAvailable(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded.');
        }
        $pair = Sm2EncryptionService::generateKeyPairHex();
        $other = Sm2EncryptionService::generateKeyPairHex();
        $plain = 'wrong-key attempt';
        $ct = Sm2EncryptionService::encrypt($plain, $pair->getPublicKey());
        try {
            $result = Sm2EncryptionService::decrypt($ct, $other->getPrivateKey());
            self::assertNotSame($plain, $result);
        } catch (CryptoException $e) {
            // vendor 库对 C3 校验失败抛 CryptoException，属于预期的失败路径。
            self::assertStringContainsStringIgnoringCase('fail', $e->getMessage());
        }
    }
}
