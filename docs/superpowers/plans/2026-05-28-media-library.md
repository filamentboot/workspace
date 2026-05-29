# 媒体库 实现计划

> 修订记录：2026-05-29 根据代码审查问题清单修复 10 项问题。

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 集成 Spatie MediaLibrary，实现文件上传、缩略图自动生成和媒体库管理界面。

**Architecture:** 使用 `spatie/laravel-medialibrary` 处理文件存储和缩略图转换，文件默认存储在 `public` 磁盘（本地）。`MediaResource` 提供只读管理界面，支持按文件类型筛选和图片预览。媒体关联示例直接复用项目已有的 `AdminUser` 模型（演示头像 `avatars` Collection），**不新增任何示例业务模型**，避免污染 `app/Models/` 生产代码。

**Tech Stack:** `spatie/laravel-medialibrary ^11.0`（已验证 v11.x 可用）、`filament/spatie-laravel-media-library-plugin ^5.0`（已验证 v5.6.6 可用）、Pest 4。

> **关于示例模型的设计决策**：原计划新增 `app/Models/Post.php` 演示用法，但这会向生产命名空间引入仅供文档/测试用途的代码。本计划改为：
> - **生产端**：直接为已有的 `AdminUser` 增加 `HasMedia` 接口与 `InteractsWithMedia` Trait，新增 `avatars` 集合（项目本身有头像需求，属于真实业务）。
> - **文档端**：示例代码直接以 `AdminUser` 为载体，无需任何示例模型。
> - **测试端**：上传/缩略图/删除等核心路径全部基于 `AdminUser` 编写，无须新建 fixture。

---

## 文件结构规划

### 新建文件

```
app/
├── Support/
│   └── MediaCollections.php             # 媒体 Collection 常量定义
├── Policies/
│   └── MediaPolicy.php                  # 媒体权限策略（继承 BasePolicy）
└── Filament/
    └── Resources/
        ├── MediaResource.php            # 媒体库只读管理资源
        └── MediaResource/
            └── Pages/
                └── ListMedia.php        # 媒体列表页

database/
└── migrations/
    └── xxxx_xx_xx_create_media_table.php  # 由 Spatie vendor:publish 发布

config/
└── media-library.php                    # Spatie 媒体库配置（vendor:publish 后修改）

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
config/filesystems.php                   # 确认 public disk 配置（一般已存在，无需修改）
app/Models/AdminUser.php                 # 实现 HasMedia + InteractsWithMedia，新增 avatars 集合
app/Providers/AuthServiceProvider.php    # 注册 Media => MediaPolicy 映射
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
unset HTTP_PROXY HTTPS_PROXY http_proxy https_proxy
composer require spatie/laravel-medialibrary:"^11.0" filament/spatie-laravel-media-library-plugin:"^5.0"
```

预期输出：`Package operations: 2 installs, ...`（无报错）。版本验证：`spatie/laravel-medialibrary` 锁定 v11.x，`filament/spatie-laravel-media-library-plugin` 锁定 v5.6.6 或更高 v5.x。

- [ ] **步骤 2：发布迁移文件**

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
```

预期输出：`Publishing complete.`，在 `database/migrations/` 下生成 `xxxx_xx_xx_create_media_table.php`（**禁止手工建表**，必须使用 Spatie 发布的官方迁移）。

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

// 将 queue_conversions_by_default 改为 true（生产环境建议异步；本地开发若 QUEUE_CONNECTION=sync 也无副作用）
'queue_conversions_by_default' => env('QUEUE_CONVERSIONS_BY_DEFAULT', true),
```

- [ ] **步骤 6：确认磁盘配置**

检查 `config/filesystems.php` 中 `disks.public` 已存在（Laravel 13 默认即有）：

```bash
grep -nA 6 "'public' => \[" config/filesystems.php
```

预期看到 `'driver' => 'local'`、`'root' => storage_path('app/public')`、`'url' => env('APP_URL').'/storage'`、`'visibility' => 'public'`。如未来需要将媒体文件分离到独立磁盘，可在此文件添加 `'media'` disk，并将上一步 `disk_name` 设为 `'media'`。本次保持使用 `public`。

- [ ] **步骤 7：创建 `app/Support/MediaCollections.php`**

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

