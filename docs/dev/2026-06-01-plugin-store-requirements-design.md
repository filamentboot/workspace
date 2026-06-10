# 插件商店独立项目需求与交互设计规格

> 文档版本：2026-06-01
> 文档状态：**决策已锁定，可进入实施计划阶段**
> 关联主项目：FilamentAdmin（`xitongapp.com`）
> 本文档范围：`www.xitongapp.com` 插件商店独立项目（仓库：`filament-admin/store`）的完整需求与交互方案

---

## 一、域名和仓库规划架构规划

| 子域名 / 仓库 | 用途 | 状态 | Gitee（镜像） | GitHub（主） | Packagist |
|--------|------|------|------|------|------|
| `www.xitongapp.com` | **All-in-One 主站**：官网营销页 + 文档站 + 插件商店（浏览/购买/API） + 付费插件分发（Satis 兼容私有 Composer 仓库） | 规划中 | - | - | - |
| `demo.xitongapp.com` | 演示站（基于主仓库部署，每日自动重置） | 规划中 | `https://gitee.com/johncaptain/filament-admin` | filament-admin | - |
| 无 | `laravelstack/filament-admin`（主后台基础包，独立 Composer 包） | 规划中 | 镜像 | laravelstack/filament-admin | packagist |
| 无 | `filament-admin/plugin-platform`（插件市场客户端包，独立 Composer 包，需依赖主包） | 规划中 | 镜像 | filament-admin/plugin-platform | packagist |

---

> ## ⚠️ 重要：两个包是完全独立的，不是同一个东西
>
> 过去开发过程中多次将这两个包混淆，此处强制说明，后续所有代码、文档、讨论必须严格区分。
>
> | | `laravelstack/filament-admin` | `filament-admin/plugin-platform` |
> |---|---|---|
> | **定位** | 主后台基础框架，核心宿主 | 插件市场客户端，是一个**可选插件** |
> | **职责** | 提供后台登录、权限、管理员管理、日志等基础能力 | 提供插件浏览、安装、License 校验等市场 UI 能力 |
> | **依赖关系** | 不依赖 plugin-platform | **必须依赖** laravelstack/filament-admin 才能运行 |
> | **可否单独使用** | **可以**，不装 plugin-platform 也是完整的后台 | **不可以**，没有主包则无法运行 |
> | **安装** | `composer require laravelstack/filament-admin` | `composer require filament-admin/plugin-platform` |
> | **开源协议** | MIT | MIT |
>
> **一句话总结：`filament-admin` 是地基，`plugin-platform` 是盖在地基上的一栋楼。没有地基楼无法存在，但地基不需要楼也能使用。**

---

**依赖关系：**

```
FilamentAdmin 后台
    └── 调用 www.xitongapp.com/api/v1/index.json（获取插件索引）
    └── 调用 www.xitongapp.com/api/v1/license/verify（验证 License）
    └── 调用 www.xitongapp.com（付费插件分发，License 校验通过后下载）
```

---

## 二、项目定位与边界

### 2.1 这是什么

`www.xitongapp.com` 是 FilamentAdmin 插件生态的商业闭环平台，承载：

- **对用户**：浏览、购买、管理已购插件
- **对开发者**：提交、上架、管理插件，接收收益
- **对 FilamentAdmin 实例**：提供机器可读的插件索引 JSON 和 License 校验 API
- **对平台管理员**：插件审核、开发者管理、订单管理、结算管理

### 2.2 这不是什么

- 不是 FilamentAdmin 主项目的功能模块
- 不是主项目的 Filament 后台里的一个 Resource
- 不处理 FilamentAdmin 本身的安装和部署
- 第一版不提供插件在线升级（由 FilamentAdmin 主项目的后续版本实现）
- 不是官方网站的营销页或文档站（官网需求后续独立移出）

### 2.3 与主项目的关系

```
FilamentAdmin（主项目）          www.xitongapp.com（本项目）
    ├── 内置：插件市场 UI           ├── 插件索引 JSON API
    ├── 内置：License 输入框  <-->  ├── License 校验 API
    └── 内置：安装链路              └── 付费插件分发（集成路由）
```

主项目只调用本项目的 API，不共享数据库，不共享代码仓库。

---

## 三、用户角色与权限

