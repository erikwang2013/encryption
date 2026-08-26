<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Kdf\HkdfSha256;
use Erikwang2013\Encryption\KeyDerivationManager;
use Erikwang2013\Encryption\KeyDerivationRegistry;
use PHPUnit\Framework\TestCase;

final class KeyDerivationManagerTest extends TestCase
{
    public function testConstructorRejectsUnregisteredDefault(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Default KDF "nope" is not registered.');
        new KeyDerivationManager(new KeyDerivationRegistry(), 'nope');
    }

    public function testDeriveWithDefaultAndExplicitIdentifier(): void
    {
        $kdf = new HkdfSha256('hkdf-sha256');
        $kdf2 = new HkdfSha256('hkdf-sha256-v2');
        $mgr = new KeyDerivationManager(new KeyDerivationRegistry($kdf, $kdf2), 'hkdf-sha256');
        $ikm = random_bytes(32);
        $salt = random_bytes(16);
        self::assertSame(32, strlen($mgr->derive($ikm, $salt, 32)));
        self::assertSame($kdf2->derive($ikm, $salt, 16, 'ctx'), $mgr->derive($ikm, $salt, 16, 'ctx', 'hkdf-sha256-v2'));
    }

    public function testDefaultKdfReturnsBoundInstance(): void
    {
        $kdf = new HkdfSha256('hkdf-sha256');
        $mgr = new KeyDerivationManager(new KeyDerivationRegistry($kdf), 'hkdf-sha256');
        self::assertSame($kdf, $mgr->defaultKdf());
    }

    public function testSetDefaultIdentifierToUnknownThrows(): void
    {
        $mgr = new KeyDerivationManager(new KeyDerivationRegistry(new HkdfSha256()), 'hkdf-sha256');
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('KDF "nonexistent" is not registered.');
        $mgr->setDefaultIdentifier('nonexistent');
    }

    public function testRegistryReturnsSameInstance(): void
    {
        $registry = new KeyDerivationRegistry(new HkdfSha256());
        $mgr = new KeyDerivationManager($registry, 'hkdf-sha256');
        self::assertSame($registry, $mgr->registry());
    }

    public function testDeriveWithUnknownIdentifierThrows(): void
    {
        $mgr = new KeyDerivationManager(new KeyDerivationRegistry(new HkdfSha256()), 'hkdf-sha256');
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown KDF: nope');
        $mgr->derive(random_bytes(32), random_bytes(16), 16, '', 'nope');
    }
}
