# erikwang2013/encryption

**语言 / Languages:** [English](README.md) | **简体中文** | [한국어](docs/i18n/ko/README.md) | [Русский](docs/i18n/ru/README.md) | [Deutsch](docs/i18n/de/README.md) | [Français](docs/i18n/fr/README.md) | [Español](docs/i18n/es/README.md) | [Português](docs/i18n/pt/README.md) | [हिन्दी](docs/i18n/hi/README.md) | [العربية](docs/i18n/ar/README.md) | [বাংলা](docs/i18n/bn/README.md) | [Bahasa Indonesia](docs/i18n/id/README.md) | [日本語](docs/i18n/ja/README.md) | [全部语言 / All translations](docs/i18n/README.md)

<p align="center">
  <img src="./docs/mascot.svg" alt="项目宠物 Locky：一只拿着金色钥匙的小锁" width="150" height="150">
</p>

可插拔密码学组件库：在统一契约下提供**对称加密**、**非对称加密**、**哈希**、**密钥派生**（HKDF / PBKDF2），并包含 AES/Sodium、国密 SM2/SM3/SM4/ZUC 等实现，支持 Composer 安装。

**Locky** 就是上面这只小锁，也是本项目的宠物：每个算法各持一把子密钥，每次加密都用新的 IV，钥匙孔从不泄露秘密。宿主项目也可以展示它：`Mascot::svg()` 返回 SVG 内容（可内联进 HTML），`Mascot::ascii()` 返回终端横幅，`Mascot::NAME` 即 `Locky`。

## 项目说明

### 这是什么

`erikwang2013/encryption` 是一个纯 PHP 密码学组件库，为 PHP 应用提供**类型安全、可扩展**的加解密、哈希与密钥派生能力。它不与任何框架耦合，可在 Laravel、ThinkPHP、Hyperf、webman 或纯 PHP 项目中独立使用。

