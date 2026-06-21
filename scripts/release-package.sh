#!/usr/bin/env bash
# 用途：本地手动发版脚本，按 PRD 07 §2.1-2.7 顺序完成全部发版步骤
# 用法：scripts/release-package.sh vX.Y.Z
# 依赖：
#   - gh CLI 已认证（gh auth status 通过）
#   - splitsh-lite 已安装（sudo mv splitsh-lite /usr/local/bin/splitsh-lite）
#   - PACKAGE_GITHUB_TOKEN 环境变量已设置（GitHub PAT，repo scope）
# 注意：Gitee 推送由 GitHub Actions mirror-to-gitee job 自动处理（D-11）
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
grep -q "^## \[${VERSION#v}\]" packages/filamentboot/CHANGELOG.md || \
    { echo "错误：CHANGELOG 中未找到 [${VERSION#v}] 节，请先将 [Unreleased] 改为 [$VERSION] 并标注日期"; exit 1; }
echo "CHANGELOG [$VERSION] 节已确认 ✓"

# §2.2 本地测试 ----------------------------------------------------------

echo ""
echo "--- §2.2 本地测试 ---"
composer test
echo "测试通过 ✓"

# §2.3 splitsh-lite subtree split ---------------------------------------

echo ""
echo "--- §2.3 splitsh-lite subtree split ---"
BRANCH="release/filamentboot-package-$VERSION"

# 防止重跑时分支冲突（Pitfall 4 缓解）
git branch -D "$BRANCH" 2>/dev/null || true

# 使用 splitsh-lite 分割核心包（与 release.yml CI 对齐）
SPLIT_SHA=$(splitsh-lite --prefix=packages/filamentboot)
git branch "$BRANCH" "$SPLIT_SHA"
echo "split 完成，commit SHA：$SPLIT_SHA"

# §2.4 推送核心包仓库（GitHub）------------------------------------------

echo ""
echo "--- §2.4 推送 filamentboot/filamentboot（GitHub）---"
REPO="https://x-access-token:${PACKAGE_GITHUB_TOKEN}@github.com/filamentboot/filamentboot.git"
git remote add filamentboot-github "$REPO" 2>/dev/null || \
    git remote set-url filamentboot-github "$REPO"
git push filamentboot-github "${SPLIT_SHA}:refs/heads/main" --force
echo "GitHub 主包仓库推送完成 ✓"
echo "注意：Gitee 镜像由 GitHub Actions mirror-to-gitee job 自动处理（D-11）"

# §2.5 打 tag 并推送 ----------------------------------------------------

echo ""
echo "--- §2.5 创建 tag 并推送到 GitHub ---"
git tag -a "$VERSION" "$SPLIT_SHA" -m "$VERSION 发布" 2>/dev/null || \
    echo "tag $VERSION 已存在，跳过创建"
git push filamentboot-github "$VERSION"
echo "tag $VERSION 已推送到 filamentboot/filamentboot ✓"

# §2.6 创建 GitHub Release ---------------------------------------------

echo ""
echo "--- §2.6 创建 GitHub Release ---"
VER="${VERSION#v}"

# 使用版本号过滤版 awk 提取 CHANGELOG
awk -v ver="$VER" '
    /^## \[/{
        if(found) exit
        if(index($0, "[" ver "]")) found=1
        next
    }
    found{print}
' packages/filamentboot/CHANGELOG.md > "/tmp/release-notes-$VERSION.md"

echo "=== Release Notes 预览 ==="
cat "/tmp/release-notes-$VERSION.md"
echo "========================="

gh release create "$VERSION" \
    --repo filamentboot/filamentboot \
    --title "$VERSION" \
    --notes-file "/tmp/release-notes-$VERSION.md"

echo "GitHub Release $VERSION 已创建 ✓"

# §2.7 收尾提示 ---------------------------------------------------------

echo ""
echo "=== $VERSION 发布完成 ==="
echo "下一步：确认 Packagist 已收录 https://packagist.org/packages/filamentboot/filamentboot"
echo "如需回滚：执行 scripts/release-rollback.sh $VERSION"
