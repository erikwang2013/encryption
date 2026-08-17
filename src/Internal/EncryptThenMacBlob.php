<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Internal;

use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 加密后计算 HMAC-SHA256（encrypt-then-mac）并打包/校验载荷的公共逻辑。
 * 使用方需提供 self::PREFIX、self::IV_LEN、self::MAC_LEN、private $key 与 label()。
 */
trait EncryptThenMacBlob
{
    /** 惰性缓存：key 构造后不可变，macKey() 首次计算后复用 */
    private ?string $cachedMacKey = null;

    private function macKey(): string
    {
        return $this->cachedMacKey ??= hash_hmac('sha256', $this->key, 'dgn:enc:hmac', true);
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