下文给出了[项目结构](#项目结构)，以及[架构设计](#架构概览)、[功能设计](#功能设计)、[请求生命周期](#请求生命周期)三张 SVG 图；图源文件位于 [`docs/`](./docs)。

### 解决什么问题

PHP 生态中密码学方案长期分散：Laravel 有自己的 `Crypt`，国密 SM2/SM3/SM4 在 Composer 上缺乏统一封装，HKDF/PBKDF2 等派生算法缺少统一接口。本库在**单一契约体系**下收敛了主流对称/非对称/哈希/KDF 算法，使得：

- **业务层只需面对接口**，切换算法不改业务代码
- **国密与 AES/Sodium 同等待遇**，注册后通过同一 Manager 调用
- **密钥管理规范化**：一键从主密钥派生各算法子密钥，避免密钥复用
- **安全默认值**：认证加密（GCM / encrypt-then-MAC）、随机 IV、常量时间比较等开箱即用

### 适用场景

- 字段级加解密（手机号、身份证号等敏感字段入库前加密）
- 多算法并存与平滑迁移（如从 AES-256-CBC 迁移到 AES-256-GCM）
- 需要国密合规的中后台系统（SM2 非对称、SM3 哈希、SM4 对称、ZUC 流密码）
- API 签名与验证（HMAC / SHA-256 / SM3）
- 基于口令或主密钥的子密钥派生（PBKDF2 / HKDF）

## 目录

- [框架兼容性](#框架兼容性)
- [各框架接入方式](#各框架接入方式)
- [快速开始](#快速开始)
- [架构概览](#架构概览)
- [功能设计](#功能设计)
- [请求生命周期](#请求生命周期)
- [环境要求](#环境要求)
- [安装](#安装)
- [内置算法与标识](#内置算法与标识)
- [使用说明](#使用说明)
- [项目结构](#项目结构)
- [常见问题](#常见问题)
- [安全建议](#安全建议)
- [运行单元测试](#运行单元测试)
- [许可证](#许可证)

---

## 框架兼容性

本库**不依赖**任何 Web 框架，仅以 Composer 包形式提供类与自动加载；在业务项目中 `composer require erikwang2013/encryption` 即可，与路由、容器、配置方式无关。

前提为 **PHP ≥ 8.0** 且满足下文「环境要求」中的扩展与依赖。在此前提下，下列框架版本均可使用（与框架自带加密组件并行，按需注入 `EncryptionManager` 等即可）：

| 框架 | 说明 |
|------|------|
| **Laravel** 7 / 8 / 9 / 10 / 11 | 在 **PHP 8.0+** 的运行环境中安装；仅当 Laravel 7 仍停留在 PHP 7.x 时才不满足本库 PHP 约束，需先升级运行环境。 |
| **ThinkPHP** 6 / 8 | 在 TP 应用的标准 `composer.json` 中 `require` 本包即可。 |
| **Hyperf** 2 / 3 | 在 Hyperf 服务的 `composer.json` 中引入；按 Hyperf 习惯可在 `config` 或工厂类中注册单例。 |
| **webman** 1 / 2 | 在 webman 项目根目录执行 `composer require`，在业务类或 `support` 辅助函数中直接使用。 |

### 各框架接入方式

本库**不提供** Laravel ServiceProvider、ThinkPHP 行为扩展等专用封装；接入方式是在各框架的**依赖注入容器**或**单例工厂**中注册 `EncryptionManager`（或其它 Manager），由配置或环境变量提供主密钥（32 字节）后再构造实例。下文为最小示例，**密钥来源请按你方安全规范**（`.env`、KMS、配置中心等），勿直接写死在代码里。

**Laravel（`App\Providers\AppServiceProvider` 或独立 ServiceProvider）**

```php
use Erikwang2013\Encryption\EncryptionManagerFactory;

public function register(): void
{
    $this->app->singleton(\Erikwang2013\Encryption\EncryptionManager::class, function () {
        $raw = config('app.custom_master_key'); // 例如 base64 存 32 字节
        $master = is_string($raw) ? base64_decode($raw, true) : '';
        if ($master === false || strlen($master) !== 32) {
            throw new \RuntimeException('Invalid 32-byte master key.');
        }
        return EncryptionManagerFactory::fromMasterKey($master, 'aes-256-gcm');
    });
}
```

容器解析：`app(\Erikwang2013\Encryption\EncryptionManager::class)`。与 Laravel 自带的 `Crypt` / `encrypt()` **互不替代**：前者用于字段级、多算法注册表场景；后者用于框架序列化与 Cookie 等。

**ThinkPHP 6 / 8（服务类或 `common.php` 中工厂函数）**

```php
use Erikwang2013\Encryption\EncryptionManagerFactory;

function app_encryption_manager(): \Erikwang2013\Encryption\EncryptionManager
{
    static $mgr = null;
    if ($mgr === null) {
        $master = base64_decode(config('app.master_key'), true);
        $mgr = EncryptionManagerFactory::fromMasterKey($master, 'aes-256-gcm');
    }
    return $mgr;
}
```

也可在 `app\service` 下定义 `EncryptionService` 并在控制器中注入，便于单测 Mock。

**Hyperf 2 / 3（`config/autoload/dependencies.php` 或注解工厂）**

```php
use Erikwang2013\Encryption\EncryptionManager;
use Erikwang2013\Encryption\EncryptionManagerFactory;

return [
    EncryptionManager::class => function () {
        $master = base64_decode((string) config('encryption.master_key'), true);
        return EncryptionManagerFactory::fromMasterKey($master, 'aes-256-gcm');
    },
];
```

注意协程环境下若密钥来自远程配置，缓存解析结果即可。

**webman 1 / 2**

在 `config/plugin.php` / 自定义 `bootstrap` 或 `support/bootstrap.php` 中把 `EncryptionManager` 挂到全局 `support` 容器（若使用），或直接在需要的服务类构造函数里用 `EncryptionManagerFactory::fromMasterKey(...)` 构造；webman 无强制容器约定，**以项目现有组织方式为准**。

**原生 PHP（无框架）**

没有容器可挂：在项目根目录 `composer require` 后，构建一次管理器并复用即可。可直接运行的同款示例见 [`examples/plain-php/`](examples/plain-php)——`php examples/plain-php/demo.php` 会打印完整的「加密 → 存储 → 读取 → 解密 → 篡改检测」流程。

```php
// bootstrap.php —— 在入口文件里 require 一次
use Erikwang2013\Encryption\EncryptionManagerFactory;

$raw = getenv('ENCRYPTION_MASTER_KEY');            // 32 字节随机密钥的 base64
$key = is_string($raw) ? base64_decode($raw, true) : false;
if ($key === false || strlen($key) !== 32) {
    throw new RuntimeException('ENCRYPTION_MASTER_KEY 必须是 32 字节的 base64。');
}
$manager = EncryptionManagerFactory::fromMasterKey($key, 'aes-256-gcm');

// 项目任意位置
$stored = base64_encode($manager->encrypt($phone));   // 存入 TEXT 字段
$phone  = $manager->decrypt(base64_decode($stored));  // 读取后解密
```

```bash
# 密钥只生成一次，放在服务端环境变量里，不要写进代码
export ENCRYPTION_MASTER_KEY="$(php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;')"
```

纯 PHP 项目注意事项：工厂是「重」操作（要派生各算法子密钥并注册全部实现），每个进程构建一次并复用实例，不要每次查询都构造；密钥放在进程环境变量或你自己的密钥服务里并做好备份——丢失即无法解密历史数据；读取时捕获 `EncryptionException`，只记服务端日志，不要把失败原因回显给客户端；密文是二进制，文本字段存 `base64_encode(...)`，二进制字段直接存原始 blob。

### 与本库无关的说明

- 框架版本升级（如 Laravel 10 → 11）一般**不需要**改本库 API；若 Composer 提示 PHP 版本冲突，以本库 `composer.json` 中 `php` 约束为准。
- 国密 **SM2** 需 **`ext-gmp`**；未安装时相关类在运行时会失败，与框架种类无关。

---

## 快速开始

1. 在业务项目根目录执行：`composer require erikwang2013/encryption:^1.0`（或你发布的版本约束）。
2. 确认 `php -v` 为 **8.0+**，且已启用 `openssl`；若使用 `sodium-xchacha20` 或 SM2，按需安装 `sodium`、`gmp` 扩展。
3. 在代码中 `use Erikwang2013\Encryption\...`，按下文「使用说明」选择 `EncryptionManager`、哈希或 KDF 等类即可。

---

## 架构概览

按能力划分为四类契约，每类对应独立注册表与可选门面（`*Manager`），便于组合与单元测试。四类结构完全一致——契约接口 → 注册表 → 门面 → 具体实现，其中对称加解密一族可由 `EncryptionManagerFactory` 用同一个主密钥一键装配。

![架构设计图：业务代码调用门面，门面通过注册表按标识解析实现，所有实现都满足对应契约；工厂负责按算法派生子密钥](./docs/architecture-design.svg)

图源：[`docs/architecture-design.svg`](./docs/architecture-design.svg)

| 能力 | 契约 | 注册表 | 门面（默认算法） |
|------|------|--------|------------------|
| 对称加解密 | `SymmetricCipherInterface`（`EncryptorInterface` 为其别名） | `EncryptorRegistry` | `EncryptionManager` |
| 非对称加解密 | `AsymmetricCipherInterface` | `AsymmetricCipherRegistry` | `AsymmetricCryptoManager` |
| 哈希 | `HasherInterface` | `HasherRegistry` | `HashingManager` |
| 密钥派生（IKM） | `KeyDerivationInterface` | `KeyDerivationRegistry` | `KeyDerivationManager` |
| 口令派生密钥 | `PasswordBasedKdfInterface` | `PasswordBasedKdfRegistry` | `PasswordBasedKdfManager` |

设计要点：

- **对称**：实例绑定固定密钥，载荷为二进制；适合批量字段加解密。
- **非对称**：每次调用传入公钥/私钥字符串（格式由实现约定，如 SM2 十六进制）。
- **哈希**：单向摘要，无密钥（或 SM3 等同标准杂凑）。
- **密钥派生**：**HKDF** 用于已有高熵密钥材料扩展子密钥；**PBKDF2** 用于从人类口令拉伸出密钥（需随机盐与高迭代次数）。

---

## 功能设计

六类能力，各自内置下列算法标识。新增算法只需“实现契约 + 一次 `register()` 调用”，核心代码不动，业务层继续只依赖接口。

![功能设计图：对称加密、非对称加密、哈希、密钥派生、口令派生与国密六类能力，以及设计原则、安全默认值与扩展步骤](./docs/functional-design.svg)

图源：[`docs/functional-design.svg`](./docs/functional-design.svg)

| 能力族 | 标识 | 用途 |
|--------|------|------|
| 对称加密 | `aes-256-gcm`、`sodium-xchacha20`、`aes-256-cbc-hmac`、`sm4-cbc`、`zuc-128` | 任意长度的字段级加解密，一个实例绑定一把密钥 |
| 非对称加密 | `sm2` | 每次调用传入公钥/私钥（十六进制），需要 `ext-gmp` |
| 哈希 | `sha256`、`sm3` | 单向摘要，用于签名与完整性校验 |
| 密钥派生（IKM） | `hkdf-sha256` | 将高熵密钥材料扩展为按用途区分的子密钥 |
| 口令派生密钥 | `pbkdf2-sha256` | 从人类口令拉伸密钥（随机盐，默认 310 000 次迭代） |
| 国密 | SM2 / SM3 / SM4 / ZUC | 通过同一套契约调用；SM1 / SM7 / SM9 抛出 `UnsupportedNationalAlgorithmException` |

---

## 请求生命周期

引导（bootstrap）每个进程只做一次，加解密才是每请求的热路径。每条密文都带版本前缀（`v1`），因此今天写入的数据在轮换密钥后仍可解密。

![请求生命周期图：主密钥 → 派生子密钥 → 注册实现 → 随机 IV 加密 → 落库 → 按标识解析 → 常量时间校验 → 解密，并给出轮换与异常分支](./docs/lifecycle.svg)

图源：[`docs/lifecycle.svg`](./docs/lifecycle.svg)

1. **准备密钥**：从 `.env` 或 KMS 取 32 字节主密钥，长度不符会直接抛异常。
2. **派生与注册**：`EncryptionManagerFactory::fromMasterKey()` 用 HMAC-SHA256（各算法使用不同 info 标签）派生一把子密钥，并一次性注册全部加密实现。
3. **加密**：`$manager->encrypt($data, 'aes-256-gcm')`，每次调用生成新的随机 IV/nonce，并对密文计算 tag 或 MAC。
4. **存储**：二进制载荷 `v1 | IV | tag/MAC | 密文` 存入 `BLOB` 字段，文本场景可先 `base64_encode`。
5. **解密**：存储的算法标识决定用哪个实现，随后校验前缀与长度、以常量时间比对 tag/MAC，最后返回明文；任一环节失败都抛 `EncryptionException`。

---

## 环境要求

| 项目 | 说明 |
|------|------|
| PHP | `^8.0`（与上述框架组合时，以本约束为准） |
| 扩展 | `ext-openssl`（必需） |
| 扩展 | `ext-sodium`（可选，用于 `sodium-xchacha20`） |
| 扩展 | `ext-gmp`（可选，**SM2** 加解密与密钥生成） |
| Composer | `pohoc/crypto-sm`（已作为依赖，提供 SM2/SM3/SM4 封装） |

## 安装

### 从本地路径（开发）

在业务项目 `composer.json` 中增加：

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/绝对路径/encryption"
        }
    ],
    "require": {
        "erikwang2013/encryption": "@dev"
    }
}
```

执行：

```bash
composer update erikwang2013/encryption
```

### 从 Git / Packagist（发布后）

```bash
composer require erikwang2013/encryption:^1.0
```

（需将本库推送到可访问的 Git 仓库并配置 Composer 源，或发布到 Packagist。）

---

## 内置算法与标识

### 对称加密（`SymmetricCipherInterface`）

| 标识 (`getIdentifier`) | 类 | 密钥长度 | 说明 |
|------------------------|-----|----------|------|
| `aes-256-gcm` | `Aes256GcmEncryptor` | 32 字节 | 认证加密，推荐新系统默认 |
| `sodium-xchacha20` | `SodiumXChaCha20Encryptor` | 32 字节 | 需 `ext-sodium` |
| `aes-256-cbc-hmac` | `OpenSslAes256CbcEncryptor` | 32 字节 | CBC + HMAC，兼容旧环境 |
| `sm4-cbc` | `Sm4CbcEncryptor` | 16 字节 | 国密 SM4-CBC（OpenSSL SM4） |
| `zuc-128` | `ZucEncryptor` | 16 字节 | ZUC-128 流密码 |

### 非对称加密（`AsymmetricCipherInterface`）

| 标识 | 类 | 说明 |
|------|-----|------|
| `sm2` | `Sm2AsymmetricCipher` | 国密 SM2；密钥与密文为十六进制；需 `ext-gmp` |

（亦可继续使用静态门面 `Sm2EncryptionService`，与 `Sm2AsymmetricCipher` 行为一致。）

### 哈希（`HasherInterface`）

| 标识 | 类 | 输出长度 |
|------|-----|----------|
| `sha256` | `Sha256Hasher` | 32 字节 |
| `sm3` | `Sm3Hasher` | 32 字节 |

### 密钥派生

| 标识 | 类 | 契约 | 说明 |
|------|-----|------|------|
| `hkdf-sha256` | `HkdfSha256` | `KeyDerivationInterface` | RFC 5869，基于 IKM + salt + info |
| `pbkdf2-sha256` | `Pbkdf2Sha256` | `PasswordBasedKdfInterface` | 口令 + 盐 + 迭代次数（构造参数） |

密文/摘要多为二进制字符串；若需存入 JSON/文本，请自行 `base64_encode` / `base64_decode`。

---

## 使用说明

### 1. 对称加密：单算法与注册表

```php
<?php

use Erikwang2013\Encryption\Encryptor\Aes256GcmEncryptor;
use Erikwang2013\Encryption\EncryptionManager;
use Erikwang2013\Encryption\EncryptorRegistry;

$key = random_bytes(32);
$encryptor = new Aes256GcmEncryptor($key);
$ciphertext = $encryptor->encrypt('明文');
$plaintext  = $encryptor->decrypt($ciphertext);

$registry = new EncryptorRegistry(new Aes256GcmEncryptor($key));
$manager = new EncryptionManager($registry, 'aes-256-gcm');
$blob = $manager->encrypt('数据');
```

主密钥工厂：`EncryptionManagerFactory::fromMasterKey($masterKey32, 'aes-256-gcm')` 从主密钥派生各算法独立子密钥，一次性注册 **aes-256-gcm**、**aes-256-cbc-hmac**、**sm4-cbc**、**zuc-128**，以及 **sodium-xchacha20**（需 ext-sodium 可用）。

### 2. 非对称加密

```php
<?php

use Erikwang2013\Encryption\Asymmetric\Sm2AsymmetricCipher;
use Erikwang2013\Encryption\AsymmetricCipherRegistry;
use Erikwang2013\Encryption\AsymmetricCryptoManager;
use Erikwang2013\Encryption\Guomi\Sm2EncryptionService;

// 需 ext-gmp
$pair = Sm2EncryptionService::generateKeyPairHex();

$cipher = new Sm2AsymmetricCipher();
$hexCipher = $cipher->encrypt('明文', $pair->getPublicKey());
$plain = $cipher->decrypt($hexCipher, $pair->getPrivateKey());

$mgr = new AsymmetricCryptoManager(new AsymmetricCipherRegistry($cipher), 'sm2');
$hexCipher2 = $mgr->encrypt('明文', $pair->getPublicKey());
```

### 3. 哈希

```php
<?php

use Erikwang2013\Encryption\Hash\Sha256Hasher;
use Erikwang2013\Encryption\Guomi\Sm3Hasher;
use Erikwang2013\Encryption\HasherRegistry;
use Erikwang2013\Encryption\HashingManager;

$registry = new HasherRegistry(
    new Sha256Hasher(),
    new Sm3Hasher(),
);
$hashing = new HashingManager($registry, 'sha256');
$bin = $hashing->digest('数据');
$hex = $hashing->digestHex('数据', 'sm3');
```

### 4. 密钥派生（HKDF / PBKDF2）

```php
<?php

use Erikwang2013\Encryption\Kdf\HkdfSha256;
use Erikwang2013\Encryption\Kdf\Pbkdf2Sha256;
use Erikwang2013\Encryption\KeyDerivationManager;
use Erikwang2013\Encryption\KeyDerivationRegistry;
use Erikwang2013\Encryption\PasswordBasedKdfManager;
use Erikwang2013\Encryption\PasswordBasedKdfRegistry;

// 从高熵材料派生子密钥（如 TLS、信封加密中的子密钥）
$hkdf = new HkdfSha256();
$subKey = $hkdf->derive($ikm32, $salt, 32, 'app:v1');

$kdfMgr = new KeyDerivationManager(new KeyDerivationRegistry($hkdf), 'hkdf-sha256');

// 从用户口令派生密钥（存储密码哈希时请改用 password_hash / Argon2 等专用 API）
$pbkdf2 = new Pbkdf2Sha256(iterations: 310_000);
$derived = $pbkdf2->deriveFromPassword('用户口令', random_bytes(16), 32);

$pwdMgr = new PasswordBasedKdfManager(new PasswordBasedKdfRegistry($pbkdf2), 'pbkdf2-sha256');
```

### 5. 国密（SM3 / SM4 / ZUC / SM2）

```php
<?php

use Erikwang2013\Encryption\Guomi\Sm2EncryptionService;
use Erikwang2013\Encryption\Guomi\Sm3Hasher;
use Erikwang2013\Encryption\Guomi\Sm4CbcEncryptor;
use Erikwang2013\Encryption\Guomi\ZucEncryptor;

$sm3 = new Sm3Hasher();
$bin = $sm3->digest('数据');

$key16 = random_bytes(16);
$sm4 = new Sm4CbcEncryptor($key16);
$blob = $sm4->encrypt('明文');

$zuc = new ZucEncryptor($key16);
$blob2 = $zuc->encrypt('明文');

// SM2：见上文非对称示例或 Sm2EncryptionService
```

SM1、SM7、SM9：`UnavailableNationalAlgorithms::sm1()` 等会抛出 `UnsupportedNationalAlgorithmException`。

### 6. 自定义插件

- 对称：实现 `EncryptorInterface`（即 `SymmetricCipherInterface`），注册到 `EncryptorRegistry`。
- 非对称：实现 `AsymmetricCipherInterface`，注册到 `AsymmetricCipherRegistry`。
- 哈希：实现 `HasherInterface`，注册到 `HasherRegistry`。
- KDF：实现 `KeyDerivationInterface` 或 `PasswordBasedKdfInterface`，注册到对应 `Registry`。

### 7. 异常

失败时抛出 `Erikwang2013\Encryption\Exception\EncryptionException`；国密不可用算法为 `UnsupportedNationalAlgorithmException`。业务层应捕获并记录，勿向前端泄露细节。

---

## 项目结构

```text
encryption/
├── src/
│   ├── Contract/                    能力契约接口（对外唯一依赖）：
│   │                                SymmetricCipherInterface（别名 EncryptorInterface）、
│   │                                AsymmetricCipherInterface、HasherInterface、
│   │                                KeyDerivationInterface、PasswordBasedKdfInterface
│   ├── Encryptor/                   Aes256GcmEncryptor、OpenSslAes256CbcEncryptor、
│   │                                SodiumXChaCha20Encryptor
│   ├── Asymmetric/                  Sm2AsymmetricCipher
│   ├── Hash/                        Sha256Hasher
│   ├── Kdf/                         HkdfSha256、Pbkdf2Sha256
│   ├── Guomi/                       Sm2EncryptionService、Sm3Hasher、Sm4CbcEncryptor、
│   │                                ZucEncryptor、UnavailableNationalAlgorithms
│   │   └── Internal/ZucEngine.php   ZUC 密钥流引擎
│   ├── Internal/                    EncryptThenMacBlob trait（共用 encrypt-then-MAC）
│   ├── Exception/                   EncryptionException、
│   │                                UnsupportedNationalAlgorithmException
│   ├── AbstractRegistry.php         标识 → 实现的存储基类，各注册表共用
│   ├── EncryptorRegistry.php        AsymmetricCipherRegistry.php、HasherRegistry.php、
│   │                                KeyDerivationRegistry.php、PasswordBasedKdfRegistry.php
│   ├── EncryptionManager.php        AsymmetricCryptoManager.php、HashingManager.php、
│   │                                KeyDerivationManager.php、PasswordBasedKdfManager.php
│   ├── EncryptionManagerFactory.php 主密钥 → 各算法子密钥 → 注册表
│   └── Mascot.php                   项目宠物 API（SVG / ASCII，供宿主展示，不参与加解密）
├── tests/                           PHPUnit 测试：契约、注册表、门面与算法用例，
│                                    以及 TestCase 辅助基类
├── docs/                            项目宠物与设计图
│   ├── mascot.svg                   项目宠物（Locky）
│   ├── architecture-design.svg      「架构概览」章节引用
│   ├── functional-design.svg        「功能设计」章节引用
│   ├── lifecycle.svg                「请求生命周期」章节引用
│   ├── i18n/                        本 README 的另外 12 种语言版本，
│   │                                每种语言各带一份本地化设计图（含 labels/*.json）
│   └── *.md                         评审 / 测试报告存档
├── examples/plain-php/              可直接运行的纯 PHP 接入示例（bootstrap + demo）
├── scripts/i18n-build-svg.php       由词条字典生成 docs/i18n/<lang>/*.svg
├── composer.json                    psr-4 自动加载、PHP ^8.0、phpunit 开发依赖
├── phpunit.xml.dist
└── README.md  README.zh-CN.md
```

| 路径 | 说明 |
|------|------|
| `src/Contract/` | 各类能力接口（`EncryptorInterface`、`HasherInterface` 等） |
| `src/Encryptor/`、`src/Asymmetric/`、`src/Hash/`、`src/Kdf/` | 具体算法实现 |
| `src/Guomi/` | 国密相关实现与 `UnavailableNationalAlgorithms` |
| `src/Internal/` | `EncryptThenMacBlob`，CBC / SM4 / ZUC 共用的 encrypt-then-MAC 逻辑 |
| `src/Exception/` | `EncryptionException` 等 |
| `*Registry.php`、`*Manager.php`、`EncryptionManagerFactory.php` | 注册表与门面、主密钥工厂 |
| `docs/*.svg` | 项目宠物与本文引用的设计图 |

命名空间前缀：`Erikwang2013\Encryption\`，与 Composer `psr-4` 一致。

---

## 常见问题

**Composer 提示 PHP 版本不满足**

本库要求 `php ^8.0`。若业务项目仍使用 PHP 8.0 或更低，需先升级运行环境，或勿使用本包。

**`sodium-xchacha20` 不可用**

安装并启用 PHP 扩展 `sodium`（`ext-sodium`）。未启用时 `EncryptionManagerFactory::fromMasterKey(..., 'sodium-xchacha20')` 会报错，可改用默认 `aes-256-gcm`。

**SM2 报错或无法生成密钥**

安装并启用 **`ext-gmp`**。SM2 依赖大数运算，无 GMP 时无法保证完整功能。

**密文要存数据库 / JSON**

二进制字段可直接存 `BLOB`；若只能存文本，对密文与 IV 等做 **`base64_encode`**，解密前再 `base64_decode`。

**与 Laravel `encrypt()` / `Crypt` 的区别**

Laravel 封装主要用于框架内序列化与 Cookie 等；本库面向**显式算法标识、多注册表、国密与 HKDF/PBKDF2** 等场景，二者可并存，不要混用同一密钥约定除非你自己对齐格式。

---

## 安全建议

1. **密钥**：高熵密钥使用 `random_bytes()` 或 KMS；口令必须先经 **PBKDF2 / Argon2** 等派生，勿直接作为 AES 密钥。
2. **算法**：新系统优先 **AES-256-GCM** 或 **Sodium**；国密用 **SM3/SM4/ZUC/SM2**；**HKDF** 用于子密钥扩展，**PBKDF2** 仅作口令拉伸时需足够迭代与随机盐。
3. **传输**：仍建议使用 TLS；本库提供字段级加解密与摘要。
4. **迁移**：用 `identifier` 区分算法版本，便于解密旧数据后重加密。

---

## 运行单元测试

克隆本仓库后：

```bash
composer install
composer test
```

等价于执行 `./vendor/bin/phpunit tests/`。若已为项目添加 `phpunit.xml`，可将 `composer.json` 中 `test` 脚本改为使用配置文件。

---

## 开源不易，欢迎支持

| 微信 | 支付宝 |
|:---:|:---:|
| <img src="./docs/weixinpay.png" alt="微信" width="130" height="130" /> | <img src="./docs/alipay.png" alt="支付宝" width="130" height="130" /> |

---

## 许可证

MIT（见 `composer.json` 中 `license` 字段）。