| 角色 | 来源 | 核心权限 |
|------|------|---------|
| **访客（Guest）** | 未登录 | 浏览插件列表和详情、查看文档链接 |
| **注册用户（User）** | 邮箱注册 或 GitHub OAuth | 购买插件、管理 License、配置私有源 token |
| **插件开发者（Developer）** | 注册用户 + 申请认证 | 提交插件、管理版本、查看收益、申请提现 |
| **平台管理员（Admin）** | 后台创建 | 插件审核、开发者管理、订单管理、分成结算、索引发布 |

---

## 四、功能模块清单

### 4.1 公开浏览（面向访客和注册用户）

**首页**

- 精选插件轮播（管理员配置）
- 分类导航（官方插件、扩展能力、方案型插件）
- 热门插件（按下载量/购买量）
- 最新上架
- 统计展示（插件总数、开发者数、安装次数）

**插件列表**

- 搜索（关键词匹配名称、描述、标签）
- 筛选：分类、价格（免费/付费）、来源（官方可信/官方收录）、兼容版本
- 排序：最新上架、最多下载、评分最高
- 卡片展示：名称、Logo、简介、价格、评分、来源标签

**插件详情页**

- 基本信息：名称、Logo、作者、版本、更新时间、兼容版本范围
- 来源标签（「官方可信来源」徽章）
- 价格与购买入口（免费 / 一次性买断）
- Tabs：
  - 介绍（README 渲染）
  - 更新日志（CHANGELOG）
  - 安装说明（命令或 License Key 引导）
  - 评价（评分 + 文字评价，购买后才能评价）
- 侧边栏：依赖说明、开发者信息、文档链接、代码仓库链接（公开插件）

**开发者主页**

- 开发者信息（头像、简介、加入时间）
- 已发布插件列表

### 4.2 用户中心（注册用户）

- 登录 / 注册（邮箱密码 + GitHub OAuth）
- 个人信息（头像来源 GitHub/Gravatar，邮箱，密码修改）
- **已购插件**：购买记录、License Key 查看
- **私有源配置**：查看 `www.xitongapp.com` 的 Composer auth token（付费插件下载凭证）
- **订单记录**：订单列表、状态、支付凭据、发票申请（可选）

### 4.3 开发者后台（插件开发者）

**入驻流程**

1. 注册用户申请成为开发者
2. 填写：真实姓名（或公司名）、联系方式、收款信息（支付宝/银行账户）、GitHub 账号
3. 平台管理员审核（1-3 个工作日）
4. 审核通过后解锁开发者权限

**插件管理**

- 插件列表（自己的插件）
- **提交新插件**（见 §六 提交流程）
- 版本管理：发布新版本、填写 CHANGELOG、标注兼容版本范围
- 下架插件（需说明理由，已购用户可继续使用）

**收益统计**

- 近期收益图表（按天/月）
- 各插件销量明细
- 待结算金额、历史已结算金额

**提现申请**

- 申请提现（满 100 元可提现）
- 提现记录（状态：处理中 / 已打款 / 拒绝）

### 4.4 平台管理后台（Admin Panel，基于 Filament）

**插件审核**

- 待审核队列：插件名称、提交时间、开发者
- CI 检测结果展示（Pest 通过 / PHPStan 通过 / composer audit 状态 / 危险函数扫描）
- 人工审核表单：审核意见、通过 / 驳回
- 驳回时填写原因（自动通知开发者）

**开发者管理**

- 开发者列表（认证状态、插件数、总销量）
- 入驻申请审核
- 冻结 / 解冻开发者

**订单管理**

- 订单列表（用户、插件、金额、支付方式、状态）
- 退款处理（7 天无理由退款）

**分成结算**

- 待结算列表（开发者、金额、周期）
- 批量打款操作
- 结算记录

**索引发布**

- 手动触发重新生成 `index.json`
- 查看当前 index.json 内容
- 版本历史（可回滚到上一个版本）

**内容管理**

- 首页精选插件配置
- 分类管理
- 公告管理

---

## 五、插件索引 JSON 服务

### 5.1 接口规范

```
GET https://www.xitongapp.com/api/v1/index.json
```

响应格式：

