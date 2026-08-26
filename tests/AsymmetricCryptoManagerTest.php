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
use PHPUnit\Framework\TestCase;

final class AsymmetricCryptoManagerTest extends TestCase
{
    public function testConstructorRejectsUnregisteredDefault(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Default asymmetric cipher "nope" is not registered.');
        new AsymmetricCryptoManager(new AsymmetricCipherRegistry(), 'nope');
    }

    public function testDefaultCipherReturnsBoundInstance(): void
    {
        $cipher = new Sm2AsymmetricCipher();
        $mgr = new AsymmetricCryptoManager(new AsymmetricCipherRegistry($cipher), 'sm2');
        self::assertSame($cipher, $mgr->defaultCipher());
    }

    public function testSetDefaultIdentifierToUnknownThrows(): void
    {
        $mgr = new AsymmetricCryptoManager(new AsymmetricCipherRegistry(new Sm2AsymmetricCipher()), 'sm2');
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Asymmetric cipher "nonexistent" is not registered.');
        $mgr->setDefaultIdentifier('nonexistent');
    }

    public function testRegistryReturnsSameInstance(): void
    {
        $registry = new AsymmetricCipherRegistry(new Sm2AsymmetricCipher());
        $mgr = new AsymmetricCryptoManager($registry, 'sm2');
        self::assertSame($registry, $mgr->registry());
    }

    public function testRoundTripWhenGmpAvailable(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded');
        }
        $pair = Sm2EncryptionService::generateKeyPairHex();
        $mgr = new AsymmetricCryptoManager(new AsymmetricCipherRegistry(new Sm2AsymmetricCipher()), 'sm2');
        $plain = 'asymmetric-manager';
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain, $pair->getPublicKey()), $pair->getPrivateKey()));
    }

    public function testExplicitIdentifierRoutingWhenGmpAvailable(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded');
        }
        $pair = Sm2EncryptionService::generateKeyPairHex();
        $registry = new AsymmetricCipherRegistry(new Sm2AsymmetricCipher('sm2'), new Sm2AsymmetricCipher('sm2-v2'));
        $mgr = new AsymmetricCryptoManager($registry, 'sm2');
        $plain = 'asymmetric-routing';
        $ct = $mgr->encrypt($plain, $pair->getPublicKey(), 'sm2-v2');
        self::assertSame($plain, $mgr->decrypt($ct, $pair->getPrivateKey(), 'sm2-v2'));
        self::assertNotSame($ct, $mgr->encrypt($plain, $pair->getPublicKey(), 'sm2'));
    }
}
