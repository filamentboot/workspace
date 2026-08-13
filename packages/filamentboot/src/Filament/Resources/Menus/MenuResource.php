<?php

namespace Filamentboot\Filament\Resources\Menus;

use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filamentboot\Filament\Resources\Menus\Pages\CreateMenu;
use Filamentboot\Filament\Resources\Menus\Pages\EditMenu;
use Filamentboot\Filament\Resources\Menus\Pages\MenuTree;
use Filamentboot\Models\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\Models\Permission;
use UnitEnum;

/**
 * 菜单规则资源
 */
class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static string|UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = '菜单';

    protected static ?string $pluralModelLabel = '菜单规则';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formComponents());
    }

    /**
     * 表单组件列表（未挂载到任何 Schema 容器）
     *
     * 与 form() 分开的唯一理由是给树页 MenuTree 的模态动作用——它必须拿到一批
     * **没被绑过容器**的新组件，走 form() 会先绑到一个临时容器上，Filament 5
     * 把每个字段的绝对状态路径缓存成裸字段名后就再也改不回来（同构问题见
     * SiteMenuItemResource::formComponents() 的说明）。MenuTree 尤其要靠这条：
     * 裸字段名 'title' 会和 Filament\Pages\BasePage::$title 撞名，
     * Livewire 用旧缓存路径 data_set($this, 'title', ...) 直接写受保护属性，
     * 服务端直接抛 "Cannot access protected property" 致命错误。
     *
     * @return list<Component>
     */
    public static function formComponents(): array
    {
        return [
            Select::make('parent_id')
                ->label('上级菜单')
                ->relationship(name: 'parent', titleAttribute: 'title')
                ->searchable()
                ->preload(),
            Select::make('type')
                ->label('节点类型')
                ->options([
                    'menu'   => '导航菜单',
                    'action' => '操作节点',
                ])
                ->default('menu')
                ->required()
                ->live(),
            TextInput::make('title')
                ->label('菜单名称')
                ->required()
                ->maxLength(255),
            TextInput::make('icon')
                ->label('图标')
                ->maxLength(255)
                ->prefixIcon(fn (?string $state): string => filled($state) ? $state : 'heroicon-o-photo')
                ->hint('填写 Heroicon 名称，如 heroicon-o-home')
                ->visible(fn (Get $get): bool => $get('type') !== 'action'),
            Radio::make('link_type')
                ->label('导航类型')
                ->options([
                    'route' => '路由名称',
                    'url'   => '自定义 URL',
                ])
                ->default(fn ($record): string => filled($record?->link_type) ? $record->link_type : (filled($record?->url) ? 'url' : 'route'))
                ->inline()
                ->live()
                ->visible(fn (Get $get): bool => $get('type') !== 'action'),
            TextInput::make('route_name')
                ->label('路由名称')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('type') !== 'action' && $get('link_type') !== 'url')
                ->requiredIf('link_type', 'route'),
            TextInput::make('url')
                ->label('URL')
                ->maxLength(255)
                ->url()
                ->visible(fn (Get $get): bool => $get('type') !== 'action' && $get('link_type') === 'url')
                ->requiredIf('link_type', 'url'),
            Select::make('permission_name')
                ->label('绑定权限')
                ->options(fn (): array => Permission::query()
                    ->where('guard_name', 'admin')
                    ->orderBy('name')
                    ->pluck('name', 'name')
                    ->all())
                ->searchable(),
            TextInput::make('sort')
                ->label('排序')
                ->numeric()
                ->default(0)
                ->required(),
            Toggle::make('is_active')
                ->label('启用')
                ->default(true),
            Select::make('target')
                ->label('打开方式')
                ->options([
                    'self'  => '当前窗口',
                    'blank' => '新窗口',
                ])
                ->default('self')
                ->required()
                ->visible(fn (Get $get): bool => $get('type') !== 'action'),
            Hidden::make('source')
                ->default('core'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('icon')
                    ->label('图标')
                    ->icon(fn (?string $state): string => filled($state) ? $state : 'heroicon-o-minus')
                    ->default('heroicon-o-minus')
                    ->color('gray'),
                TextColumn::make('title')
                    ->label('菜单名称')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('类型')
                    ->formatStateUsing(fn (string $state): string => $state === 'action' ? '操作' : '菜单')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'action' ? 'gray' : 'primary'),
                TextColumn::make('navigation_target')
                    ->label('导航目标')
                    ->getStateUsing(fn (Menu $record): string => $record->route_name ?: ($record->url ?: '-'))
                    ->url(fn (Menu $record): ?string => $record->url ?: null)
                    ->openUrlInNewTab(fn (Menu $record): bool => $record->url && $record->target === 'blank')
                    ->color(fn (Menu $record): ?string => $record->url ? 'primary' : null)
                    ->copyable(fn (Menu $record): bool => filled($record->route_name))
                    ->copyMessage('路由名已复制'),
                TextColumn::make('permission_name')
                    ->label('绑定权限')
                    ->default('-')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('sort')
                    ->label('排序')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('启用')
                    ->disabled(fn (): bool => ! (auth('admin')->user()?->can('update_menu') ?? false)),
                TextColumn::make('source')
                    ->label('来源')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('类型')
                    ->options([
                        'menu'   => '导航菜单',
                        'action' => '操作节点',
                    ]),
                SelectFilter::make('is_active')
                    ->label('状态')
                    ->options([
                        '1' => '已启用',
                        '0' => '已禁用',
                    ]),
                TrashedFilter::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('enable')
                        ->label('批量启用')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (): bool => auth('admin')->user()?->can('update_menu') ?? false)
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            // 后端二次鉴权：前端 visible() 可被绕过，此处为最终防线（L-02）
                            abort_unless(auth('admin')->user()?->can('update_menu') ?? false, 403);
                            $records->each->update(['is_active' => true]);
                        }),
                    BulkAction::make('disable')
                        ->label('批量禁用')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->visible(fn (): bool => auth('admin')->user()?->can('update_menu') ?? false)
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            // 后端二次鉴权：前端 visible() 可被绕过，此处为最终防线（L-02）
                            abort_unless(auth('admin')->user()?->can('update_menu') ?? false, 403);
                            $records->each->update(['is_active' => false]);
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('parent')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => MenuTree::route('/'),
            'create' => CreateMenu::route('/create'),
            'edit'   => EditMenu::route('/{record}/edit'),
        ];
    }
}
