<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Exception\EncryptionException;
use PHPUnit\Framework\TestCase;

abstract class AbstractManagerTestCase extends TestCase
{
    /**
     * 构造一个使用默认标识的条目。
     */
    abstract protected function makeItem(): object;

    /**
     * 构造注册表并预注册条目。
     */
    abstract protected function makeRegistry(object ...$items): AbstractRegistry;

    /**
     * 构造门面实例。
     */
    abstract protected function makeManager(object $registry, string $defaultIdentifier): object;

    /**
     * 模块默认标识，如 'aes-256-gcm'。
     */
    abstract protected function defaultIdentifier(): string;

    /**
     * 注册表条目名称（错误消息前缀），如 'Encryptor'。
     */
    abstract protected function itemName(): string;

    /**
     * 未知条目错误消息中的小写形式；全大写缩写（如 'KDF'）需覆写。
     */
    protected function itemNameLower(): string
    {
        return lcfirst($this->itemName());
    }

    /**
     * 读取门面绑定的默认条目，如 defaultEncryptor()。
     */
    abstract protected function boundDefault(object $manager): object;

    /**
     * 触发一次未注册标识的调用，如 encrypt('x', 'nope')。
     */
    abstract protected function dispatchToUnknown(object $manager): void;

    public function testConstructorRejectsUnregisteredDefault(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage(sprintf('Default %s "nope" is not registered.', $this->itemNameLower()));
        $this->makeManager($this->makeRegistry(), 'nope');
    }

    public function testDefaultIdentifierReturnsBoundInstance(): void
    {
        $item = $this->makeItem();
        $mgr = $this->makeManager($this->makeRegistry($item), $this->defaultIdentifier());
        self::assertSame($item, $this->boundDefault($mgr));
    }

    public function testSetDefaultIdentifierToUnknownThrows(): void
    {
        $mgr = $this->makeManager($this->makeRegistry($this->makeItem()), $this->defaultIdentifier());
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage(sprintf('%s "nonexistent" is not registered.', $this->itemName()));
        $mgr->setDefaultIdentifier('nonexistent');
    }

    public function testRegistryReturnsSameInstance(): void
    {
        $registry = $this->makeRegistry($this->makeItem());
        $mgr = $this->makeManager($registry, $this->defaultIdentifier());
        self::assertSame($registry, $mgr->registry());
    }

    public function testUnknownIdentifierThrows(): void
    {
        $mgr = $this->makeManager($this->makeRegistry($this->makeItem()), $this->defaultIdentifier());
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage(sprintf('Unknown %s: nope', $this->itemNameLower()));
        $this->dispatchToUnknown($mgr);
    }
}
