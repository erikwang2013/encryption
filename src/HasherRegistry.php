<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

/**
 * 杂凑算法注册表。
 */
final class HasherRegistry extends AbstractRegistry
{
    protected function itemName(): string
    {
        return 'Hasher';
    }
}
