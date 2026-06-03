# API 响应格式

所有 API 接口均返回统一的 JSON 格式。

## 成功响应

```json
{
  "success": true,
  "message": "操作成功",
  "data": { ... }
}
```

## 分页响应

```json
{
  "success": true,
  "message": "获取成功",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7,
    "from": 1,
    "to": 15
  },
  "links": {
    "first": "https://example.com/api/v1/...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

## 错误响应

```json
{
  "success": false,
  "message": "用户名或密码错误",
  "error_code": 2003,
  "data": null
}
```

校验错误的 `data` 字段包含具体错误详情：

```json
{
  "success": false,
  "message": "请求参数校验失败",
  "error_code": 1001,
  "data": {
    "account": ["账号或邮箱 不能为空"],
    "password": ["密码 不能为空"]
  }
}
```

## HTTP 状态码

| 场景 | HTTP 状态码 |
|------|------------|
| 成功 | 200 |
| 资源已创建 | 201 |
| 参数校验失败 | 422 |
| 未认证 | 401 |
| 无权限 | 403 |
| 资源不存在 | 404 |
| 服务器错误 | 500 |
