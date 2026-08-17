<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

/**
 * 密钥派生（HKDF 等）实现注册表。
 */
final class KeyDerivationRegistry extends AbstractRegistry
{
    protected function itemName(): string
    {
        return 'KDF';
    }

    protected function itemNameLower(): string
    {
        return 'KDF';
    }
}
