# Gitee Webhook 自动部署教程

本教程适用于任何需要部署到自有服务器的 PHP/Laravel 项目。按照以下步骤操作，可实现代码推送到 main 分支时自动部署。

## 前置条件

- 服务器：Linux（Debian/Ubuntu/CentOS）
- Web 服务器：Nginx + PHP-FPM
- 项目：Git 仓库托管在 Gitee
- SSH 访问：可 SSH 登录服务器

## 步骤 1：服务器准备

### 1.1 创建部署用户和目录

```bash
# SSH 登录服务器
ssh root@your-server-ip

# 创建项目目录（替换 your-project 为实际项目名称）
mkdir -p /data/your-project
chown -R www-data:www-data /data/your-project
chmod -R 775 /data/your-project
```

### 1.2 配置 SSH 密钥

```bash
# 生成 SSH 密钥（如果还没有）
ssh-keygen -t ed25519 -C "deploy@your-project"

# 将公钥添加到 Gitee 部署密钥
cat ~/.ssh/id_ed25519.pub

# 复制密钥到 www-data 用户（PHP-FPM 运行用户）
mkdir -p /var/www/.ssh
cp ~/.ssh/id_ed25519 /var/www/.ssh/id_ed25519
cp ~/.ssh/id_ed25519.pub /var/www/.ssh/id_ed25519.pub
chown -R www-data:www-data /var/www/.ssh
chmod 700 /var/www/.ssh
chmod 600 /var/www/.ssh/id_ed25519
chmod 644 /var/www/.ssh/id_ed25519.pub

# 添加 Gitee 主机密钥
ssh-keyscan gitee.com >> /var/www/.ssh/known_hosts 2>/dev/null
chown www-data:www-data /var/www/.ssh/known_hosts
chmod 644 /var/www/.ssh/known_hosts
```

### 1.3 配置 Git 安全目录

```bash
# 为 www-data 配置 git safe.directory
su -s /bin/bash www-data -c "git config --global --add safe.directory /data/your-project"

# 创建 www-data 的 .gitconfig 目录
mkdir -p /var/www
chown www-data:www-data /var/www
```

### 1.4 配置 Supervisor 权限（如果使用队列）

```bash
# 允许 www-data 无密码执行 supervisorctl
echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl" > /etc/sudoers.d/www-data-supervisor
chmod 440 /etc/sudoers.d/www-data-supervisor
visudo -c  # 验证配置
```

### 1.5 增加 PHP 执行时间

```bash
# 创建 PHP 配置（根据你的 PHP 版本调整路径）
echo "max_execution_time = 300" > /etc/php/8.4/fpm/conf.d/99-webhook.ini
echo "max_input_time = 300" >> /etc/php/8.4/fpm/conf.d/99-webhook.ini
systemctl reload php8.4-fpm
```

## 步骤 2：创建部署脚本

### 2.1 创建 deploy.sh

在项目根目录创建 `deploy.sh`：

```bash
#!/bin/bash
set -e

cd /data/your-project

echo "[deploy] 记录当前 commit（供回滚参考）"
PREV_COMMIT=$(git rev-parse HEAD)
echo "[deploy] 当前 commit: $PREV_COMMIT"

echo "[deploy] 开启维护模式"
php artisan down --refresh=60 --secret="your-maintenance-secret"

echo "[deploy] 拉取最新代码"
git pull origin main

echo "[deploy] 安装生产依赖"
composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-intl

echo "[deploy] 执行数据库迁移"
php artisan migrate --force

echo "[deploy] 修复目录权限"
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "[deploy] 刷新缓存"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[deploy] 重启 Queue Worker"
sudo supervisorctl restart your-worker-name:*

echo "[deploy] 关闭维护模式"
php artisan up

echo "[deploy] 完成：$(date)，上一个 commit: $PREV_COMMIT"
```

### 2.2 设置权限

```bash
chmod +x /data/your-project/deploy.sh
chown www-data:www-data /data/your-project/deploy.sh
```

