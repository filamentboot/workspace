# 媒体库 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 集成 Spatie MediaLibrary，实现文件上传、缩略图自动生成和媒体库管理界面。

**Architecture:** 使用 spatie/laravel-medialibrary 处理文件存储和缩略图转换，文件默认存储在 public 磁盘（本地）。MediaResource 提供只读管理界面，支持按文件类型筛选和图片预览。

**Tech Stack:** spatie/laravel-medialibrary ^11.0, filament/spatie-laravel-media-library-plugin ^5.0, Pest

---

## 文件结构规划

### 新建文件

```
app/
├── Support/
│   └── MediaCollections.php             # 媒体 Collection 常量定义
├── Models/
│   └── Post.php                         # 示例模型，实现 HasMedia + InteractsWithMedia
└── Filament/
    └── Resources/
        ├── MediaResource.php            # 媒体库只读管理资源
        └── MediaResource/
            └── Pages/
                └── ListMedia.php        # 媒体列表页

database/
└── migrations/
    └── xxxx_xx_xx_create_media_table.php  # 由 Spatie 迁移文件发布

config/
└── media-library.php                    # Spatie 媒体库配置

docs/
└── features/
    └── media.md                         # 使用文档

tests/
└── Feature/
    └── Media/
        └── MediaTest.php                # 媒体库核心功能测试
```

### 修改文件

```
composer.json                            # 添加 spatie/laravel-medialibrary 等依赖
config/media-library.php                 # 配置 disk_name、image_conversions
```

---

## Task 1: 安装 MediaLibrary

**Files:**
- Modify: `composer.json`
- Create: `config/media-library.php`（由 vendor:publish 生成后修改）
- Create: `app/Support/MediaCollections.php`

- [ ] **步骤 1：安装 Composer 包**

```bash
cd /home/john/projects/personal/filament-admin
composer require spatie/laravel-medialibrary:"^11.0" filament/spatie-laravel-media-library-plugin:"^5.0"
```

预期输出：`Package operations: 2 installs, ...`（无报错）

- [ ] **步骤 2：发布迁移文件**

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
```

预期输出：`Publishing complete.`，在 `database/migrations/` 下生成 `create_media_table.php`。

- [ ] **步骤 3：运行迁移**

```bash
php artisan migrate
```

预期输出：迁移成功，`media` 表被创建。

- [ ] **步骤 4：发布配置文件**

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"
```

预期输出：`Publishing complete.`，生成 `config/media-library.php`。

- [ ] **步骤 5：修改 `config/media-library.php` 基础配置**

找到以下两项并修改：

```php
// 将 disk_name 改为 'public'
'disk_name' => env('MEDIA_DISK', 'public'),

// 将 queue_conversions_by_default 改为 true
'queue_conversions_by_default' => env('QUEUE_CONVERSIONS_BY_DEFAULT', true),
```

- [ ] **步骤 6：创建 `app/Support/MediaCollections.php`**

```php
<?php

namespace App\Support;

/**
 * 媒体 Collection 常量定义
 *
 * 用于统一管理各业务模块的媒体集合名称，避免魔法字符串。
 */
final class MediaCollections
{
    /** 默认集合（通用文件） */
    public const string DEFAULT = 'default';

    /** 用户头像集合 */
    public const string AVATARS = 'avatars';

    /** 附件集合（文档、PDF 等） */
    public const string ATTACHMENTS = 'attachments';
}
```

- [ ] **步骤 7：确认 `storage/app/public` 软链接存在**

```bash
php artisan storage:link
```

若已存在会输出 `The [public/storage] link already exists.`，正常。

- [ ] **步骤 8：提交**

```bash
git add composer.json composer.lock config/media-library.php \
    app/Support/MediaCollections.php \
    database/migrations/*create_media_table*
git commit -m "feat(media): 安装 spatie/laravel-medialibrary 并初始化配置"
```

---

## Task 2: 缩略图配置与示例 Post 模型

**Files:**
- Modify: `config/media-library.php`
- Create: `app/Models/Post.php`
- Create: `database/migrations/xxxx_create_posts_table.php`
- Create: `database/factories/PostFactory.php`

- [ ] **步骤 1：在 `config/media-library.php` 中添加 image_conversions**

