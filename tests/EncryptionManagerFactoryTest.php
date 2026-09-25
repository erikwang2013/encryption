<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Encryptor\OpenSslAes256CbcEncryptor;
use Erikwang2013\Encryption\EncryptionManager;
use Erikwang2013\Encryption\EncryptionManagerFactory;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Sm4CbcEncryptor;
use Erikwang2013\Encryption\Guomi\ZucEncryptor;
use PHPUnit\Framework\TestCase;

final class EncryptionManagerFactoryTest extends TestCase
{
    /** 固化向量用的固定 32 字节主密钥 */
    private const PINNED_MASTER = 'kdf-v2-test-master-key-32-bytes!';
    /** 固定主密钥 → 五个子密钥（hex）：v1 为历史行为，v2 为修正后的 HMAC 参数顺序 */
    private const V1_SUBKEYS = [
        'aes-256-gcm' => '03e651ec2a0f56a632c36ed38da385efdc6a23a4512b51ad8143748bfa8b365c',
        'aes-256-cbc-hmac' => '188e84a83b9a9f03e263ab40d0f5ad68dbb7b53aa3534ea5e53ebc4e6bef6bd7',
        'sm4-cbc' => '06ca3ed566a814dd6380b5407c93f82f',
        'zuc-128' => '0ac42d0299ac7ae4b93af4a07f05ec24',
        'sodium-xchacha20' => 'e320542d91f38bffe48854bf37a0235b2450f1ce51f09e9ae6486bddccdc10da',
    ];
    private const V2_SUBKEYS = [
        'aes-256-gcm' => '16733c573d0d44124210f08e34b6f07cb5afa752f0c4b8818fc49851b0794698',
        'aes-256-cbc-hmac' => '6922a992c672f548c3b0ecfb6670011217f425269aa3d9ca29ad02a0dd03ea0f',
        'sm4-cbc' => '39ede30835592c5c725fa8b1acaf89a2',
        'zuc-128' => 'ce9806bf2b2e0aa82e37e10f0afb8319',
        'sodium-xchacha20' => 'dc61f7b088fdee9c8f59849cc50da1bc54e384f725fdb49be6d2cb28802f078d',
    ];