### 2.3 创建回滚脚本

创建 `rollback.sh`：

```bash
#!/bin/bash
set -e

cd /data/your-project

if [ -z "$1" ]; then
    echo "用法: bash rollback.sh <tag-or-commit>"
    exit 1
fi

TARGET=$1

echo "[rollback] 回滚到: $TARGET"

echo "[rollback] 开启维护模式"
php artisan down --refresh=60

echo "[rollback] 切换到目标版本"
git reset --hard $TARGET

echo "[rollback] 安装生产依赖"
composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-intl

echo "[rollback] 执行数据库迁移"
php artisan migrate --force

echo "[rollback] 修复目录权限"
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "[rollback] 刷新缓存"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[rollback] 重启 Queue Worker"
sudo supervisorctl restart your-worker-name:*

echo "[rollback] 关闭维护模式"
php artisan up

echo "[rollback] 完成：$(date)"
```

## 步骤 3：创建 Webhook 端点

### 3.1 生成 Token

```bash
# 生成随机 token
openssl rand -hex 16
# 记录输出，例如：ea2477cb66ce98e48754deb262ca3393
```

### 3.2 保存 Token

```bash
# 将 token 保存到文件（替换为你的 token）
echo "your-generated-token" > /data/your-project/.webhook_token
chown www-data:www-data /data/your-project/.webhook_token
chmod 640 /data/your-project/.webhook_token
```

### 3.3 创建 PHP 端点

在 `public/deploy-webhook.php` 创建文件：

```php
<?php
/**
 * Gitee Webhook 自动部署脚本
 * 
 * 安全验证：
 * 1. Token 验证（URL 参数或 POST 数据）
 * 2. 事件类型验证（仅处理 Push Hook）
 * 3. 分支验证（仅处理 main/master 分支）
 */

$secret = trim(file_get_contents("/data/your-project/.webhook_token"));
$token = $_GET["token"] ?? $_POST["token"] ?? "";

if ($token !== $secret) {
    http_response_code(403);
    exit(json_encode(["error" => "Invalid token"]));
}

$body = file_get_contents("php://input");
$data = json_decode($body, true);

$ref = $data["ref"] ?? "";
$event = $_SERVER["HTTP_X_GITEE_EVENT"] ?? "";

if ($event !== "Push Hook" && $event !== "push") {
    http_response_code(400);
    exit(json_encode(["error" => "Invalid event type"]));
}

if (strpos($ref, "refs/heads/main") === false && strpos($ref, "refs/heads/master") === false) {
    http_response_code(200);
    exit(json_encode(["message" => "Ignored non-main branch"]));
}

$output = [];
$returnCode = 0;
$deployScript = "/data/your-project/deploy.sh";

if (!file_exists($deployScript)) {
    http_response_code(500);
    exit(json_encode(["error" => "Deploy script not found"]));
}

exec("cd /data/your-project && bash deploy.sh 2>&1", $output, $returnCode);

if ($returnCode === 0) {
    http_response_code(200);
    exit(json_encode([
        "message" => "Deploy success",
        "commit" => $data["head_commit"]["id"] ?? "unknown",
        "output" => implode("\n", array_slice($output, -5))
    ]));
} else {
    http_response_code(500);
    exit(json_encode([
        "error" => "Deploy failed",
        "output" => implode("\n", array_slice($output, -10))
    ]));
}
```

### 3.4 设置权限

```bash
chmod 644 /data/your-project/public/deploy-webhook.php
chown www-data:www-data /data/your-project/public/deploy-webhook.php
```

## 步骤 4：配置 Gitee Webhook

1. 打开 Gitee 项目 → **管理** → **WebHooks**
2. 点击 **添加 WebHook**
3. 填写配置：
   - **URL**: `https://your-domain.com/deploy-webhook.php?token=your-generated-token`
   - **密码**: 留空（我们用 token 验证）
   - **触发事件**: 勾选 **Push**
   - **激活**: 勾选
4. 点击 **添加**

## 步骤 5：测试部署

