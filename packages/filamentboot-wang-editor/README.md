# filamentboot-wang-editor — wangEditor 富文本编辑器插件

wangEditor 富文本编辑器插件，为 filamentboot/filamentboot 提供轻量级国内生态友好的 wangEditor，可一键替换默认 Tiptap，图片上传经 UploadValidator 三重安全校验后落到当前生效磁盘。

## 简介

本包将 wangEditor 集成为 Filament 5 自定义字段（`WangEditorField`），在国内网络环境下比 Tiptap 加载更快、兼容性更好。启用后可直接用 `WangEditorField::make()` 替换默认的 `RichEditor`，无需改动其余业务代码。图片上传由包内置的 `WangEditorUploadController` 接收，经三重安全校验后落到当前生效磁盘（支持 `oss`、`cos`、`public`），CSRF 由前端 `customUpload` 自动附加 `X-CSRF-TOKEN` header 防护。

## 要求

- PHP `^8.3`、Laravel `^13`、Filament `^5`
- 依赖主包 `filamentboot/filamentboot`（`*`，跟随主包版本）

## 安装

```bash
composer require filamentboot/filamentboot-wang-editor
```

发布配置文件（可选）：

```bash
php artisan vendor:publish --tag=filamentboot-wang-editor-config
```

## 使用

### 1. 注册插件

在 `app/Providers/Filament/AdminPanelProvider.php` 中注册：

```php
use Filamentboot\FilamentbootWangEditor\WangEditorPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            WangEditorPlugin::make(),
        ]);
}
```

### 2. 在表单中使用

```php
use Filamentboot\FilamentbootWangEditor\Forms\Components\WangEditorField;

WangEditorField::make('content')
    ->label('内容')
    ->columnSpanFull(),
```

指定组件级上传磁盘（优先于全局 UploadSettings）：

```php
WangEditorField::make('content')
    ->disk('oss'),   // 或 'cos'、'public'
```

磁盘解析优先级：组件级 `->disk()` > `UploadSettings.default_disk` > `config('filesystems.default')`，`local` 磁盘自动回退为 `public`。

## 许可

MIT License，详见 [LICENSE](LICENSE)。
