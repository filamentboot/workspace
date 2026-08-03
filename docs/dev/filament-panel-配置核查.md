# Filament Panel 官方配置核查

> 更新时间：2026-08-03
> 核查范围：`vendor/filament/filament/src/Panel/Concerns/` 全部 34 个 Concern 的默认值，逐项对照 `app/Providers/Filament/AdminPanelProvider.php` 与 `packages/filamentboot/stubs/AdminPanelProvider.stub`。
> 结论口径：只列**官方原生能力**，不引入任何社区主题包 / 第三方 UI 包。
> 状态：**A 级 3 项、B 级 7 行已全部处理完毕**（第二、三节记录实施结果，第四节为已决策不做项）。

---

## 一、已配置项

### 1.1 后台观感国产化（提交 `5cce837`）

| 配置 | 取值 | 位置 |
|------|------|------|
| `brandName()` | `Filamentboot` | 演示项目 + stub |
| `colors()` | primary `#1677ff` / success `#52c41a` / warning `#faad14` / danger `#ff4d4f` | 演示项目 + stub |
| `font()` | `system-ui` + `LocalFontProvider`（不输出 `<link>`，断开 `fonts.bunny.net`） | 演示项目 + stub |
| `defaultThemeMode()` | `ThemeMode::Light`（保留明暗切换） | 演示项目 + stub |
| `maxContentWidth()` | `Width::Full` | 演示项目 + stub |
| `sidebarCollapsibleOnDesktop()` | 开启（折叠为 4.5rem 图标条） | 演示项目 + stub |
| `viteTheme()` | 仅演示项目（stub 故意不加，下游未配 vite 入口会白屏） | `app/Providers/Filament/AdminPanelProvider.php` |

视觉覆盖层（圆角 / 表格密度 / 边框 / 表头灰底）由主包 `packages/filamentboot/resources/dist/filamentboot-theme.css` 经 `FilamentAsset::register()` 注入，下游零构建步骤，开关为 `FILAMENTBOOT_THEME`。

### 1.2 本次新增

| 配置 | 取值 | 位置 |
|------|------|------|
| `favicon()` | `asset('favicon.svg')` | 演示项目 + stub |
| `brandLogo()` / `darkModeBrandLogo()` / `brandLogoHeight()` | `brand-logo.svg` / `brand-logo-dark.svg` / `1.75rem` | 演示项目 + stub |
| `spa()` | 开启 | 演示项目 + stub |
| `unsavedChangesAlerts()` | 开启 | 演示项目 + stub |
| `globalSearchKeyBindings()` | `['command+k', 'ctrl+k']` | 演示项目 + stub |
| `globalSearchFieldKeyBindingSuffix()` | 开启（搜索框显示 ⌘K / Ctrl+K 提示） | 演示项目 + stub |
| `databaseTransactions()` | 开启，5 个插件市场 Action 单独退出 | 演示项目 + stub |
| `defaultAvatarProvider()` | `Filamentboot\AvatarProviders\InitialsAvatarProvider` | 主包 `FilamentbootPlugin::register()` |

**默认值已符合预期、无需改动**：`errorNotifications`（默认 `true`）、`breadcrumbs`（`true`）、`topbar`（`true`）、`darkMode`（`true`）、`collapsibleNavigationGroups`（`true`，详见 3.7）。

---

## 二、A 级：真实缺口（已修复）

### A-1 默认头像请求外网 `ui-avatars.com` ✅ 已修复

**问题**

- `HasAvatars.php:10` 默认 `defaultAvatarProvider = UiAvatarsProvider::class`，该 provider 返回硬编码的 `https://ui-avatars.com/api/?...`
- `FilamentManager.php:575` `getUserAvatarUrl()` 仅当用户 `instanceof HasAvatar` 时走自定义，否则一律落到 provider
- `AdminUser` 当时只实现 `FilamentUser, HasMedia, HasName`，**未实现 `HasAvatar`**

侧边栏用户区、用户菜单、任何头像列每次渲染都发起外网请求，国内网络下长时间转圈直至超时。附带问题：`AdminUser` 已通过 `InteractsWithMedia` 挂了 `avatar` 媒体集合，但因为没实现 `HasAvatar`，**用户上传的头像根本不会被 Filament 使用**。

**修复**

