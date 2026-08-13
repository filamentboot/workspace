#!/usr/bin/env bash
#
# 用途：发版前置检查——把批次3手工发版 v0.13.0 时做过的确定性检查全部收进一个脚本，
#      供以后每次发版前跑一遍。本脚本只做检查，不 push、不打 tag、不触发任何 CI，
#      跑完之后是否继续发版仍由人决定（见 .claude/skills/release/SKILL.md）。
#
# 用法：bin/release-preflight.sh vX.Y.Z
#
# 退出码：0 = 全部通过；1 = 至少一项未通过，输出里能看到具体是哪项、哪个包。
#
set -uo pipefail

VERSION=${1:?用法: $0 vX.Y.Z}
VER="${VERSION#v}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PACKAGES=(filamentboot filamentboot-cos filamentboot-oss filamentboot-rich-editor filamentboot-markdown-editor filamentboot-wang-editor filamentboot-site)
FAIL=0
TMPDIR=$(mktemp -d)
trap 'rm -rf "$TMPDIR"' EXIT

step() { echo ""; echo "--- $1 ---"; }
ok()   { echo "  ✓ $1"; }
warn() { echo "  ⚠ $1"; }
fail() { echo "  ✗ $1"; FAIL=1; }

echo "=== 发版前置检查：$VERSION ==="

# 1. 工作区干净 --------------------------------------------------------------
step "1. 工作区状态"
if [ -z "$(git status --short)" ]; then
    ok "工作区干净"
else
    fail "工作区有未提交改动，先提交或暂存（见下方 git status）"
    git status --short | sed 's/^/      /'
fi

# 2. 7 个包 composer validate -------------------------------------------------
step "2. 7 个包 composer validate"
for pkg in "${PACKAGES[@]}"; do
    LOG="$TMPDIR/validate-$pkg.log"
    if composer validate --no-check-all --no-check-lock "packages/$pkg/composer.json" >"$LOG" 2>&1; then
        ok "$pkg"
    else
        fail "$pkg composer.json 校验失败"
        sed 's/^/      /' "$LOG"
    fi
done

# 3. 全量测试（两条独立套件，避免宿主真实 Laravel 测试与 Testbench 包测试混跑污染，
#    这个坑 2026-08-13 修 workspace CI 红时踩过一次，见 docs/上线账本.md）--------
step "3. 全量测试"
if PAO_DISABLE=1 php artisan test --testsuite=Unit,Feature >"$TMPDIR/test-host.log" 2>&1; then
    ok "宿主 Unit+Feature 套件"
else
    fail "宿主 Unit+Feature 套件未通过，详见 $TMPDIR/test-host.log（脚本退出后此目录会被清理，重跑时另存）"
    tail -40 "$TMPDIR/test-host.log" | sed 's/^/      /'
fi
if PAO_DISABLE=1 php artisan test --testsuite=PackageUnit,PackageFeature,OssUnit,CosUnit >"$TMPDIR/test-package.log" 2>&1; then
    ok "包级 Testbench 套件（PackageUnit/PackageFeature/OssUnit/CosUnit）"
else
    fail "包级 Testbench 套件未通过"
    tail -40 "$TMPDIR/test-package.log" | sed 's/^/      /'
fi

# 4. CHANGELOG 精确匹配 -------------------------------------------------------
# release.yml 的 release job 按 `## [x.y.z]` 精确字符串匹配抽取 Release 说明，
# 格式不对（比如漏了方括号，或者还是 [Unreleased]）会抽出空文本但不报错。
step "4. CHANGELOG [$VER] 节"
for pkg in filamentboot filamentboot-site; do
    CHANGELOG="packages/$pkg/CHANGELOG.md"
    if [ ! -f "$CHANGELOG" ]; then
        fail "$CHANGELOG 不存在"
    elif grep -q "^## \[${VER}\]" "$CHANGELOG"; then
        ok "$pkg/CHANGELOG.md 含 [$VER] 节"
    else
        fail "$pkg/CHANGELOG.md 未找到 \"## [$VER]\" 节（需先把 [Unreleased] 改成 [$VER] 并标注日期）"
    fi