在配置文件中找到 `'image_generators'` 配置块附近，添加（或找到已有的 `conversions` 键，替换/添加）以下内容。  
若文件中无 `image_conversions` 键，在文件末尾 `return [...]` 数组内添加：

```php
/*
|--------------------------------------------------------------------------
| 全局默认图片转换规则
|--------------------------------------------------------------------------
| 这里定义的转换会在所有模型的所有 Collection 上自动应用。
| 若需要针对特定 Collection 定制，请在模型的 registerMediaConversions() 中覆盖。
*/
'image_conversions' => [
    // 150×150 裁剪缩略图
    'thumb' => [
        'width'  => 150,
        'height' => 150,
        'fit'    => Spatie\Image\Enums\Fit::Crop,
    ],
    // 600×600 等比缩放
    'medium' => [
        'width'  => 600,
        'height' => 600,
        'fit'    => Spatie\Image\Enums\Fit::Contain,
    ],
    // 1200×1200 大图等比缩放
    'large' => [
        'width'  => 1200,
        'height' => 1200,
        'fit'    => Spatie\Image\Enums\Fit::Contain,
    ],
],
```

> **注意**：spatie/laravel-medialibrary v11 推荐在模型的 `registerMediaConversions()` 方法中注册转换。全局 `image_conversions` 为 v11 新增配置项。若运行后发现缩略图未生成，请参考 Task 2 步骤 4 的模型级注册方式。

- [ ] **步骤 2：创建 posts 迁移文件**

```bash
php artisan make:migration create_posts_table
```

编辑生成的迁移文件（路径示例：`database/migrations/2026_05_28_xxxxxx_create_posts_table.php`）：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建文章表（用于 MediaLibrary 集成测试的示例模型）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

```bash
php artisan migrate
```

- [ ] **步骤 3：创建 `app/Models/Post.php`**

```php
<?php

namespace App\Models;

use App\Support\MediaCollections;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * 示例文章模型，演示 Spatie MediaLibrary 集成
 *
 * @property int $id
 * @property string $title
 * @property string|null $body
 */
class Post extends Model implements HasMedia
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['title', 'body'];

    /**
     * 注册媒体 Collection
     *
     * DEFAULT：单文件封面图，自动生成三种缩略图
     * ATTACHMENTS：多文件附件，无数量限制
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollections::DEFAULT)
            ->singleFile();

        $this->addMediaCollection(MediaCollections::ATTACHMENTS);
    }

    /**
     * 注册图片转换（缩略图规则）
     *
     * 仅对 DEFAULT Collection 生成缩略图，节省存储空间。
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->fit(Fit::Crop)
            ->performOnCollections(MediaCollections::DEFAULT);

        $this->addMediaConversion('medium')
            ->width(600)
            ->height(600)
            ->fit(Fit::Contain)
            ->performOnCollections(MediaCollections::DEFAULT);

        $this->addMediaConversion('large')
            ->width(1200)
            ->height(1200)
            ->fit(Fit::Contain)
            ->performOnCollections(MediaCollections::DEFAULT);
    }
}
```

- [ ] **步骤 4：创建 `database/factories/PostFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Post 模型工厂
 *
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'body'  => $this->faker->paragraph(),
        ];
    }
}
```

- [ ] **步骤 5：提交**

```bash
git add config/media-library.php \
    app/Models/Post.php \
    database/factories/PostFactory.php \
    database/migrations/*create_posts_table*
git commit -m "feat(media): 添加 Post 示例模型并配置图片缩略图转换"
```

---

## Task 3: MediaResource（只读媒体管理界面）

**Files:**
- Create: `app/Filament/Resources/MediaResource.php`
- Create: `app/Filament/Resources/MediaResource/Pages/ListMedia.php`

- [ ] **步骤 1：创建 `app/Filament/Resources/MediaResource.php`**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages\ListMedia;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * 媒体库管理资源（只读）
 *
 * 提供媒体文件列表、按类型筛选、图片预览和删除操作。
 * 不提供独立的新增/编辑页面，文件通过各业务模块上传。
 */
