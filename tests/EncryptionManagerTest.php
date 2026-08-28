<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\EncryptionManager;
use Erikwang2013\Encryption\Encryptor\Aes256GcmEncryptor;
use Erikwang2013\Encryption\Encryptor\OpenSslAes256CbcEncryptor;
use Erikwang2013\Encryption\EncryptorRegistry;
use Erikwang2013\Encryption\Exception\EncryptionException;

final class EncryptionManagerTest extends AbstractManagerTestCase
{
    protected function makeItem(): object
    {
        return new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN));
    }

    protected function makeRegistry(object ...$items): AbstractRegistry
    {
        return new EncryptorRegistry(...$items);
    }

    protected function makeManager(object $registry, string $defaultIdentifier): object
    {
        return new EncryptionManager($registry, $defaultIdentifier);
    }

    protected function defaultIdentifier(): string
    {
        return 'aes-256-gcm';
    }

    protected function itemName(): string
    {
        return 'Encryptor';
    }

    protected function boundDefault(object $manager): object
    {
        return $manager->defaultEncryptor();
    }

    protected function dispatchToUnknown(object $manager): void
    {
        $manager->encrypt('x', 'nope');
    }

    private function manager(): EncryptionManager
    {
        $gcm = new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN));
        $cbc = new OpenSslAes256CbcEncryptor(random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN));

        return new EncryptionManager(new EncryptorRegistry($gcm, $cbc), 'aes-256-gcm');
    }

    public function testRoundTripWithDefaultIdentifier(): void
    {
        $mgr = $this->manager();
        $plain = 'manager-default';
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain)));
    }

    public function testEncryptRoutesToExplicitIdentifier(): void
    {
        $mgr = $this->manager();
        $plain = 'manager-explicit';
        $ct = $mgr->encrypt($plain, 'aes-256-cbc-hmac');
        // 两个算法使用不同密钥：默认 GCM 无法解开 CBC 密文，显式 CBC 可以。
        self::assertSame($plain, $mgr->decrypt($ct, 'aes-256-cbc-hmac'));
        $this->expectException(EncryptionException::class);
        $mgr->decrypt($ct, 'aes-256-gcm');
    }

    public function testSetDefaultIdentifierSwitchesRouting(): void
    {
        $mgr = $this->manager();
        $mgr->setDefaultIdentifier('aes-256-cbc-hmac');
        self::assertSame('aes-256-cbc-hmac', $mgr->getDefaultIdentifier());
        $plain = 'manager-switch';
        $ct = $mgr->encrypt($plain);
        self::assertSame($plain, $mgr->decrypt($ct));
    }
}
