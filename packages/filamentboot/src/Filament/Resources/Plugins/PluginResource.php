<?php

namespace Filamentboot\Filament\Resources\Plugins;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filamentboot\Filament\Resources\Plugins\Pages\ListPlugins;
use Filamentboot\Filament\Resources\Plugins\Pages\ViewPlugin;
use Filamentboot\Models\Plugin;
use Filamentboot\Services\PluginManager;
use Illuminate\Support\Facades\Route;
use UnitEnum;

/**
 * 插件管理后台资源
 *
 * 提供已安装插件的列表展示、启停操作和安装/卸载入口。
 * 安装 Action：调 PluginManager::install()，社区来源触发第三方风险确认（D-12-13）。
 * 卸载 Action：带 drop_tables 复选框（默认未勾选），调 PluginManager::uninstall()（D-12-14）。
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
                TextColumn::make('compatibility_status')
                    ->label('兼容性')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'compatible'   => 'success',
                        'incompatible' => 'danger',
                        default        => 'warning',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'compatible'   => '兼容',
                        'incompatible' => '不兼容',
                        default        => '未知',
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('install')
                    ->label('安装插件')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->requiresConfirmation(fn (Plugin $record): bool => $record->source === 'community')
                    ->modalHeading(fn (Plugin $record): string => $record->source === 'community' ? '安装第三方插件' : '安装插件')
                    ->modalDescription(fn (Plugin $record): string => $record->source === 'community'
                        ? '此插件来自社区，未经官方审核。安装前请自行评估安全风险。继续安装即表示您接受相关风险。'
                        : '确认安装此插件？')
                    ->modalSubmitActionLabel(fn (Plugin $record): string => $record->source === 'community' ? '我已了解，继续安装' : '确认安装')
                    ->action(function (Plugin $record): void {
                        app(PluginManager::class)->install($record);
                    })
                    // 退出面板级 databaseTransactions()：install 会 dispatch ComposerInstallJob，
                    // 队列 after_commit=false，事务内入队会让 worker 读到未提交数据
                    ->databaseTransaction(false)
                    ->authorize('install_plugin')
                    ->visible(fn (Plugin $record): bool => $record->init_status !== 'done'
                        && $record->compatibility_status !== 'incompatible'),
                Action::make('uninstall')
                    ->label('卸载')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('卸载插件')
                    ->modalDescription('此操作不可逆。卸载将移除 Composer 包并清除插件状态记录。')
                    ->modalSubmitActionLabel('确认卸载')
                    ->form([
                        Checkbox::make('drop_tables')
                            ->label('同时删除插件自建数据表')
                            ->default(false)
                            ->helperText(fn (Plugin $record): string => self::buildDropTablesHelperText($record)),
                    ])
                    ->action(function (Plugin $record, array $data): void {
                        app(PluginManager::class)->uninstall($record, $data['drop_tables'] ?? false);
                    })
                    // 退出面板级事务：uninstall 会 dispatch ComposerRemoveJob 并可能删表（DDL 隐式提交）
                    ->databaseTransaction(false)
                    ->authorize('uninstall_plugin')
                    ->visible(fn (Plugin $record): bool => $record->init_status === 'done'),
                Action::make('settings')
                    ->label('设置')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(function (Plugin $record): ?string {
                        if (blank($record->settings_page_slug)) {
                            return null;
                        }

                        $routeName = 'filament.admin.pages.'.str_replace('/', '.', $record->settings_page_slug);

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
                    // 退出面板级事务：启停会调 Artisan optimize:clear 等外部副作用
                    ->databaseTransaction(false)
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

    /**
     * 构建卸载弹窗中受影响数据表的提示文案
     */
    protected static function buildDropTablesHelperText(Plugin $record): string
    {
        $data   = $record->post_install_data ?? [];
        $tables = $data['tables'] ?? [];

        if (empty($tables)) {
            return '（该插件未声明自建数据表）';
        }

        return '受影响的数据表：'.implode('、', array_map(
            fn (string $t): string => "`{$t}`",
            $tables
        ));
    }
}
