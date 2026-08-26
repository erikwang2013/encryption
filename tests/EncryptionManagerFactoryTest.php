<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\EncryptionManager;
use Erikwang2013\Encryption\EncryptionManagerFactory;
use Erikwang2013\Encryption\Exception\EncryptionException;
use PHPUnit\Framework\TestCase;

final class EncryptionManagerFactoryTest extends TestCase
{
    public function testDefaultConfigurationUsesAes256Gcm(): void
    {
        $mgr = EncryptionManagerFactory::fromMasterKey(random_bytes(32));
        self::assertInstanceOf(EncryptionManager::class, $mgr);
        self::assertSame('aes-256-gcm', $mgr->getDefaultIdentifier());
        $plain = 'factory-default';
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain)));
    }

    public function testRegistersAllBuiltInAlgorithms(): void
    {
        $mgr = EncryptionManagerFactory::fromMasterKey(random_bytes(32));
        foreach (['aes-256-gcm', 'aes-256-cbc-hmac', 'sm4-cbc', 'zuc-128'] as $id) {
            self::assertTrue($mgr->registry()->has($id), "expected {$id} to be registered");
        }
        if (extension_loaded('sodium')) {
            self::assertTrue($mgr->registry()->has('sodium-xchacha20'));
        }
    }

    public function testEveryRegisteredAlgorithmRoundTrips(): void
    {
        $mgr = EncryptionManagerFactory::fromMasterKey(random_bytes(32));
        $plain = 'factory-all-algorithms';
        foreach ($mgr->registry()->identifiers() as $id) {
            self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain, $id), $id), "round trip failed for {$id}");
        }
    }

    public function testSodiumCanBeDefaultWhenLoaded(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium not loaded');
        }
        $mgr = EncryptionManagerFactory::fromMasterKey(random_bytes(32), 'sodium-xchacha20');
        self::assertSame('sodium-xchacha20', $mgr->getDefaultIdentifier());
        $plain = 'factory-sodium-default';
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain)));
    }

    public function testAlgorithmsUseIndependentDerivedKeys(): void
    {
        $mgr = EncryptionManagerFactory::fromMasterKey(random_bytes(32));
        $plain = 'factory-cross-decrypt';
        $gcmCt = $mgr->encrypt($plain, 'aes-256-gcm');
        // 同一主密钥派生的子密钥因用途标签不同而互异：GCM 密文不能被 CBC 解开。
        $this->expectException(EncryptionException::class);
        $mgr->decrypt($gcmCt, 'aes-256-cbc-hmac');
    }

    public function testWrongMasterKeyLengthThrows(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Master key must be exactly 32 bytes.');
        EncryptionManagerFactory::fromMasterKey(random_bytes(16));
    }

    public function testWrongMasterKeyLengthLongThrows(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Master key must be exactly 32 bytes.');
        EncryptionManagerFactory::fromMasterKey(random_bytes(33));
    }

    public function testUnavailableDefaultThrows(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Default encryptor "nonexistent" is not available');
        EncryptionManagerFactory::fromMasterKey(random_bytes(32), 'nonexistent');
    }
}
