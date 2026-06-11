#!/usr/bin/env bash
# 用途：本地手动发版脚本，按 PRD 07 §2.1-2.7 顺序完成全部发版步骤（含 Gitee 推送）
# 用法：scripts/release-package.sh vX.Y.Z
# 依赖：
#   - gh CLI 已认证（gh auth status 通过）
#   - package-github SSH remote 已配置（git@github.com:john-captain/filament-admin.git）
#   - package-gitee SSH remote 已配置（git@gitee.com:johncaptain/filament-admin.git）
# 注意：Gitee 推送仅在本脚本中执行，release.yml CI 不自动推 Gitee（D-41/D-42）
set -euo pipefail

VERSION=${1:?用法: $0 vX.Y.Z}

echo "=== 开始发布 $VERSION ==="
echo ""

# §2.1 发布前检查 -------------------------------------------------------

echo "--- §2.1 发布前检查 ---"

# 检查工作区干净
git status --short
test -z "$(git status --short)" || { echo "错误：工作区有未提交改动，请先提交再执行发版"; exit 1; }
echo "工作区干净 ✓"

# 检查 CHANGELOG 中是否存在目标版本节（Pitfall 1 前置检查）
# 防止 awk 提取 [Unreleased] 而非目标版本内容
grep -q "^## \[${VERSION#v}\]" packages/filament-admin/CHANGELOG.md || \
    { echo "错误：CHANGELOG 中未找到 [${VERSION#v}] 节，请先将 [Unreleased] 改为 [$VERSION] 并标注日期"; exit 1; }
echo "CHANGELOG [$VERSION] 节已确认 ✓"

# §2.2 本地测试 ----------------------------------------------------------

echo ""
echo "--- §2.2 本地测试 ---"
composer test
echo "测试通过 ✓"

# §2.3 subtree split ----------------------------------------------------

echo ""
echo "--- §2.3 subtree split ---"
BRANCH="release/filament-admin-package-$VERSION"

# 防止重跑时分支冲突（Pitfall 4 缓解）
git branch -D "$BRANCH" 2>/dev/null || true

git subtree split --prefix=packages/filament-admin -b "$BRANCH"
SPLIT_SHA=$(git rev-parse "$BRANCH")
echo "split 完成，commit SHA：$SPLIT_SHA"

# §2.4 推送包仓库 -------------------------------------------------------

echo ""
echo "--- §2.4 推送 GitHub 包仓库 ---"
git push package-github "$BRANCH":main --force

echo ""
echo "--- §2.4 推送 Gitee 包仓库（D-42：Gitee 同步唯一入口）---"
git push package-gitee "$BRANCH":main --force

# §2.5 打 tag 并推送两端 ------------------------------------------------

echo ""
echo "--- §2.5 创建 tag 并推送到 GitHub 和 Gitee ---"
git tag -a "$VERSION" "$SPLIT_SHA" -m "$VERSION 发布"
git push package-github "$VERSION"
git push package-gitee "$VERSION"
echo "tag $VERSION 已推送到 GitHub 和 Gitee ✓"

# §2.6 创建 GitHub Release ---------------------------------------------

echo ""
echo "--- §2.6 创建 GitHub Release ---"
VER="${VERSION#v}"

# 使用版本号过滤版 awk 提取 CHANGELOG（D-38 修正版，防止提取 [Unreleased] 节）
awk -v ver="$VER" '
    /^## \[/{
        if(found) exit
        if(index($0, "[" ver "]")) found=1
        next
    }
    found{print}
' packages/filament-admin/CHANGELOG.md > "/tmp/release-notes-$VERSION.md"

echo "=== Release Notes 预览 ==="
cat "/tmp/release-notes-$VERSION.md"
echo "========================="

gh release create "$VERSION" \
    --repo john-captain/filament-admin \
    --title "$VERSION" \
    --notes-file "/tmp/release-notes-$VERSION.md"

echo "GitHub Release $VERSION 已创建 ✓"

# §2.7 收尾提示 ---------------------------------------------------------

echo ""
echo "=== $VERSION 发布完成 ==="
echo "下一步：执行 scripts/verify-package-install.sh $VERSION 验证安装可用性"
echo "如需回滚：执行 scripts/release-rollback.sh $VERSION"
