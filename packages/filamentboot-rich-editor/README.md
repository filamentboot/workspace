# filamentboot-rich-editor — 增强版富文本编辑器插件

基于 Filament 5 内置 Tiptap RichEditor 的增强版富文本编辑器插件，含媒体库图片上传、XSS 过滤、磁盘联动。

## 简介

本包在 Filament 5 内置 Tiptap RichEditor 基础上进行扩展，提供 `RichEditorField` 字段组件。图片上传磁盘动态读取后台 `UploadSettings.default_disk`，支持 `oss`、`cos`、`public` 等磁盘，并可通过 `->disk()` 在组件级单独覆盖；保存前的 HTML 内容经 `mews/purifier` 过滤，防止 XSS 注入。

## 要求

- PHP `^8.3`、Laravel `^13`、Filament `^5`
- 依赖主包 `filamentboot/filamentboot ^0.5`
- `mews/purifier ^3.4`（HTML XSS 过滤）

## 安装

```bash
composer require filamentboot/filamentboot-rich-editor
```

发布配置文件（可选）：

```bash
php artisan vendor:publish --tag=filamentboot-rich-editor-config
```

## 使用

### 1. 注册插件

在 `app/Providers/Filament/AdminPanelProvider.php` 中注册：

```php
use Filamentboot\FilamentbootRichEditor\RichEditorPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            RichEditorPlugin::make(),
        ]);
}
```

### 2. 在表单中使用

```php
use Filamentboot\FilamentbootRichEditor\Forms\RichEditorField;

RichEditorField::make('content')
    ->label('内容')
    ->columnSpanFull(),
```

指定组件级上传磁盘（优先于全局 UploadSettings）：

```php
RichEditorField::make('content')
    ->disk('oss'),   // 或 'cos'、'public'
```

### 3. XSS 过滤

在 Resource 的 `mutateFormDataBeforeSave` 中调用净化器：

```php
use Filamentboot\FilamentbootRichEditor\Support\RichEditorPurifier;

protected function mutateFormDataBeforeSave(array $data): array
{
    $data['content'] = app(RichEditorPurifier::class)->clean($data['content']);
    return $data;
}
```

> 禁止直接使用 `{!! $html !!}` 输出未经过滤的富文本内容。

## 许可

MIT License，详见 [LICENSE](LICENSE)。