- [ ] **步骤 8：确认 `storage/app/public` 软链接存在**

```bash
php artisan storage:link
```

若已存在会输出 `The [public/storage] link already exists.`，正常。

- [ ] **步骤 9：执行 Pint 格式化**

```bash
composer pint
```

- [ ] **步骤 10：提交**

```bash
git add composer.json composer.lock config/media-library.php \
    app/Support/MediaCollections.php \
    database/migrations/*create_media_table*
git commit -m "feat(media): 安装 spatie/laravel-medialibrary 并初始化配置"
```

---

## Task 2: 缩略图配置与 AdminUser 媒体集成

**Files:**
- Modify: `config/media-library.php`
- Modify: `app/Models/AdminUser.php`

> 本任务不新建任何示例模型。直接让生产模型 `AdminUser` 承担媒体关联示例的角色（头像 Collection 本就是真实业务需求）。

- [ ] **步骤 1：在 `config/media-library.php` 中添加 image_conversions（可选全局配置）**

在配置文件 `return [...]` 数组内添加（若版本中已无 `image_conversions` 键，则此步可跳过，统一在模型 `registerMediaConversions()` 中注册即可）：

```php
/*
|--------------------------------------------------------------------------
| 全局默认图片转换规则（可选）
|--------------------------------------------------------------------------
| v11 推荐在模型的 registerMediaConversions() 中按需注册，
| 此处仅做集中说明，实际生效以模型方法为准。
*/
'image_conversions' => [
    'thumb'  => ['width' => 150,  'height' => 150,  'fit' => Spatie\Image\Enums\Fit::Crop],
    'medium' => ['width' => 600,  'height' => 600,  'fit' => Spatie\Image\Enums\Fit::Contain],
    'large'  => ['width' => 1200, 'height' => 1200, 'fit' => Spatie\Image\Enums\Fit::Contain],
],
```

> **说明**：spatie/laravel-medialibrary v11 推荐在模型的 `registerMediaConversions()` 中注册转换。下方 `AdminUser` 模型会以方法形式声明三种缩略图规则。

- [ ] **步骤 2：修改 `app/Models/AdminUser.php`，接入 MediaLibrary**

在文件顶部 `use` 区追加：

```php
use App\Support\MediaCollections;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
```

修改类签名，新增 `implements HasMedia`（与现有接口并列），并在 `use` Trait 区追加 `InteractsWithMedia`：

```php
class AdminUser extends Authenticatable implements FilamentUser, HasMedia
{
    use HasFactory, InteractsWithMedia, Notifiable, SoftDeletes, TwoFactorAuthenticatable;
    // ……现有代码保持不变……
}
```

> 注：上面 `implements` 列表请按 `AdminUser` 当前已实现的接口顺序追加 `HasMedia`，不要重复或遗漏既有接口。Trait 同理，仅追加 `InteractsWithMedia`。

在类末尾追加两个方法：

```php
/**
 * 注册媒体 Collection
 *
 * AVATARS：单文件头像，自动替换旧文件。
 */
public function registerMediaCollections(): void
{
    $this->addMediaCollection(MediaCollections::AVATARS)
        ->singleFile();
}

/**
 * 注册图片转换（缩略图规则）
 *
 * 仅对 AVATARS Collection 生成缩略图。
 */
public function registerMediaConversions(?Media $media = null): void
{
    $this->addMediaConversion('thumb')
        ->width(150)
        ->height(150)
        ->fit(Fit::Crop)
        ->performOnCollections(MediaCollections::AVATARS);

    $this->addMediaConversion('medium')
        ->width(600)
        ->height(600)
        ->fit(Fit::Contain)
        ->performOnCollections(MediaCollections::AVATARS);

    $this->addMediaConversion('large')
        ->width(1200)
        ->height(1200)
        ->fit(Fit::Contain)
        ->performOnCollections(MediaCollections::AVATARS);
}
```

- [ ] **步骤 3：执行 Pint 格式化**

```bash
composer pint
```

- [ ] **步骤 4：提交**

```bash
git add config/media-library.php app/Models/AdminUser.php
git commit -m "feat(media): AdminUser 接入 MediaLibrary 并配置头像缩略图转换"
```

---

## Task 3: MediaResource（只读媒体管理界面）

