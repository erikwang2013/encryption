<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Kdf\Pbkdf2Sha256;
use Erikwang2013\Encryption\PasswordBasedKdfManager;
use Erikwang2013\Encryption\PasswordBasedKdfRegistry;
use PHPUnit\Framework\TestCase;

final class PasswordBasedKdfManagerTest extends TestCase
{
    public function testConstructorRejectsUnregisteredDefault(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Default password KDF "nope" is not registered.');
        new PasswordBasedKdfManager(new PasswordBasedKdfRegistry(), 'nope');
    }

    public function testDeriveWithDefaultAndExplicitIdentifier(): void
    {
        $weak = new Pbkdf2Sha256(1000, 'pbkdf2-sha256');
        $strong = new Pbkdf2Sha256(5000, 'pbkdf2-strong');
        $mgr = new PasswordBasedKdfManager(new PasswordBasedKdfRegistry($weak, $strong), 'pbkdf2-sha256');
        $salt = random_bytes(16);
        self::assertSame(32, strlen($mgr->deriveFromPassword('secret', $salt, 32)));
        // 显式标识走不同迭代次数，输出不同，证明路由生效。
        self::assertSame(
            $strong->deriveFromPassword('secret', $salt, 32),
            $mgr->deriveFromPassword('secret', $salt, 32, 'pbkdf2-strong')
        );
    }

    public function testDefaultKdfReturnsBoundInstance(): void
    {
        $kdf = new Pbkdf2Sha256(1000, 'pbkdf2-sha256');
        $mgr = new PasswordBasedKdfManager(new PasswordBasedKdfRegistry($kdf), 'pbkdf2-sha256');
        self::assertSame($kdf, $mgr->defaultKdf());
    }

    public function testSetDefaultIdentifierToUnknownThrows(): void
    {
        $mgr = new PasswordBasedKdfManager(new PasswordBasedKdfRegistry(new Pbkdf2Sha256(1000)), 'pbkdf2-sha256');
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Password KDF "nonexistent" is not registered.');
        $mgr->setDefaultIdentifier('nonexistent');
    }

    public function testRegistryReturnsSameInstance(): void
    {
        $registry = new PasswordBasedKdfRegistry(new Pbkdf2Sha256(1000));
        $mgr = new PasswordBasedKdfManager($registry, 'pbkdf2-sha256');
        self::assertSame($registry, $mgr->registry());
    }

    public function testDeriveWithUnknownIdentifierThrows(): void
    {
        $mgr = new PasswordBasedKdfManager(new PasswordBasedKdfRegistry(new Pbkdf2Sha256(1000)), 'pbkdf2-sha256');
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown password KDF: nope');
        $mgr->deriveFromPassword('secret', random_bytes(16), 16, 'nope');
    }
}
