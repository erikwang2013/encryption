<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Exception\EncryptionException;
use PHPUnit\Framework\TestCase;

abstract class AbstractRegistryTestCase extends TestCase
{
    /**
     * 构造一个条目；$identifier 为 null 时使用默认标识。
     */
    abstract protected function makeItem(?string $identifier = null): object;

    /**
     * 构造注册表并预注册条目。
     */
    abstract protected function makeRegistry(object ...$items): AbstractRegistry;

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
     * 自定义标识解析测试使用的标识。
     */
    abstract protected function customIdentifier(): string;

    /**
     * 自定义标识解析断言；子类可追加模块特有断言。
     */
    protected function assertCustomIdentifierResolved(AbstractRegistry $registry, object $item): void
    {
        self::assertSame($item, $registry->get($this->customIdentifier()));
    }

    public function testDuplicateRegistrationThrows(): void
    {
        $registry = $this->makeRegistry($this->makeItem());
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage(sprintf('%s "%s" is already registered.', $this->itemName(), $this->makeItem()->getIdentifier()));
        $registry->register($this->makeItem());
    }

    public function testUnknownIdentifierMessage(): void
    {
        $registry = $this->makeRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage(sprintf('Unknown %s: nope', $this->itemNameLower()));
        $registry->get('nope');
    }

    public function testEmptyIdentifierThrows(): void
    {
        $registry = $this->makeRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage(sprintf('%s identifier must not be empty.', $this->itemName()));
        $registry->register($this->makeItem(''));
    }

    public function testCustomIdentifierResolution(): void
    {
        $item = $this->makeItem($this->customIdentifier());
        $registry = $this->makeRegistry($item);
        $this->assertCustomIdentifierResolved($registry, $item);
    }
}
