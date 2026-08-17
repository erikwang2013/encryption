<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Asymmetric\Sm2AsymmetricCipher;
use Erikwang2013\Encryption\AsymmetricCipherRegistry;
use Erikwang2013\Encryption\AsymmetricCryptoManager;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Sm2EncryptionService;
use Erikwang2013\Encryption\Guomi\Sm3Hasher;
use Erikwang2013\Encryption\Hash\Sha256Hasher;
use Erikwang2013\Encryption\HashingManager;
use Erikwang2013\Encryption\HasherRegistry;
use Erikwang2013\Encryption\Kdf\HkdfSha256;
use Erikwang2013\Encryption\Kdf\Pbkdf2Sha256;
use Erikwang2013\Encryption\KeyDerivationManager;
use Erikwang2013\Encryption\KeyDerivationRegistry;
use Erikwang2013\Encryption\PasswordBasedKdfManager;
use Erikwang2013\Encryption\PasswordBasedKdfRegistry;
use PHPUnit\Framework\TestCase;

final class CryptoPrimitivesTest extends TestCase
{
    public function testSha256Hasher(): void
    {
        $h = new Sha256Hasher();
        self::assertSame(32, strlen($h->digest('x')));
        self::assertSame(64, strlen($h->digestHex('x')));
    }

    public function testHashingManager(): void
    {
        $mgr = new HashingManager(new HasherRegistry(new Sha256Hasher()), 'sha256');
        $d = $mgr->digestHex('abc');
        self::assertSame(64, strlen($d));
    }

    public function testHashingManagerSetDefaultIdentifier(): void
    {
        $registry = new HasherRegistry(new Sha256Hasher(), new Sm3Hasher());
        $mgr = new HashingManager($registry, 'sha256');
        $mgr->setDefaultIdentifier('sm3');
        self::assertSame('sm3', $mgr->getDefaultIdentifier());
        // 默认切到 SM3 后，digestHex('abc') 必须等于 SM3 KAT 向量而非 SHA-256。
        self::assertSame(
            '66c7f0f462eeedd9d1f2d46bdc10e4e24167c4875cf2f7a2297da02b8f4ba8e0',
            $mgr->digestHex('abc')
        );
    }

    public function testHasherRegistryDuplicateThrows(): void
    {
        $registry = new HasherRegistry(new Sha256Hasher());
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('already registered');
        $registry->register(new Sha256Hasher());
    }

    public function testHkdf(): void
    {
        $kdf = new HkdfSha256();
        $ikm = random_bytes(32);
        $salt = random_bytes(16);
        $key = $kdf->derive($ikm, $salt, 32, 'ctx');
        self::assertSame(32, strlen($key));
        $mgr = new KeyDerivationManager(new KeyDerivationRegistry($kdf), 'hkdf-sha256');
        self::assertSame(16, strlen($mgr->derive($ikm, $salt, 16, 'ctx')));
    }

    public function testHkdfZeroLength(): void
    {
        $kdf = new HkdfSha256();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('HKDF output length must be positive');
        $kdf->derive(random_bytes(32), random_bytes(16), 0);
    }

    public function testHkdfEmptySalt(): void
    {
        $kdf = new HkdfSha256();
        $key = $kdf->derive(random_bytes(32), '', 32, 'ctx');
        self::assertSame(32, strlen($key));
    }

    public function testPbkdf2(): void
    {
        $kdf = new Pbkdf2Sha256(1000);
        $out = $kdf->deriveFromPassword('secret', random_bytes(16), 32);
        self::assertSame(32, strlen($out));
        $mgr = new PasswordBasedKdfManager(new PasswordBasedKdfRegistry($kdf), 'pbkdf2-sha256');
        self::assertSame(32, strlen($mgr->deriveFromPassword('secret', random_bytes(16), 32)));
    }

    public function testKeyDerivationManagerSetDefaultIdentifier(): void
    {
        $registry = new KeyDerivationRegistry(new HkdfSha256('hkdf-sha256'), new HkdfSha256('hkdf-sha256-v2'));
        $mgr = new KeyDerivationManager($registry, 'hkdf-sha256');
        $mgr->setDefaultIdentifier('hkdf-sha256-v2');
        self::assertSame('hkdf-sha256-v2', $mgr->getDefaultIdentifier());
        self::assertSame('hkdf-sha256-v2', $mgr->defaultKdf()->getIdentifier());
        self::assertSame(16, strlen($mgr->derive(random_bytes(32), random_bytes(16), 16)));
    }

