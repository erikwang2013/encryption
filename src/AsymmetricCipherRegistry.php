<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

/**
 * 非对称加解密实现注册表。
 */
final class AsymmetricCipherRegistry extends AbstractRegistry
{
    protected function itemName(): string
    {
        return 'Asymmetric cipher';
    }
}
