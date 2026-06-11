#!/usr/bin/env bash
# 用途：在隔离临时目录验证 laravelstack/filament-admin 包安装可用性
# 用法：scripts/verify-package-install.sh vX.Y.Z
# 说明：
#   - 在 /tmp/verify-vX.Y.Z 中创建全新 Laravel 13 项目，安装目标版本包
#   - 验证 package:discover 成功（ServiceProvider 已注册）
#   - 验证深度限于 package:discover（完整 acceptance 测试由 RELEASE-06 人工覆盖）
#   - 隔离目录不污染工作区，验证完成后可手动删除
set -euo pipefail

VERSION=${1:?用法: $0 vX.Y.Z}
VERIFY_DIR="/tmp/verify-${VERSION}"

echo "=== 验证 laravelstack/filament-admin ${VERSION} 安装可用性 ==="
echo "隔离目录：${VERIFY_DIR}"
echo ""

# 清理并创建隔离目录
rm -rf "${VERIFY_DIR}"
mkdir -p "${VERIFY_DIR}"
cd "${VERIFY_DIR}"

# 创建全新 Laravel 13 项目
echo "--- 创建 Laravel 13 项目 ---"
composer create-project --prefer-dist laravel/laravel . "^13.0" --no-interaction

# 安装目标版本包
echo ""
echo "--- 安装 laravelstack/filament-admin:${VERSION} ---"
composer require "laravelstack/filament-admin:${VERSION}" --no-interaction

# 验证 ServiceProvider 已正确注册
echo ""
echo "--- 验证 package:discover ---"
php artisan package:discover --ansi

echo ""
echo "=== 验证通过：laravelstack/filament-admin ${VERSION} 安装成功 ==="
echo "隔离目录保留于：${VERIFY_DIR}（可手动删除：rm -rf ${VERIFY_DIR}）"
