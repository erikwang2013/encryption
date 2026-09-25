<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Contract\AsymmetricCipherInterface;
use Erikwang2013\Encryption\Contract\HasherInterface;
use Erikwang2013\Encryption\Contract\KeyDerivationInterface;
use Erikwang2013\Encryption\Contract\PasswordBasedKdfInterface;
use Erikwang2013\Encryption\Contract\SymmetricCipherInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 注册表公共基类：按标识（getIdentifier()）注册并解析实现对象。
 *
 * 泛型参数 T 是注册表承载的实现类型，由子类用 @extends 指定；
 * 这样 get() 返回的是契约接口而非 object，调用方无需再断言类型。
 *
 * @template T of SymmetricCipherInterface|AsymmetricCipherInterface|HasherInterface|KeyDerivationInterface|PasswordBasedKdfInterface
 */
abstract class AbstractRegistry
{
    /** @var array<string, T> */
    private array $items = [];

    /**
     * @param T ...$items
     */
    public function __construct(object ...$items)
    {
        foreach ($items as $item) {
            $this->register($item);
        }
    }

    /**
     * @param T $item
     */
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

    /**
     * @return T
     */
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
