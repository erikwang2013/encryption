<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Sm2EncryptionService;
use Erikwang2013\Encryption\Guomi\Sm3Hasher;
use Erikwang2013\Encryption\Guomi\Sm4CbcEncryptor;
use Erikwang2013\Encryption\Guomi\UnavailableNationalAlgorithms;
use Erikwang2013\Encryption\Guomi\ZucEncryptor;
use PHPUnit\Framework\TestCase;

final class GuomiAlgorithmsTest extends TestCase
{
    public function testSm3DigestLength(): void
    {
        $h = new Sm3Hasher();
        self::assertSame(32, strlen($h->digest('abc')));
        self::assertSame(64, strlen($h->digestHex('abc')));
    }

    public function testSm3Deterministic(): void
    {
        $h = new Sm3Hasher();
        self::assertSame($h->digest('abc'), $h->digest('abc'));
        self::assertSame($h->digestHex('abc'), $h->digestHex('abc'));
    }

    public function testSm4CbcRoundTrip(): void
    {
        $key = random_bytes(Sm4CbcEncryptor::KEY_LEN);
        $e = new Sm4CbcEncryptor($key);
        $plain = '国密 SM4';
        self::assertSame($plain, $e->decrypt($e->encrypt($plain)));
    }

    public function testSm4CbcWrongKeyLength(): void
    {
        $this->expectException(EncryptionException::class);
        new Sm4CbcEncryptor(random_bytes(32));
    }

    public function testSm4CbcBadPrefix(): void
    {
        $key = random_bytes(Sm4CbcEncryptor::KEY_LEN);
        $e = new Sm4CbcEncryptor($key);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid SM4 ciphertext prefix');
        $e->decrypt('bad');
    }

    public function testSm4CbcTruncated(): void
    {
        $key = random_bytes(Sm4CbcEncryptor::KEY_LEN);
        $e = new Sm4CbcEncryptor($key);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('SM4 ciphertext too short');
        $e->decrypt('v1' . random_bytes(5));
    }

    public function testSm4CbcTamperedMac(): void
    {
        $key = random_bytes(Sm4CbcEncryptor::KEY_LEN);
        $e = new Sm4CbcEncryptor($key);
        $ct = $e->encrypt('plaintext');
        $tampered = substr($ct, 0, 3) . chr(ord($ct[3]) ^ 0xff) . substr($ct, 4);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('SM4 MAC verification failed');
        $e->decrypt($tampered);
    }

    public function testZucRoundTrip(): void
    {
        $key = random_bytes(ZucEncryptor::KEY_LEN);
        $z = new ZucEncryptor($key);
        $plain = 'ZUC stream';
        self::assertSame($plain, $z->decrypt($z->encrypt($plain)));
    }

    public function testZucWrongKeyLength(): void
    {
        $this->expectException(EncryptionException::class);
        new ZucEncryptor(random_bytes(32));
    }

    public function testZucBadPrefix(): void
    {
        $key = random_bytes(ZucEncryptor::KEY_LEN);
        $z = new ZucEncryptor($key);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid ZUC ciphertext prefix');
        $z->decrypt('bad');
    }

    public function testZucTruncated(): void
    {
        $key = random_bytes(ZucEncryptor::KEY_LEN);
        $z = new ZucEncryptor($key);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('ZUC ciphertext too short');
        $z->decrypt('v1' . random_bytes(5));
    }

    public function testZucTamperedMac(): void
    {
        $key = random_bytes(ZucEncryptor::KEY_LEN);
        $z = new ZucEncryptor($key);
        $ct = $z->encrypt('plaintext');
        $tampered = substr($ct, 0, 3) . chr(ord($ct[3]) ^ 0xff) . substr($ct, 4);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('ZUC MAC verification failed');
        $z->decrypt($tampered);
    }

    public function testSm2RoundTripWhenGmpAvailable(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded');
        }
        $kp = Sm2EncryptionService::generateKeyPairHex();
        $plain = 'sm2';
        $ct = Sm2EncryptionService::encrypt($plain, $kp->getPublicKey());
        self::assertSame($plain, Sm2EncryptionService::decrypt($ct, $kp->getPrivateKey()));
    }

    public function testSm2RequireGmpMessageIsEnglish(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp loaded, cannot test missing-gmp message.');
        }
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('SM2 requires ext-gmp');
        Sm2EncryptionService::requireGmp();
    }

    public function testUnavailableAlgorithmsThrow(): void
    {
        $this->expectException(EncryptionException::class);
        UnavailableNationalAlgorithms::sm1();
    }
}