**Files:**
- Create: `app/Filament/Resources/MediaResource.php`
- Create: `app/Filament/Resources/MediaResource/Pages/ListMedia.php`
- Create: `app/Policies/MediaPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php`

- [ ] **步骤 1：创建 `app/Policies/MediaPolicy.php`**

继承项目已有的 `App\Policies\BasePolicy`，权限通过 Spatie Permission 包统一管理：

```php
<?php

namespace App\Policies;

use App\Models\AdminUser;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * 媒体文件权限策略
 *
 * 继承 BasePolicy，权限名约定为 viewAny_media / view_media / delete_media / deleteAny_media。
 */
class MediaPolicy extends BasePolicy
{
    /** 资源权限前缀（与 BasePolicy 约定一致） */
    protected string $resource = 'media';

    /**
     * 媒体库不提供创建/更新接口（文件由业务模块上传），强制禁用。
     */
    public function create(AdminUser $user): bool
    {
        return false;
    }

    public function update(AdminUser $user, Media $media): bool
    {
        return false;
    }
}
```

> 若 `BasePolicy` 已提供 `$resource` 之外的不同约定（例如以方法形式返回前缀），请按 `app/Policies/BasePolicy.php` 实际签名调整；本步骤的关键是**必须继承 BasePolicy**，禁止重新实现授权逻辑。

- [ ] **步骤 2：在 `app/Providers/AuthServiceProvider.php` 注册策略**

打开 `app/Providers/AuthServiceProvider.php`，在 `$policies` 数组追加：

```php
use App\Policies\MediaPolicy;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

protected $policies = [
    // ……现有映射保持不变……
    Media::class => MediaPolicy::class,
];
```

> 提醒：Laravel 11+ 已移除框架 `AuthServiceProvider` 基类，本项目的 `AuthServiceProvider` 必须 `extends Illuminate\Support\ServiceProvider`，并在 `boot()` 中通过 `Gate::policy()` 循环注册 `$policies`。若现有写法不同，请按既有模式追加映射，**不要改变注册方式**。

- [ ] **步骤 3：创建 `app/Filament/Resources/MediaResource.php`**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages\ListMedia;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
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
     * 媒体库无独立表单，返回空 Schema。
     *
     * Filament 5 API：使用 Schemas\Schema + ->components([])，
     * 不再使用 Filament 3.x 的 Forms\Form + ->schema([])。
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
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
                        DatePicker::make('from')->label('开始日期'),
                        DatePicker::make('until')->label('结束日期'),
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

> 关于 Filament 5 媒体相关组件命名空间（在其他 Resource 中使用上传组件时引用）：
> - 表单上传：`Filament\Forms\Components\SpatieMediaLibraryFileUpload`
> - 表格预览：`Filament\Tables\Columns\SpatieMediaLibraryImageColumn`
>
> 本 `MediaResource` 直接以 `Media` 模型为列表数据源，不使用上述两个组件，但其他业务 Resource（如未来 `AdminUserResource` 头像字段）需按此命名空间引入。

- [ ] **步骤 4：创建 `app/Filament/Resources/MediaResource/Pages/ListMedia.php`**

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

- [ ] **步骤 5：确认 MediaResource 已被发现**

`AdminPanelProvider` 当前已使用 `->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')`，新建的 `MediaResource` 会自动被发现，无需手动注册。

```bash
grep -n "discoverResources" app/Providers/Filament/AdminPanelProvider.php
```

- [ ] **步骤 6：执行 Pint 格式化**

```bash
composer pint
```

- [ ] **步骤 7：提交**

```bash
git add app/Filament/Resources/MediaResource.php \
    app/Filament/Resources/MediaResource/Pages/ListMedia.php \
    app/Policies/MediaPolicy.php \
    app/Providers/AuthServiceProvider.php
git commit -m "feat(media): 创建 MediaResource 只读媒体库管理界面与 MediaPolicy"
```

---

## Task 4: 测试

**Files:**
- Create: `tests/Feature/Media/MediaTest.php`

- [ ] **步骤 1：确认依赖**

```bash
grep -n "pest-plugin-laravel\|pest-plugin-livewire" /home/john/projects/personal/filament-admin/composer.json
```

若缺少 `pestphp/pest-plugin-livewire`，安装：

