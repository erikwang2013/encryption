<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Contract\HasherInterface;

/**
 * 杂凑算法注册表。
 *
 * @extends AbstractRegistry<HasherInterface>
 */
final class HasherRegistry extends AbstractRegistry
{
    protected function itemName(): string
    {
        return 'Hasher';
    }
}
