# FilamentAdmin

基于 Laravel 13 + Filament 5 的后台管理系统。

## 技术栈

| 组件 | 版本 |
|------|------|
| PHP | 8.3.31 (php8.3-fpm) |
| Laravel | 13.12.0 |
| Filament | 5.x |
| MySQL | 8.0.46 (Docker, 端口 3380) |
| Redis | 7.0.15 (本地, 端口 6379) |
| Node.js | 20.20.2 (nvm) |
| Nginx | 1.24.0 |

## 本地访问

- **管理面板：** http://filamentadmin.local/admin
- **登录页：** http://filamentadmin.local/admin/login

> Windows 浏览器访问需在 `C:\Windows\System32\drivers\etc\hosts` 添加：
> ```
> 127.0.0.1 filamentadmin.local
> ```

## 管理员账号

| 字段 | 值 |
|------|----|
| 邮箱 | admin@admin.com |
| 密码 | *(安装时设置)* |

后台地址	http://filamentadmin.local/admin/login
用户名	admin
邮箱	admin@example.com
密码	password

## 环境配置

```env
APP_NAME=FilamentAdmin
APP_URL=http://filamentadmin.local

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3380
DB_DATABASE=filamentadmin
DB_USERNAME=root
DB_PASSWORD=123456

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=123456
REDIS_DB=15
```

## 本地安装步骤

```bash
# 1. 安装依赖（取消系统代理，避免超时）
unset HTTP_PROXY HTTPS_PROXY http_proxy https_proxy
composer install --no-interaction --prefer-dist

# 2. 复制环境配置
cp .env.example .env
php artisan key:generate

# 3. 运行数据库迁移
php artisan migrate

# 4. 创建管理员用户
php artisan make:filament-user

# 5. 构建前端资源
npm install && npm run build
```

## Nginx 配置

配置文件路径：`/etc/nginx/conf.d/filamentadmin.conf`

```nginx
server {
    listen 80;
    server_name filamentadmin.local;
    root /home/john/projects/personal/filament-admin/public;
    index index.php index.html;

    access_log /var/log/nginx/filamentadmin-access.log;
    error_log  /var/log/nginx/filamentadmin-error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ /\.(env|git) {
        deny all;
    }

    include /etc/nginx/snippets/php83.conf;
}
```

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