### 5.1 手动测试

```bash
curl -sk -X POST \
  -H "Content-Type: application/json" \
  -H "X-Gitee-Event: Push Hook" \
  -d '{"ref":"refs/heads/main","head_commit":{"id":"test"}}' \
  "https://your-domain.com/deploy-webhook.php?token=your-generated-token"
```

### 5.2 推送代码测试

```bash
# 本地推送代码
git add .
git commit -m "test: 触发 Webhook 部署"
git push origin main
```

### 5.3 检查部署状态

```bash
# SSH 登录服务器
ssh root@your-server-ip

# 查看最新 commit
cd /data/your-project && git log --oneline -3

# 查看站点状态
curl -sk -o /dev/null -w "HTTP Status: %{http_code}\n" https://your-domain.com
```

## 步骤 6：初始化部署（首次）

如果是新项目，需要先手动初始化：

```bash
ssh root@your-server-ip

cd /data/your-project

# 克隆项目
git clone git@gitee.com:username/project.git .

# 安装依赖
composer install --no-dev --optimize-autoloader

# 配置环境
cp .env.example .env
# 编辑 .env 配置数据库等

# 生成密钥
php artisan key:generate

# 执行迁移
php artisan migrate --force

# 修复权限
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 缓存配置
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 常见问题

### 1. 权限被拒绝

```bash
# 修复目录所有权
chown -R www-data:www-data /data/your-project
chmod -R 775 /data/your-project/storage /data/your-project/bootstrap/cache

# 修复 SSH 密钥权限
chown -R www-data:www-data /var/www/.ssh
chmod 700 /var/www/.ssh
chmod 600 /var/www/.ssh/id_ed25519
```

### 2. Git pull 失败

```bash
# 添加 safe.directory
su -s /bin/bash www-data -c "git config --global --add safe.directory /data/your-project"

# 检查 SSH 连接
su -s /bin/bash www-data -c "ssh -T git@gitee.com"
```

### 3. Composer 失败

```bash
# 检查 PHP 版本
php -v

# 清除 composer 缓存
su -s /bin/bash www-data -c "composer clear-cache"

# 忽略平台要求
composer install --ignore-platform-req=ext-intl
```

### 4. Supervisor 权限问题

```bash
# 检查 sudo 配置
visudo -c

# 测试 www-data 执行 supervisorctl
su -s /bin/bash www-data -c "sudo supervisorctl status"
```

### 5. 维护模式未关闭

```bash
# 手动关闭
cd /data/your-project && php artisan up

# 检查 deploy.sh 是否包含 php artisan up
tail -5 /data/your-project/deploy.sh
```

## 安全建议

1. **Token 管理**: 定期更换 token，不要提交到仓库
2. **HTTPS**: 确保 Webhook 端点使用 HTTPS
3. **IP 白名单**: 在 Nginx 配置中限制 Webhook 端点仅允许 Gitee IP 访问
4. **日志监控**: 定期检查 Webhook 执行日志
5. **备份**: 部署前自动备份数据库和文件

## Nginx 配置示例（可选）

```nginx
# 限制 Webhook 端点访问
location = /deploy-webhook.php {
    # 仅允许 Gitee IP（根据实际 IP 段调整）
    allow 116.228.0.0/16;  # Gitee IP 段示例
    deny all;
    
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## 更新 PHP 版本时的注意事项

如果服务器 PHP 版本变更，需要更新：

1. PHP-FPM 配置路径：`/etc/php/X.X/fpm/conf.d/`
2. Nginx fastcgi_pass：`unix:/run/php/phpX.X-fpm.sock`
3. Composer 平台要求：`--ignore-platform-req=ext-xxx`

## 扩展：多项目部署

如果有多个项目，可以：

1. 为每个项目生成不同的 token
2. 创建独立的 deploy.sh 和 webhook 端点
3. 使用不同的子域名或路径区分

示例：
- `https://project1.com/deploy-webhook.php?token=token1`
- `https://project2.com/deploy-webhook.php?token=token2`
