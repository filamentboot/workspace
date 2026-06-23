# Phase 1 部署清单

## 环境要求

- PHP 8.2+
- MySQL 8.0+ / PostgreSQL 14+
- Composer 2.x
- Node.js 18+（用于 Filament 静态资源）

## 部署步骤

### 1. 代码部署

```bash
git clone <repository-url>
cd filament-admin
composer install --no-dev --optimize-autoloader
```

### 2. 环境配置

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env` 文件：

```env
APP_NAME=Filamentboot
APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=filament_admin
DB_USERNAME=root
DB_PASSWORD=
```

### 3. 数据库迁移

```bash
php artisan migrate --force
```

### 4. 创建管理员账号

**生产环境请勿使用 Seeder**，手动创建：

```bash
php artisan tinker
>>> $user = \App\Models\AdminUser::create([
...     'username' => 'admin',
...     'email' => 'admin@yourdomain.com',
...     'name' => '系统管理员',
...     'password' => 'secure-password-here',
...     'email_verified_at' => now(),
... ]);
>>> exit
```

### 5. 缓存优化

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 6. 文件权限

```bash
chmod -R 755 storage bootstrap/cache
```

### 7. Web 服务器配置

Nginx 示例配置：

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/filament-admin/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 8. 验证部署

访问 `https://yourdomain.com/admin/login`：

- [ ] 登录页正常显示
- [ ] 可使用 username 登录
- [ ] 可使用 email 登录
- [ ] 登录成功后进入 Dashboard
- [ ] 个人资料页可访问
- [ ] 可启用/禁用 2FA

---

## 安全检查

- [ ] `APP_DEBUG=false` 已设置
- [ ] 数据库凭据安全
- [ ] 文件权限正确（不是 777）
- [ ] HTTPS 已启用
- [ ] 默认密码已修改
- [ ] `.env` 文件未提交到 Git

---

## 回滚计划

如需回滚：

```bash
php artisan migrate:rollback --step=2
```

这将回滚 `admin_users` 和 `login_logs` 表的迁移。

---

## 监控指标

部署后监控以下指标：

- 登录成功率
- 失败登录次数
- 速率限制触发次数
- 平均响应时间

---

## 故障排查

### 登录页 404

检查 Web 服务器配置，确保路由正确。

### 数据库连接失败

检查 `.env` 中的数据库配置。

### 2FA QR 码不显示

确保 `two_factor_*` 字段已存在于 `admin_users` 表。

---

## 联系方式

如遇问题，请联系开发团队。
