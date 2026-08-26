<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\EncryptionManager;
use Erikwang2013\Encryption\Encryptor\Aes256GcmEncryptor;
use Erikwang2013\Encryption\Encryptor\OpenSslAes256CbcEncryptor;
use Erikwang2013\Encryption\EncryptorRegistry;
use Erikwang2013\Encryption\Exception\EncryptionException;
use PHPUnit\Framework\TestCase;

final class EncryptionManagerTest extends TestCase
{
    private function manager(): EncryptionManager
    {
        $gcm = new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN));
        $cbc = new OpenSslAes256CbcEncryptor(random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN));

        return new EncryptionManager(new EncryptorRegistry($gcm, $cbc), 'aes-256-gcm');
    }

    public function testConstructorRejectsUnregisteredDefault(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Default encryptor "nope" is not registered.');
        new EncryptionManager(new EncryptorRegistry(), 'nope');
    }

    public function testRoundTripWithDefaultIdentifier(): void
    {
        $mgr = $this->manager();
        $plain = 'manager-default';
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain)));
    }

    public function testEncryptRoutesToExplicitIdentifier(): void
    {
        $mgr = $this->manager();
        $plain = 'manager-explicit';
        $ct = $mgr->encrypt($plain, 'aes-256-cbc-hmac');
        // 两个算法使用不同密钥：默认 GCM 无法解开 CBC 密文，显式 CBC 可以。
        self::assertSame($plain, $mgr->decrypt($ct, 'aes-256-cbc-hmac'));
        $this->expectException(EncryptionException::class);
        $mgr->decrypt($ct, 'aes-256-gcm');
    }

    public function testDefaultEncryptorReturnsBoundInstance(): void
    {
        $mgr = $this->manager();
        self::assertSame('aes-256-gcm', $mgr->defaultEncryptor()->getIdentifier());
    }

    public function testSetDefaultIdentifierSwitchesRouting(): void
    {
        $mgr = $this->manager();
        $mgr->setDefaultIdentifier('aes-256-cbc-hmac');
        self::assertSame('aes-256-cbc-hmac', $mgr->getDefaultIdentifier());
        $plain = 'manager-switch';
        $ct = $mgr->encrypt($plain);
        self::assertSame($plain, $mgr->decrypt($ct));
    }

    public function testSetDefaultIdentifierToUnknownThrows(): void
    {
        $mgr = $this->manager();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Encryptor "nonexistent" is not registered.');
        $mgr->setDefaultIdentifier('nonexistent');
    }

    public function testRegistryReturnsSameInstance(): void
    {
        $registry = new EncryptorRegistry(new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN)));
        $mgr = new EncryptionManager($registry, 'aes-256-gcm');
        self::assertSame($registry, $mgr->registry());
    }

    public function testEncryptWithUnknownIdentifierThrows(): void
    {
        $mgr = $this->manager();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown encryptor: nope');
        $mgr->encrypt('x', 'nope');
    }
}
