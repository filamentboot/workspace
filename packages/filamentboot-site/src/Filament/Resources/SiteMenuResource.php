<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteMenuItemResource\Pages\SiteMenuItemTree;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteMenuResource\Pages\CreateSiteMenu;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteMenuResource\Pages\EditSiteMenu;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteMenuResource\Pages\ListSiteMenus;
use Filamentboot\FilamentbootSite\Models\SiteMenu;
use UnitEnum;

/**
 * 前台导航菜单资源（#17）
 *
 * 管的是**前台导航**（nav / footer 两条），与主包 MenuResource 管的后台侧边栏
 * 是两回事——两者表不同、模型不同、渲染位置不同。
 *
 * 菜单本体只有 key 与名称两个字段，真正的结构在菜单项树里，
 * 所以列表页的主要动作是「管理菜单项」而不是「编辑」。
 */
class SiteMenuResource extends Resource
{
    /** @var class-string<SiteMenu> */
    protected static ?string $model = SiteMenu::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = '前台导航';

    protected static ?string $pluralModelLabel = '前台导航';

    /**
     * 表单定义
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->label('标识')
                ->required()
                ->unique(ignoreRecord: true)
                ->rules(['regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'])
                ->maxLength(50)
                // key 是前台 blade 里 resolve('main') 的那个参数，改了导航就读不到了
                ->helperText('前台用它取菜单：main 是顶部导航，footer 是页脚导航。只允许小写字母、数字与连字符。'),
            TextInput::make('name')
                ->label('名称')
                ->required()
                ->maxLength(100)
                ->helperText('仅后台显示，方便识别'),
        ]);
    }

    /**
     * 列表表格定义
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('名称')
                    ->searchable(),
                TextColumn::make('key')
                    ->label('标识')
                    ->badge()
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label('菜单项')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('manageItems')
                    ->label('管理菜单项')
                    ->icon('heroicon-o-list-bullet')
                    ->url(fn (SiteMenu $record): string => SiteMenuItemTree::getUrl(['menu' => $record->key])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('key');
    }

    /**
     * 路由页面映射
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index'  => ListSiteMenus::route('/'),
            'create' => CreateSiteMenu::route('/create'),
            'edit'   => EditSiteMenu::route('/{record}/edit'),
        ];
    }
}
