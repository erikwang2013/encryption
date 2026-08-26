<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Kdf\Pbkdf2Sha256;
use Erikwang2013\Encryption\PasswordBasedKdfRegistry;
use PHPUnit\Framework\TestCase;

final class PasswordBasedKdfRegistryTest extends TestCase
{
    public function testDuplicateRegistrationThrows(): void
    {
        $registry = new PasswordBasedKdfRegistry(new Pbkdf2Sha256(1000));
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Password KDF "pbkdf2-sha256" is already registered.');
        $registry->register(new Pbkdf2Sha256(1000));
    }

    public function testUnknownIdentifierMessage(): void
    {
        $registry = new PasswordBasedKdfRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown password KDF: nope');
        $registry->get('nope');
    }

    public function testEmptyIdentifierThrows(): void
    {
        $registry = new PasswordBasedKdfRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Password KDF identifier must not be empty.');
        $registry->register(new Pbkdf2Sha256(1000, ''));
    }

    public function testCustomIdentifierResolution(): void
    {
        $kdf = new Pbkdf2Sha256(5000, 'pbkdf2-strong');
        $registry = new PasswordBasedKdfRegistry($kdf);
        self::assertSame($kdf, $registry->get('pbkdf2-strong'));
    }
}
