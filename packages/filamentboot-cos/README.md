# filamentboot-cos — 腾讯云 COS 存储插件

腾讯云 COS 存储插件：为 filamentboot/filamentboot 提供 COS 凭证后台配置与 Flysystem 磁盘注入。

## 简介

本包为 Filamentboot 后台增加腾讯云对象存储（COS）支持。超级管理员可在后台「存储设置 → COS」页面直接配置 SecretId、SecretKey（加密存储）、AppId、Bucket、Region，无需手动修改 `.env` 文件。凭证通过 `spatie/laravel-settings` 写入数据库并加密保存，`overtrue/laravel-filesystem-cos` 负责注册 Flysystem `cos` 磁盘驱动，其余编辑器插件可直接将 `cos` 作为上传磁盘使用。

## 要求

- PHP `^8.3`、Laravel `^13`、Filament `^5`
- 依赖主包 `filamentboot/filamentboot ^0.5`
- `overtrue/laravel-filesystem-cos ^4.0`（COS Flysystem 驱动）
- `spatie/laravel-settings ^3.9`（凭证加密持久化）
- `filament/spatie-laravel-settings-plugin ^5.6`（设置页表单集成）

## 安装

```bash
composer require filamentboot/filamentboot-cos
```

发布配置文件（可选，用于自定义）：

```bash
php artisan vendor:publish --tag=filamentboot-cos-config
```

## 使用

在 `app/Providers/Filament/AdminPanelProvider.php` 中注册插件：

```php
use Filamentboot\FilamentbootCos\CosPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            CosPlugin::make(),
        ]);
}
```

注册后，后台导航中将出现「存储设置 / COS」（`CosSettingsPage`）配置页面。在该页面填写以下字段即可激活 `cos` 磁盘：

| 字段 | Settings 属性 | 说明 |
|------|--------------|------|
| SecretId | `CosSettings::$secret_id` | 腾讯云访问密钥 ID |
| SecretKey | `CosSettings::$secret_key` | 访问密钥（加密存储） |
| AppId | `CosSettings::$app_id` | 腾讯云账户 AppId |
| Bucket | `CosSettings::$bucket` | 存储桶名称（不含 AppId 后缀） |
| Region | `CosSettings::$region` | 地域，默认 `ap-guangzhou` |

配置完成后，其他插件（如 `filamentboot-rich-editor`）可使用 `->disk('cos')` 将上传直传到 COS。

## 许可

MIT License，详见 [LICENSE](LICENSE)。
