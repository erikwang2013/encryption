<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Contract\PasswordBasedKdfInterface;

/**
 * 基于口令的 KDF（如 PBKDF2）注册表。
 *
 * @extends AbstractRegistry<PasswordBasedKdfInterface>
 */
final class PasswordBasedKdfRegistry extends AbstractRegistry
{
    protected function itemName(): string
    {
        return 'Password KDF';
    }
}