1. 新增 `packages/filamentboot/src/AvatarProviders/InitialsAvatarProvider.php` —— 自绘首字 SVG，以 `data:image/svg+xml;base64,` 形式返回。底色取 `FilamentColor::getColor('primary')[600]`，跟随面板主色。
   选 data URI 的原因：`FilamentManager.php:584` 对 `data:image/` 开头的返回值直通不加工（其余会被 `url()` 包一层），这是唯一零文件、零请求、零 publish 步骤的兜底形态。
2. `AdminUser` 实现 `HasAvatar::getFilamentAvatarUrl()`，优先返回 `getFirstMediaUrl('avatar')`，回退 `avatar` 列，都为空时返回 `null` 交给 provider。
   注意 `getFirstMediaUrl()` 无媒体时返回**空字符串**而非 null，需归一化。
3. 在 `FilamentbootPlugin::register()` 里 `$panel->defaultAvatarProvider(...)` —— 下游 `composer require` 后零配置生效；用户在 `->plugin()` 之后再调一次即可覆盖。

**顺带记录**：`admin_users.avatar` 列此前是死列 —— `SpatieMediaLibraryFileUpload` 带 `dehydrated(false)`，永远写不进该列，全仓库也无读写方。现在 `getFilamentAvatarUrl()` 的二级回退让这一列对下游有了意义。

回归测试：`tests/Feature/Media/AdminUserAvatarUrlTest.php`（3 个用例）。

### A-2 生产部署缺 `filament:optimize` ✅ 已修复（结论较初稿收窄）

**初稿口径需修正**：`vendor/filament/support/src/SupportServiceProvider.php:442` 里 Filament 调了 `$this->optimizes(optimize: 'filament:optimize', clear: 'filament:optimize-clear', key: 'filament')`，已挂进 Laravel 的 optimize 钩子。因此：

- `php artisan optimize` **已包含** `filament:optimize`
- `php artisan optimize:clear` **已包含** `filament:optimize-clear`
- `scripts/migrate-server.sh:273` 的 `php artisan optimize` —— 本就已覆盖，无需改动
- `PluginManager.php:304` 的 `Artisan::call('optimize:clear')` —— 本就已清理 Filament 组件缓存，插件启停后 Resource 列表能正确刷新

**真正的缺口**只在用分列 cache 命令、不走 `optimize` 的两个脚本：

| 文件 | 改动 |
|---|---|
| `deploy.sh` | `view:cache` 后加 `php artisan filament:optimize`；另加幂等 `php artisan storage:link`（媒体库走 public 磁盘，缺软链头像 404） |
| `rollback.sh` | `view:cache` 后加 `php artisan filament:optimize` |

**明确不放**：`composer.json` 的 `post-autoload-dump`（组件缓存后开发时改 Resource 不生效）、`.github/workflows/ci.yml`（纯质量门，不部署）、`release.yml`（纯 git subtree split，不构建）、`.workflow/*.yml`（只做 SSH 调用 / `php --version`）。

文档同步：`wiki/guide/deployment.md`、`wiki/guide/auto-deployment.md`、`wiki/guide/webhook-deployment-tutorial.md`（3 处脚本样板）、`docs/dev/phase-1-checklist.md`、`UPGRADING.md`（加 `filament:optimize-clear`）、`packages/filamentboot/README.md` 与 `wiki/installation.md`（新增「生产环境优化」小节，下游此前完全看不到这条建议）。

各文档均补了一句：**启用 / 停用插件后需 `php artisan filament:optimize-clear`**，否则组件缓存会固化住 Resource 清单。

### A-3 `public/favicon.ico` 是 0 字节空文件 ✅ 已修复

仓库此前**没有任何品牌资源**（`packages/filamentboot/art/` 下两张只是 README 截图），用现有品牌色 `#1677ff` 从零生成了一套占位标识：

| 文件 | 内容 |
|---|---|
| `public/favicon.svg` | 圆角方块 `#1677ff` + 白色「F」，矢量主用 |
| `public/favicon.ico` | 32×32，覆盖原 0 字节文件；PNG 载荷的 ICO 容器，供浏览器自动请求 `/favicon.ico` 时回落 |
| `public/brand-logo.svg` / `public/brand-logo-dark.svg` | 横向锁定：F 标 + 「Filamentboot」字标（深色 / 浅色两版） |
| `packages/filamentboot/resources/dist/` 下三份 SVG | 同一套，随包发布给下游 |

