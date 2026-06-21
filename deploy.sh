#!/bin/bash
set -e

# 使用方式：bash /data/filamentboot/deploy.sh
# 约定：只在 /data/filamentboot/ 目录下执行，拉取 main 分支最新代码
# 触发方式：push main 经 .workflow/master-pipeline.yml SSH 调起本脚本

cd /data/filamentboot

echo "[deploy] 记录当前 commit（供回滚参考）"
PREV_COMMIT=$(git rev-parse --short HEAD)
echo "[deploy] 当前 commit：$PREV_COMMIT"

echo "[deploy] 开启维护模式"
php artisan down

echo "[deploy] 拉取最新代码"
git pull origin main

echo "[deploy] 安装生产依赖"
COMPOSER_ALLOW_SUPERUSER=1 composer2 install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-intl

echo "[deploy] 构建前端资产"
# 注意：构建工具链（vite / @tailwindcss/vite / laravel-vite-plugin / tailwindcss）位于
# devDependencies，故必须装全部依赖（不可 --omit=dev），否则 npm run build 因缺 vite 失败。
npm ci
npm run build

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
supervisorctl restart filamentboot-worker:*

echo "[deploy] 关闭维护模式"
php artisan up

echo "[deploy] 完成：$(date)，上一个 commit：$PREV_COMMIT"
