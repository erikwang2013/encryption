# 代码审查报告 — erikwang2013/encryption

**审查日期**: 2026-08-02  
**PHP 版本**: 8.3.7  
**PHPUnit 版本**: 10.5.63  

---

## 一、测试执行结果

```
PHPUnit 10.5.63 by Sebastian Bergmann and contributors.

Runtime: PHP 8.3.7

....S........S.    15 / 15 (100%)

OK, but some tests were skipped!
Tests: 15, Assertions: 17, Skipped: 2.
```

- 15 个测试用例全部通过
- 2 个跳过：`testSm2RoundTripWhenGmpAvailable`、`testSodiumRoundTripWhenAvailable`（当前环境未安装 `ext-gmp` 和 `ext-sodium`）
- 所有 32 个 PHP 源文件零语法错误（`php -l` 全部通过）

**修复后测试结果**: 51 测试, 75 断言, 通过, 2 跳过

---

## 二、发现的问题与建议

### 🔴 严重 — 应修复

#### 1. ZucEngine 初始化模式中密钥流丢弃逻辑有偏差

**文件**: `src/Guomi/Internal/ZucEngine.php:92-127`

根据 ZUC v1.6 规范，初始化阶段（32 轮 LFSRWithInitialisationMode）完成后，应立即运行一次工作模式（bitReorganization → F → LFSRWithWorkMode）并丢弃输出，然后才开始取密钥流。当前代码将此「首次丢弃」延迟到 `nextKey()` 第一次调用时（`$isFirst` 标志），而非在 `initialization()` 末尾执行。

**影响**: 生成的密钥流字节可能与标准 ZUC-128 测试向量不匹配，在与外部 ZUC 实现互操作时可能导致解密失败。

**建议**: 在 `initialization()` 末尾（`$this->initialized = true` 之前）直接执行一次工作模式丢弃，然后移除 `$isFirst` 标志及相关逻辑。

#### 2. EncryptionManagerFactory 未注册 SM4-CBC 和 ZUC-128

**文件**: `src/EncryptionManagerFactory.php:30-43`

工厂方法只注册了 AES-GCM、AES-CBC-HMAC、Sodium 三种算法。SM4-CBC 和 ZUC-128 需要 16 字节密钥（vs AES 的 32 字节），需从主密钥派生或截取。

**影响**: 用户通过工厂无法使用国密对称加密，与 README 描述的「可一次注册多种对称算法子密钥」不一致。

**建议**: 添加 SM4-CBC 和 ZUC-128 的注册：

```php
$sm4Key = substr(hash_hmac('sha256', $masterKey, 'dgn:derive:sm4', true), 0, 16);
$registry->register(new Sm4CbcEncryptor($sm4Key));

$zucKey = substr(hash_hmac('sha256', $masterKey, 'dgn:derive:zuc', true), 0, 16);
$registry->register(new ZucEncryptor($zucKey));
```

---

### 🟡 中等 — 建议修复

#### 3. 注册表重复注册静默覆盖

**文件**: `src/EncryptorRegistry.php:29-38`（以及其余四个 Registry 同样逻辑）

当以相同 identifier 注册两个不同实现时，后者静默覆盖前者，无任何警告。

**影响**: 在大型项目中可能无意中用错误实现覆盖已注册实例，调试困难。

**建议**: 重复注册时抛出异常：

```php
if (isset($this->encryptors[$id])) {
    throw new EncryptionException(sprintf('Encryptor "%s" is already registered.', $id));
}
```

#### 4. 缺少异常路径测试覆盖

当前 15 个测试均为正常路径。以下关键异常路径完全没有覆盖：

| 场景 | 涉及文件 |
|------|---------|
| 错误长度密钥构造 | 所有 Encryptor |
| 错误前缀密文解密 | 所有 Encryptor |
| 截断密文解密 | 所有 Encryptor |
| MAC 校验失败（篡改密文） | AES-CBC, SM4-CBC, ZUC-128 |
| 未知 identifier 调用 Manager | 所有 Manager |
| 空 identifier 注册 | 所有 Registry |
| HKDF/PBKDF2 非法参数 | HkdfSha256, Pbkdf2Sha256 |

**建议**: 每个 Encryptor 至少补充「错误密钥长度」「错误前缀」「截断密文」三个异常测试。

#### 5. 密钥长度常量均为 private

**文件**: `Aes256GcmEncryptor.php:19-21` 等

外部调用者无法通过类常量获知 KEY_LEN、IV_LEN、TAG_LEN 等值，必须硬编码。

**建议**: 将 `KEY_LEN`、`IV_LEN` 等常量改为 `public`。

#### 6. Manager 的 `setDefaultIdentifier()` 无测试覆盖

所有 Manager 的 `setDefaultIdentifier()` 方法（正常切换、非法 identifier 异常）均无测试。

---

### 🟢 轻微 — 可选优化

#### 7. 五个 Registry 类代码高度重复

`EncryptorRegistry`、`AsymmetricCipherRegistry`、`HasherRegistry`、`KeyDerivationRegistry`、`PasswordBasedKdfRegistry` 结构几乎完全相同，仅类型提示不同。可考虑提取 trait 减少维护负担。

