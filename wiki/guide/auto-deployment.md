# FilamentAdmin 自动部署指南

## 概述

本项目使用 **Gitee Webhook + PHP 端点** 实现代码推送到 `main` 分支时自动部署到生产服务器。

## 架构

```
开发者 push → Gitee → Webhook → 服务器 PHP 端点 → deploy.sh → 自动部署
```

## 服务器信息

| 项目 | 配置 |
|------|------|
| 服务器 IP | 118.25.27.49 |
| 操作系统 | Debian 12 |
| 代码目录 | /data/filament-admin/ |
| 域名 | www.xitongapp.com |
| PHP 版本 | 8.4-fpm |
| Web 服务器 | Nginx 1.22 |
| 数据库 | MariaDB 10.11 |
| 缓存 | Redis |
| 队列 | Supervisor (filament-admin-worker) |

## 部署流程

### 1. 触发条件
- Push 代码到 `main` 分支
- Gitee 自动发送 Webhook 请求到服务器

### 2. Webhook 端点
- **URL**: `https://www.xitongapp.com/deploy-webhook.php?token=ea2477cb66ce98e48754deb262ca3393`
- **文件**: `/data/filament-admin/public/deploy-webhook.php`
- **Token**: 存储在 `/data/filament-admin/.webhook_token`

### 3. 验证逻辑
1. Token 验证（URL 参数或 POST 数据）
2. 事件类型验证（仅处理 Push Hook）
3. 分支验证（仅处理 main/master 分支）

### 4. 部署步骤
`deploy.sh` 执行以下操作：
1. 记录当前 commit（供回滚参考）
2. 开启维护模式
3. Git pull 最新代码
4. Composer install（生产依赖）
5. PHP artisan migrate --force
6. PHP artisan config:cache / route:cache / view:cache
7. 修复目录权限（storage、bootstrap/cache）
8. 重启 Queue Worker
9. 关闭维护模式

## 配置文件

### Webhook 配置
- Gitee 项目设置 → WebHook → 已配置
- URL: `https://www.xitongapp.com/deploy-webhook.php?token=ea2477cb66ce98e48754deb262ca3393`
- 触发事件: Push
- 状态: 激活

### 权限配置
- 代码目录所有者: www-data:www-data
- SSH 密钥: /var/www/.ssh/id_ed25519（www-data 可访问）
- Supervisor: www-data 可通过 sudo 无密码执行 supervisorctl
- PHP 执行时间: 300 秒

## 操作方案

### 正常部署
```bash
# 本地开发完成后
git add .
git commit -m "feat: 新功能"
git push origin main
# 等待 30-60 秒，自动部署完成
```

### 手动触发部署
```bash
curl -sk -X POST \
  -H "Content-Type: application/json" \
  -H "X-Gitee-Event: Push Hook" \
  -d '{"ref":"refs/heads/main","head_commit":{"id":"manual"}}' \
  "https://www.xitongapp.com/deploy-webhook.php?token=ea2477cb66ce98e48754deb262ca3393"
```

### 检查部署状态
```bash
# SSH 登录服务器
ssh -i ~/.ssh/filament_deploy root@118.25.27.49

# 查看最新 commit
cd /data/filament-admin && git log --oneline -3

# 查看部署日志
tail -f /var/log/nginx/access.log | grep webhook
```

### 回滚操作
```bash
# SSH 登录服务器
ssh -i ~/.ssh/filament_deploy root@118.25.27.49

# 执行回滚脚本（接受 Tag 或 commit hash）
cd /data/filament-admin
bash rollback.sh v1.0.0
# 或
bash rollback.sh abc1234
```

## 故障排查

### 部署失败
1. 检查 Webhook 响应（Gitee WebHook 页面查看历史记录）
2. 检查服务器日志：`tail -100 /var/log/nginx/error.log`
3. 手动执行部署脚本：`cd /data/filament-admin && bash deploy.sh`

### 维护模式未关闭
```bash
ssh -i ~/.ssh/filament_deploy root@118.25.27.49
cd /data/filament-admin && php artisan up
```

### 权限问题
```bash
ssh -i ~/.ssh/filament_deploy root@118.25.27.49
chown -R www-data:www-data /data/filament-admin
chmod -R 775 /data/filament-admin/storage /data/filament-admin/bootstrap/cache
```

## 安全注意事项

1. **Token 安全**: 当前 Token 暴露在 URL 中，建议后续改用 Header 验证
2. **SSH 密钥**: www-data 用户拥有 Gitee 仓库访问权限，需定期审计
3. **sudo 权限**: www-data 仅可执行 supervisorctl，不可执行其他命令
4. **Webhook 验证**: 仅处理 main/master 分支，其他分支请求被忽略

## 更新记录

| 日期 | 更新内容 |
|------|----------|
| 2026-06-01 | 初始配置完成，Webhook 自动部署上线 |
