<?php

namespace App\Filament\Resources\Menus;

use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Models\Menu;
use App\Services\ActivityLogger;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label('上级菜单')
                    ->relationship(name: 'parent', titleAttribute: 'title')
                    ->searchable()
                    ->preload(),
                TextInput::make('title')
                    ->label('菜单名称')
                    ->required()
                    ->maxLength(255),
                TextInput::make('icon')
                    ->label('图标')
                    ->maxLength(255),
                TextInput::make('route_name')
                    ->label('路由名称')
                    ->requiredWithout('url')
                    ->maxLength(255),
                TextInput::make('url')
                    ->label('URL')
                    ->requiredWithout('route_name')
                    ->maxLength(255),
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
                    ->required(),
                Hidden::make('source')
                    ->default('core'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('菜单名称')
                    ->searchable(),
                TextColumn::make('parent.title')
                    ->label('上级菜单')
                    ->default('-'),
                TextColumn::make('route_name')
                    ->label('路由名称')
                    ->default('-'),
                TextColumn::make('url')
                    ->label('URL')
                    ->default('-'),
                TextColumn::make('permission_name')
                    ->label('绑定权限')
                    ->default('-'),
                TextColumn::make('sort')
                    ->label('排序')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean(),
                TextColumn::make('source')
                    ->label('来源')
                    ->badge(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->beforeReordering(function (array $order): void {
                static::rememberReorderSnapshot($order);
            })
            ->afterReordering(function (array $order): void {
                static::logReorderActivity($order);
            })
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function canReorder(): bool
    {
        return auth('admin')->user()?->can('reorder_menu') ?? false;
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
            'index'  => ListMenus::route('/'),
            'create' => CreateMenu::route('/create'),
            'edit'   => EditMenu::route('/{record}/edit'),
        ];
    }

    /**
     * 缓存排序前快照
     *
     * @param  array<int, string|int>  $order
     */
    protected static function rememberReorderSnapshot(array $order): void
    {
        request()->attributes->set('menu_reorder_before', static::buildReorderSnapshot($order));
    }

    /**
     * 记录菜单排序日志
     *
     * @param  array<int, string|int>  $order
     */
    protected static function logReorderActivity(array $order): void
    {
        $logger = app(ActivityLogger::class);
        $causer = $logger->currentCauser();
        $record = Menu::query()->find($order[0] ?? null);

        if (! $causer || ! $record) {
            return;
        }

        $before = request()->attributes->get('menu_reorder_before', []);
        $after  = static::buildReorderSnapshot($order);

        $logger->logChanges(
            causer: $causer,
            subject: $record,
            action: 'reordered',
            before: ['order' => $before],
            after: ['order' => $after],
        );
    }

    /**
     * 构建排序快照
     *
     * @param  array<int, string|int>  $order
     * @return array<int, array<string, mixed>>
     */
    protected static function buildReorderSnapshot(array $order): array
    {
        return Menu::query()
            ->whereKey($order)
            ->orderBy('sort')
            ->get(['id', 'parent_id', 'title', 'sort', 'is_active'])
            ->map(fn (Menu $menu): array => [
                'id'        => $menu->id,
                'parent_id' => $menu->parent_id,
                'title'     => $menu->title,
                'sort'      => $menu->sort,
                'is_active' => $menu->is_active,
            ])
            ->values()
            ->all();
    }
}