```json
{
  "generated_at": "2026-06-01T00:00:00Z",
  "schema_version": "1",
  "filamentadmin_compat": "^1.0",
  "plugins": [
    {
      "name": "filament-admin/plugin-platform",
      "display_name": "插件市场",
      "description": "FilamentAdmin 官方插件市场基础包，提供插件发现、状态管理、安装链路能力。",
      "type": "plugin",
      "category": "core",
      "source": "official-trusted",
      "latest_version": "1.0.0",
      "versions": [
        {
          "version": "1.0.0",
          "requires_filamentadmin": "^1.0",
          "requires_php": "^8.2",
          "released_at": "2026-06-01"
        }
      ],
      "price": 0,
      "price_type": "free",
      "license": "MIT",
      "free": true,
      "author": {
        "name": "FilamentAdmin",
        "url": "https://www.xitongapp.com/developers/filament-admin"
      },
      "homepage": "https://www.xitongapp.com/plugins/plugin-platform",
      "packagist": "https://packagist.org/packages/filament-admin/plugin-platform",
      "docs_url": "https://www.xitongapp.com/docs/plugins/plugin-platform",
      "install_command": "composer require filament-admin/plugin-platform",
      "requires_license": false,
      "tags": ["core", "marketplace", "official"]
    }
  ]
}
```

### 5.2 更新触发机制

- 插件上架 / 下架 / 发布新版本时，自动触发 index.json 重新生成
- 管理员也可手动触发
- 生成后存入对象存储（阿里云 OSS 或本地文件），通过 CDN 分发
- index.json 设置 `Cache-Control: public, max-age=3600`（1 小时）

### 5.3 版本化索引

- 当前最新：`/api/v1/index.json`
- 历史版本：`/api/v1/index/{date}.json`（每次生成保留一份快照）
- 主版本锁定版本（可选，未来多版本并存时使用）：`/api/v1/index/fa-1.json`

### 5.4 License 校验接口

```
POST https://www.xitongapp.com/api/v1/license/verify

Request:
{
  "package": "vendor/package-name",
  "license_key": "XXXX-XXXX-XXXX-XXXX"
}

Response（成功）:
{
  "valid": true,
  "expires_at": null,          // null 表示买断，有值表示到期时间
  "download_token": "xxx",     // 短期有效的下载 Token（5 分钟有效期）
  "message": "License 有效"
}

Response（失败）:
{
  "valid": false,
  "message": "License 无效或已过期"
}
```

**安全策略（已决策）：**

- 验证成功后返回**短期有效的下载 Token**（5 分钟），而非长期 Composer Token
- 下载 Token 仅用于单次插件包下载，使用后失效
- 避免 Token 泄露导致付费插件被无限分发

**校验策略（已决策）：**

- **不绑定域名**：License Key 只要存在且未过期即有效，不限制使用域名数量
- 允许 72 小时离线缓存，缓存期内断网不影响插件运行
- 校验失败时插件进入「只读模式」，不影响主项目运行，但功能受限

---

## 六、关键交互流程

### 6.1 用户购买并安装付费插件

```
访客浏览插件列表
    → 点击插件详情
    → 点击「立即购买」
    → 跳转登录/注册（未登录）
    → 确认订单（价格、授权数量）
    → 选择支付方式（微信支付 / 支付宝）
    → 扫码/跳转完成支付
    → 支付成功页
        └── 显示 License Key（可复制）
        └── 显示私有 Packagist Token
        └── 显示详细安装说明
    → 进入用户中心「已购插件」
    → 复制 License Key
    → 进入 FilamentAdmin 后台 → 插件市场
    → 找到对应插件 → 点击「安装」
    → 弹出 License Key 输入框
    → 填入 License Key → 系统校验（调 License API）
    → 校验通过 → 执行 Composer 安装流程
    → 安装成功 → 插件进入「已安装」状态
```

### 6.2 开发者提交新插件

```
开发者登录
    → 进入开发者后台 → 「提交新插件」
    → 填写基本信息：
        - Composer 包名（vendor/package-name）
        - 分类、标签
        - 简介
        - 代码仓库 URL（公开插件必须填写）
        - README（Markdown，可预览）
        - CHANGELOG（Markdown）
        - 截图上传（最少 1 张，最多 6 张）
        - 兼容版本范围
        - 价格（免费 / 一次性买断）
        - 来源申请（「官方可信来源」需额外审核）
    → 填写版本信息：
        - 版本号（SemVer）
        - composer.json 内容（粘贴或 URL 拉取）
        - 安装说明
    → 提交
    → 系统自动 CI（5-15 分钟）：
        ├── composer.json 格式合规性检查
        ├── Pest 测试（从仓库拉取代码执行）
        ├── PHPStan level 5
        ├── composer audit（已知漏洞）
        └── 危险函数扫描（eval/exec/system/passthru/shell_exec）
    → CI 通过 → 进入人工审核队列
    → 管理员审核（1-5 个工作日）
        ├── 通过 → 插件上架 → 更新 index.json → 邮件通知开发者
        └── 驳回 → 填写原因 → 邮件通知开发者 → 开发者可修改后重新提交
```

