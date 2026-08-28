<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Kdf\HkdfSha256;
use Erikwang2013\Encryption\KeyDerivationRegistry;

final class KeyDerivationRegistryTest extends AbstractRegistryTestCase
{
    protected function makeItem(?string $identifier = null): object
    {
        return new HkdfSha256($identifier ?? 'hkdf-sha256');
    }

    protected function makeRegistry(object ...$items): AbstractRegistry
    {
        return new KeyDerivationRegistry(...$items);
    }

    protected function itemName(): string
    {
        return 'KDF';
    }

    protected function itemNameLower(): string
    {
        return 'KDF';
    }

    protected function customIdentifier(): string
    {
        return 'kdf-v2';
    }

    protected function assertCustomIdentifierResolved(AbstractRegistry $registry, object $item): void
    {
        self::assertSame($item, $registry->get($this->customIdentifier()));
        self::assertSame([$this->customIdentifier()], $registry->identifiers());
    }
}
