# Filamentboot 生态基础设施 — 用户外部操作总清单

> **作者：** Agent（13-05 Task 1 产出）
> **上次更新：** 2026-06-21
>
> 本清单列出所有 agent 无法自动执行、须用户手动完成的外部操作（建 org/repo、Packagist、服务器、secrets 填写）。
> 每项均含 **what / how / where** + 验收勾选框。
>
> **不含任何真实 token 或凭据值。** Secrets 详细获取步骤见 `SECRETS-CHECKLIST.md`。

---

## Step 0 — 前置确认：Gitee Go 流水线仍在运行（D-15 前提）

**What：** 确认 Gitee Go（CI/CD）在 `filamentboot/workspace` 仓库的 MasterPipeline 仍可正常触发。
演示站部署依赖 Gitee Go（`.workflow/master-pipeline.yml` → SSH → `/data/filamentboot/deploy.sh`），若流水线失效则整个部署链断开。

**How：**
1. 登录 [gitee.com](https://gitee.com)，进入 workspace 仓库 → 流水线（Gitee Go）→ 查看 `MasterPipeline` 最近运行记录
2. 若无最近记录，向 `main` 分支推送一个空 commit 验证：
   ```bash
   git commit --allow-empty -m "chore: verify gitee go pipeline"
   git push gitee main  # 或 git push origin main（Actions 触发后 gitee 镜像）
   ```
3. 确认 Gitee Go 面板里 MasterPipeline 出现新运行记录，状态为 success / running

**Where：** [gitee.com → filamentboot/workspace → 流水线]

**验收：**
- [ ] Gitee Go MasterPipeline 可正常触发，最近一次运行状态为 success

---

## Step 1 — 创建 10 个 GitHub 仓库（D-02）

**What：** 在 GitHub org `filamentboot` 下创建 10 个仓库：1 个 workspace monorepo + 7 个包 clean repo + 1 个官网 + 1 个演示站。

**How：**
1. 创建 GitHub org：[github.com/organizations/new](https://github.com/organizations/new)
   - Organization name: `filamentboot`
   - 选择 Free plan
2. 在该 org 下逐一创建以下仓库（[github.com/organizations/filamentboot/repositories/new](https://github.com/organizations/filamentboot/repositories/new)）：

| 仓库名 | 用途 | 可见性 |
|--------|------|--------|
| `workspace` | 主 monorepo（当前这份代码） | Public |
| `filamentboot` | 核心包 clean repo（subtree split 目标） | Public |
| `filamentboot-cos` | 腾讯云 COS 插件 clean repo | Public |
| `filamentboot-oss` | 阿里云 OSS 插件 clean repo | Public |
| `filamentboot-rich-editor` | 富文本编辑器插件 clean repo | Public |
| `filamentboot-markdown-editor` | Markdown 编辑器插件 clean repo | Public |
| `filamentboot-wang-editor` | WangEditor 插件 clean repo | Public |
| `filamentboot-site` | 官网插件 clean repo | Public |
| `filamentboot-www` | 官网静态站（D-22，独立 repo） | Public |
| `filamentboot-demo` | 演示站 Laravel app（D-21，独立 repo） | Public |

3. 仓库初始化选项：
   - Description: 对应包的简短说明
   - 不勾选「Initialize this repository with a README」（避免与 push 冲突）
   - License: MIT

**Where：** [github.com/organizations/filamentboot](https://github.com/organizations/filamentboot)

**验收：**
- [ ] `github.com/filamentboot/workspace` 仓库存在
- [ ] `github.com/filamentboot/filamentboot` 仓库存在
- [ ] 全部 10 个仓库均已创建

---

## Step 2 — 创建 Gitee 同名 org + 10 个 mirror repo + 逐 repo 加部署公钥（D-11）

**What：** 在 Gitee 创建 `filamentboot` org 及 10 个镜像仓库，并为每个仓库添加 `GITEE_SSH_KEY` 对应的公钥（可推送权限）。
GitHub Actions 会自动推送到 Gitee 镜像，但**每个 Gitee repo 必须单独配置 Deploy Key**（Gitee 无 Org 级 Deploy Key，Pitfall 4 缓解）。

**How：**
1. 创建 Gitee org：[gitee.com/organizations/new](https://gitee.com/organizations/new)
   - 组织路径：`filamentboot`
2. 在该 org 下逐一创建 10 个仓库（与 GitHub 同名，设为公开）
3. 为每个仓库添加部署公钥：
   - 公钥文件：`~/.ssh/filamentboot_gitee.pub`（由 `SECRETS-CHECKLIST.md` Step 1 生成）
   - 操作路径：`仓库设置 → 部署公钥 → 添加部署公钥`
   - **必须勾选「可推送」**（否则只读，Actions 推送报 403）

需配置的 8 个 Gitee repo（workspace + 7 包 clean repo，不含 www/demo 镜像）：

| Gitee 仓库 | 操作 |
|-----------|------|
| `filamentboot/workspace` | 添加 `GITEE_SSH_KEY` 公钥，勾选「可推送」 |
| `filamentboot/filamentboot` | 添加 `GITEE_SSH_KEY` 公钥，勾选「可推送」 |
| `filamentboot/filamentboot-cos` | 添加 `GITEE_SSH_KEY` 公钥，勾选「可推送」 |
| `filamentboot/filamentboot-oss` | 添加 `GITEE_SSH_KEY` 公钥，勾选「可推送」 |
| `filamentboot/filamentboot-rich-editor` | 添加 `GITEE_SSH_KEY` 公钥，勾选「可推送」 |
| `filamentboot/filamentboot-markdown-editor` | 添加 `GITEE_SSH_KEY` 公钥，勾选「可推送」 |
| `filamentboot/filamentboot-wang-editor` | 添加 `GITEE_SSH_KEY` 公钥，勾选「可推送」 |
| `filamentboot/filamentboot-site` | 添加 `GITEE_SSH_KEY` 公钥，勾选「可推送」 |

**Where：** [gitee.com/filamentboot](https://gitee.com/filamentboot)（需先创建 org）

**验收：**
- [ ] Gitee org `filamentboot` 存在
- [ ] 10 个 mirror repo 均已创建
- [ ] 8 个 repo（workspace + 7 包）均已添加 `GITEE_SSH_KEY` 公钥，且勾选了「可推送」

---

## Step 3 — 填写 GitHub Actions / Gitee Go Secrets（D-17）

**What：** 按 `SECRETS-CHECKLIST.md` 中每个 secret 的获取步骤，逐项填写到对应平台。

**How：** 参照 `SECRETS-CHECKLIST.md` 各节，按顺序操作：

1. **GitHub Actions Secrets**（`filamentboot/workspace` repo settings）：
   - `PACKAGE_GITHUB_TOKEN` — GitHub PAT（repo scope）
   - `GITEE_SSH_KEY` — Gitee 镜像 SSH 私钥
   - `PACKAGIST_TOKEN` — Packagist API token
   - `DEPLOY_KEY` — 服务器 SSH 私钥（filamentboot_deploy）
   - `DEPLOY_HOST` = `118.25.27.49`
   - `DEPLOY_USER` = `root`
   - `CI_APP_KEY` — `php artisan key:generate --show` 的输出

2. **Gitee Go 通用变量**（workspace 仓库 → 流水线 → 通用变量）：
   - `DEPLOY_KEY`、`DEPLOY_HOST`、`DEPLOY_USER`（与 GitHub Actions 同值）

3. **filamentboot-www repo GitHub Actions Secrets**（该 repo settings）：
   - `DEPLOY_KEY`、`DEPLOY_HOST`、`DEPLOY_USER`

**Where：**
- GitHub Actions Secrets：`https://github.com/filamentboot/workspace/settings/secrets/actions`
- Gitee Go 变量：`gitee.com/filamentboot/workspace` → 流水线 → 通用变量

**验收：**
- [ ] GitHub Actions 7 个 secrets 全部填写
- [ ] Gitee Go 3 个变量全部填写
- [ ] filamentboot-www repo 3 个 secrets 填写

---

## Step 4 — Packagist 注册 + webhook 配置（D-16，D-25 demo 前置）

**What：** 注册 Packagist 账号，提交 7 个 clean repo 到 Packagist，配置每个 GitHub clean repo 的 push webhook。
**此步骤是 demo 站部署的前置条件（D-25）：demo `composer require filamentboot/filamentboot` 必须等 Packagist 收录后才能执行。**

**How：**
1. 注册 Packagist 账号（若无）：[packagist.org/register](https://packagist.org/register/)
2. 提交核心包：[packagist.org/packages/submit](https://packagist.org/packages/submit)
   - 填入：`https://github.com/filamentboot/filamentboot`
3. 对其余 6 个插件 clean repo 逐一提交（重复步骤 2）：
   - `https://github.com/filamentboot/filamentboot-cos`
   - `https://github.com/filamentboot/filamentboot-oss`
   - `https://github.com/filamentboot/filamentboot-rich-editor`
   - `https://github.com/filamentboot/filamentboot-markdown-editor`
   - `https://github.com/filamentboot/filamentboot-wang-editor`
   - `https://github.com/filamentboot/filamentboot-site`
4. 在**每个 GitHub clean repo** 配置 push webhook（Settings → Webhooks → Add webhook）：
   - Payload URL：`https://packagist.org/api/github?username=<YOUR_PACKAGIST_USERNAME>`
   - Content type：`application/json`
   - Secret：填入 `PACKAGIST_TOKEN`（Packagist API token）
   - Events：**Just the push event**（不选 All events）
5. 获取 Packagist API token：登录 packagist.org → Profile → API tokens → Create

**Pitfall 5 缓解：** demo 站执行 `composer require filamentboot/filamentboot` 之前，必须先在
`https://packagist.org/packages/filamentboot/filamentboot` 确认包已存在且版本正确。

**Where：**
- Packagist 提交：[packagist.org/packages/submit](https://packagist.org/packages/submit)
- 每个 GitHub clean repo Settings → Webhooks

**验收：**
- [ ] Packagist 账号已注册
- [ ] `packagist.org/packages/filamentboot/filamentboot` 页面存在且有版本
- [ ] 7 个 clean repo 均已提交到 Packagist
- [ ] 每个 clean repo 均已配置 GitHub push webhook
- [ ] `curl -sf https://packagist.org/p2/filamentboot/filamentboot.json | python3 -m json.tool` 返回有版本的 JSON

---

## Step 5 — 服务器一次性迁移（D-19/D-20）

**What：** 把服务器 `118.25.27.49` 的旧 filament-admin 配置全部迁移为 filamentboot 标识：
目录改名、supervisor 改名、新建 demo 数据库、nginx vhost、webhook token 轮换。

**How：** 执行 `scripts/migrate-server.sh`（本 repo 根目录 `scripts/` 下，Task 2 产出）。

脚本关键顺序（RESEARCH Critical ordering）：
1. 停旧 worker：`supervisorctl stop filament-admin-worker:*`
2. 移目录：`mv /data/filament-admin /data/filamentboot`（含确认步骤）
3. supervisor 改名：`filament-admin-worker` → `filamentboot-worker`，路径更新，重启
4. 新建 demo 库：`CREATE DATABASE IF NOT EXISTS filamentboot_demo`
5. nginx vhost：复制模板，reload
6. webhook token 轮换（D-18，见 Step 6）
7. 演示站部署（**必须等 Packagist 收录后，D-25**）
8. 验证：`curl -sI http://127.0.0.1 -H 'Host: demo.xitongapp.com'`

**完整迁移脚本见：** `scripts/migrate-server.sh`
**nginx vhost 模板见：** `nginx/demo.xitongapp.com.conf`

**Where：** SSH `root@118.25.27.49` 执行迁移脚本

**验收：**
- [ ] `/data/filamentboot` 目录存在
- [ ] `supervisorctl status filamentboot-worker:*` 显示 RUNNING
- [ ] `filamentboot_demo` 数据库存在
- [ ] nginx 加载 `demo.xitongapp.com.conf` 无报错（`nginx -t` 通过）
- [ ] `demo.xitongapp.com` HTTP 200

---

## Step 6 — Webhook Token 轮换（D-18）

**What：** 旧 webhook token `ea2477cb...` 已明文泄露在 wiki 中（`wiki/guide/auto-deployment.md`，已在 13-03 清除明文），服务器侧需生成新 token 并更新 Gitee webhook URL。

**How：**
1. 在服务器生成新 token：
   ```bash
   openssl rand -hex 32 > /data/filamentboot/.webhook_token
   chmod 600 /data/filamentboot/.webhook_token
   cat /data/filamentboot/.webhook_token  # 查看值，用于步骤 2
   ```
2. 登录 Gitee → `filamentboot/workspace` 仓库 → 管理 → WebHooks
   - 找到旧 webhook URL（`https://www.xitongapp.com/deploy-webhook.php?token=ea2477cb...`）
   - 改为：`https://www.xitongapp.com/deploy-webhook.php?token=<新 token>`
3. 旧 webhook token 文件 `/data/filament-admin/.webhook_token`（若存在）可在旧目录稳定后删除

**安全约束：**
- `.webhook_token` 文件已在 `.gitignore` 中排除，不提交到代码库
- 新 token 仅存在于服务器文件系统，不填写到任何 secret store

**Where：** SSH 服务器 + Gitee 仓库 WebHook 管理页面

**验收：**
- [ ] `/data/filamentboot/.webhook_token` 文件存在，权限 600
- [ ] Gitee webhook URL 已更新为新 token
- [ ] 推一次 commit，Gitee webhook 触发成功（Gitee → WebHook → 最近推送记录）

---

## Step 7 — SSH Key 轮换（D-20）

**What：** 生成新 `filamentboot_deploy` SSH key，替换旧 `filament_deploy` key，更新服务器 authorized_keys 和所有 secret。

**How：**
1. 生成新 key（本地执行）：
   ```bash
   ssh-keygen -t ed25519 -C "filamentboot_deploy" -f ~/.ssh/filamentboot_deploy
   ```
2. 公钥追加到服务器：
   ```bash
   ssh-copy-id -i ~/.ssh/filamentboot_deploy.pub root@118.25.27.49
   # 或手动：cat ~/.ssh/filamentboot_deploy.pub | ssh root@118.25.27.49 "cat >> ~/.ssh/authorized_keys"
   ```
3. 测试新 key 可连接：
   ```bash
   ssh -i ~/.ssh/filamentboot_deploy root@118.25.27.49 "echo 'new key works'"
   ```
4. 将新私钥内容填入所有 `DEPLOY_KEY` secret（GitHub Actions + Gitee Go，见 Step 3）
5. 测试 Gitee Go 流水线与 GitHub Actions deploy 均可正常触发
6. 确认无误后，从服务器移除旧 `filament_deploy` 公钥：
   ```bash
   # 服务器上编辑 ~/.ssh/authorized_keys，删除含 "filament_deploy" 注释的行
   ```

**Where：** 本地 terminal + SSH 服务器 + GitHub/Gitee secret 管理页面

**验收：**
- [ ] `ssh -i ~/.ssh/filamentboot_deploy root@118.25.27.49 "echo ok"` 输出 `ok`
- [ ] Gitee Go 流水线可用新 key 触发成功
- [ ] 旧 `filament_deploy` 公钥已从服务器 authorized_keys 移除

---

## 附：本地 git remote 重配（D-12，agent 已执行）

**Agent 已完成**（本 Task 1 执行期间）：
- 删除旧混乱 remote：`package-github`、`package-gitee`、`preview`、`site-github`
- 添加/更新 `origin` → `https://github.com/filamentboot/workspace.git`
- 添加 `gitee` → `https://gitee.com/filamentboot/workspace.git`

用户可通过 `git remote -v` 验证：
```
origin   https://github.com/filamentboot/workspace.git (fetch)
origin   https://github.com/filamentboot/workspace.git (push)
gitee    https://gitee.com/filamentboot/workspace.git (fetch)
gitee    https://gitee.com/filamentboot/workspace.git (push)
```

**本地不需要手动 push Gitee — GitHub Actions `release.yml` 会自动镜像（D-11）。**

---

## 附：本地目录重命名（可选）

本地工作目录 `~/src/personal/filament-admin/` 可选重命名为 `filamentboot/`：
```bash
mv ~/src/personal/filament-admin ~/src/personal/filamentboot
```
不影响功能（remote 和 git 历史不变），是纯本地路径偏好。不阻塞任何外部步骤。

---

## 操作顺序总结

| 步骤 | 操作 | 阻塞项 |
|------|------|--------|
| Step 0 | 确认 Gitee Go 运行 | Wave 2 CD 前提 |
| Step 1 | 创建 10 个 GitHub repo | Step 3/4 的前置 |
| Step 2 | 创建 Gitee org + repo + 部署公钥 | GitHub Actions mirror 前置 |
| Step 3 | 填写所有 secrets | release.yml / deploy.sh 运行前置 |
| Step 4 | Packagist 注册 + webhook | **demo 站部署的强前置（D-25）** |
| Step 5 | 服务器迁移（执行 migrate-server.sh） | Packagist 收录后才执行 demo 部署子步 |
| Step 6 | Webhook token 轮换 | 可与 Step 5 合并执行 |
| Step 7 | SSH key 轮换 | Step 3 secrets 更新后验证 |

**关键依赖链（D-25）：** Wave 1 改名落地 → Packagist 收录 → demo `composer require` → 演示站部署
