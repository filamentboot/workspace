---
name: release
description: filamentboot 系列包发版助手——先跑 bin/release-preflight.sh 做确定性检查，再逐步走 push main → 确认 CI 绿 → push tag → 盯 release.yml 四个 job → curl 确认 Packagist，每个不可逆步骤单独停下确认，不做成无人值守全自动。
---

# /release —— filamentboot 发版助手

本 skill 只在 `~/src/personal/filamentboot`（workspace 仓库，`release.yml`/全部 tag 都在这里，
本仓库无 git remote）里有意义。发版真正做了什么、为什么是这个顺序，见
`docs/上线账本.md` 与 `.github/workflows/release.yml`/`ci.yml`。

**核心纪律：这是一个逐步确认的清单，不是一键脚本。** 每一步标了 ⚠️ 的，做之前必须
先跟用户确认一次，得到明确同意才能继续；不能因为上一步做完了就默认这一步也批准了。

## 输入

版本号 `vX.Y.Z`（跟 `$ARGUMENTS` 要，或者直接问用户）。发版前，两个包的
`CHANGELOG.md` 里的 `## [Unreleased]` 应该已经改成 `## [$VERSION] - 日期`——如果
还没改，先帮用户把这一步做掉（这不是不可逆操作，是本地文件编辑）。

## 步骤

### 1. 跑 preflight，不通过就停

```bash
bin/release-preflight.sh vX.Y.Z
```

覆盖 7 个包 `composer validate`、全量测试（宿主套件 + Testbench 包套件分开跑，
混跑会互相污染状态，2026-08-13 修 workspace CI 红时踩过）、两个包的 CHANGELOG
版本节精确匹配、qkznj 真实客户信息人工复核清单（这条是 warn，不是硬门槛，
自己读一遍命中的文件确认不是真泄漏）、7 个包本地 dry-run subtree split（不推送，
跟 CI 用同一个 `splitsh-lite` 版本 v1.0.1）、两个 GitHub Secret 的存在性
（**不能验证有效性**——`GITEE_SSH_KEY` 在 2026-08-12 发 v0.13.0 时就已经失效，
这条检查通过只代表配置了，不代表真的能用，参见任务 #22）。

退出码非 0 就把失败项列给用户，**停在这里**，不要自己决定怎么绕过去。

### 2. ⚠️ push main

Preflight 全绿后，跟用户确认："preflight 通过，要 push main 吗？" 得到同意后：

```bash
git push origin main
```

### 3. 确认 CI 绿

```bash
gh run list --repo filamentboot/workspace --limit 1 --json databaseId,headSha
gh run watch <databaseId> --repo filamentboot/workspace --exit-status
```

CI 红就停在这里，把失败日志（`gh run view <id> --log-failed`）拿给用户，不要带着红 CI 继续走第 4 步——`release.yml` 不 `needs` CI，两个 workflow 由同一次 push 独立触发，CI 失败拦不住包被 split 推出去，这正是这条纪律存在的原因。

### 4. ⚠️ 打 tag 并 push

CI 确认绿后，跟用户确认："CI 绿了，要打 tag vX.Y.Z 并推送触发发版吗？这一步不可逆
（会真的把 7 个包 split 推到各自的 GitHub 仓库 + 建 Release）。" 得到同意后：

```bash
git tag -a vX.Y.Z -m "vX.Y.Z 发布"
git push origin vX.Y.Z
```

### 5. 盯 release.yml 的 4 个 job

```bash
gh run list --repo filamentboot/workspace --limit 1 --json databaseId
gh run watch <databaseId> --repo filamentboot/workspace --exit-status
```

四个 job：`subtree-split`（7 个包矩阵）→ `release`（只对 `filamentboot/filamentboot`
建 Release）→ `mirror-to-gitee`（8 个仓库矩阵）→ `verify`。

- `subtree-split`/`release`/`verify` 任一失败：**硬性停下**，报告给用户，
  发版没有真正完成。
- `mirror-to-gitee` 失败：**已知会失败**（任务 #22，`GITEE_SSH_KEY` 失效），
  不是这次操作引入的新问题，报告一句但不当作发版失败——包本身（GitHub +
  Packagist 这条链路）跟 Gitee 镜像是独立的两件事。

### 6. 自己 curl 确认 Packagist 真收录

`verify` job 里那步轮询是 warning-only、不阻塞，**不能当验收依据**，必须自己再查一次：

```bash
curl -sf "https://packagist.org/p2/filamentboot/filamentboot.json" | \
  python3 -c "import sys,json; d=json.load(sys.stdin); print('vX.Y.Z' in [p['version'] for p in d['packages']['filamentboot/filamentboot']])"
```

### 7. 收尾报告

跟用户汇报：这次发的版本号、7 个包是否都 split 成功、Gitee 镜像是否又失败（如果是，
提醒任务 #22 还没解决，跟这次发版无关）、Packagist 是否真的收录。**不要自己决定
要不要接着做别的事**（比如通知下游、改文档版本号之类），发版这个动作本身完成后就
停下汇报。

## 出问题时

`scripts/release-rollback.sh vX.Y.Z` 可以撤销 GitHub Release + 远端/本地 tag +
本地 split 分支，但 **Packagist 上已收录的版本和 Gitee 上已推送的 tag 需要手动处理**
（脚本注释里写了具体位置）。跑这个脚本前先跟用户确认要回滚，这也是不可逆程度不低的操作。

`scripts/release-package.sh`/`verify-package-install.sh` 是发版自动化（`release.yml`
里的 `subtree-split`+`release` job）出现之前就有的手工脚本，现在跟 CI 的自动化有
重叠（`release-package.sh` 只手工处理 `filamentboot` 一个包，不推其余 6 个、也不管
Gitee）——除非 CI 本身坏了需要绕过去发版，否则不需要用它们。`verify-package-install.sh`
里硬编码的包名 `laravelstack/filament-admin` 是改名前的旧名字，已经过期，如果真要用
这个脚本记得先改成 `filamentboot/filamentboot`。
