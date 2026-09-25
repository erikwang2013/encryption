<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Internal;

use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 加密后计算 HMAC-SHA256（encrypt-then-mac）并打包/校验载荷的公共逻辑。
 * 使用方需提供 self::PREFIX、self::IV_LEN、self::MAC_LEN、private $key 与 label()；
 * 可选覆写 macKeyScheme() 切换到修正后的 v2 MAC 密钥派生（默认 'v1' 不变）。
 */
trait EncryptThenMacBlob
{
    /** 惰性缓存：key 构造后不可变，macKey() 首次计算后复用 */
    private ?string $cachedMacKey = null;

    private function macKey(): string
    {
        $label = 'dgn:enc:hmac';

        // v1 把用途标签当 HMAC key（历史顺序），v2 修正为密文密钥作 HMAC key；两者结果不同，不可互解。
        return $this->cachedMacKey ??= $this->macKeyScheme() === 'v2'
            ? hash_hmac('sha256', $label, $this->key, true)
            : hash_hmac('sha256', $this->key, $label, true);
    }

    /**
     * MAC 密钥派生方案：'v1'（默认）沿用历史顺序，'v2' 使用修正顺序（密钥作 HMAC key）。
     * 使用方覆写本方法返回自己的方案；默认 'v1' 使既有构造调用与历史密文保持字节级不变。
     */
    protected function macKeyScheme(): string
    {
        return 'v1';
    }

    /**
     * 校验 MAC 密钥派生方案，供使用方构造函数调用：拼写错误不能静默退回 v1（或误升 v2）。
     */
    protected function assertMacKeyScheme(string $scheme): void
    {
        if ($scheme !== 'v1' && $scheme !== 'v2') {
            throw new EncryptionException(sprintf(
                'Unknown %s MAC key derivation scheme "%s" (expected "v1" or "v2").',
                $this->label(),
                $scheme
            ));
        }
    }

    private function packWithMac(string $iv, string $ct): string
    {
        $mac = hash_hmac('sha256', $iv . $ct, $this->macKey(), true);
        if (strlen($mac) !== self::MAC_LEN) {
            throw new EncryptionException($this->macGenerationError());
        }

        return self::PREFIX . $iv . $mac . $ct;
    }

    /**
     * @return array{0: string, 1: string, 2: string} [iv, mac, ct]
     */
    private function unpackAndVerify(string $ciphertext, string $prefix): array
    {
        if (!str_starts_with($ciphertext, $prefix)) {
            throw new EncryptionException($this->prefixError());
        }
        $blob = substr($ciphertext, strlen($prefix));
        if (strlen($blob) < self::IV_LEN + self::MAC_LEN) {
            throw new EncryptionException($this->tooShortError());
        }
        $iv = substr($blob, 0, self::IV_LEN);
        $mac = substr($blob, self::IV_LEN, self::MAC_LEN);
        $ct = substr($blob, self::IV_LEN + self::MAC_LEN);
        $expected = hash_hmac('sha256', $iv . $ct, $this->macKey(), true);
        if (!hash_equals($expected, $mac)) {
            throw new EncryptionException($this->macError());
        }

        return [$iv, $mac, $ct];
    }

    /**
     * 算法名（错误消息前缀），如 'SM4'。
     */
    abstract protected function label(): string;

    protected function prefixError(): string
    {
        return sprintf('Invalid %s ciphertext prefix.', $this->label());
    }

    protected function tooShortError(): string
    {
        return sprintf('%s ciphertext too short.', $this->label());
    }

    protected function macError(): string
    {
        return sprintf('%s MAC verification failed.', $this->label());
    }

    protected function macGenerationError(): string
    {
        return sprintf('%s HMAC generation failed.', $this->label());
    }
}
