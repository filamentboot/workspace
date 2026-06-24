# filamentboot-oss — 阿里云 OSS 存储插件

阿里云 OSS 存储插件，为 filamentboot/filamentboot 提供 Flysystem OSS 磁盘驱动与后台凭证配置页。

## 简介

本包为 Filamentboot 后台增加阿里云对象存储（OSS）支持。超级管理员可在后台「存储设置 → OSS」页面直接填写 AccessKeyId、AccessKeySecret（加密存储）、Bucket、Endpoint、Region，无需手动修改 `.env` 文件。凭证通过 `spatie/laravel-settings` 加密写入数据库，`iidestiny/flysystem-oss` 负责注册 Flysystem `oss` 磁盘驱动，其余编辑器插件可将 `oss` 作为上传磁盘直接使用。

## 要求

- PHP `^8.3`、Laravel `^13`、Filament `^5`
- 依赖主包 `filamentboot/filamentboot ^0.5`
- `iidestiny/flysystem-oss ^4.8`（OSS Flysystem 驱动）
- `spatie/laravel-settings ^3.9`（凭证加密持久化）
- `filament/spatie-laravel-settings-plugin ^5.6`（设置页表单集成）

## 安装

```bash
composer require filamentboot/filamentboot-oss
```

发布配置文件（可选，用于自定义）：

```bash
php artisan vendor:publish --tag=filamentboot-oss-config
```

## 使用

在 `app/Providers/Filament/AdminPanelProvider.php` 中注册插件：

```php
use Filamentboot\FilamentbootOss\OssPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            OssPlugin::make(),
        ]);
}
```

注册后，后台导航中将出现「存储设置 / OSS」（`OssSettingsPage`）配置页面。在该页面填写以下字段即可激活 `oss` 磁盘：

| 字段 | Settings 属性 | 说明 |
|------|--------------|------|
| AccessKeyId | `OssSettings::$access_key_id` | 阿里云 RAM 账号 Key |
| AccessKeySecret | `OssSettings::$access_key_secret` | 访问密钥（加密存储） |
| Bucket | `OssSettings::$bucket` | OSS Bucket 名称 |
| Endpoint | `OssSettings::$endpoint` | 例如 `oss-cn-hangzhou.aliyuncs.com` |
| Region | `OssSettings::$region` | 例如 `cn-hangzhou` |

配置完成后，其他插件（如 `filamentboot-rich-editor`）可使用 `->disk('oss')` 将上传直传到 OSS。

## 许可

MIT License，详见 [LICENSE](LICENSE)。
