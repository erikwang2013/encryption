<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Asymmetric\Sm2AsymmetricCipher;
use Erikwang2013\Encryption\AsymmetricCipherRegistry;
use Erikwang2013\Encryption\AsymmetricCryptoManager;
use Erikwang2013\Encryption\Guomi\Sm2EncryptionService;

final class AsymmetricCryptoManagerTest extends AbstractManagerTestCase
{
    protected function makeItem(): object
    {
        return new Sm2AsymmetricCipher();
    }

    protected function makeRegistry(object ...$items): AbstractRegistry
    {
        return new AsymmetricCipherRegistry(...$items);
    }

    protected function makeManager(object $registry, string $defaultIdentifier): object
    {
        return new AsymmetricCryptoManager($registry, $defaultIdentifier);
    }

    protected function defaultIdentifier(): string
    {
        return 'sm2';
    }

    protected function itemName(): string
    {
        return 'Asymmetric cipher';
    }

    protected function boundDefault(object $manager): object
    {
        return $manager->defaultCipher();
    }

    protected function dispatchToUnknown(object $manager): void
    {
        $manager->encrypt('x', 'public-key', 'nope');
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
