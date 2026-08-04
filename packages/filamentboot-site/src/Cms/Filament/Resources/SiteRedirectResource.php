<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Resources;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteRedirectResource\Pages\ListSiteRedirects;
use Filamentboot\FilamentbootSite\Cms\Models\SiteRedirect;
use Filamentboot\FilamentbootSite\Cms\Routing\SiteRedirectMiddleware;
use UnitEnum;

/**
 * URL 重定向资源（#18）
 *
 * hits 列只读且可排序：一眼看出哪条旧链接还有人在走——那说明外部引用还没更新，
 * 这条重定向短期内不能删。
 *
 * 表单里 from_path / to_path 在保存时都过 SiteRedirectMiddleware::normalizePath()，
 * 与中间件查询侧用同一个方法，避免 `/old` 与 `old/` 对不上。
 */
class SiteRedirectResource extends Resource
{
    /** @var class-string<SiteRedirect> */
    protected static ?string $model = SiteRedirect::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-right';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 60;

    protected static ?string $recordTitleAttribute = 'from_path';

    protected static ?string $modelLabel = 'URL 重定向';

    protected static ?string $pluralModelLabel = 'URL 重定向';

    /**
     * 表单定义
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')
                ->label('旧路径')
                ->required()
                ->maxLength(500)
                ->unique(ignoreRecord: true)
                // 入库即归一，与中间件查询侧同一个方法
                ->dehydrateStateUsing(fn (?string $state): string => SiteRedirectMiddleware::normalizePath((string) $state))
                ->helperText('不含域名，前后斜杠会自动去掉。例：old-about'),
            TextInput::make('to_path')
                ->label('新地址')
                ->required()
                ->maxLength(500)
                ->helperText('站内路径（如 /about）或完整外链。javascript: 一类伪协议不会生效。'),
            Select::make('status_code')
                ->label('跳转类型')
                ->options([
                    301 => '301 永久跳转（推荐，会转移搜索权重）',
                    302 => '302 临时跳转',
                ])
                ->default(301)
                ->required()
                ->native(false),
        ]);
    }

    /**
     * 列表表格定义
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')
                    ->label('旧路径')
                    ->searchable()
                    ->prefix('/')
                    ->copyable(),
                TextColumn::make('to_path')
                    ->label('新地址')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('status_code')
                    ->label('类型')
                    ->badge()
                    ->color(fn (int $state): string => $state === 301 ? 'success' : 'warning'),
                TextColumn::make('hits')
                    ->label('命中次数')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->tooltip('还在被访问说明外部引用尚未更新，这条重定向暂时不能删'),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    /**
     * 路由页面映射
     *
     * 列表页内建 create / edit 模态：重定向只有三个字段，独立页面是多余的跳转。
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSiteRedirects::route('/'),
        ];
    }
}