class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = '媒体库';

    protected static ?string $modelLabel = '媒体文件';

    protected static ?string $pluralModelLabel = '媒体文件';

    protected static ?string $navigationGroup = '内容管理';

    protected static ?int $navigationSort = 10;

    /**
     * 媒体库无独立表单，禁用 Form。
     */
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 图片预览（仅图片类型显示缩略图）
                ImageColumn::make('thumb_url')
                    ->label('预览')
                    ->getStateUsing(function (Media $record): ?string {
                        if (! str_starts_with($record->mime_type, 'image/')) {
                            return null;
                        }

                        return $record->hasGeneratedConversion('thumb')
                            ? $record->getUrl('thumb')
                            : $record->getUrl();
                    })
                    ->width(60)
                    ->height(60)
                    ->defaultImageUrl(asset('images/file-placeholder.png')),

                TextColumn::make('file_name')
                    ->label('文件名')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(fn (Media $record): string => $record->file_name),

                TextColumn::make('mime_type')
                    ->label('文件类型')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'image/')       => 'success',
                        str_starts_with($state, 'video/')       => 'warning',
                        str_starts_with($state, 'application/') => 'info',
                        default                                  => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('human_readable_size')
                    ->label('文件大小')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('size', $direction);
                    }),

                TextColumn::make('model_type')
                    ->label('关联模型')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? class_basename($state)
                        : '—'
                    )
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('上传时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // 按 MIME 大类筛选
                SelectFilter::make('mime_category')
                    ->label('文件类型')
                    ->options([
                        'image'    => '图片',
                        'video'    => '视频',
                        'document' => '文档',
                        'other'    => '其他',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'image'    => $query->where('mime_type', 'like', 'image/%'),
                            'video'    => $query->where('mime_type', 'like', 'video/%'),
                            'document' => $query->where(function (Builder $q): void {
                                $q->where('mime_type', 'like', 'application/pdf')
                                  ->orWhere('mime_type', 'like', 'application/msword')
                                  ->orWhere('mime_type', 'like', 'application/vnd.%')
                                  ->orWhere('mime_type', 'like', 'text/%');
                            }),
                            'other' => $query->where('mime_type', 'not like', 'image/%')
                                             ->where('mime_type', 'not like', 'video/%')
                                             ->where('mime_type', 'not like', 'application/pdf')
                                             ->where('mime_type', 'not like', 'application/msword')
                                             ->where('mime_type', 'not like', 'application/vnd.%')
                                             ->where('mime_type', 'not like', 'text/%'),
                            default => $query,
                        };
                    }),

                // 按上传时间区间筛选
                Filter::make('created_at_range')
                    ->label('上传时间')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('开始日期'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('结束日期'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('删除')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('批量删除'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
        ];
    }
}
```

- [ ] **步骤 2：创建 `app/Filament/Resources/MediaResource/Pages/ListMedia.php`**

先创建目录：

```bash
mkdir -p /home/john/projects/personal/filament-admin/app/Filament/Resources/MediaResource/Pages
```

```php
<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

/**
 * 媒体库列表页
 *
 * 只读界面，无新增按钮。媒体文件通过各业务模块上传。
 */
class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    protected static ?string $title = '媒体库';

    /**
     * 覆盖父类的 HeaderActions，移除默认的「新建」按钮。
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

- [ ] **步骤 3：注册 MediaResource 到 AdminPanelProvider**

打开 `app/Providers/Filament/AdminPanelProvider.php`，在 `->plugins([...])` 或 `->resources([...])` 部分添加：

```php
use App\Filament\Resources\MediaResource;

// 在 resources() 数组内添加（若用自动发现则跳过此步）
->resources([
    MediaResource::class,
])
```

> 若项目使用 Filament 自动发现（`discoverResources()`），此步骤可跳过。检查方式：
> ```bash
> grep -n "discoverResources\|resources(" app/Providers/Filament/AdminPanelProvider.php
> ```

- [ ] **步骤 4：提交**

```bash
git add app/Filament/Resources/MediaResource.php \
    app/Filament/Resources/MediaResource/Pages/ListMedia.php \
    app/Providers/Filament/AdminPanelProvider.php
git commit -m "feat(media): 创建 MediaResource 只读媒体库管理界面"
```

---

## Task 4: 测试

**Files:**
- Create: `tests/Feature/Media/MediaTest.php`

- [ ] **步骤 1：安装 Pest Laravel Filesystem 插件（如未安装）**

检查是否已有：

```bash
grep -n "pest-plugin-laravel" /home/john/projects/personal/filament-admin/composer.json
```

若已有 `pestphp/pest-plugin-laravel`，跳过此步。

- [ ] **步骤 2：准备测试辅助图片**

创建测试用小图片（1x1 像素 PNG，Base64 编码后写入）：

```bash
mkdir -p /home/john/projects/personal/filament-admin/tests/fixtures
# 创建一个 1x1 透明 PNG
php -r "file_put_contents('tests/fixtures/test-image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));"
```

- [ ] **步骤 3：创建 `tests/Feature/Media/MediaTest.php`**

```php
<?php

use App\Filament\Resources\MediaResource;
use App\Filament\Resources\MediaResource\Pages\ListMedia;
use App\Models\Post;
use App\Models\User;
use App\Support\MediaCollections;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

// 所有测试共用 RefreshDatabase + 伪造存储
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class)->in(__DIR__);

beforeEach(function (): void {
    Storage::fake('public');
    $this->adminUser = User::factory()->create();
});

/**
 * 测试 1：上传图片到 Post 模型后，media 记录被正确创建
 */