### 6.3 FilamentAdmin 实例获取插件索引

```
FilamentAdmin 后台启动
    → 插件市场 Filament Plugin 初始化
    → 检查本地缓存（app cache，TTL 60 分钟）
        ├── 缓存命中 → 直接使用
        └── 缓存未命中 → 请求 www.xitongapp.com/api/v1/index.json
            ├── 请求成功 → 更新缓存 → 渲染插件列表
            └── 请求失败（超时/离线）→ 使用上次缓存（若存在）→ 显示「市场数据可能不是最新」提示

注意：未安装插件的市场条目不写入 MySQL，只存在于 Cache 和页面渲染层。
```


---

## 七、插件开发规范与品质门槛

> 本节为插件开发者参考文档，上架前必须满足全部硬性要求。

### 7.1 目录结构规范

```
vendor-name/
├── composer.json           # 必须，完整 Composer 元数据
├── README.md               # 必须，安装 + 配置 + 升级指南（中文或英文）
├── CHANGELOG.md            # 必须，遵循 Keep a Changelog 格式
├── LICENSE                 # 必须，明确授权协议
├── src/
│   ├── Plugin.php          # 必须，实现 FilamentPlugin 接口
│   ├── PluginServiceProvider.php  # 必须
│   └── ...                 # 其余业务代码
├── config/                 # 可选，插件配置文件
├── resources/
│   ├── views/              # 可选
│   └── lang/               # 可选，语言包
├── database/
│   ├── migrations/         # 可选
│   └── seeders/            # 可选
└── tests/
    ├── Pest.php
    └── Feature/            # 至少包含基础冒烟测试
```

### 7.2 composer.json 元数据要求

```json
{
    "name": "vendor/package-name",
    "description": "插件功能一句话描述",
    "type": "library",
    "license": "MIT",
    "authors": [
        {
            "name": "开发者名称",
            "email": "email@example.com"
        }
    ],
    "require": {
        "php": "^8.2",
        "filament/filament": "^3.0 || ^5.0"
    },
    "require-dev": {
        "pestphp/pest": "^3.0",
        "larastan/larastan": "^3.0"
    },
    "extra": {
        "laravel": {
            "providers": [
                "Vendor\\PackageName\\PluginServiceProvider"
            ]
        },
        "filament-admin": {
            "plugin": "Vendor\\PackageName\\Plugin",
            "display_name": "插件中文名",
            "category": "capability",
            "min_filamentadmin": "1.0.0"
        }
    },
    "autoload": {
        "psr-4": {
            "Vendor\\PackageName\\": "src/"
        }
    }
}
```

### 7.3 Plugin.php 接口规范

```php
<?php

namespace Vendor\PackageName;

use FilamentAdmin\Contracts\FilamentAdminPlugin;

/**
 * 插件主入口类，必须实现 FilamentAdminPlugin 接口。
 */
class Plugin implements FilamentAdminPlugin
{
    /**
     * 返回插件唯一标识符（与 composer.json name 一致）。
     */
    public function getId(): string
    {
        return 'vendor/package-name';
    }

    /**
     * 注册插件：绑定服务、注册事件监听器等。
     */
    public function register(Panel $panel): void
    {
        // 注册 Filament 资源、页面、Widget
    }

    /**
     * 启动插件：执行需要在所有服务注册完成后才能执行的逻辑。
     */
    public function boot(Panel $panel): void
    {
        // 注册菜单、权限等
    }

    /**
     * 返回插件所需的初始化步骤（可选）。
     * 返回空数组表示无需初始化向导。
     */
    public function getInitializationSteps(): array
    {
        return [];
    }
}
```

### 7.4 自动化 CI 检查项（上架必须全部通过）

