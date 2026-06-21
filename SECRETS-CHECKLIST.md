# Secrets 配置 Checklist

> 本文件列出所有需要手动填入的 secret/凭据。**不要将任何实际 token 值提交到代码库。**
> Agent 不持有任何凭据 — 所有 secret 值由用户自行填入到对应平台。

---

## 1. GitHub Actions Secrets（filamentboot/workspace repo）

填入位置：`https://github.com/filamentboot/workspace/settings/secrets/actions`
（Settings → Secrets and variables → Actions → New repository secret）

| Secret 名称 | 用途 | 获取方式 |
|------------|------|---------|
| `PACKAGE_GITHUB_TOKEN` | 在 `release.yml` subtree-split job 中 push 到各 `filamentboot/*` clean repo | 登录 GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic) → Generate new token → 勾选 **`repo` scope（仅此一个，最小权限）** → 复制 token 值 |
| `GITEE_SSH_KEY` | 在 `release.yml` mirror-to-gitee job 中 SSH 推送到 Gitee 各仓库 | `ssh-keygen -t ed25519 -C "filamentboot-gitee-mirror" -f ~/.ssh/filamentboot_gitee` → **私钥**内容（`~/.ssh/filamentboot_gitee`）填入此 secret；**公钥**（`~/.ssh/filamentboot_gitee.pub`）需在所有 Gitee 目标仓库逐一配置（见下方 Gitee Deploy Key 说明） |
| `PACKAGIST_TOKEN` | 触发 Packagist 收录验证（verify job）及手动 webhook 测试 | 登录 `https://packagist.org` → Profile → API tokens → Create → 复制 token 值 |
| `DEPLOY_KEY` | SSH 推送到演示站服务器 `118.25.27.49`（master-pipeline.yml 调用 deploy.sh） | 生成新 SSH key：`ssh-keygen -t ed25519 -C "filamentboot_deploy" -f ~/.ssh/filamentboot_deploy` → **私钥**内容（`~/.ssh/filamentboot_deploy`）填入此 secret；**公钥**需添加到服务器 `~/.ssh/authorized_keys`（D-20 轮换，替换旧 filament_deploy） |
| `DEPLOY_HOST` | 服务器 IP 地址（deploy.sh SSH 目标） | 固定值：`118.25.27.49` |
| `DEPLOY_USER` | 服务器 SSH 登录用户（deploy.sh SSH 目标） | 固定值：`root` |
| `CI_APP_KEY` | 根目录 CI 的 Laravel APP_KEY | `php artisan key:generate --show` 输出的 base64 值 |

**重要注意事项：**

- `PACKAGE_GITHUB_TOKEN` 的 PAT scope **仅勾选 `repo`**，不需要 `admin:org` 或 `workflow`。若 clean repo 内有 `.github/workflows/`，还需加 `workflow` scope。
- `DEPLOY_KEY` 对应的 SSH key 建议命名为 `filamentboot_deploy`（区别于旧的 `filament_deploy`），完成后可停用旧 key（D-20）。

---

## 2. Gitee Deploy Key 配置（每个 Gitee 仓库逐一添加）

Gitee Deploy Key 是**仓库级别**配置（不同于 GitHub 的 Org 级 Deploy Key），需要在每个目标仓库单独添加公钥。

填入位置：`https://gitee.com/filamentboot/<REPO>/settings/keys`
（仓库设置 → 部署公钥 → 添加部署公钥 → 勾选「可推送」）

需配置的仓库（共 8 个）：

| Gitee 仓库 | 操作 |
|-----------|------|
| `filamentboot/workspace` | 添加 `GITEE_SSH_KEY` 对应公钥，勾选「可推送」 |
| `filamentboot/filamentboot` | 添加 `GITEE_SSH_KEY` 对应公钥，勾选「可推送」 |
| `filamentboot/filamentboot-cos` | 添加 `GITEE_SSH_KEY` 对应公钥，勾选「可推送」 |
| `filamentboot/filamentboot-oss` | 添加 `GITEE_SSH_KEY` 对应公钥，勾选「可推送」 |
| `filamentboot/filamentboot-rich-editor` | 添加 `GITEE_SSH_KEY` 对应公钥，勾选「可推送」 |
| `filamentboot/filamentboot-markdown-editor` | 添加 `GITEE_SSH_KEY` 对应公钥，勾选「可推送」 |
| `filamentboot/filamentboot-wang-editor` | 添加 `GITEE_SSH_KEY` 对应公钥，勾选「可推送」 |
| `filamentboot/filamentboot-site` | 添加 `GITEE_SSH_KEY` 对应公钥，勾选「可推送」 |