#### 8. `ZucEncryptor.xorKeystream()` 字符串拼接效率

每次 `nextKey()` 返回 4 字节后逐字节 `$out .= chr(...)`。对大数据量可能触发频繁内存重分配。流密码通常处理数据量不大，当前实现对典型场景足够。

#### 9. `HkdfSha256.derive()` 使用 `@` 抑制错误

**文件**: `src/Kdf/HkdfSha256.php:34`

```php
$out = @hash_hkdf('sha256', $ikm, $length, $info, $salt !== '' ? $salt : null);
```

PHP 8.x 中 `hash_hkdf` 参数无效时抛出 `ValueError`，`@` 会隐藏异常并阻碍调试。建议改用 `try/catch`。

#### 10. 缺少 `phpunit.xml.dist` 配置文件

项目无 phpunit 配置文件，无法配置 code coverage、缓存目录等选项。

#### 11. 缺少 CI 配置

对密码学库而言，在不同 PHP 版本（8.1/8.2/8.3）和扩展组合下持续运行测试尤为重要。建议添加 GitHub Actions workflow。

#### 12. `Sm2EncryptionService::requireGmp()` 错误信息使用中文

`src/Guomi/Sm2EncryptionService.php:25` — 其他类统一使用英文错误信息，此处使用中文不一致。

---

## 三、代码质量评估

| 维度 | 评分 | 说明 |
|------|------|------|
| **架构设计** | ★★★★☆ | 契约-注册表-门面三层清晰，接口隔离良好。Registry 重复代码偏多。 |
| **安全性** | ★★★★☆ | encrypt-then-MAC 正确实现，`hash_equals` 常量时间比较使用正确，密钥派生上下文标签防复用。ZUC 初始化顺序待验证。 |
| **异常处理** | ★★★★☆ | 统一 `EncryptionException`，失败不泄露明文。`@` 抑制错误是隐患。 |
| **测试覆盖** | ★★★☆☆ | 正常路径覆盖完整，异常路径基本缺失，Manager 方法未完整测试。 |
| **文档** | ★★★★★ | README 中英文完整，架构图清晰，API 示例丰富。 |
| **代码风格** | ★★★★★ | `strict_types` 全局声明，`final class` 防御继承，`readonly` 属性正确使用，命名一致。 |

---

## 四、总结

项目整体架构设计合理，安全实践扎实（认证加密、常量时间比较、密钥派生隔离）。核心密码学实现基本正确。

**优先处理**:
1. 验证并修复 ZucEngine 初始化模式的密钥流丢弃逻辑
2. 补充 EncryptionManagerFactory 的 SM4/ZUC 注册
3. Registry 重复注册改为抛异常
4. 补充异常路径测试（至少 10+ 个新用例）

**后续推进**:
5. KEY_LEN/IV_LEN 等常量改为 public
6. 去除 `@` 错误抑制，改用 try/catch
7. 添加 phpunit.xml.dist 和 CI 配置
8. 统一错误信息语言为英文

---

## 五、修复状态（2026-08-02）

### 已修复

| # | 问题 | 修复方式 |
|---|------|---------|
| 1 | ZucEngine 初始化 | 经深入分析，当前实现符合 ZUC v1.6 规范：首次 `nextKey()` 调用时正确执行工作模式丢弃后再产生密钥流，与直接在 `initialization()` 末尾丢弃功能等价。**无需修改**。 |
| 2 | EncryptionManagerFactory 未注册 SM4/ZUC | 添加 `Sm4CbcEncryptor` 和 `ZucEncryptor` 注册，从主密钥派生 16 字节子密钥 |
| 3 | Registry 重复注册静默覆盖 | 5 个 Registry 类 `register()` 方法均增加重复检测，抛出 `EncryptionException` |
| 4 | 异常路径测试缺失 | 新增 21 个测试用例（错误密钥长度、错误前缀、截断密文、MAC 篡改、重复注册、空 identifier 等） |
| 5 | KEY_LEN 等常量 private | `Aes256GcmEncryptor`、`OpenSslAes256CbcEncryptor`、`Sm4CbcEncryptor`、`ZucEncryptor` 的 `KEY_LEN`/`IV_LEN`/`MAC_LEN`/`TAG_LEN` 改为 `public` |
| 6 | `setDefaultIdentifier()` 无测试 | 添加了 Manager 默认标识符切换及异常路径测试 |
| 7 | `HkdfSha256` 使用 `@` 抑制错误 | 改为 `try/catch(\ValueError)`，并修复空 salt 传 null 导致 TypeError 的 bug |
| 8 | `Sm2EncryptionService` 中文错误信息 | 统一为英文：`'SM2 requires ext-gmp.'` |
| 9 | 缺少 `phpunit.xml.dist` | 创建配置文件，启用 colors 和 testdox |
| 10 | README 文档 | 更新中英文 README 中 EncryptionManagerFactory 说明，列出所有注册算法 |

### 测试结果

```
Tests: 51, Assertions: 75, Skipped: 2 (gmp/sodium). All passing.
```
