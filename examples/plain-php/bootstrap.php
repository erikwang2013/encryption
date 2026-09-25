<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 纯 PHP（无框架）接入示例 —— 没有容器、没有配置文件，一个 include 就能用。
 *
 * 1. composer require erikwang2013/encryption
 * 2. 把 32 字节主密钥放进环境变量（或你自己的密钥服务）：
 *      export ENCRYPTION_MASTER_KEY="$(php -r 'echo base64_encode(random_bytes(32));')"
 *    密钥只应存在于服务端进程环境里，不要写进代码，也不要提交进仓库。
 * 3. 在入口文件（index.php / 脚本第一行）require 本文件一次即可。
 */

use Erikwang2013\Encryption\EncryptionManager;
use Erikwang2013\Encryption\EncryptionManagerFactory;
use Erikwang2013\Encryption\Exception\EncryptionException;

require __DIR__ . '/../../vendor/autoload.php';

if (!function_exists('encryption_manager')) {
    /**
     * 全局唯一的加密管理器：工厂会派生各算法子密钥并注册全部实现，
     * 因此每个进程只应构建一次，后续复用（static 缓存）。
     *
     * @throws RuntimeException 密钥缺失或长度不对
     */
    function encryption_manager(): EncryptionManager
    {
        static $manager = null;

        if ($manager === null) {
            $encoded = getenv('ENCRYPTION_MASTER_KEY');
            if (!is_string($encoded) || $encoded === '') {
                throw new RuntimeException(
                    'ENCRYPTION_MASTER_KEY is not set. Generate one with: '
                    . "php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'"
                );
            }

            $key = base64_decode($encoded, true);
            if ($key === false || strlen($key) !== 32) {
                throw new RuntimeException('ENCRYPTION_MASTER_KEY must be base64 of exactly 32 bytes.');
            }

            $manager = EncryptionManagerFactory::fromMasterKey($key, 'aes-256-gcm');
        }

        return $manager;
    }
}

if (!function_exists('encrypt_field')) {
    /**
     * 字段级加密：返回值是 base64 文本，可直接存入 VARCHAR / TEXT 字段。
     * 二进制场景（BLOB）去掉 base64 即可，载荷本身是二进制的。
     *
     * @throws EncryptionException 加密失败（key 不合法等）
     */
    function encrypt_field(string $plaintext, ?string $identifier = null): string
    {
        return base64_encode(encryption_manager()->encrypt($plaintext, $identifier));
    }
}

if (!function_exists('decrypt_field')) {
    /**
     * 与 encrypt_field() 配对使用；数据被篡改或密钥不对时抛出 EncryptionException。
     *
     * @throws EncryptionException 前缀 / 长度 / 认证标签校验失败
     */
    function decrypt_field(string $stored, ?string $identifier = null): string
    {
        $binary = base64_decode($stored, true);
        if ($binary === false) {
            throw new EncryptionException('Stored value is not valid base64.');
        }

        return encryption_manager()->decrypt($binary, $identifier);
    }
}
