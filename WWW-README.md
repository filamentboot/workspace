# Filamentboot WWW — 官网占位页

本文档说明官网仓库位置、部署机制、Secrets 配置，以及内容/设计 DEFERRED 事项。

---

## 仓库位置

| 项 | 值 |
|---|---|
| 本地路径 | `~/src/personal/filamentboot-www` |
| GitHub | `https://github.com/filamentboot/filamentboot-www` |
| 主分支 | `main` |
| 布局 | workspace 之外的独立 sibling repo（D-23） |

---

## 部署机制

**触发**：`git push origin main`

**流程**：GitHub Actions（`.github/workflows/deploy.yml`）→ SSH rsync → 服务器 `/var/www/www.xitongapp.com/`

```
push main
  └─ GitHub Actions deploy.yml
       └─ actions/checkout@v4
       └─ rsync -avz --delete
            -e "ssh -i /tmp/deploy_key -o StrictHostKeyChecking=no"
            ./ root@118.25.27.49:/var/www/www.xitongapp.com/
            --exclude='.git' --exclude='.github'
```

**目标服务器**：`118.25.27.49`（腾讯云 Debian 12）

---

## Secrets 配置（用户填入）

在 `https://github.com/filamentboot/filamentboot-www/settings/secrets/actions` 填入以下 3 个 secret：

| Secret 名称 | 值 | 获取方式 |
|------------|---|---------|
| `DEPLOY_KEY` | SSH 私钥内容（`~/.ssh/filamentboot_deploy`） | 详见 `SECRETS-CHECKLIST.md` § 1 和 § 5（SSH key 轮换说明） |
| `DEPLOY_HOST` | `118.25.27.49` | 固定值，服务器 IP |
| `DEPLOY_USER` | `root` | 固定值，服务器 SSH 登录用户 |

> **完整 Secrets 获取与配置步骤**：参见本仓库根目录 [`SECRETS-CHECKLIST.md`](./SECRETS-CHECKLIST.md) § 4（filamentboot-www repo GitHub Actions Secrets）。

---

## 上线步骤（用户执行）

Agent 已完成本地代码 scaffold。以下外部步骤需由用户手动执行：

### 1. 确认 nginx vhost

SSH 登录服务器，检查 `www.xitongapp.com` 的 nginx vhost：

```bash
ssh root@118.25.27.49
grep -r 'www.xitongapp.com' /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ 2>/dev/null
```

**预期**：vhost 应将 `root` 指向 `/var/www/www.xitongapp.com`。

- 若 `root` 指向其他路径（当前 500 多因此引起）：修改路径 → `nginx -t && systemctl reload nginx`。
- 若 vhost 不存在：新建完整 vhost（含 acme.sh 证书，参考 `wiki/guide/deployment.md`）。

### 2. 创建目标目录（若不存在）

```bash
mkdir -p /var/www/www.xitongapp.com
```

### 3. 在 GitHub 创建远端仓库

在 `https://github.com/organizations/filamentboot/repositories/new` 创建 `filamentboot-www` public repo（空仓库，不初始化 README/LICENSE）。

### 4. 填写 Secrets

按上方「Secrets 配置」表在 GitHub repo Settings → Secrets and variables → Actions 填入 3 个 secret。

### 5. 推送触发部署

```bash
cd ~/src/personal/filamentboot-www
git push -u origin main
```

GitHub Actions 将自动运行 deploy.yml，rsync 静态文件到服务器。

### 6. 验证上线

```bash
curl -sI https://www.xitongapp.com | head -5
```

预期：返回 `HTTP/2 200`，不再 500。浏览器访问看到 Filamentboot 占位页。

---

## DEFERRED（不在本期范围）

| 事项 | 原因 |
|-----|------|
| `filamentboot.com` 正式切换 | 管局备案审核中（约 7-14 天，≈ 2026-06-28 ~ 07-05）；备案通过后用户手动修改 nginx server_name 并切换 DNS |
| `www.xitongapp.com` → `filamentboot.com` DNS 迁移 | 同上，卡备案 |
| 官网真正的内容/设计/技术栈 | 用户将专门做（可能 Astro/HTML/其它）；本期只做最小占位，证明部署流水线跑通（D-22） |
| Gitee 镜像同步 | `filamentboot-www` 无 Gitee 镜像需求；若后续需要，参照 `SECRETS-CHECKLIST.md` § 2 的 Gitee Deploy Key 配置模式 |

---

*Produced by Phase 13 Plan 06 — 2026-06-21*
*Scaffold: ~/src/personal/filamentboot-www（workspace 外，D-23）*