```bash
composer require --dev pestphp/pest-plugin-livewire
```

- [ ] **步骤 2：准备测试辅助图片（可选，本计划测试用 `UploadedFile::fake()->image()`，无需物理 fixture）**

如未来需要真实图片测试，可执行：

```bash
mkdir -p /home/john/projects/personal/filament-admin/tests/fixtures
php -r "file_put_contents('tests/fixtures/test-image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));"
```

- [ ] **步骤 3：创建 `tests/Feature/Media/MediaTest.php`**

```php
<?php

use App\Filament\Resources\MediaResource\Pages\ListMedia;
use App\Models\AdminUser;
use App\Support\MediaCollections;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

// tests/Pest.php 已对 Feature 套件全局 apply RefreshDatabase，此处无需重复声明。

beforeEach(function (): void {
    Storage::fake('public');

    // 清理 Spatie Permission 缓存，避免跨测试污染
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 创建一个具备超管能力的 AdminUser（项目约定：超级管理员绕过所有权限检查）
    $this->adminUser = AdminUser::factory()->create();
});

/**
 * 测试 1：上传图片到 AdminUser 后，media 记录被正确创建
 */
it('上传图片后 media 表记录被创建', function (): void {
    $image = UploadedFile::fake()->image('avatar.jpg', 200, 200);

    $this->adminUser->addMedia($image->getPathname())
        ->usingFileName('avatar.jpg')
        ->toMediaCollection(MediaCollections::AVATARS);

    expect(Media::count())->toBe(1);

    $media = Media::first();
    expect($media->file_name)->toBe('avatar.jpg')
        ->and($media->model_id)->toBe($this->adminUser->id)
        ->and($media->model_type)->toBe(AdminUser::class)
        ->and($media->collection_name)->toBe(MediaCollections::AVATARS);
});

/**
 * 测试 2：通过 MediaResource 列表页可以列出媒体文件
 */
it('MediaResource 列表页正确展示媒体文件', function (): void {
    $image = UploadedFile::fake()->image('photo.png', 100, 100);

    $this->adminUser->addMedia($image->getPathname())
        ->usingFileName('photo.png')
        ->toMediaCollection(MediaCollections::AVATARS);

    actingAs($this->adminUser, 'admin');

    livewire(ListMedia::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(Media::all());
});

/**
 * 测试 3：删除媒体文件后，数据库记录消失
 */
it('删除媒体文件后数据库记录被清除', function (): void {
    $image = UploadedFile::fake()->image('delete-me.jpg', 50, 50);

    $this->adminUser->addMedia($image->getPathname())
        ->usingFileName('delete-me.jpg')
        ->toMediaCollection(MediaCollections::AVATARS);

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
- `actingAs($user, 'admin')` 必须显式传入 `'admin'` guard，且要在 `livewire()` 之前调用
- 测试库 `filamentadmin_test` 必须已创建（参见 AGENTS.md 环境说明）

- [ ] **步骤 5：执行 Pint 格式化**

```bash
composer pint
```

- [ ] **步骤 6：提交**

```bash
git add tests/Feature/Media/MediaTest.php
git commit -m "test(media): 添加媒体库核心路径测试"
```

---

## Task 5: 功能文档与版本标记

**Files:**
- Create: `docs/features/media.md`

- [ ] **步骤 1：创建 `docs/features/media.md`**

````markdown
# 媒体库功能说明

## 概述

本项目使用 [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary) v11 管理文件上传和缩略图生成。
后台通过 **MediaResource** 提供只读的媒体库管理界面，支持按文件类型筛选和图片预览。

## 技术栈

| 包 | 版本 | 用途 |
|----|------|------|
| `spatie/laravel-medialibrary` | ^11.0 | 文件存储、缩略图生成 |
| `filament/spatie-laravel-media-library-plugin` | ^5.0（已验证 v5.6.6） | Filament 上传组件 |

## Collection 规范

所有媒体集合名称统一在 `App\Support\MediaCollections` 中定义为常量，禁止在业务代码中使用魔法字符串。

| 常量 | 值 | 用途 |
|------|----|------|
| `MediaCollections::DEFAULT` | `'default'` | 通用文件（单文件，自动替换） |
| `MediaCollections::AVATARS` | `'avatars'` | 用户头像（单文件） |
| `MediaCollections::ATTACHMENTS` | `'attachments'` | 多文件附件 |

## 为 Model 关联媒体（以 AdminUser 为例）

项目中 `AdminUser` 已实现 `HasMedia` 接口，演示完整接入流程如下。

### 第一步：实现接口与 Trait

```php
use App\Support\MediaCollections;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