done

# 5. qkznj / 真实客户信息泄漏扫描 ---------------------------------------------
# 关键词扫描分不清"注释里说明历史上曾经是真实客户内容、五期已改成虚构主体"
# 和"这里真的还留着真实客户数据"——这条注定测不出"干净"，所以是人工复核清单
# （warn，不拉低退出码），不是硬闸门。排除 tests/（占位域名/断言合理）和
# README.md（xitongapp.com 是官方演示站，链过去是故意的）。
step "5. qkznj/真实客户信息泄漏扫描（人工复核清单，非硬闸门）"
LEAK_FOUND=0
for pkg in "${PACKAGES[@]}"; do
    MATCHES=$(grep -rln "qkznj\|湖北晴空妙享" "packages/$pkg" \
        --include="*.php" --include="*.blade.php" 2>/dev/null \
        | grep -v "/tests/" || true)
    if [ -n "$MATCHES" ]; then
        LEAK_FOUND=1
        echo "$MATCHES" | sed 's/^/      /'
    fi
done
if [ "$LEAK_FOUND" -eq 0 ]; then
    ok "未发现 qkznj 相关字符串（非 tests/、非 README）"
else
    warn "以上文件命中 qkznj 相关字符串，逐条确认是「说明历史」的注释还是真的留了客户数据"
fi

# 6. dry-run subtree split（本地不推送，跟 release.yml 用同一个工具） ----------
step "6. dry-run subtree split（不推送）"
if ! command -v splitsh-lite >/dev/null 2>&1; then
    warn "本机未安装 splitsh-lite，跳过本地 split 预演"
    warn "真正的 split 由 release.yml 的 subtree-split job 在 CI 里做，届时才会验证"
else
    for pkg in "${PACKAGES[@]}"; do
        LOG="$TMPDIR/split-$pkg.log"
        SHA=$(splitsh-lite --prefix="packages/$pkg" 2>"$LOG")
        if [ -z "$SHA" ]; then
            fail "$pkg split 产生空 SHA"
            sed 's/^/      /' "$LOG"
        elif ! git cat-file -p "$SHA:composer.json" >/dev/null 2>&1; then
            fail "$pkg split 树（$SHA）里没有 composer.json"
        else
            ok "$pkg → $SHA"
        fi
    done
fi

# 7. Secret 存在性检查 ---------------------------------------------------------
# 只能确认 secret 已配置，无法在本地验证其有效性/是否过期——
# GITEE_SSH_KEY 在 2026-08-12 发 v0.13.0 时就已失效（mirror-to-gitee 全部失败，
# 见 docs/上线账本.md 与仓库 task #22），这一项检查通过不代表它真的能用。
step "7. GitHub Secrets 存在性（PACKAGE_GITHUB_TOKEN / GITEE_SSH_KEY）"
if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
    SECRETS=$(gh secret list --repo filamentboot/workspace 2>/dev/null || true)
    for s in PACKAGE_GITHUB_TOKEN GITEE_SSH_KEY; do
        if echo "$SECRETS" | grep -q "^$s"; then
            UPDATED=$(echo "$SECRETS" | grep "^$s" | awk '{print $2}')
            ok "$s 已配置（最后更新：$UPDATED）"
        else
            fail "$s 未配置"
        fi
    done
    warn "存在性检查通过 ≠ 有效——真正的有效性只有 release.yml 真跑一次 mirror-to-gitee/release job 才能确认，参见 task #22 的已知缺口"
else
    warn "本机 gh CLI 未登录，跳过 secret 存在性检查（不算失败，只是跳过）"
fi

# 结论 -------------------------------------------------------------------------
echo ""
echo "=== 检查结束 ==="
if [ "$FAIL" -eq 0 ]; then
    echo "✓ 全部通过，可以进入发版流程：push main → 确认 CI 绿 → push tag → 盯 4 个 job → curl 确认 Packagist"
    exit 0
else
    echo "✗ 至少一项未通过，处理完上面输出里标 ✗ 的问题再重跑"
    exit 1
fi