**公钥内容**：`cat ~/.ssh/filamentboot_gitee.pub`（与第 1 部分 `GITEE_SSH_KEY` 对应的公钥）

**Pitfall 4 缓解**：每个 Gitee repo 必须单独添加，否则 `git push gitee ...` 报 `Permission denied (publickey)` 或 403 错误。

---

## 3. Gitee Go 通用变量（filamentboot/workspace 流水线）

填入位置：登录 Gitee → 进入 `filamentboot/workspace` 仓库 → 流水线 → 通用变量

| 变量名 | 用途 | 值 |
|-------|------|-----|
| `DEPLOY_KEY` | SSH 私钥，供 master-pipeline.yml 连接服务器 | 与 GitHub Actions `DEPLOY_KEY` 相同私钥内容（`filamentboot_deploy`）|
| `DEPLOY_HOST` | 服务器 IP 地址 | `118.25.27.49` |
| `DEPLOY_USER` | SSH 登录用户 | `root` |

---

## 4. filamentboot-www repo GitHub Actions Secrets

填入位置：`https://github.com/filamentboot/filamentboot-www/settings/secrets/actions`

| Secret 名称 | 用途 | 值 |
|------------|------|-----|
| `DEPLOY_KEY` | SSH 推送静态文件到服务器 nginx 目录 | 与第 1 部分 `DEPLOY_KEY` 相同私钥内容 |
| `DEPLOY_HOST` | 服务器 IP 地址 | `118.25.27.49` |
| `DEPLOY_USER` | SSH 登录用户 | `root` |

---

## 5. SSH Key 轮换说明（D-20）

当前服务器使用 `filament_deploy` SSH key（旧名）。建议在本期完成如下轮换：

1. 生成新 key：`ssh-keygen -t ed25519 -C "filamentboot_deploy" -f ~/.ssh/filamentboot_deploy`
2. 将公钥追加到服务器：`ssh-copy-id -i ~/.ssh/filamentboot_deploy.pub root@118.25.27.49`
3. 测试新 key 可连接：`ssh -i ~/.ssh/filamentboot_deploy root@118.25.27.49 "echo ok"`
4. 将新私钥填入上方所有 `DEPLOY_KEY` secret
5. 测试 Gitee Go 流水线与 GitHub Actions deploy 均可正常触发
6. 确认无误后，从服务器 `~/.ssh/authorized_keys` 移除旧 `filament_deploy` 公钥

---

## 6. Packagist 注册（用户手动步骤，D-16）

1. 注册 Packagist 账号（若未有）：`https://packagist.org/register/`
2. 提交核心包：`https://packagist.org/packages/submit` → 填入 `https://github.com/filamentboot/filamentboot`
3. 对 7 个 clean repo 逐一提交（重复步骤 2，依次填入各 clean repo URL）
4. 在每个 GitHub clean repo 配置 webhook：
   - Payload URL：`https://packagist.org/api/github?username=<YOUR_PACKAGIST_USERNAME>`
   - Content type：`application/json`
   - Secret：填入 Packagist API token（即 `PACKAGIST_TOKEN`）
   - Events：Just the push event

**注意（Pitfall 5 缓解）**：在 demo 站执行 `composer require filamentboot/filamentboot` 之前，必须先在 `https://packagist.org/packages/filamentboot/filamentboot` 确认包已存在且版本正确（D-25）。

---

## 7. 服务器迁移 Checklist（D-20，参考顺序）

以下步骤为人工操作，无法由 agent 代为执行：

- [ ] SSH 登录服务器 `root@118.25.27.49`
- [ ] 新建目录 `mkdir -p /data/filamentboot`（或从 Packagist clone demo repo）
- [ ] 更新 supervisor conf：`filament-admin-worker` → `filamentboot-worker`，路径 `/data/filament-admin` → `/data/filamentboot`
- [ ] 执行 `supervisorctl update && supervisorctl restart filamentboot-worker:*`
- [ ] 更新 nginx vhost：`root /data/filament-admin/public` → `root /data/filamentboot/public`
- [ ] 执行 `nginx -t && systemctl reload nginx`
- [ ] 测试 `https://demo.xitongapp.com` 返回 200
- [ ] 旧目录 `/data/filament-admin` 暂时保留（待 demo 站稳定后删除）
- [ ] 生成新 webhook token：`openssl rand -hex 32`，更新服务器 `.webhook_token` 文件，更新 Gitee webhook URL
