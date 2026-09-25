<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 直接运行：php examples/plain-php/demo.php
 * 演示字段级加密的完整闭环：加密 → 存储 → 读取 → 解密 → 篡改检测。
 * 未设置 ENCRYPTION_MASTER_KEY 时，本次运行会临时生成一把演示用密钥。
 */
$generatedForDemo = getenv('ENCRYPTION_MASTER_KEY') === false;
$demoKey = '';
if ($generatedForDemo) {
    $demoKey = base64_encode(random_bytes(32));
    putenv('ENCRYPTION_MASTER_KEY=' . $demoKey);
}

require __DIR__ . '/bootstrap.php';

echo "纯 PHP 接入示例 / vanilla PHP example\n";
echo str_repeat('-', 60), "\n";

if ($generatedForDemo) {
    echo "本次运行未检测到 ENCRYPTION_MASTER_KEY，已临时生成演示密钥：\n";
    echo '  export ENCRYPTION_MASTER_KEY=', $demoKey, "\n";
    echo "生产环境请自行生成并妥善保管，丢失将无法解密历史数据。\n\n";
}

$phone = '13800000000';

// 1. 加密后存库（这里用一个数组模拟数据表）
$row = ['phone' => encrypt_field($phone)];
echo "1. 明文           : {$phone}\n";
echo '2. 落库（base64） : ', substr($row['phone'], 0, 44), "…\n";
echo '   长度           : ', strlen($row['phone']), " 字符\n";

// 3. 从库里读出来解密
echo "3. 解密还原       : ", decrypt_field($row['phone']), "\n";

// 4. 换个算法标识——同一个 Manager，无需改业务逻辑
$row['phone_sm4'] = encrypt_field($phone, 'sm4-cbc');
echo "4. SM4 加密存储   : ", substr($row['phone_sm4'], 0, 44), "…\n";
echo '   解密还原       : ', decrypt_field($row['phone_sm4'], 'sm4-cbc'), "\n";

// 5. 改动密文任意一字节 → 认证标签校验失败，抛 EncryptionException
$binary = base64_decode($row['phone'], true);
$last = strlen($binary) - 1;
$binary[$last] = chr(ord($binary[$last]) ^ 0xFF);
try {
    decrypt_field(base64_encode($binary));
    echo "5. 篡改检测       : 未捕获异常（不应发生）\n";
} catch (EncryptionException $e) {
    echo "5. 篡改检测       : 已被拒绝 — ", $e->getMessage(), "\n";
}

echo "\n密钥来自环境变量，代码与仓库里都没有密钥；密文自带算法版本前缀（v1），\n";
echo "换算法或轮换密钥时可以逐条解密再重新加密。\n";