`brandLogo()` 一旦设值会**替代** brandName 文字（`components/logo.blade.php` 的 `@elseif (filled($logo))` 分支渲染 `<img>`），所以 logo 做成"图标 + 字标"横向锁定，不能只做方形标。

下游链路：新增第 7 个 publish tag `filamentboot-brand`（→ `public_path()`），`filamentboot:install` 增加一步**幂等复制**（目标文件已存在时跳过，不覆盖下游自有品牌）。下游若跳过发布，`asset('favicon.svg')` 只是 404，浏览器回落默认图标，不会白屏。

替换品牌：直接换掉 `public/` 下这三个文件即可。

回归测试：`ServiceProviderPublishesTest`（新增 theme / brand 两个 tag 用例）、`InstallCommandTest`（新增复制与不覆盖两个用例）。

---

## 三、B 级：官方可选项（已逐行处理）

### 3.1 `spa()` ✅ 已开启

面板内导航走 `wire:navigate`，无整页刷新。风险已逐条复核，**不需要** `spaUrlExceptions()`：

- **外链不会被劫持** —— `FilamentView::hasSpaMode($url)` 最后一步调 `is_app_url()`（`vendor/filament/support/src/helpers.php:120-133`），跨 host 的 URL 直接返回 false。所以 `AdminNavigationBuilder.php:110` 那些来自 DB 菜单表的任意 URL 是安全的
- **裸 `<a>` 不受影响** —— `wire:navigate` 是逐链接 opt-in，只有经 `generate_href_html()` 生成的 Filament 链接才带。impersonate 退出链接与 marketplace 的两处 `target="_blank"` 外链都是整页跳转，行为不变
- **下载安全** —— 唯一下载入口 `MediaResource.php:81-82` 已 `openUrlInNewTab()`；导出走官方 `ExportAction` 的队列下载链接

仍需手测：`EnsureTwoFactorEnabled.php:68` 的 302 在 `wire:navigate` 下的跳转、impersonate 进出、动态注册插件 Page 的资产加载。

### 3.2 `unsavedChangesAlerts()` ✅ 已开启

影响面：所有含表单的 Create / Edit 页 + 4 个 Settings Page + Profile 页。

### 3.3 `globalSearchKeyBindings(['command+k', 'ctrl+k'])` ✅ 已开启

`AdminUserResource`（`account`）/ `DepartmentResource`（`name`）/ `MenuResource`（`title`）/ `LoginLogResource`（`username`）/ Shield `RoleResource`（`name`）均已设 `$recordTitleAttribute`，全局搜索本身早就可用，此前仅缺快捷键。

同时加了 `globalSearchFieldKeyBindingSuffix()` —— 不加这行快捷键可用但用户看不见提示（`HasGlobalSearch.php:66-90`）。

### 3.4 `databaseTransactions()` ✅ 已开启（5 个 Action 退出）

`Panel.php:104-106` 会对面板内**所有** Filament Action 套 `->databaseTransaction()`。以下 5 个必须退出，各加了一行 `->databaseTransaction(false)`：

| Action | 位置 | 原因 |
|---|---|---|
| `install` | `app/Filament/Resources/PluginResource.php` | `PluginManager` dispatch `ComposerInstallJob`，队列 `after_commit => false`，事务内入队会让 worker 读到未提交数据 |
| `uninstall` | 同上 | dispatch `ComposerRemoveJob`，且可能删表（DDL 隐式提交） |
| `toggle` | 同上 | 触发 `Artisan::call('optimize:clear')` 等外部副作用 |
| `scan` | `PluginResource/Pages/ListPlugins.php` | `Artisan::call('plugin:scan')` |
| `initialize` | `PluginResource/Pages/ViewPlugin.php` | 跑 `migrate` / `db:seed` / `vendor:publish`，MySQL 下 DDL 造成隐式提交，静默破坏外层事务边界 |

### 3.5 `brandLogo()` / `darkModeBrandLogo()` / `brandLogoHeight()` ✅ 已开启

见 A-3。

### 3.6 `sidebarFullyCollapsibleOnDesktop()` —— 不加（与现有设置互斥）

