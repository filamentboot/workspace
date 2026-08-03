# Filamentboot 部署与运维指南

## 目录

- [环境概况](#环境概况)
- [自动部署（Gitee Go CI/CD）](#自动部署)
- [手动部署](#手动部署)
- [回滚操作](#回滚操作)
- [日常运维命令](#日常运维命令)
- [故障排查](#故障排查)

---

## 环境概况

| 项目 | 值 |
|------|----|
| 服务器系统 | Debian 12 |
| 应用目录 | `/data/filament-admin/` |
| 域名 | `https://www.xitongapp.com` |
| PHP 版本 | 8.4-fpm（`www-data` 用户运行） |
| 数据库 | MariaDB 10.11，库名 `Filamentboot` |
| 缓存/队列 | Redis，DB 0 |
| Web 服务器 | Nginx 1.22 |
| SSL 证书 | Let's Encrypt，acme.sh 自动续期 |
| 队列进程 | Supervisor，服务名 `filament-admin-worker`，2 个进程 |
| Composer | `/usr/local/bin/composer2`（2.x） |

---

## 自动部署

### 触发条件

向 `main` 分支推送代码时，Gitee Go `MasterPipeline` 自动触发。

### 流程说明

```
push to main
    └─ Gitee Go MasterPipeline
        └─ build@general 容器（Ubuntu）
            └─ 写入 SSH 私钥
            └─ ssh root@服务器
                └─ bash /data/filament-admin/deploy.sh
```

`deploy.sh` 在服务器上依次执行：

1. 记录当前 commit（供回滚参考）
2. `php artisan down`（开启维护模式）
3. `git pull origin main`（拉取最新代码）
4. `composer2 install --no-dev`（安装依赖，忽略缺失的 intl 扩展）
5. `php artisan migrate --force`（执行数据库迁移）
6. `php artisan storage:link`（确保 public/storage 软链存在，媒体库走 public 磁盘）
7. `chown/chmod storage bootstrap/cache`（修复 www-data 写权限）
8. `php artisan config:cache && route:cache && view:cache`（刷新缓存）
9. `php artisan filament:optimize`（缓存 Filament 组件与 Blade 图标）
10. `supervisorctl restart filament-admin-worker`（重启队列进程）
11. `php artisan up`（关闭维护模式）

> `php artisan filament:optimize` = `filament:cache-components` + `icons:cache`。
> 若改用 `php artisan optimize`（Laravel 全量），Filament 已通过 `optimizes()` 钩子自动带上这一条，无需重复调用；
> 本脚本用的是分列 cache 命令，所以必须显式写出来。
>
> **注意**：组件缓存会固化 Resource / Page 清单。启用或停用插件后需执行
> `php artisan filament:optimize-clear`（或 `php artisan optimize:clear`）重建，否则新插件的界面不会出现。

### Gitee Go 配置

通用变量（「通用变量」标签页配置，不提交到代码库）：

| 变量名 | 说明 |
|--------|------|
| `DEPLOY_HOST` | 服务器 IP |
| `DEPLOY_USER` | SSH 登录用户（`root`） |
| `DEPLOY_KEY` | SSH 私钥内容（完整 PEM 格式，含换行） |

引用方式：`${DEPLOY_HOST}`

### 流水线文件位置

```
.workflow/
├── master-pipeline.yml   ← 触发 main push，执行 SSH 部署（CD）
├── branch-pipeline.yml   ← 触发 feature/* 等分支 push（CI 编译检查）
└── pr-pipeline.yml       ← PR 触发（CI 检查）
```

**注意**：`branch-pipeline.yml` 已配置排除 `main` 和 `master`，避免与 CD 流水线重复触发。

---

## 手动部署

当 CI/CD 不可用或需要紧急上线时，SSH 登录后手动执行：

```bash
ssh root@<服务器IP>
bash /data/filament-admin/deploy.sh
```

或分步执行（适合排查问题）：

```bash
cd /data/filament-admin

php artisan down

git pull origin main

COMPOSER_ALLOW_SUPERUSER=1 composer2 install \
  --no-dev --optimize-autoloader --no-interaction \
  --ignore-platform-req=ext-intl

php artisan migrate --force

php artisan storage:link

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize

supervisorctl restart filament-admin-worker

php artisan up
```

---

## 回滚操作

### 查看可用版本

```bash
# 查看最近 10 个 commit
git -C /data/filament-admin log --oneline -10

# 查看所有 Tag
git -C /data/filament-admin tag -l
```

### 执行回滚

```bash
ssh root@<服务器IP>

# 回滚到指定 commit 或 tag
bash /data/filament-admin/rollback.sh <commit-hash 或 tag>

# 示例：
bash /data/filament-admin/rollback.sh d9fb5fd
bash /data/filament-admin/rollback.sh v1.0.0
```

### 回滚脚本说明

`rollback.sh` 执行流程：

1. `php artisan down`（维护模式）
2. `git fetch --tags && git checkout <目标版本>`
3. `composer2 install --no-dev`
4. 刷新三类缓存
5. 重启队列进程
6. `php artisan up`

### 重要提醒

- 回滚**不会**自动回滚数据库迁移。若目标版本的迁移文件与当前数据库结构不兼容，需手动执行 `php artisan migrate:rollback`。
- 执行删除列的迁移后，数据无法通过脚本恢复，需提前备份。
- 回滚后如有问题，可再次执行 `deploy.sh` 重新部署到最新版本。

---

## 日常运维命令

### 查看站点状态

```bash
curl -sI https://www.xitongapp.com/admin/login | head -3
```

### 查看队列进程

```bash
supervisorctl status filament-admin-worker
```

### 查看实时日志

```bash
tail -f /data/filament-admin/storage/logs/laravel.log
```

### 查看 Nginx 日志

```bash
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log
```

### 查看 PHP-FPM 状态

```bash
systemctl status php8.4-fpm
```

### 手动清除所有缓存

```bash
cd /data/filament-admin
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### 重启关键服务

```bash
systemctl restart php8.4-fpm
systemctl restart nginx
supervisorctl restart filament-admin-worker
```

---

## 故障排查

### 站点返回 500

1. 查看 Laravel 日志：
   ```bash
   tail -30 /data/filament-admin/storage/logs/laravel.log
   ```
2. 常见原因：
   - `storage/` 权限不对（PHP-FPM 是 `www-data`，目录须可写）：
     ```bash
     chown -R www-data:www-data /data/filament-admin/storage /data/filament-admin/bootstrap/cache
     chmod -R 775 /data/filament-admin/storage /data/filament-admin/bootstrap/cache
     ```
   - config cache 过时，重新生成：
     ```bash
     cd /data/filament-admin && php artisan config:cache
     ```
   - `.env` 中含 `#` 的密码未加引号（`#` 会被识别为注释）：
     ```
     DB_PASSWORD="含#号的密码"   # 必须加引号
     ```

### MasterPipeline 未触发

1. 到 Gitee Go「流水线」标签检查 `MasterPipeline` 是否已注册。
2. 若未注册，点击「新建流水线」→ 选择「从代码库导入」→ 选 `.workflow/master-pipeline.yml`。
3. 检查「通用变量」中 `DEPLOY_HOST`、`DEPLOY_USER`、`DEPLOY_KEY` 是否已配置。
4. 检查 `.workflow/master-pipeline.yml` 的触发条件是否含 `main` 分支。
5. 手动点击「执行构建」验证 SSH 步骤是否正常。

### SSH Key 写入异常（部署密钥问题）

私钥含换行，`echo` 处理多行变量可能丢失换行，改用：

```bash
printf '%s\n' "${DEPLOY_KEY}" > ~/.ssh/deploy_key
```

若仍有问题，将私钥 base64 编码后存入变量：

```bash
# 本地编码
base64 -w 0 ~/.ssh/filament_deploy

# 流水线解码
echo "${DEPLOY_KEY_B64}" | base64 -d > ~/.ssh/deploy_key
```

### 服务器 PHP 扩展缺失（php8.4-intl、php8.4-bcmath）

sury.org 源 CDN 证书过期，安装命令：

```bash
# 跳过证书验证安装
apt-get -o Acquire::https::Verify-Peer=false install -y php8.4-intl php8.4-bcmath
```

或等待 sury.org 证书恢复后直接安装。当前 `composer2 install` 通过 `--ignore-platform-req=ext-intl` 绕过此限制。