class AdminUser extends Authenticatable implements FilamentUser, HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollections::AVATARS)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)->height(150)
            ->fit(Fit::Crop)
            ->performOnCollections(MediaCollections::AVATARS);
    }
}
```

### 第二步：上传文件

```php
// 从请求文件上传
$adminUser->addMediaFromRequest('avatar')
    ->toMediaCollection(MediaCollections::AVATARS);

// 从本地路径上传
$adminUser->addMedia('/path/to/file.jpg')
    ->usingFileName('avatar.jpg')
    ->toMediaCollection(MediaCollections::AVATARS);
```

### 第三步：获取文件 URL

```php
// 原图 URL
$adminUser->getFirstMediaUrl(MediaCollections::AVATARS);

// 缩略图 URL
$adminUser->getFirstMediaUrl(MediaCollections::AVATARS, 'thumb');

// 获取 Media 对象
$media = $adminUser->getFirstMedia(MediaCollections::AVATARS);
$thumbUrl = $media->getUrl('thumb');
```

### 在 Filament 表单/表格中使用

```php
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;

// 表单
SpatieMediaLibraryFileUpload::make('avatar')
    ->collection(MediaCollections::AVATARS)
    ->image()
    ->avatar();

// 表格
SpatieMediaLibraryImageColumn::make('avatar')
    ->collection(MediaCollections::AVATARS)
    ->conversion('thumb');
```

## 图片转换规格

| 转换名 | 尺寸 | 算法 | 用途 |
|--------|------|------|------|
| `thumb` | 150×150 | Crop（裁剪） | 列表缩略图 |
| `medium` | 600×600 | Contain（等比） | 详情页预览 |
| `large` | 1200×1200 | Contain（等比） | 大图查看 |

> 转换异步执行（队列驱动），本地开发可运行 `php artisan queue:work` 触发；
> 或将 `.env` 中 `QUEUE_CONNECTION=sync` 改为同步执行。

## 存储配置

- 磁盘：`public`（`config/filesystems.php` 中 `disks.public`，根目录 `storage/app/public`）
- 软链接：`php artisan storage:link`，将 `public/storage` 链接到 `storage/app/public`
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
````

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

- [ ] MySQL 8.0 已运行（`~/start-dev.sh` 或 Docker Compose），端口 `3380`
- [ ] `.env` 中 `DB_CONNECTION=mysql`、`DB_PORT=3380` 已正确配置
- [ ] 测试库 `filamentadmin_test` 已创建：
  `mysql -uroot -p123456 -h127.0.0.1 -P3380 -e "CREATE DATABASE filamentadmin_test"`
- [ ] `.env` 中 `QUEUE_CONNECTION=sync`（本地开发，否则缩略图不会立即生成）
- [ ] `php artisan storage:link` 已执行，`public/storage` 软链存在
- [ ] `composer install` 前已 `unset HTTP_PROXY HTTPS_PROXY http_proxy https_proxy`

## 常见问题

**Q: 缩略图没有生成？**
A: 检查 `.env` 中 `QUEUE_CONNECTION=sync`，或手动运行 `php artisan queue:work --once`。

**Q: 删除 Media 记录后物理文件没有删除？**
A: spatie/laravel-medialibrary 会在 `deleting` 事件中删除物理文件。若使用 `DB::table('media')->delete()` 直接删除会跳过此逻辑，务必通过 Eloquent 模型删除：`$media->delete()`。

**Q: `addMedia()` 报权限错误？**
A: 检查 `storage/app/public` 目录权限：`chmod -R 775 storage/app/public`。

**Q: MediaPolicy 测试报「该用户无权限」？**
A: 测试前调用 `app(PermissionRegistrar::class)->forgetCachedPermissions()`，并确认 `actingAs($user, 'admin')` 显式传入 admin guard；若用普通管理员账号，需先为其分配 `viewAny_media`、`delete_media` 等权限。
