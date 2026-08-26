<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Sm3Hasher;
use Erikwang2013\Encryption\Hash\Sha256Hasher;
use Erikwang2013\Encryption\HashingManager;
use Erikwang2013\Encryption\HasherRegistry;
use PHPUnit\Framework\TestCase;

final class HashingManagerTest extends TestCase
{
    public function testConstructorRejectsUnregisteredDefault(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Default hasher "nope" is not registered.');
        new HashingManager(new HasherRegistry(), 'nope');
    }

    public function testDigestRoutesByIdentifier(): void
    {
        $registry = new HasherRegistry(new Sha256Hasher(), new Sm3Hasher());
        $mgr = new HashingManager($registry, 'sha256');
        $data = 'routing-check';
        self::assertSame((new Sha256Hasher())->digestHex($data), $mgr->digestHex($data));
        self::assertSame((new Sm3Hasher())->digestHex($data), $mgr->digestHex($data, 'sm3'));
    }

    public function testDefaultHasherReturnsBoundInstance(): void
    {
        $sha = new Sha256Hasher();
        $mgr = new HashingManager(new HasherRegistry($sha), 'sha256');
        self::assertSame($sha, $mgr->defaultHasher());
    }

    public function testSetDefaultIdentifierToUnknownThrows(): void
    {
        $mgr = new HashingManager(new HasherRegistry(new Sha256Hasher()), 'sha256');
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Hasher "nonexistent" is not registered.');
        $mgr->setDefaultIdentifier('nonexistent');
    }

    public function testRegistryReturnsSameInstance(): void
    {
        $registry = new HasherRegistry(new Sha256Hasher());
        $mgr = new HashingManager($registry, 'sha256');
        self::assertSame($registry, $mgr->registry());
    }

    public function testDigestWithUnknownIdentifierThrows(): void
    {
        $mgr = new HashingManager(new HasherRegistry(new Sha256Hasher()), 'sha256');
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown hasher: nope');
        $mgr->digest('data', 'nope');
    }
}
