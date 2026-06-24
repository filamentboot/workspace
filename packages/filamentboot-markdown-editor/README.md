# filamentboot-markdown-editor — Markdown 编辑器插件

Markdown 编辑器插件，为 filamentboot/filamentboot 提供基于 EasyMDE 的增强 Markdown 编辑器，含图片上传磁盘联动、分屏预览、代码高亮与 XSS 安全渲染。

## 简介

本包在 Filament 5 内置 EasyMDE MarkdownEditor 基础上进行扩展，提供 `MarkdownEditorField` 字段组件。图片上传磁盘动态读取后台 `UploadSettings.default_disk`，支持云存储直传；分屏预览通过 Alpine.js 指令在 EasyMDE 初始化后触发（可通过 `->withSideBySide(false)` 关闭）；Markdown 渲染经 `league/commonmark` 转换后再由 `mews/purifier` 过滤 XSS，保证展示安全。

## 要求

- PHP `^8.3`、Laravel `^13`、Filament `^5`
- 依赖主包 `filamentboot/filamentboot ^0.5`
- `league/commonmark ^2.8`（Markdown 转 HTML）
- `mews/purifier ^3.4`（HTML XSS 过滤）

## 安装

```bash
composer require filamentboot/filamentboot-markdown-editor
```

发布配置文件（可选）：

```bash
php artisan vendor:publish --tag=filamentboot-markdown-editor-config
```

## 使用

### 1. 注册插件

在 `app/Providers/Filament/AdminPanelProvider.php` 中注册：

```php
use Filamentboot\FilamentbootMarkdownEditor\MarkdownEditorPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            MarkdownEditorPlugin::make(),
        ]);
}
```

### 2. 在表单中使用

```php
use Filamentboot\FilamentbootMarkdownEditor\Forms\MarkdownEditorField;

MarkdownEditorField::make('content')
    ->label('内容')
    ->columnSpanFull(),
```

关闭分屏预览：

```php
MarkdownEditorField::make('content')
    ->withSideBySide(false),
```

### 3. 安全渲染

前端展示 Markdown 内容时，使用 `MarkdownRenderer` 进行安全转换：

```php
use Filamentboot\FilamentbootMarkdownEditor\Support\MarkdownRenderer;

$html = app(MarkdownRenderer::class)->render($markdownContent);
// 输出: {!! $html !!}（已经过 XSS 过滤，安全）
```

> 图片上传的磁盘禁止设为 `private`，Filament 5 的 `fileAttachmentsVisibility('private')` 会抛出 `LogicException`。

## 许可

MIT License，详见 [LICENSE](LICENSE)。
