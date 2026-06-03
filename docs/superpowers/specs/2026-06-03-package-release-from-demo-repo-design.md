# 演示项目内孵化独立包并完成发布设计

## 1. 背景

当前仓库 `/home/john/projects/personal/filament-admin` 仍然是一个完整的 Laravel 演示项目仓库，包含 `app/`、`bootstrap/`、`routes/`、`public/`、`storage/` 等应用级目录。

这意味着：

- 当前仓库根目录适合作为演示项目运行与联调
- 当前仓库根目录不适合作为 `filament-admin/filament-admin` 的最终对外发布仓库
- 若直接将根目录发布到 GitHub / Packagist，消费者拿到的会是“演示项目伪装成包”的混合体

本次任务需要纠正这个边界错误，同时保证“包能发布”和“演示项目能继续跑通”两件事都成立。

## 2. 目标

本次只做一个完整任务：

**在当前演示项目仓库内孵化 `filament-admin` 独立包，并成功发布到 GitHub 与 Packagist。**

### 2.1 必须达成

1. 当前根仓库继续保留为演示项目仓库
2. 新建真正的包目录，作为 `filament-admin/filament-admin` 的源码根
3. 本次要彻底摆脱 `PluginPlatform`
4. 演示项目通过本地 `path repository` 或等价联调方式使用该包
5. 对外 GitHub 包仓库根目录必须是纯包内容，而不是整个 Laravel 项目
6. 完成 Packagist 接入
7. 验证 `composer require filament-admin/filament-admin` 可安装

### 2.2 不在本次范围

1. 不要求把演示项目和包彻底拆成两个长期独立开发仓库
2. 不要求本次解决所有业务 bug
3. 不要求本次完成插件市场独立包发布
4. 不要求本次把全部测试、静态分析和 CI 优化到最终形态

## 3. 核心判断

### 3.1 当前根仓库的正确角色

当前根仓库的正确角色应定义为：

- **演示项目仓库**
- 用于启动 Laravel、跑联调、做人工验收、承载宿主项目配置

它不应再直接承担以下职责：

- `filament-admin/filament-admin` 的最终发布仓库
- Packagist 的根发布源

### 3.2 包的正确形态

`filament-admin` 应作为一个独立 Composer 包存在，但可以先在当前演示项目仓库中以**子目录包**的方式孵化。

推荐形态：

```text
packages/filament-admin/
```

该目录将作为包的真实边界，包含：

- `composer.json`
- `src/`
- `config/`
- `database/`
- `resources/`
- `stubs/`
- `tests/`
- `README.md`
- `CHANGELOG.md`
- `LICENSE`

### 3.3 演示项目与包的关系

演示项目是宿主，包是被依赖方。

开发与测试路径应调整为：

1. 在当前根仓库内维护演示项目
2. 在 `packages/filament-admin/` 内维护主包
3. 演示项目通过本地 `path repository` 依赖该包
4. 发布时只把 `packages/filament-admin/` 的内容推送到 GitHub 包仓库

## 4. 方案比较

### 方案 A：继续直接发布当前根仓库

优点：

- 最快

缺点：

- 根目录仍是应用，不是纯包
- Packagist 消费者拿到错误仓库边界
- 后续 README、安装方式、版本语义都会继续混乱

结论：

- 不采用

### 方案 B：单仓库开发，子目录孵化包，对外分仓发布

优点：

- 演示项目仍能正常运行和测试
- 包边界清晰
- 对外发布仓库可以保持纯包形态
- 迁移成本比双仓完全拆分低

缺点：

- 需要一次目录迁移和 split 发布流程
- 仓库内会同时存在宿主与包两套语义

结论：

- **本次采用**

### 方案 C：立即彻底拆成两个长期独立仓库

优点：

- 仓库边界最干净

缺点：

- 迁移成本高
- 当前发包目标容易被打断
- 演示项目联调链路会先断一次

结论：

- 本次不采用

## 5. 采用方案

本次采用 **方案 B：单仓库开发，子目录孵化包，对外分仓发布**。