| 检查项 | 工具 | 通过标准 |
|--------|------|---------|
| 单元/功能测试 | Pest 4+ | 全部通过，无跳过 |
| 静态分析 | PHPStan / Larastan | Level 5 无错误 |
| 安全漏洞 | `composer audit` | 无已知漏洞 |
| 危险函数 | 自定义扫描脚本 | 不含 eval/exec/system/passthru/shell_exec |
| composer.json | 格式校验 | `composer validate` 通过 |
| `extra.filament-admin` | 字段完整性检查 | 必填字段不缺失 |

### 7.5 上架品质要求（人工审核）

- README 中文或英文，含安装步骤、配置说明、升级指南
- CHANGELOG 有至少一条真实记录
- 截图至少 1 张（实际后台界面截图，不是设计图）
- 不含品牌侵权内容（包名不冲突、不冒充官方插件）
- 代码无明显"恶意行为"（远程代码执行、数据窃取等）

---

## 八、演示插件（Demo Plugin）规范

### 8.1 演示插件定位

`filament-admin/demo-plugin` 是一个官方维护的最小可用插件，用途：

1. **测试用**：验证 FilamentAdmin 插件安装链路端到端可用
2. **参考用**：新插件开发者的目录结构和代码规范参考
3. **演示用**：在演示站和文档中展示插件生命周期

### 8.2 演示插件功能

最小功能集：

- 在 Filament 后台侧边栏注册一个「演示插件」菜单项
- 展示一个简单的 Dashboard Widget（显示"演示插件运行正常"）
- 提供一个配置页面（可配置一段自定义文案）
- 支持启用/禁用（禁用后菜单和 Widget 消失）

### 8.3 演示插件目录结构

```
filament-admin/demo-plugin/
├── composer.json
├── README.md
├── CHANGELOG.md
├── LICENSE
├── src/
│   ├── Plugin.php
│   ├── DemoPluginServiceProvider.php
│   ├── Filament/
│   │   ├── Pages/
│   │   │   └── DemoPage.php
│   │   └── Widgets/
│   │       └── DemoWidget.php
│   └── Settings/
│       └── DemoSettings.php
├── config/
│   └── demo-plugin.php
├── resources/
│   └── views/
│       ├── demo-page.blade.php
│       └── demo-widget.blade.php
└── tests/
    ├── Pest.php
    └── Feature/
        ├── PluginInstallationTest.php
        └── DemoWidgetTest.php
```

### 8.4 演示插件 composer.json 示例

```json
{
    "name": "filament-admin/demo-plugin",
    "description": "FilamentAdmin 官方演示插件，用于测试插件安装链路和作为开发参考。",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "filament/filament": "^5.0"
    },
    "extra": {
        "laravel": {
            "providers": ["FilamentAdmin\\DemoPlugin\\DemoPluginServiceProvider"]
        },
        "filament-admin": {
            "plugin": "FilamentAdmin\\DemoPlugin\\Plugin",
            "display_name": "演示插件",
            "category": "demo",
            "min_filamentadmin": "1.0.0"
        }
    }
}
```

---

## 九、技术选型建议

### 9.1 后端框架

- **Laravel 13 + Filament 5**：与主项目一致，降低学习成本，管理后台用 Filament 搭建
- Filament Admin Panel 承载：平台管理后台、开发者后台（两个独立 Panel）
- 公开浏览页面（插件列表、详情）：Livewire 3 + Tailwind CSS（或 Blade + Alpine.js）

### 9.2 数据存储

- MySQL 8.0：主数据库（用户、插件、订单、License、结算）
- Redis：缓存、Session、Queue
- 对象存储（阿里云 OSS 或 S3 兼容）：插件 Logo、截图、index.json 快照

### 9.3 支付

- `yansongda/pay`（Laravel Pay）：统一封装微信支付 v3 + 支付宝，社区活跃，文档完善
- 先接微信支付（扫码 + H5），再接支付宝
- 退款走支付渠道原路退回

### 9.4 付费插件分发（集成于 `www.xitongapp.com`）

