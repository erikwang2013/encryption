<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Kdf\Pbkdf2Sha256;
use Erikwang2013\Encryption\PasswordBasedKdfRegistry;

final class PasswordBasedKdfRegistryTest extends AbstractRegistryTestCase
{
    protected function makeItem(?string $identifier = null): object
    {
        return new Pbkdf2Sha256(1000, $identifier ?? 'pbkdf2-sha256');
    }

    protected function makeRegistry(object ...$items): AbstractRegistry
    {
        return new PasswordBasedKdfRegistry(...$items);
    }

    protected function itemName(): string
    {
        return 'Password KDF';
    }

    protected function customIdentifier(): string
    {
        return 'pbkdf2-strong';
    }
}
