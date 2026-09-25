<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Encryptor\Aes256GcmEncryptor;
use Erikwang2013\Encryption\Encryptor\OpenSslAes256CbcEncryptor;
use Erikwang2013\Encryption\Encryptor\SodiumXChaCha20Encryptor;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Sm4CbcEncryptor;
use Erikwang2013\Encryption\Guomi\ZucEncryptor;

/**
 * 从 32 字节主密钥快速构建常用组合（各算法独立派生子密钥，避免同一密钥跨算法复用）。
 *
 * 派生方案 $derivation：'v1'（默认）沿用历史顺序（HMAC 的 key 是用途标签），
 * 'v2' 修正为 HMAC 的 key 是主密钥、message 是用途标签。v1 与 v2 派生结果不同：
 * v1 管理器解不开 v2 密文，反之亦然；载荷的 "v1" 前缀是密文格式版本，与派生方案无关。
 * 迁移方式见 README「子密钥派生方案 v1 → v2（可选迁移）」。
 */
final class EncryptionManagerFactory
{
    /**
     * @param string $masterKey 32 字节主密钥
     * @param string $default 默认算法标识，如 aes-256-gcm、sodium-xchacha20
     * @param string $derivation 子密钥派生方案：'v1'（默认，历史行为）或 'v2'（修正顺序）
     */
    public static function fromMasterKey(
        string $masterKey,
        string $default = 'aes-256-gcm',
        string $derivation = 'v1'
    ): EncryptionManager {
        if (strlen($masterKey) !== 32) {
            throw new EncryptionException('Master key must be exactly 32 bytes.');
        }
        if ($derivation !== 'v1' && $derivation !== 'v2') {
            throw new EncryptionException(sprintf(
                'Unknown key derivation scheme "%s" (expected "v1" or "v2").',
                $derivation
            ));
        }

        $registry = new EncryptorRegistry();

        // v1：用途标签作 HMAC key（历史错误顺序）；v2：主密钥作 HMAC key、标签作 message。
        $derive = static fn (string $label): string => $derivation === 'v2'
            ? hash_hmac('sha256', $label, $masterKey, true)
            : hash_hmac('sha256', $masterKey, $label, true);

        $aesKey = $derive('dgn:derive:aes-gcm');
        $registry->register(new Aes256GcmEncryptor($aesKey));

        $cbcKey = $derive('dgn:derive:aes-cbc');
        $registry->register(new OpenSslAes256CbcEncryptor($cbcKey, macDerivation: $derivation));

        $sm4Key = substr($derive('dgn:derive:sm4'), 0, 16);
        $registry->register(new Sm4CbcEncryptor($sm4Key, macDerivation: $derivation));

        $zucKey = substr($derive('dgn:derive:zuc'), 0, 16);
        $registry->register(new ZucEncryptor($zucKey, macDerivation: $derivation));

        if (extension_loaded('sodium')) {
            // sha256 输出恒为 32 字节，即 XChaCha20 的 KEYBYTES，直接注册。
            $sodiumKey = $derive('dgn:derive:sodium');
            $registry->register(new SodiumXChaCha20Encryptor($sodiumKey));
        }

        if (!$registry->has($default)) {
            throw new EncryptionException(sprintf(
                'Default encryptor "%s" is not available (register ext-sodium for sodium-xchacha20).',
                $default
            ));
        }

        return new EncryptionManager($registry, $default);
    }
}