    public function testDefaultConfigurationUsesAes256Gcm(): void
    {
        $mgr = EncryptionManagerFactory::fromMasterKey(random_bytes(32));
        self::assertInstanceOf(EncryptionManager::class, $mgr);
        self::assertSame('aes-256-gcm', $mgr->getDefaultIdentifier());
        $plain = 'factory-default';
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain)));
    }

    public function testRegistersAllBuiltInAlgorithms(): void
    {
        $mgr = EncryptionManagerFactory::fromMasterKey(random_bytes(32));
        foreach (['aes-256-gcm', 'aes-256-cbc-hmac', 'sm4-cbc', 'zuc-128'] as $id) {
            self::assertTrue($mgr->registry()->has($id), "expected {$id} to be registered");
        }
        if (extension_loaded('sodium')) {
            self::assertTrue($mgr->registry()->has('sodium-xchacha20'));
        }
    }

    public function testEveryRegisteredAlgorithmRoundTrips(): void
    {
        $mgr = EncryptionManagerFactory::fromMasterKey(random_bytes(32));
        $plain = 'factory-all-algorithms';
        foreach ($mgr->registry()->identifiers() as $id) {
            self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain, $id), $id), "round trip failed for {$id}");
        }
    }

    public function testSodiumCanBeDefaultWhenLoaded(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium not loaded');
        }
        $mgr = EncryptionManagerFactory::fromMasterKey(random_bytes(32), 'sodium-xchacha20');
        self::assertSame('sodium-xchacha20', $mgr->getDefaultIdentifier());
        $plain = 'factory-sodium-default';
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain)));
    }

    public function testAlgorithmsUseIndependentDerivedKeys(): void
    {
        $mgr = EncryptionManagerFactory::fromMasterKey(random_bytes(32));
        $plain = 'factory-cross-decrypt';
        $gcmCt = $mgr->encrypt($plain, 'aes-256-gcm');
        // 同一主密钥派生的子密钥因用途标签不同而互异：GCM 密文不能被 CBC 解开。
        $this->expectException(EncryptionException::class);
        $mgr->decrypt($gcmCt, 'aes-256-cbc-hmac');
    }

    public function testWrongMasterKeyLengthThrows(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Master key must be exactly 32 bytes.');
        EncryptionManagerFactory::fromMasterKey(random_bytes(16));
    }

    public function testWrongMasterKeyLengthLongThrows(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Master key must be exactly 32 bytes.');
        EncryptionManagerFactory::fromMasterKey(random_bytes(33));
    }

    public function testUnavailableDefaultThrows(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Default encryptor "nonexistent" is not available');
        EncryptionManagerFactory::fromMasterKey(random_bytes(32), 'nonexistent');
    }

    public function testV1SubkeysMatchPinnedVectorAndAreTheDefault(): void
    {
        // 不传派生方案 = v1；固定向量保证默认方案永不静默漂移（改了顺序这里必红）。
        $mgr = EncryptionManagerFactory::fromMasterKey(self::PINNED_MASTER);
        foreach ($this->expectedSubkeys(self::V1_SUBKEYS) as $id => $expected) {
            self::assertSame($expected, $this->subkeyHex($mgr, $id), "v1 subkey drifted for {$id}");
        }
    }

    public function testV2SubkeysMatchPinnedVectorAndDifferFromV1(): void
    {
        $mgr = EncryptionManagerFactory::fromMasterKey(self::PINNED_MASTER, 'aes-256-gcm', 'v2');
        foreach ($this->expectedSubkeys(self::V2_SUBKEYS) as $id => $expected) {
            self::assertSame($expected, $this->subkeyHex($mgr, $id), "v2 subkey drifted for {$id}");
            self::assertNotSame(self::V1_SUBKEYS[$id], $expected, "v2 must differ from v1 for {$id}");
        }
    }

    public function testV2ManagerRoundTripsEveryAlgorithm(): void
    {
        $mgr = EncryptionManagerFactory::fromMasterKey(random_bytes(32), 'aes-256-gcm', 'v2');
        $plain = 'factory-v2-all-algorithms';
        foreach ($mgr->registry()->identifiers() as $id) {
            self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain, $id), $id), "v2 round trip failed for {$id}");
        }
    }

    public function testV1AndV2ManagersCannotReadEachOthersCiphertext(): void
    {
        $master = random_bytes(32);
        $v1 = EncryptionManagerFactory::fromMasterKey($master);
        $v2 = EncryptionManagerFactory::fromMasterKey($master, 'aes-256-gcm', 'v2');
        try {
            $v1->decrypt($v2->encrypt('payload'));
            self::fail('A v1 manager must not decrypt v2 ciphertext: the derived subkeys differ.');
        } catch (EncryptionException $e) {
            self::assertStringContainsString('AES-256-GCM', $e->getMessage());
        }
        try {
            $v2->decrypt($v1->encrypt('payload'));
            self::fail('A v2 manager must not decrypt v1 ciphertext: the derived subkeys differ.');
        } catch (EncryptionException $e) {
            self::assertStringContainsString('AES-256-GCM', $e->getMessage());
        }
    }

    public function testV2ManagerPropagatesMacSchemeToEveryMacEncryptor(): void
    {
        // v2 工厂必须把 v2 透传给 CBC/SM4/ZUC：用固定 v2 子密钥 + 显式 v2 方案独立构造的实例必须能解开。
        $mgr = EncryptionManagerFactory::fromMasterKey(self::PINNED_MASTER, 'aes-256-cbc-hmac', 'v2');
        $standalones = [
            'aes-256-cbc-hmac' => new OpenSslAes256CbcEncryptor($this->subkey(self::V2_SUBKEYS, 'aes-256-cbc-hmac'), macDerivation: 'v2'),
            'sm4-cbc' => new Sm4CbcEncryptor($this->subkey(self::V2_SUBKEYS, 'sm4-cbc'), macDerivation: 'v2'),
            'zuc-128' => new ZucEncryptor($this->subkey(self::V2_SUBKEYS, 'zuc-128'), macDerivation: 'v2'),
        ];
        foreach ($standalones as $id => $standalone) {
            self::assertSame(
                'payload',
                $standalone->decrypt($mgr->encrypt('payload', $id)),
                "v2 scheme was not propagated to {$id} (or its subkey differs)"
            );
        }
    }

    public function testUnknownDerivationSchemeThrows(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown key derivation scheme "v3" (expected "v1" or "v2").');
        EncryptionManagerFactory::fromMasterKey(random_bytes(32), 'aes-256-gcm', 'v3');
    }

    /**
     * 去掉未装载 sodium 时的期望项（工厂不会注册该加密器）。
     *
     * @param array<string, string> $expected
     * @return array<string, string>
     */
    private function expectedSubkeys(array $expected): array
    {
        if (!extension_loaded('sodium')) {
            unset($expected['sodium-xchacha20']);
        }

        return $expected;
    }

    /**
     * @param array<string, string> $subkeys
     */
    private function subkey(array $subkeys, string $identifier): string
    {
        return (string) hex2bin($subkeys[$identifier]);
    }

    /**
     * 读出加密器实例的已派生密钥（private $key）——冻结向量需要直接观察派生结果。
     */
    private function subkeyHex(EncryptionManager $mgr, string $identifier): string
    {
        $encryptor = $mgr->registry()->get($identifier);
        self::assertInstanceOf(EncryptorInterface::class, $encryptor);
        $prop = new \ReflectionProperty($encryptor, 'key');
        $prop->setAccessible(true); // PHP 8.0 必需，8.1+ 为 no-op

        return bin2hex((string) $prop->getValue($encryptor));
    }
}
