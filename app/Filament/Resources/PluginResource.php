<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PluginResource\Pages\ListPlugins;
use App\Filament\Resources\PluginResource\Pages\ViewPlugin;
use App\Services\PluginManager;
use FilamentAdmin\Models\Plugin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Route;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * 插件管理后台资源
 *
 * 提供已安装插件的列表展示、启停操作和初始化入口。
 * 扫描功能通过 ListPlugins 页头按钮调 plugin:scan 命令触发。
 */
class PluginResource extends Resource
{
    protected static ?string $model = Plugin::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string|UnitEnum|null $navigationGroup = '插件市场';

    protected static ?string $modelLabel = '插件';

    protected static ?string $pluralModelLabel = '扫描已安装插件';

    protected static ?string $navigationLabel = '已安装插件';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('插件名称')
                    ->searchable(),
                TextColumn::make('package_name')
                    ->label('包名')
                    ->searchable(),
                TextColumn::make('kind')
                    ->label('类型')
                    ->badge(),
                IconColumn::make('is_enabled')
                    ->label('启用状态')
                    ->boolean(),
                TextColumn::make('installed_version')
                    ->label('版本'),
                TextColumn::make('init_status')
                    ->label('初始化状态')
                    ->badge(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('settings')
                    ->label('设置')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(function (Plugin $record): ?string {
                        if (blank($record->settings_page_slug)) {
                            return null;
                        }

                        $routeName = 'filament.admin.pages.' . str_replace('/', '.', $record->settings_page_slug);

                        return Route::has($routeName) ? route($routeName) : null;
                    })
                    ->visible(fn (Plugin $record): bool => $record->is_enabled && filled($record->settings_page_slug)),
                Action::make('toggle')
                    ->label(fn (Plugin $record): string => $record->is_enabled ? '禁用' : '启用')
                    ->action(function (Plugin $record): void {
                        if ($record->is_enabled) {
                            app(PluginManager::class)->disable($record);
                        } else {
                            app(PluginManager::class)->enable($record);
                        }
                    })
                    ->requiresConfirmation()
                    ->authorize('update_plugin'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlugins::route('/'),
            'view'  => ViewPlugin::route('/{record}'),
        ];
    }
}
