<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Internal\ZucEngine;
use Erikwang2013\Encryption\Guomi\Sm2EncryptionService;
use Erikwang2013\Encryption\Guomi\Sm3Hasher;
use Erikwang2013\Encryption\Guomi\Sm4CbcEncryptor;
use Erikwang2013\Encryption\Guomi\UnavailableNationalAlgorithms;
use Erikwang2013\Encryption\Guomi\ZucEncryptor;

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

    public function testSm3KatVector(): void
    {
        $expected = '66c7f0f462eeedd9d1f2d46bdc10e4e24167c4875cf2f7a2297da02b8f4ba8e0';
        $h = new Sm3Hasher();
        self::assertSame($expected, $h->digestHex('abc'));
        self::assertSame($expected, bin2hex($h->digest('abc')));
        if (in_array('sm3', hash_algos(), true)) {
            self::assertSame($expected, hash('sm3', 'abc'));
        }
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

    public function testSm4EcbGbTKatVector(): void
    {
        // GB/T 32907-2016 公开样例：key/plaintext 各 16 字节，ECB 单块无填充。
        $key = '0123456789abcdeffedcba9876543210';
        $plain = hex2bin('0123456789abcdeffedcba9876543210');
        $options = (new Sm4Options())->setPadding('none');
        self::assertSame('681edf34d206965e86b3e94f536e4246', Sm4::encrypt($plain, $key, $options));
        self::assertSame($plain, Sm4::decrypt('681edf34d206965e86b3e94f536e4246', $key, $options));
    }

    public function testSm4CbcBoundarySizes(): void
    {
        $e = new Sm4CbcEncryptor(random_bytes(Sm4CbcEncryptor::KEY_LEN));
        foreach ([0, 1, 15, 16, 17, 31, 32] as $len) {
            $plain = $len === 0 ? '' : random_bytes($len);
            self::assertSame($plain, $e->decrypt($e->encrypt($plain)), "SM4-CBC round trip failed for {$len} bytes");
        }
    }

    public function testZucRoundTrip(): void
    {
        $key = random_bytes(ZucEncryptor::KEY_LEN);
        $z = new ZucEncryptor($key);
        $plain = 'ZUC stream';
        self::assertSame($plain, $z->decrypt($z->encrypt($plain)));
    }

    public function testZucEngineKatAllZeroKeyAndIv(): void
    {
        $engine = new ZucEngine(str_repeat("\x00", 16), str_repeat("\x00", 16));
        $keystream = pack('N', $engine->nextKey()) . pack('N', $engine->nextKey());
        self::assertSame('27bede74018082da', bin2hex($keystream));
    }

    public function testZucEngineKatAllOneKeyAndIv(): void
    {
        $engine = new ZucEngine(str_repeat("\xff", 16), str_repeat("\xff", 16));
        $keystream = pack('N', $engine->nextKey()) . pack('N', $engine->nextKey());
        self::assertSame('0657cfa07096398b', bin2hex($keystream));
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

    public function testZucBoundarySizes(): void
    {
        $z = new ZucEncryptor(random_bytes(ZucEncryptor::KEY_LEN));
        foreach ([0, 1, 15, 16, 17, 31, 32] as $len) {
            $plain = $len === 0 ? '' : random_bytes($len);
            self::assertSame($plain, $z->decrypt($z->encrypt($plain)), "ZUC round trip failed for {$len} bytes");
        }
    }

    public function testSm2RoundTripWhenGmpAvailable(): void
    {
        $this->skipWithoutGmp();
        $kp = Sm2EncryptionService::generateKeyPairHex();
        $plain = 'sm2';
        $ct = Sm2EncryptionService::encrypt($plain, $kp->getPublicKey());
        self::assertSame($plain, Sm2EncryptionService::decrypt($ct, $kp->getPrivateKey()));
    }

    public function testSm2RequireGmpMessageIsEnglish(): void
    {
        $this->skipIfGmpLoaded();
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