### 5.1 仓库结构目标

#### 演示项目仓库根目录

继续保留：

- Laravel 宿主应用结构
- 演示环境启动能力
- 面向演示项目的 `.env`、路由、页面、宿主配置

#### 包目录

新增：

```text
packages/filament-admin/
```

该目录成为主包唯一真实发布源。

### 5.2 发布仓库策略

- GitHub：`https://github.com/john-captain/filament-admin`
  - 作为主包公开发布仓库
- Gitee：`https://gitee.com/johncaptain/filament-admin`
  - 作为同步发布仓库
- Gitee Preview：`git@gitee.com:johncaptain/filament-admin-preview.git`
  - 继续作为演示项目仓库

### 5.3 Packagist 策略

Packagist 只接 GitHub 包仓库，不接演示项目仓库。

Packagist 条目必须对应：

- 仓库根目录是纯包
- `composer.json` 位于包仓库根目录
- 安装命令直接为：

```bash
composer require filament-admin/filament-admin
```

## 6. 功能与结构要求

### 6.1 主包必须彻底摆脱 PluginPlatform

本次是硬约束，不允许残留：

1. 不再依赖 `filament-admin/plugin-platform`
2. 不再包含 `packages/plugin-platform/`
3. 不再引用 `FilamentAdmin\\PluginPlatform`
4. 不再保留 `plugin_platform_*` 数据结构残留
5. 不再把插件市场描述为当前主包内置能力

### 6.2 演示项目继续可运行

包独立化后，演示项目仍需满足：

1. 仍可作为 Laravel 项目启动
2. 仍可加载 `FilamentAdmin` 主包
3. 仍可作为主包联调和人工验收环境

### 6.3 包目录需要具备基本发布能力

至少包括：

1. 独立 `composer.json`
2. 独立自动加载
3. 独立 README / CHANGELOG / 升级说明
4. 独立测试入口
5. 独立 ServiceProvider / Plugin 注册能力

## 7. 实施顺序

本次执行顺序固定如下：

1. 重新定义仓库边界
2. 在当前仓库中建立 `packages/filament-admin/`
3. 将主包代码迁移到包目录
4. 让演示项目改为依赖本地包
5. 清理 `PluginPlatform` 与旧残留
6. 补齐包目录发布资料
7. 验证演示项目仍可联调
8. 将包目录发布到 GitHub 包仓库
9. 同步到 Gitee 包仓库
10. 接入 Packagist
11. 验证 `composer require filament-admin/filament-admin`

## 8. 测试与验证要求

### 8.1 包边界验证

必须验证：

- 包目录不包含演示项目根级 Laravel 运行结构
- 包目录中不再出现 `PluginPlatform` 运行时引用
- 包目录 `composer.json` 符合对外发布要求

### 8.2 演示项目联调验证

必须验证：

- 演示项目仍可加载本地包
- 关键后台入口仍可访问
- 与本次迁移直接相关的测试可以通过

### 8.3 发布验证

必须验证：

- GitHub 包仓库根目录为纯包内容
- Packagist 已收录
- 新建干净 Laravel 项目可执行：

```bash
composer require filament-admin/filament-admin
```

## 9. 风险与约束

### 9.1 最大风险

最大的风险不是代码 bug，而是**仓库边界再次混乱**。

如果继续把：

- 演示项目
- 主包
- 插件市场

混在同一个发布根里，对外安装体验会持续失真。

### 9.2 本次约束

1. 先保证发包成功，再谈结构美化
2. 包不能依赖演示项目根目录语义
3. 演示项目可以依赖包
4. 任何发布步骤都不得再把当前根仓库直接当最终包仓库

## 10. 成功标准

本次任务完成的唯一判定标准是：

1. 当前根仓库仍然是可运行的演示项目
2. `packages/filament-admin/` 已成为真实包目录
3. 主包已彻底摆脱 `PluginPlatform`
4. GitHub 包仓库根目录是纯包
5. Gitee 包仓库同步完成
6. Packagist 已成功接入
7. `composer require filament-admin/filament-admin` 验证通过