与商店主站同域，使用 [Satis](https://github.com/composer/satis) 搭建 Composer 兼容私有仓库。

**MVP 阶段：直接 zip 下载**

- License 校验通过后，API 返回短期有效的下载 Token（5 分钟）
- FilamentAdmin 后端使用 Token 下载 zip 包，解压到 `vendor/`，执行 `composer dump-autoload`
- 适用于官方自维护插件（依赖关系简单可控）

**V2 阶段：标准 Composer 私有仓库**

开放第三方插件后，`www.xitongapp.com` 提供完整 Satis 兼容接口：

```
www.xitongapp.com/packages.json          # 包元数据
www.xitongapp.com/dist/{vendor}/{pkg}.zip  # 实际下载，需 token 鉴权
```

用户 `composer.json` 只需加：

```json
"repositories": [
    {"type": "composer", "url": "https://www.xitongapp.com"}
]
```

- 每个用户一个独立 token（便于吊销）
- 与商店主站同域，通过路由区分 API 和下载流量

### 9.5 搜索

- 第一版：MySQL 全文索引（FULLTEXT）
- 第二版：接入 [Meilisearch](https://www.meilisearch.com/) + Laravel Scout（插件量大后替换）

### 9.6 CI 服务

- MVP 阶段由管理员人工审查后手动上架，不做自动 CI
- 第二版再接入 GitHub Actions 自动检测（Pest / PHPStan / composer audit / 危险函数扫描）
- CI 结果通过 Webhook 回调 www.xitongapp.com 更新审核状态（V2 实现）

---

## 十、数据模型要点

### 10.1 核心表清单

| 表名 | 用途 |
|------|------|
| `users` | 平台用户（注册用户 + 开发者） |
| `plugins` | 插件主记录（名称、状态、开发者、价格） |
| `plugin_versions` | 插件版本明细（版本号、兼容范围、CI 状态） |
| `plugin_screenshots` | 插件截图 |
| `orders` | 购买订单 |
| `licenses` | License Key（与订单、用户、插件关联；不存储域名绑定） |
| `developer_profiles` | 开发者扩展信息（收款信息、认证状态） |
| `settlements` | 结算记录（周期、金额、状态） |
| `plugin_reviews` | 用户评价 |
| `index_snapshots` | index.json 快照记录 |
| `ci_results` | CI 检测结果记录 |

### 10.2 License 状态流转

```
created → active → suspended / expired
                 ↑
            renewed（续费后从 expired 恢复）
```

### 10.3 插件状态流转

```
draft（草稿）
  → submitted（已提交，等待 CI）
  → ci_passed（CI 通过，等待人工审核）
  → ci_failed（CI 失败，退回开发者）
  → under_review（人工审核中）
  → published（已上架）
  → rejected（驳回，退回开发者修改）
  → unpublished（已下架，已购用户可继续使用）
```

---

## 十一、第一版范围与优先级

### 第一版（MVP）必须完成

- 插件列表和详情浏览（公开，访客可用）
- 邮箱注册 + GitHub OAuth 登录
- 免费插件浏览和 index.json 访问（无需 License）
- 管理员手动上架插件（无需开发者自助提交）
- index.json 生成和发布（手动触发）
- License 校验 API（付费插件验证）

### 第一版暂不做

- 开发者自助入驻和插件提交（MVP 阶段由管理员代劳）
- 自动化 CI 检测（MVP 阶段人工检查后手动上架）
- 在线支付（MVP 阶段先人工沟通 License，测试完整链路后再接支付）
- 用户评价和评分
- 收益统计和提现

### 第二版再做

- 开发者自助入驻 + 插件提交 + 自动 CI
- 支付宝 / 微信支付接入
- 收益统计和月结提现
- 用户评价
- 搜索优化（Meilisearch）

---

## 十二、演示站建设方案

### 12.1 方案选择

**推荐：基于主仓库 `filament-admin` 部署**

理由：
- 演示站代码与主仓库一致，通过环境变量区分
- 演示专用代码（`DemoResetCommand`、`DemoGuard` 中间件）通过条件加载
- 简化部署流程，无需维护独立仓库
- 演示站 Gitee 地址：`https://gitee.com/johncaptain/filament-admin`

### 12.2 演示站配置

```
filament-admin/（主仓库）
├── .env.demo                        # 演示环境配置（通过环境变量加载）
├── demo/
│   ├── DemoServiceProvider.php      # 注册演示限制（仅 demo 环境加载）
│   ├── Middleware/
│   │   └── BlockDestructiveDemo.php # 拦截高危操作
│   └── Console/
│       └── DemoResetCommand.php     # 定时重置数据
── database/
    └── seeders/
        └── DemoSeeder.php           # 演示数据 Seeder
```

### 12.3 演示账号体系

| 角色 | 账号 | 密码 | 权限 |
|------|------|------|------|
| 超级管理员 | `superadmin@demo.xitongapp.com` | `Demo@2025` | 只读，无法删除核心数据 |
| 普通管理员 | `admin@demo.xitongapp.com` | `Demo@2025` | 标准管理员权限 |
| 编辑员 | `editor@demo.xitongapp.com` | `Demo@2025` | 内容管理权限 |

### 12.4 高危操作屏蔽

`BlockDestructiveDemo` Middleware 拦截以下操作：

- 删除管理员账号（演示账号本身）
- 清空日志表
- 修改超管密码
- 删除所有菜单
- 卸载核心插件

拦截响应：HTTP 403 + 提示消息「演示站限制：该操作在演示环境不可用」

### 12.5 数据重置

`php artisan demo:reset` 命令：

1. 清空非演示账号的管理员
2. 重置演示账号密码
3. 清空操作日志和登录日志
4. 重新执行 DemoSeeder（重置菜单、角色、权限）
5. 清理 Laravel 缓存

Schedule：每天凌晨 3:00 自动执行。

---

## 十三、Packagist 注册流程

### 13.1 注册步骤

1. 在 [packagist.org](https://packagist.org) 注册账号（建议使用 GitHub OAuth）
2. 确认 `composer.json` 的 `name` 字段为 `laravelstack/filament-admin`
3. 在 Packagist 提交包，填写 GitHub/Gitee 仓库 URL
4. 在 GitHub 仓库 Settings → Webhooks 添加 Packagist Webhook（或通过 GitHub Integration 自动配置）
5. 验证包在 Packagist 页面正常展示
6. 发布首个 Tag（推荐 `v1.0.0-beta.1`）
7. 更新 README 中的徽章 URL（替换占位符）

### 13.2 composer.json 发布前复核清单

- [ ] `name`：`laravelstack/filament-admin`
- [ ] `description`：英文简介（Packagist 展示用）
- [ ] `keywords`：`["laravel", "filament", "admin", "backend"]`
- [ ] `homepage`：`https://xitongapp.com`
- [ ] `license`：`MIT`
- [ ] `authors`：填写真实作者信息
- [ ] `support.issues`：GitHub Issues URL
- [ ] `support.source`：GitHub 仓库 URL
- [ ] `minimum-stability`：`stable`（正式发布前可用 `beta`）
- [ ] `prefer-stable`：`true`

### 13.3 版本号约定

- 第一版公开 Beta：`v1.0.0-beta.1`
- 正式版：`v1.0.0`
- 后续遵循 SemVer：`MAJOR.MINOR.PATCH`
- MAJOR 版本变更时必须更新 `docs/guide/upgrading.md`

---

## 十四、已确认决策

所有设计决策已由项目负责人确认（2026-06-01），规格文档以下内容为最终口径。

| # | 问题 | 决策 | 影响 |
|---|------|------|------|
| 1 | 插件商店仓库命名 | `filament-admin/store` | 与主项目同组织，管理统一 |
| 2 | 演示站仓库 | **基于主仓库 `filament-admin` 部署** | 通过环境变量区分，无需独立仓库；Gitee 地址：`https://gitee.com/johncaptain/filament-admin` |
| 3 | 付费插件分成比例 | **暂不收取**（待平台规模达到一定量级后再定） | 早期完全让利开发者，优先积累生态；收取时机和比例后续单独决策 |
| 4 | License Key 域名绑定 | **不绑定域名**，Key 有效即可用 | 简化 License 校验逻辑，移除 `license_domains` 表，Request 无需传 domain 字段 |
| 5 | 付费插件分发方案 | **集成于 `www.xitongapp.com`**，使用 Satis 搭建私有 Composer 仓库；MVP 直接 zip 下载 + 短期 Token，V2 升级为完整 Satis 接口 | 与商店主站同域，通过路由区分；简化部署架构；量大后可考虑独立子域 |
| 6 | 演示站重置频率 | **每天凌晨 3:00**，能跑起来即可 | 简单可靠，后期按需调整 |
| 7 | 代码仓库主副 | **GitHub 为主，Gitee 自动镜像**（Actions 推送） | Issue / PR 统一在 GitHub；Gitee 仅用于国内访问加速 |

---

*规格已锁定。下一步：调用 writing-plans 技能，拆解 `filament-admin/store` 的实施计划。*
