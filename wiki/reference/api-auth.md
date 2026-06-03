# API 认证

FilamentAdmin 管理员 API 使用 Laravel Sanctum Bearer Token 认证。

## 获取 Token

**POST** `/api/v1/admin/login`

请求体：

```json
{
  "account": "admin",
  "password": "your-password"
}
```

成功响应：

```json
{
  "success": true,
  "message": "登录成功",
  "data": {
    "access_token": "1|abc123...",
    "token_type": "Bearer",
    "expires_at": "2026-06-03T10:00:00+08:00"
  }
}
```

## 使用 Token

在请求头中携带：

```
Authorization: Bearer {access_token}
```

## 获取当前用户

**GET** `/api/v1/admin/me`

需要认证。返回当前管理员信息。

## 登出

**DELETE** `/api/v1/admin/logout`

撤销当前 Token，登出后该 Token 立即失效。

## Token 有效期

默认 **24 小时**，到期后需重新登录获取新 Token。
