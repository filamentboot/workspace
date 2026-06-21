# 插件安装与管理指南（plugin-usage.md）

> 面向后台用户。本文说明如何通过 filament-admin 后台插件市场安装插件（一键安装），
> 以及手动安装、启用/禁用、配置、卸载等全流程操作，并提供常见问题排查步骤。

---

## 目录

1. [后台一键安装](#1-后台一键安装)
2. [手动安装](#2-手动安装)
3. [启用后如何生效](#3-启用后如何生效)
4. [插件配置入口](#4-插件配置入口)
5. [禁用与卸载](#5-禁用与卸载)
6. [FAQ 常见问题](#6-faq-常见问题)

---

## 1. 后台一键安装

### 1.1 进入插件市场

登录后台后，点击导航"插件市场"（路径 `/admin/marketplace`），
进入包含三个 Tab 的市场目录：

| Tab | 内容 |
|-----|------|
| **官方市场** | 由 filamentboot/filamentboot 团队精选收录的一方及社区优质插件 |
| **社区插件** | 来自 Packagist（`tags=filament`）的实时搜索结果，含下载量、Stars 等信任信号 |
| **已安装** | 当前环境已通过 Composer 安装、并由 `plugin:scan` 识别的插件 |

### 1.2 环境自检与降级

点击安装前，系统自动执行环境自检（三项）：

| 检查项 | 正常状态 | 失败表现 |
|--------|---------|---------|
| `proc_open` 可用 | PHP 进程可用 | 主机商禁用了 proc_open |
| Composer 路径 | `COMPOSER_PATH` 环境变量 → `which composer` → `/usr/local/bin/composer` 依次探测 | 找不到 Composer 可执行文件 |
| vendor/ 可写 | Web 服务用户对 `vendor/` 目录有写权限 | 权限不足 |

**自检通过：** 卡片显示"安装插件"按钮。

**自检不通过（降级模式）：** 卡片显示黄色提示横幅"后台安装不可用"，
并展示可复制的手动安装命令，供在终端执行。此时请参考
[手动安装](#2-手动安装) 流程完成安装。

> **生产环境注意：** 后台一键安装依赖非 `sync` 的队列驱动（如 `redis` 或 `database`）。
> 若 `QUEUE_CONNECTION=sync`，安装任务在 HTTP 请求中同步执行，可能导致请求超时。
> 生产环境请确保配置 `QUEUE_CONNECTION=redis`（或 `database`）并运行队列监听器：
>
> ```bash
> php artisan queue:listen
> ```

### 1.3 官方市场安装流程

1. 在"官方市场"Tab 找到目标插件，确认兼容性标签为绿色"兼容 Filament 5"。
2. 点击"安装插件"按钮。
3. 系统在后台队列中执行 `composer require vendor/package`，
   卡片下方出现实时日志滚动块（每 2 秒刷新一次）。
4. 安装完成后，`post_install` 钩子自动执行（按插件声明顺序）：
   - 发布配置文件（`vendor:publish --tag=TAG --force`）
   - 执行数据库迁移（`migrate --force`）
   - 运行初始种子（`db:seed --class=X`）
   - 刷新 autoload（`composer dump-autoload`）
5. 状态变为"已安装（done）"，"启用"按钮出现。
6. 点击"启用"，插件生效（下次请求后台即可看到插件新增的菜单/功能）。

### 1.4 社区插件安装（含第三方风险确认）

社区插件来自 Packagist，**未经官方审核**，安装前系统弹出二次确认弹窗：

> **安装第三方插件**
>
> 此插件来自社区，未经官方审核。安装前请自行评估安全风险。
> 继续安装即表示您接受相关风险。

点击"我已了解，继续安装"后，流程与官方市场一致。

社区插件未声明 `extra.filament-admin.post_install` 块时，系统执行通用兜底：
`migrate --force` + `composer dump-autoload`（跳过 `vendor:publish`）。

---

## 2. 手动安装

当后台一键安装不可用，或您更倾向命令行操作时，按以下步骤手动安装：

### 2.1 Composer 安装包

```bash
composer require vendor/package-name
```

### 2.2 发布资源

```bash
# 按插件文档说明执行，例如：
php artisan vendor:publish --tag=plugin-config --force

# 若需要数据库迁移：
php artisan migrate

# 若需要初始种子：
php artisan db:seed --class="Vendor\\Package\\Database\\Seeders\\PluginSeeder"
```

### 2.3 刷新 autoload

```bash
composer dump-autoload
php artisan optimize:clear
```

### 2.4 在后台启用插件

执行 `plugin:scan` 命令让后台识别新安装的插件：

```bash
php artisan filament-admin:scan-plugins
```

然后登录后台，进入"插件市场 → 已安装" Tab，找到刚安装的插件，点击"启用"。

---

## 3. 启用后如何生效

filament-admin 的插件启停机制基于数据库状态，通过 `AdminPanelProvider` 在每次请求时
动态注册插件（Panel 层启停，D-06-02）：

```php
// app/Providers/Filament/AdminPanelProvider.php（自动生成，无需手动修改）
Plugin::where('is_enabled', true)->get()->each(function ($plugin) use ($panel) {
    $pluginClass = $plugin->plugin_class;
    if ($pluginClass && class_exists($pluginClass)) {
        $panel->plugin($pluginClass::make());
    }
});
```

**启用生效时机：** 点击"启用"后，数据库中 `is_enabled` 字段立即置为 `true`，
**下一次访问后台页面时**（新的 HTTP 请求），插件注册生效，新增的菜单、页面、功能即可访问。

**禁用生效时机：** 点击"禁用"后，`is_enabled` 立即置为 `false`，
下一次访问后台时，插件从 Panel 中卸载，相关菜单消失。

> **注意：** 如果后台页面仍显示已禁用插件的内容，请清除 Filament 导航缓存：
>
> ```bash
> php artisan optimize:clear
> ```

---

## 4. 插件配置入口

声明了 `settings_page_slug` 的插件，在"已安装"Tab 的插件卡片中会显示"设置"按钮，
点击直接跳转到插件配置页面。

如果未显示"设置"按钮，可以通过后台导航手动访问：

- 地址格式：`/admin/{settings_page_slug}`，例如阿里云 OSS 插件的配置页为 `/admin/settings/oss`。

常用一方插件配置页地址：

| 插件 | 配置页路径 |
|------|-----------|
| 阿里云 OSS | `/admin/settings/oss` |
| 腾讯云 COS | `/admin/settings/cos` |

---

## 5. 禁用与卸载

### 5.1 禁用插件

禁用仅停止插件在 Panel 中的注册，**不会**删除 Composer 包和插件数据：

1. 进入"插件市场 → 已安装"Tab，或进入"插件管理"Resource 列表。
2. 找到目标插件，点击"禁用"。
3. 确认后，`is_enabled` 立即变为 `false`，下次请求时插件功能消失。

禁用后可随时重新点击"启用"恢复，无需重新安装。

### 5.2 卸载插件

卸载操作**不可逆**，将移除 Composer 包并清除插件状态记录。

**卸载前系统自动执行禁用**（防止下次请求时因 class not found 报错）。

卸载流程：

1. 点击"卸载"按钮，弹出确认框：

   > **卸载插件**
   >
   > 此操作不可逆。卸载将移除 Composer 包并清除插件状态记录。

2. 可选：勾选"**同时删除插件自建数据表**"（默认**不勾选**）。
   勾选后，系统列出受影响的数据表名，确认前请仔细核对。
   **警告：勾选此选项将永久删除插件存储的所有数据，无法恢复。**

3. 点击"确认卸载"，系统在后台队列中执行：
   - `composer remove vendor/package`
   - 清除 `plugins` 表中该插件的记录
   - `php artisan optimize:clear`

4. 卸载完成后，插件从"已安装"列表中消失。

> **数据保留策略：** 默认不勾选"删除数据表"选项，目的是保护因误操作导致的数据丢失。
> 卸载后重新安装插件，原有数据仍可恢复使用。

---

## 6. FAQ 常见问题

### Q1：安装时提示"依赖冲突"，怎么处理？

**现象：** 安装日志中出现类似：

```
Your requirements could not be resolved to an installable set of packages.
Problem 1 - vendor/plugin requires filament/filament ^4.0 but filament/filament[5.0.x-dev, ..., v5.x.x] is installed.
```

**原因：** 目标插件要求的 Filament 版本与当前安装的版本不兼容。

**解决步骤：**

1. 查看插件的兼容性标签。若标签显示"版本不兼容"（红色），该插件不支持当前 Filament 5 版本。
2. 在插件市场搜索同类功能的兼容替代插件。
3. 若确认需要安装，可手动解决依赖：

   ```bash
   # 查看冲突详情
   composer why-not filament/filament ^5.0
   
   # 尝试宽松约束安装（有风险，仅在充分测试后使用）
   composer require vendor/plugin --with-all-dependencies
   ```

---

### Q2：有权限但安装按钮不显示，怎么处理？

**现象：** 以超级管理员账号登录，但插件卡片上没有"安装插件"按钮，而是显示黄色提示横幅。

**原因：** 环境自检未通过（proc_open 不可用 / Composer 未找到 / vendor/ 无写权限）。

**排查步骤：**

1. 黄色横幅文字会说明具体失败项，按提示处理：
   - **proc_open 不可用：** 联系主机商开启 `proc_open` 函数，或使用手动安装方式。
   - **Composer 未找到：** 确认服务器已安装 Composer，或设置 `COMPOSER_PATH` 环境变量：

     ```bash
     # .env 文件中添加
     COMPOSER_PATH=/usr/local/bin/composer
     ```

   - **vendor/ 无写权限：** 修正目录权限，确保 Web 服务用户（如 `www-data`）对 `vendor/` 有写权限：

     ```bash
     chmod -R 755 vendor/
     chown -R www-data:www-data vendor/
     ```

2. 修正后刷新页面，重新触发自检。

---

### Q3：安装完成但插件不显示在后台，怎么处理？

**现象：** 安装状态显示"已安装（done）"，点击"启用"后，后台没有出现插件的新菜单。

**排查步骤：**

1. **确认已刷新页面：** 插件生效需要新的 HTTP 请求，按 F5 刷新后台页面。

2. **清除应用缓存：**

   ```bash
   php artisan optimize:clear
   ```

3. **确认队列已处理完成：** 若 `init_status` 仍为 `running`，说明队列任务尚未执行。
   检查队列监听器是否运行：

   ```bash
   php artisan queue:listen
   ```

4. **检查 plugin_class 是否正确：** 进入"插件管理" Resource 列表，查看该插件的 `plugin_class` 字段是否为有效的类名。
   若为空，手动执行扫描：

   ```bash
   php artisan filament-admin:scan-plugins
   ```

5. **查看 init_log：** 在插件管理列表中查看 `init_log` 字段，搜索错误信息：

   ```bash
   php artisan tinker
   # >>> FilamentAdmin\Models\Plugin::where('slug', 'your-plugin-slug')->first()->init_log;
   ```

6. **检查 AdminPanelProvider 注册：** 打开 `app/Providers/Filament/AdminPanelProvider.php`，
   确认存在动态插件注册逻辑（由 filament-admin 自动注入，无需手动添加）。

---

### Q4：卸载后再次安装失败，怎么处理？

**现象：** 卸载后重新安装，日志显示 Composer 错误或安装步骤失败。

**排查步骤：**

1. 查看安装日志（init_log 字段或后台日志块）中的具体错误信息。
2. 手动清除残留状态：

   ```bash
   php artisan optimize:clear
   composer dump-autoload
   ```

3. 确认 `plugins` 表中旧记录已清除：

   ```bash
   php artisan tinker
   # >>> FilamentAdmin\Models\Plugin::where('slug', 'your-plugin-slug')->withTrashed()->get();
   ```

4. 若残留软删除记录，手动强制删除后重试安装。

---

### Q5：如何确认生产环境队列驱动配置正确？

**现象：** 本地开发可安装（`QUEUE_CONNECTION=sync`），生产环境安装卡住或超时。

**验证：**

```bash
# 确认生产队列驱动
php artisan tinker
# >>> config('queue.default');
# 应返回 'redis' 或 'database'，而不是 'sync'

# 确认队列监听器运行
php artisan queue:listen
# 或使用 Supervisor 管理长驻进程
```

**注意：** `sync` 驱动在 HTTP 请求中同步执行 Composer（可能耗时 1-5 分钟），
会导致请求超时（502 Bad Gateway）。生产环境务必使用异步队列驱动。

---

## 相关文档

- [插件开发指南](plugin-development.md) — 如何开发 filament-admin 兼容插件
- [安装指南](installation.md) — filament-admin 主包安装
- [贡献指南](https://github.com/filamentboot/filamentboot/blob/main/CONTRIBUTING.md)
- [问题反馈](https://github.com/filamentboot/filamentboot/issues)