**初稿口径需修正**：这一行与 `sidebarCollapsibleOnDesktop()` 并非叠加关系。`components/layout/index.blade.php:77-91` 是 `@if ($isSidebarCollapsibleOnDesktop) ... @elseif ($isSidebarFullyCollapsibleOnDesktop)`，前者优先 —— 两个都写等于后者永不生效。

**决策：维持图标条折叠**（折叠后剩 4.5rem 图标条，图标仍可点，贴近若依 / Element Admin）。要换成"完全隐藏侧栏"必须先删掉 `sidebarCollapsibleOnDesktop()`。

### 3.7 `collapsibleNavigationGroups()` —— 不加（默认已开）

**初稿口径需修正**：`HasSidebar.php:20` 的默认值是 `true` 而非 `false`，导航分组本来就可折叠。加这行是空操作，只会误导后来人。

---

## 四、明确不做（已决策，勿再提）

以下三项在核查中被识别为"官方有原生能力、项目未采用"，但经评估**决定维持现状**。记录在此是为了避免后续重复提案。

### 4.1 站内通知 `databaseNotifications()`

- 官方默认：`HasNotifications.php:12` → `false`
- 现状：未启用，且仓库没有 `notifications` 表迁移
- **决策：不做。** 当前没有站内通知的业务需求，开启需额外维护通知表、轮询与 Livewire 组件，收益不成立。

### 4.2 官方版强制 2FA 拦截 `requiresMultiFactorAuthentication()`

- 官方能力：
  - `vendor/filament/filament/src/Auth/MultiFactor/{App,Email,Pages,Http}/` —— 内置 TOTP 与邮件两种 provider
  - `HasAuth.php:666` `multiFactorAuthentication(...)`
  - `HasAuth.php:195` `requiresMultiFactorAuthentication()`
- 现状：使用 `stephenjude/filament-two-factor-authentication` 插件 + 自写 `Filamentboot\Http\Middleware\EnsureTwoFactorEnabled`（POLISH-02），职责与官方中间件重合
- **决策：不做。** 迁移涉及 2FA secret 存储格式差异，对已启用 2FA 的存量用户构成破坏性变更；现有实现已上线且稳定，去掉一个依赖的收益不足以覆盖迁移与回滚成本。

### 4.3 邮箱变更验证 `emailChangeVerification()`

- 官方默认：`HasAuth.php:118` → `false`
- **决策：不做。** 后台管理员邮箱由超管在受控环境下维护，不走自助变更流程，额外的验证环节不产生实际安全收益。

---

## 五、验证方式

| 项 | 验证方法 |
|----|---------|
| A-1 | 打开后台任意页面，DevTools Network 过滤 `ui-avatars`，应为 0 请求；上传头像后侧栏应显示该头像（需先 `php artisan storage:link`） |
| A-2 | `php artisan filament:optimize` 后检查 `bootstrap/cache/filament/panels` 下产物；`filament:optimize-clear` 可清除 |
| A-3 | 浏览器标签页显示蓝底「F」；`wc -c public/favicon.ico` 非 0；侧栏顶部显示图标 + 字标，切深色模式字标转白 |
| 3.1 spa | 点侧栏各菜单无整页白闪；impersonate 进出、强制 2FA 跳转、插件市场 `wire:poll` 均正常 |
| 3.2 未保存提示 | 编辑表单后不保存直接跳转，应弹出确认 |
| 3.3 快捷键 | 后台任意页面按 `Ctrl/Cmd + K`，全局搜索框获得焦点并显示快捷键后缀 |
| 3.4 事务 | 造一个保存中途失败 → 数据无残留；插件市场安装 / 卸载 / 扫描 / 初始化 4 条路径仍正常 |
| 下游链路 | `scripts/verify-package-install.sh` 干净项目：`composer require` → `filamentboot:install` → `filament:assets`，应看到同款观感 + 蓝底「F」favicon + 本地首字头像，全程不碰 `vite.config.js` |
| 全量回归 | `composer pint:test`、`composer test`、`composer phpstan` |

---

## 相关文档

- [一期开发后的梳理](./一期开发后的梳理.md) —— 附录「后台 UI 调整参考」是本次观感改造的需求来源
- [竞品分析](./竞品分析.md) —— 若依 / FastAdmin 路线参考
