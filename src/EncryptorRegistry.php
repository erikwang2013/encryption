<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

/**
 * 注册多种加密实现，按标识解析；可运行时注册自定义插件。
 */
final class EncryptorRegistry extends AbstractRegistry
{
    protected function itemName(): string
    {
        return 'Encryptor';
    }
}