it('上传图片后 media 表记录被创建', function (): void {
    $post  = Post::factory()->create();
    $image = UploadedFile::fake()->image('cover.jpg', 200, 200);

    $post->addMedia($image->getPathname())
        ->usingFileName('cover.jpg')
        ->toMediaCollection(MediaCollections::DEFAULT);

    expect(Media::count())->toBe(1);

    $media = Media::first();
    expect($media->file_name)->toBe('cover.jpg')
        ->and($media->model_id)->toBe($post->id)
        ->and($media->model_type)->toBe(Post::class)
        ->and($media->collection_name)->toBe(MediaCollections::DEFAULT);
});

/**
 * 测试 2：通过 MediaResource 列表页可以列出媒体文件
 */
it('MediaResource 列表页正确展示媒体文件', function (): void {
    $post  = Post::factory()->create();
    $image = UploadedFile::fake()->image('photo.png', 100, 100);

    $post->addMedia($image->getPathname())
        ->usingFileName('photo.png')
        ->toMediaCollection(MediaCollections::DEFAULT);

    actingAs($this->adminUser);

    livewire(ListMedia::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(Media::all());
});

/**
 * 测试 3：删除媒体文件后，数据库记录消失
 */
it('删除媒体文件后数据库记录被清除', function (): void {
    $post  = Post::factory()->create();
    $image = UploadedFile::fake()->image('delete-me.jpg', 50, 50);

    $post->addMedia($image->getPathname())
        ->usingFileName('delete-me.jpg')
        ->toMediaCollection(MediaCollections::DEFAULT);

    expect(Media::count())->toBe(1);

    $media = Media::first();
    $media->delete();

    expect(Media::count())->toBe(0);
});
```

- [ ] **步骤 4：运行测试，确认通过**

```bash
cd /home/john/projects/personal/filament-admin
php artisan test tests/Feature/Media/MediaTest.php --testdox
```

预期输出（全部绿色）：

```
Feature\Media\MediaTest
  ✓ 上传图片后 media 表记录被创建
  ✓ MediaResource 列表页正确展示媒体文件
  ✓ 删除媒体文件后数据库记录被清除
```

若测试失败，排查要点：
- `Storage::fake('public')` 必须在 `addMedia()` 之前调用
- `UploadedFile::fake()->image()` 生成的文件路径通过 `->getPathname()` 获取
- `actingAs()` 须在 `livewire()` 之前调用

- [ ] **步骤 5：提交**

```bash
git add tests/Feature/Media/MediaTest.php tests/fixtures/test-image.png
git commit -m "test(media): 添加媒体库核心路径测试"
```

---

## Task 5: 功能文档与版本标记

**Files:**
- Create: `docs/features/media.md`

- [ ] **步骤 1：创建 `docs/features/media.md`**

```markdown
# 媒体库功能说明

## 概述

本项目使用 [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary) v11 管理文件上传和缩略图生成。
后台通过 **MediaResource** 提供只读的媒体库管理界面，支持按文件类型筛选和图片预览。

## 技术栈

| 包 | 版本 | 用途 |
|----|------|------|
| `spatie/laravel-medialibrary` | ^11.0 | 文件存储、缩略图生成 |
| `filament/spatie-laravel-media-library-plugin` | ^5.0 | Filament 上传组件 |

## Collection 规范

所有媒体集合名称统一在 `App\Support\MediaCollections` 中定义为常量，禁止在业务代码中使用魔法字符串。

| 常量 | 值 | 用途 |
|------|----|------|
| `MediaCollections::DEFAULT` | `'default'` | 通用文件（单文件，自动替换） |
| `MediaCollections::AVATARS` | `'avatars'` | 用户头像（单文件） |
| `MediaCollections::ATTACHMENTS` | `'attachments'` | 多文件附件 |

## 为 Model 关联媒体（三步接入）

### 第一步：实现接口与 Trait

```php
use App\Support\MediaCollections;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

class YourModel extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        // 单文件封面图
        $this->addMediaCollection(MediaCollections::DEFAULT)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // 生成 150×150 缩略图
        $this->addMediaConversion('thumb')
            ->width(150)->height(150)
            ->fit(Fit::Crop)
            ->performOnCollections(MediaCollections::DEFAULT);
    }
}
```

### 第二步：上传文件

```php
// 从请求文件上传
$model->addMediaFromRequest('file')
    ->toMediaCollection(MediaCollections::DEFAULT);

// 从本地路径上传
$model->addMedia('/path/to/file.jpg')
    ->usingFileName('cover.jpg')
    ->toMediaCollection(MediaCollections::DEFAULT);
```

### 第三步：获取文件 URL

```php
// 原图 URL
$model->getFirstMediaUrl(MediaCollections::DEFAULT);

// 缩略图 URL
$model->getFirstMediaUrl(MediaCollections::DEFAULT, 'thumb');

// 获取 Media 对象
$media = $model->getFirstMedia(MediaCollections::DEFAULT);
$thumbUrl = $media->getUrl('thumb');
```

## 图片转换规格

| 转换名 | 尺寸 | 算法 | 用途 |
|--------|------|------|------|
| `thumb` | 150×150 | Crop（裁剪） | 列表缩略图 |
| `medium` | 600×600 | Contain（等比） | 详情页预览 |
| `large` | 1200×1200 | Contain（等比） | 大图查看 |

> 转换异步执行（队列驱动），本地开发可运行 `php artisan queue:work` 触发。

## 存储配置

- 磁盘：`public`（`storage/app/public`）
- 访问路径：`/storage/{uuid}/{file_name}`
- 缩略图路径：`/storage/{uuid}/conversions/{name}.{ext}`

## 后台管理

访问路径：`/admin/media`

功能：
- 列出所有媒体文件，默认按上传时间倒序
- 支持按文件类型（图片/视频/文档/其他）筛选
- 支持按上传日期区间筛选
- 图片文件显示缩略图预览
- 支持单条和批量删除
```

- [ ] **步骤 2：提交文档**

```bash
git add docs/features/media.md
git commit -m "docs(media): 添加媒体库功能使用文档"
```

- [ ] **步骤 3：打版本标签**

```bash
git tag v0.4.0-媒体库
```

- [ ] **步骤 4：确认标签**

```bash
git tag --list | grep 媒体库
```

预期输出：`v0.4.0-媒体库`

---

## 自检清单

在执行计划前，请确认以下前提条件：

- [ ] MySQL 8.0 已运行（`~/start-dev.sh` 或 Docker Compose）
- [ ] `.env` 中 `DB_CONNECTION=mysql`、`DB_PORT=3380` 已正确配置
- [ ] `QUEUE_CONNECTION=sync`（本地开发，同步执行队列任务，否则缩略图不会立即生成）
- [ ] `php artisan storage:link` 已执行，`public/storage` 软链存在

## 常见问题

**Q: 缩略图没有生成？**
A: 检查 `.env` 中 `QUEUE_CONNECTION=sync`，或手动运行 `php artisan queue:work --once`。

**Q: 删除 Media 记录后物理文件没有删除？**
A: spatie/laravel-medialibrary 会在 `deleting` 事件中删除物理文件。若使用 `DB::table('media')->delete()` 直接删除会跳过此逻辑，务必通过 Eloquent 模型删除：`$media->delete()`。

**Q: `addMedia()` 报权限错误？**
A: 检查 `storage/app/public` 目录权限：`chmod -R 775 storage/app/public`。