    public function testPasswordBasedKdfManagerSetDefaultIdentifier(): void
    {
        $registry = new PasswordBasedKdfRegistry(
            new Pbkdf2Sha256(1000, 'pbkdf2-sha256'),
            new Pbkdf2Sha256(2000, 'pbkdf2-sha256-strong')
        );
        $mgr = new PasswordBasedKdfManager($registry, 'pbkdf2-sha256');
        $expected = $mgr->deriveFromPassword('secret', 'saltsalt', 32, 'pbkdf2-sha256-strong');
        $mgr->setDefaultIdentifier('pbkdf2-sha256-strong');
        self::assertSame('pbkdf2-sha256-strong', $mgr->getDefaultIdentifier());
        self::assertSame('pbkdf2-sha256-strong', $mgr->defaultKdf()->getIdentifier());
        // 迭代次数不同则派生结果不同，输出相等即证明默认路由真实切换。
        self::assertSame($expected, $mgr->deriveFromPassword('secret', 'saltsalt', 32));
    }

    public function testPbkdf2EmptySalt(): void
    {
        $kdf = new Pbkdf2Sha256(1000);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('PBKDF2 salt must not be empty');
        $kdf->deriveFromPassword('secret', '', 32);
    }

    public function testPbkdf2ZeroLength(): void
    {
        $kdf = new Pbkdf2Sha256(1000);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('PBKDF2 output length must be positive');
        $kdf->deriveFromPassword('secret', random_bytes(16), 0);
    }

    public function testSm2AsymmetricRoundTripWhenGmpAvailable(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded');
        }
        $pair = Sm2EncryptionService::generateKeyPairHex();
        $cipher = new Sm2AsymmetricCipher();
        $plain = 'asymmetric-sm2';
        $ct = $cipher->encrypt($plain, $pair->getPublicKey());
        self::assertSame($plain, $cipher->decrypt($ct, $pair->getPrivateKey()));

        $mgr = new AsymmetricCryptoManager(new AsymmetricCipherRegistry($cipher), 'sm2');
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain, $pair->getPublicKey()), $pair->getPrivateKey()));
    }

    public function testAsymmetricCryptoManagerSetDefaultIdentifier(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded');
        }
        $registry = new AsymmetricCipherRegistry(new Sm2AsymmetricCipher('sm2'), new Sm2AsymmetricCipher('sm2-v2'));
        $mgr = new AsymmetricCryptoManager($registry, 'sm2');
        $pair = Sm2EncryptionService::generateKeyPairHex();
        $mgr->setDefaultIdentifier('sm2-v2');
        self::assertSame('sm2-v2', $mgr->getDefaultIdentifier());
        $plain = 'asymmetric-switch';
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain, $pair->getPublicKey()), $pair->getPrivateKey()));
    }

    public function testSm2AsymmetricCipherRejectsInvalidPrivateKey(): void
    {
        // 私有钥校验发生在 gmp 调用之前，无需 ext-gmp 即可验证。
        $cipher = new Sm2AsymmetricCipher();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid SM2 private key.');
        $cipher->decrypt('v1someciphertext', 'not-a-valid-key');
    }

    public function testAsymmetricRegistryDuplicateThrows(): void
    {
        $cipher = new Sm2AsymmetricCipher();
        $registry = new AsymmetricCipherRegistry($cipher);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('already registered');
        $registry->register(new Sm2AsymmetricCipher());
    }

    public function testKeyDerivationRegistryDuplicateThrows(): void
    {
        $kdf = new HkdfSha256();
        $registry = new KeyDerivationRegistry($kdf);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('already registered');
        $registry->register(new HkdfSha256());
    }

    public function testKeyDerivationRegistryUnknownMessagePreservesCase(): void
    {
        $registry = new KeyDerivationRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown KDF: nope');
        $registry->get('nope');
    }

    public function testPasswordKdfRegistryDuplicateThrows(): void
    {
        $kdf = new Pbkdf2Sha256(1000);
        $registry = new PasswordBasedKdfRegistry($kdf);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('already registered');
        $registry->register(new Pbkdf2Sha256(1000));
    }
}
