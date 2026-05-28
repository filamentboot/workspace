# 认证功能使用指南

## 概述

FilamentAdmin 提供安全的管理员认证系统，支持灵活的登录方式和可选的双因素认证（2FA）。

## 功能特性

- **灵活登录**：支持用户名或邮箱登录
- **双因素认证（2FA）**：可选的 TOTP 验证
- **登录日志**：记录所有登录尝试（成功与失败）
- **速率限制**：防止暴力破解（5 次/分钟）
- **防枚举攻击**：统一的错误提示

---

## 管理员登录

### 登录方式

访问 `/admin/login`，可使用以下任一方式登录：

1. **用户名登录**
   ```
   用户名或邮箱: admin
   密码: your-password
   ```

2. **邮箱登录**
   ```
   用户名或邮箱: admin@example.com
   密码: your-password
   ```

### 速率限制

连续 5 次登录失败后，将被限制 1 分钟。请确保输入正确的凭据。

---

## 双因素认证（2FA）

### 启用 2FA

1. 登录管理面板
2. 点击右上角头像 → **个人资料**
3. 找到「双因素认证」部分
4. 点击「启用双因素认证」
5. 使用认证器应用（如 Google Authenticator、Authy）扫描 QR 码
6. 输入认证器生成的 6 位验证码确认
7. **保存恢复码**（共 8 个，请妥善保管）

### 使用 2FA 登录

启用 2FA 后，登录流程变为：

1. 输入用户名/邮箱和密码
2. 输入认证器应用生成的 6 位验证码
3. 完成登录

### 恢复码

如果无法访问认证器应用，可使用恢复码登录：

1. 在 2FA 验证页面，点击「使用恢复码」
2. 输入任意一个恢复码
3. 完成登录

**注意**：每个恢复码只能使用一次，用完后会失效。

### 禁用 2FA

1. 登录管理面板
2. 点击右上角头像 → **个人资料**
3. 找到「双因素认证」部分
4. 点击「禁用双因素认证」

---

## 登录日志

### 查看日志

登录日志功能已实现，UI 查看界面将在 Phase 2 提供。

当前可通过数据库查看：

```sql
SELECT * FROM login_logs ORDER BY created_at DESC LIMIT 10;
```

### 日志字段说明

| 字段 | 说明 |
|------|------|
| `admin_user_id` | 管理员 ID（失败登录为 null） |
| `username` | 登录使用的用户名/邮箱 |
| `status` | 登录状态（success / failed） |
| `ip_address` | 客户端 IP 地址 |
| `user_agent` | 浏览器信息 |
| `failure_reason` | 失败原因（如 invalid_credentials） |
| `created_at` | 登录时间 |

---

## 常见问题

### Q: 忘记密码怎么办？

A: Phase 1 暂未实现密码重置功能，请联系系统管理员重置密码。

### Q: 可以同时使用 username 和 email 登录吗？

A: 是的，系统会自动识别输入的是 username 还是 email。

### Q: 2FA 是强制的吗？

A: 不是。Phase 1 中 2FA 默认关闭，用户可自行选择启用。

### Q: 丢失 2FA 恢复码怎么办？

A: 请联系系统管理员在数据库中重置 2FA 设置：

```sql
UPDATE admin_users
SET two_factor_secret = NULL,
    two_factor_recovery_codes = NULL,
    two_factor_confirmed_at = NULL
WHERE id = <user_id>;
```

---

## 安全建议

1. **使用强密码**：至少 12 位，包含大小写字母、数字和符号
2. **启用 2FA**：显著提升账户安全性
3. **妥善保管恢复码**：打印或存储在安全位置
4. **定期检查登录日志**：发现异常登录及时处理
5. **不共享账号**：每个管理员使用独立账号

---

## 技术细节

### 认证守卫

系统使用独立的 `admin` guard，与普通用户认证隔离。

配置文件：`config/auth.php`

### 数据表

- `admin_users`: 管理员账号表
- `login_logs`: 登录日志表

### 事件监听

登录日志通过监听 Laravel 原生事件实现：

- `Illuminate\Auth\Events\Login`: 登录成功
- `Illuminate\Auth\Events\Failed`: 登录失败

---

## 下一步

- Phase 2 将提供登录日志 UI 查看界面
- Phase 3 将实现密码重置功能
- Phase 4 将支持基于角色的 2FA 强制策略
