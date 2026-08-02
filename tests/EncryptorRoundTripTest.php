<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Encryptor\Aes256GcmEncryptor;
use Erikwang2013\Encryption\Encryptor\OpenSslAes256CbcEncryptor;
use Erikwang2013\Encryption\Encryptor\SodiumXChaCha20Encryptor;
use Erikwang2013\Encryption\EncryptionManager;
use Erikwang2013\Encryption\EncryptionManagerFactory;
use Erikwang2013\Encryption\EncryptorRegistry;
use Erikwang2013\Encryption\Exception\EncryptionException;
use PHPUnit\Framework\TestCase;

final class EncryptorRoundTripTest extends TestCase
{
    public function testAes256GcmRoundTrip(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $e = new Aes256GcmEncryptor($key);
        $plain = 'hello 世界';
        self::assertSame($plain, $e->decrypt($e->encrypt($plain)));
    }

    public function testAes256GcmWrongKeyLength(): void
    {
        $this->expectException(EncryptionException::class);
        new Aes256GcmEncryptor(random_bytes(16));
    }

    public function testAes256GcmBadPrefix(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $e = new Aes256GcmEncryptor($key);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid ciphertext prefix');
        $e->decrypt('bad');
    }

    public function testAes256GcmTruncated(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $e = new Aes256GcmEncryptor($key);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Ciphertext too short');
        $e->decrypt('v1' . random_bytes(5));
    }

    public function testAes256GcmTampered(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $e = new Aes256GcmEncryptor($key);
        $ct = $e->encrypt('plaintext');
        $tampered = substr($ct, 0, 3) . chr(ord($ct[3]) ^ 0xff) . substr($ct, 4);
        $this->expectException(EncryptionException::class);
        $e->decrypt($tampered);
    }

    public function testAes256CbcRoundTrip(): void
    {
        $key = random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN);
        $e = new OpenSslAes256CbcEncryptor($key);
        $plain = 'cbc payload';
        self::assertSame($plain, $e->decrypt($e->encrypt($plain)));
    }

    public function testAes256CbcWrongKeyLength(): void
    {
        $this->expectException(EncryptionException::class);
        new OpenSslAes256CbcEncryptor(random_bytes(16));
    }

    public function testAes256CbcBadPrefix(): void
    {
        $key = random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN);
        $e = new OpenSslAes256CbcEncryptor($key);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid ciphertext prefix');
        $e->decrypt('bad');
    }

    public function testAes256CbcTamperedMac(): void
    {
        $key = random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN);
        $e = new OpenSslAes256CbcEncryptor($key);
        $ct = $e->encrypt('plaintext');
        $tampered = substr($ct, 0, 3) . chr(ord($ct[3]) ^ 0xff) . substr($ct, 4);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('MAC verification failed');
        $e->decrypt($tampered);
    }

    public function testSodiumRoundTripWhenAvailable(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium not loaded');
        }
        $key = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $e = new SodiumXChaCha20Encryptor($key);
        $plain = 'sodium test';
        self::assertSame($plain, $e->decrypt($e->encrypt($plain)));
    }

    public function testSodiumWrongKeyLength(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium not loaded');
        }
        $this->expectException(EncryptionException::class);
        new SodiumXChaCha20Encryptor(random_bytes(16));
    }

    public function testSodiumBadPrefix(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium not loaded');
        }
        $key = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $e = new SodiumXChaCha20Encryptor($key);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid ciphertext prefix');
        $e->decrypt('bad');
    }

    public function testManagerUsesRegistry(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $gcm = new Aes256GcmEncryptor($key);
        $registry = new EncryptorRegistry($gcm);
        $mgr = new EncryptionManager($registry, 'aes-256-gcm');
        $plain = 'mgr';
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain)));
    }

    public function testManagerSetDefaultIdentifier(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $registry = new EncryptorRegistry(new Aes256GcmEncryptor($key));
        $mgr = new EncryptionManager($registry, 'aes-256-gcm');
        self::assertSame('aes-256-gcm', $mgr->getDefaultIdentifier());
    }

    public function testManagerSetDefaultIdentifierToUnknown(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $registry = new EncryptorRegistry(new Aes256GcmEncryptor($key));
        $mgr = new EncryptionManager($registry, 'aes-256-gcm');
        $this->expectException(EncryptionException::class);
        $mgr->setDefaultIdentifier('nonexistent');
    }

    public function testRegistryDuplicateThrows(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $registry = new EncryptorRegistry(new Aes256GcmEncryptor($key));
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('already registered');
        $registry->register(new Aes256GcmEncryptor($key));
    }

    public function testRegistryEmptyIdentifier(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $registry = new EncryptorRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('must not be empty');
        $registry->register(new Aes256GcmEncryptor($key, ''));
    }

    public function testFactoryMasterKey(): void
    {
        $master = random_bytes(32);
        $mgr = EncryptionManagerFactory::fromMasterKey($master, 'aes-256-gcm');
        $plain = 'factory';
        $ct = $mgr->encrypt($plain);
        self::assertSame($plain, $mgr->decrypt($ct));
    }

    public function testFactoryRegistersSm4(): void
    {
        $master = random_bytes(32);
        $mgr = EncryptionManagerFactory::fromMasterKey($master, 'aes-256-gcm');
        self::assertTrue($mgr->registry()->has('sm4-cbc'));
    }

    public function testFactoryRegistersZuc(): void
    {
        $master = random_bytes(32);
        $mgr = EncryptionManagerFactory::fromMasterKey($master, 'aes-256-gcm');
        self::assertTrue($mgr->registry()->has('zuc-128'));
    }

    public function testFactorySm4RoundTrip(): void
    {
        $master = random_bytes(32);
        $mgr = EncryptionManagerFactory::fromMasterKey($master, 'aes-256-gcm');
        $plain = 'factory-sm4';
        $ct = $mgr->encrypt($plain, 'sm4-cbc');
        self::assertSame($plain, $mgr->decrypt($ct, 'sm4-cbc'));
    }

    public function testFactoryZucRoundTrip(): void
    {
        $master = random_bytes(32);
        $mgr = EncryptionManagerFactory::fromMasterKey($master, 'aes-256-gcm');
        $plain = 'factory-zuc';
        $ct = $mgr->encrypt($plain, 'zuc-128');
        self::assertSame($plain, $mgr->decrypt($ct, 'zuc-128'));
    }
}
