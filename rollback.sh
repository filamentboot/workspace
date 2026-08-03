#!/bin/bash
set -e

# 使用方式：bash /data/filament-admin/rollback.sh <Tag或Commit>
# 示例：bash rollback.sh v0.1.0-一期
# 注意：如本次部署执行了删除列的迁移，需手动处理数据，不能直接回滚代码

if [ -z "$1" ]; then
    echo "[rollback] 错误：请提供回滚目标（Tag 或 Commit Hash）"
    echo "[rollback] 用法：bash rollback.sh <tag-or-commit>"
    echo "[rollback] 示例：bash rollback.sh v0.1.0-一期"
    exit 1
fi

TARGET=$1
cd /data/filament-admin

echo "[rollback] 回滚目标：$TARGET"
echo "[rollback] 开启维护模式"
php artisan down

echo "[rollback] 切换到目标版本"
git fetch --tags
git checkout "$TARGET"

echo "[rollback] 安装对应版本依赖"
COMPOSER_ALLOW_SUPERUSER=1 composer2 install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-intl

echo "[rollback] 刷新缓存"
php artisan config:cache
php artisan route:cache
php artisan view:cache
# 回滚同样要重建 Filament 组件与图标缓存，否则沿用的是回滚前版本的组件清单
php artisan filament:optimize

echo "[rollback] 重启 Queue Worker"
supervisorctl restart filament-admin-worker

echo "[rollback] 关闭维护模式"
php artisan up

echo "[rollback] 回滚完成：$(date)，当前版本：$TARGET"
