<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Sm3Hasher;
use Erikwang2013\Encryption\Hash\Sha256Hasher;
use Erikwang2013\Encryption\HasherRegistry;
use PHPUnit\Framework\TestCase;

final class HasherRegistryTest extends TestCase
{
    public function testDuplicateRegistrationThrows(): void
    {
        $registry = new HasherRegistry(new Sha256Hasher());
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Hasher "sha256" is already registered.');
        $registry->register(new Sha256Hasher());
    }

    public function testUnknownIdentifierMessage(): void
    {
        $registry = new HasherRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown hasher: nope');
        $registry->get('nope');
    }

    public function testEmptyIdentifierThrows(): void
    {
        $registry = new HasherRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Hasher identifier must not be empty.');
        $registry->register(new Sha256Hasher(''));
    }

    public function testMultipleHashersCoexist(): void
    {
        $sha = new Sha256Hasher();
        $sm3 = new Sm3Hasher();
        $registry = new HasherRegistry($sha, $sm3);
        self::assertSame(['sha256', 'sm3'], $registry->identifiers());
        self::assertSame($sm3, $registry->get('sm3'));
        self::assertSame($sha, $registry->get('sha256'));
    }
}
