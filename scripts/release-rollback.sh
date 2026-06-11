#!/usr/bin/env bash
# 用途：回滚发版，幂等删除 GitHub Release、双端远端 tag、本地 tag 及 split 分支
# 用法：scripts/release-rollback.sh vX.Y.Z
# 依赖：
#   - gh CLI 已认证（gh auth status 通过）
#   - package-github SSH remote 已配置（git@github.com:john-captain/filament-admin.git）
#   - package-gitee SSH remote 已配置（git@gitee.com:johncaptain/filament-admin.git）
# 注意：Packagist 上的版本需手动操作，本脚本不能自动撤回（详见收尾提示）
set -euo pipefail

VERSION=${1:?用法: $0 vX.Y.Z}

echo "=== 回滚 $VERSION ==="
echo "将执行以下操作："
echo "  1. 删除 GitHub Release（john-captain/filament-admin $VERSION）"
echo "  2. 删除 package-github 远端 tag $VERSION"
echo "  3. 删除 package-gitee 远端 tag $VERSION"
echo "  4. 删除本地 tag $VERSION"
echo "  5. 删除本地 split 分支 release/filament-admin-package-$VERSION"
echo ""

# 交互式确认，防止误操作（T-04-RB 威胁缓解）
read -r -p "确认删除 $VERSION 的 tag 和 release？[y/N] " confirm
[[ $confirm == "y" ]] || { echo "已取消"; exit 0; }

echo ""
echo "--- 删除 GitHub Release ---"
# 2>/dev/null || true 保证幂等：release 不存在时不报错
gh release delete "$VERSION" --repo john-captain/filament-admin --yes 2>/dev/null || true
echo "GitHub Release 删除完成（或不存在）"

echo ""
echo "--- 删除远端 tag（package-github）---"
# 冒号前缀语法删除远端 ref，2>/dev/null || true 保证幂等
git push package-github ":refs/tags/$VERSION" 2>/dev/null || true
echo "package-github 远端 tag 删除完成（或不存在）"

echo ""
echo "--- 删除远端 tag（package-gitee）---"
git push package-gitee ":refs/tags/$VERSION" 2>/dev/null || true
echo "package-gitee 远端 tag 删除完成（或不存在）"

echo ""
echo "--- 删除本地 tag ---"
git tag -d "$VERSION" 2>/dev/null || true
echo "本地 tag 删除完成（或不存在）"

echo ""
echo "--- 删除本地 split 分支 ---"
git branch -D "release/filament-admin-package-$VERSION" 2>/dev/null || true
echo "split 分支删除完成（或不存在）"

echo ""
echo "=== 回滚完成 ==="
echo "警告：Packagist 上的版本无法通过脚本自动撤回，请手动检查："
echo "  https://packagist.org/packages/laravelstack/filament-admin"
echo "如果版本已被 Packagist 收录，需登录 Packagist 后台操作或联系支持。"
