# 安装指南

> **注意**：当前为 Skeleton 开发阶段，安装方式为 Clone 仓库。Library 包发布后将支持 `composer require`。

## 环境要求

| 软件 | 版本要求 |
|------|---------|
| PHP | ^8.3 |
| Laravel | ^13.0 |
| MySQL | ^8.0 |
| Node.js | ^20.0（构建前端资源）|
| Composer | ^2.0 |

## 安装步骤

### 1. Clone 项目

```bash
git clone https://github.com/your-org/filament-admin.git
cd filament-admin
```

### 2. 安装依赖

```bash
# 先取消代理（避免 Composer 超时）
unset HTTP_PROXY HTTPS_PROXY http_proxy https_proxy

composer install
npm install
```

### 3. 配置环境

```bash
cp .env.example .env
php artisan key:generate
```

修改 `.env` 数据库配置：

```env
DB_HOST=127.0.0.1
DB_PORT=3380
DB_DATABASE=filamentadmin
DB_USERNAME=root
DB_PASSWORD=123456
```

### 4. 创建数据库

```bash
mysql -uroot -p123456 -h127.0.0.1 -P3380 -e "CREATE DATABASE filamentadmin"
# 测试库（运行测试需要）
mysql -uroot -p123456 -h127.0.0.1 -P3380 -e "CREATE DATABASE filamentadmin_test"
```

### 5. 执行数据库迁移与初始化

```bash
php artisan migrate
php artisan db:seed
```

### 6. 创建初始超级管理员

```bash
php artisan make:admin-user
# 按提示输入账号、邮箱、密码
```

### 7. 构建前端资源

```bash
npm run build
```

### 8. 配置 Nginx 虚拟主机

将 `filamentadmin.local` 指向项目 `public/` 目录。访问 `http://filamentadmin.local/admin` 即可进入后台。

## 常见问题

**Q: Composer install 超时**
A: 执行 `unset HTTP_PROXY HTTPS_PROXY http_proxy https_proxy` 后重试。

**Q: 测试失败，报数据库连接错误**
A: 确认 `filamentadmin_test` 数据库已创建，MySQL 端口为 3380。

**Q: 2FA 二维码无法显示**
A: 确认 Redis 已启动（`127.0.0.1:6379`，密码 `123456`）。
