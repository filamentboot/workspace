#!/usr/bin/env bash
# 用途：回滚发版，幂等删除 GitHub Release、远端 tag、本地 tag 及 split 分支
# 用法：scripts/release-rollback.sh vX.Y.Z
# 依赖：
#   - gh CLI 已认证（gh auth status 通过）
#   - PACKAGE_GITHUB_TOKEN 环境变量已设置（GitHub PAT，repo scope）
# 注意：
#   - Packagist 上的版本需手动操作，本脚本不能自动撤回（详见收尾提示）
#   - Gitee 镜像版本需登录 Gitee 手动删除（镜像由 Actions 自动推送，回滚不自动处理）
set -euo pipefail

VERSION=${1:?用法: $0 vX.Y.Z}

echo "=== 回滚 $VERSION ==="
echo "将执行以下操作："
echo "  1. 删除 GitHub Release（filamentboot/filamentboot $VERSION）"
echo "  2. 删除 filamentboot-github 远端 tag $VERSION"
echo "  3. 删除本地 tag $VERSION"
echo "  4. 删除本地 split 分支 release/filamentboot-package-$VERSION"
echo ""

# 交互式确认，防止误操作
read -r -p "确认删除 $VERSION 的 tag 和 release？[y/N] " confirm
[[ $confirm == "y" ]] || { echo "已取消"; exit 0; }

echo ""
echo "--- 删除 GitHub Release ---"
gh release delete "$VERSION" --repo filamentboot/filamentboot --yes 2>/dev/null || true
echo "GitHub Release 删除完成（或不存在）"

echo ""
echo "--- 删除远端 tag（filamentboot-github）---"
REPO="https://x-access-token:${PACKAGE_GITHUB_TOKEN}@github.com/filamentboot/filamentboot.git"
git remote add filamentboot-github "$REPO" 2>/dev/null || \
    git remote set-url filamentboot-github "$REPO"
git push filamentboot-github ":refs/tags/$VERSION" 2>/dev/null || true
echo "filamentboot-github 远端 tag 删除完成（或不存在）"

echo ""
echo "--- 删除本地 tag ---"
git tag -d "$VERSION" 2>/dev/null || true
echo "本地 tag 删除完成（或不存在）"

echo ""
echo "--- 删除本地 split 分支 ---"
git branch -D "release/filamentboot-package-$VERSION" 2>/dev/null || true
echo "split 分支删除完成（或不存在）"

echo ""
echo "=== 回滚完成 ==="
echo "警告：以下内容需手动处理："
echo "  - Packagist 版本：https://packagist.org/packages/filamentboot/filamentboot"
echo "  - Gitee 镜像 tag：登录 Gitee 在 filamentboot/filamentboot 仓库删除对应 tag"
echo "如果版本已被 Packagist 收录，需登录 Packagist 后台操作或联系支持。"
