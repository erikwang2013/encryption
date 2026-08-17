<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 注册表公共基类：按标识（getIdentifier()）注册并解析实现对象。
 */
abstract class AbstractRegistry
{
    /** @var array<string, object> */
    private array $items = [];

    public function __construct(object ...$items)
    {
        foreach ($items as $item) {
            $this->register($item);
        }
    }

    public function register(object $item): static
    {
        $id = $item->getIdentifier();
        if ($id === '') {
            throw new EncryptionException(sprintf('%s identifier must not be empty.', $this->itemName()));
        }
        if (isset($this->items[$id])) {
            throw new EncryptionException(sprintf('%s "%s" is already registered.', $this->itemName(), $id));
        }
        $this->items[$id] = $item;

        return $this;
    }

    public function has(string $identifier): bool
    {
        return isset($this->items[$identifier]);
    }

    public function get(string $identifier): object
    {
        if (!isset($this->items[$identifier])) {
            throw new EncryptionException(sprintf('Unknown %s: %s', $this->itemNameLower(), $identifier));
        }

        return $this->items[$identifier];
    }

    /**
     * @return list<string>
     */
    public function identifiers(): array
    {
        return array_keys($this->items);
    }

    /**
     * 注册表条目名称（用于错误消息），如 'Encryptor'。
     */
    abstract protected function itemName(): string;

    /**
     * 未知条目错误消息中的小写形式；全大写缩写（如 'KDF'）需覆写。
     */
    protected function itemNameLower(): string
    {
        return lcfirst($this->itemName());
    }
}
