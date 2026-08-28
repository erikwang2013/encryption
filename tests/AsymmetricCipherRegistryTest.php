<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Asymmetric\Sm2AsymmetricCipher;
use Erikwang2013\Encryption\AsymmetricCipherRegistry;

final class AsymmetricCipherRegistryTest extends AbstractRegistryTestCase
{
    protected function makeItem(?string $identifier = null): object
    {
        return new Sm2AsymmetricCipher(null, $identifier ?? 'sm2');
    }

    protected function makeRegistry(object ...$items): AbstractRegistry
    {
        return new AsymmetricCipherRegistry(...$items);
    }

    protected function itemName(): string
    {
        return 'Asymmetric cipher';
    }

    protected function customIdentifier(): string
    {
        return 'sm2-custom';
    }
}
