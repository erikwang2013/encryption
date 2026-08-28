<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Kdf\HkdfSha256;
use Erikwang2013\Encryption\KeyDerivationManager;
use Erikwang2013\Encryption\KeyDerivationRegistry;

final class KeyDerivationManagerTest extends AbstractManagerTestCase
{
    protected function makeItem(): object
    {
        return new HkdfSha256();
    }

    protected function makeRegistry(object ...$items): AbstractRegistry
    {
        return new KeyDerivationRegistry(...$items);
    }

    protected function makeManager(object $registry, string $defaultIdentifier): object
    {
        return new KeyDerivationManager($registry, $defaultIdentifier);
    }

    protected function defaultIdentifier(): string
    {
        return 'hkdf-sha256';
    }

    protected function itemName(): string
    {
        return 'KDF';
    }

    protected function itemNameLower(): string
    {
        return 'KDF';
    }

    protected function boundDefault(object $manager): object
    {
        return $manager->defaultKdf();
    }

    protected function dispatchToUnknown(object $manager): void
    {
        $manager->derive(random_bytes(32), random_bytes(16), 16, '', 'nope');
    }

    public function testDeriveWithDefaultAndExplicitIdentifier(): void
    {
        $kdf = new HkdfSha256();
        $kdf2 = new HkdfSha256('hkdf-sha256-v2');
        $mgr = new KeyDerivationManager(new KeyDerivationRegistry($kdf, $kdf2), 'hkdf-sha256');
        $ikm = random_bytes(32);
        $salt = random_bytes(16);
        self::assertSame(32, strlen($mgr->derive($ikm, $salt, 32)));
        self::assertSame($kdf2->derive($ikm, $salt, 16, 'ctx'), $mgr->derive($ikm, $salt, 16, 'ctx', 'hkdf-sha256-v2'));
    }
}
