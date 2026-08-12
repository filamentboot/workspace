<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\AdSlots\Filament;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Modules\Corporate\AdSlots\Filament\AdSlotResource\Pages\CreateAdSlot;
use Filamentboot\FilamentbootSite\Modules\Corporate\AdSlots\Filament\AdSlotResource\Pages\EditAdSlot;
use Filamentboot\FilamentbootSite\Modules\Corporate\AdSlots\Filament\AdSlotResource\Pages\ListAdSlots;
use Filamentboot\FilamentbootSite\Modules\Corporate\AdSlots\Models\AdSlot;
use UnitEnum;

/**
 * 广告位后台资源
 *
 * 由 filamentboot-site:content-type:sync 按「ad_slot」内容类型声明生成。
 *
 * @extends resource<AdSlot>
 */
class AdSlotResource extends Resource
{
    /** @var class-string<AdSlot> */
    protected static ?string $model = AdSlot::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?string $modelLabel = '广告位';

    protected static ?string $pluralModelLabel = '广告位';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('标题')->required()->maxLength(100),
            FileUpload::make('image')->label('图片')->image()->disk('public')->required(),
            TextInput::make('link_url')->label('跳转链接')->url()->maxLength(255),
            Select::make('position')->label('投放位置')->options(['home_top' => '首页顶部', 'home_sidebar' => '首页侧栏', 'product_bottom' => '产品页底部'])->required(),
            DateTimePicker::make('starts_at')->label('生效开始'),
            DateTimePicker::make('ends_at')->label('生效结束'),
            Toggle::make('is_enabled')->label('启用')->default(true),
            TextInput::make('sort')->label('排序')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('标题'),
                TextColumn::make('position')->label('投放位置'),
                TextColumn::make('is_enabled')->label('启用'),
                TextColumn::make('sort')->label('排序')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort', 'asc')
            ->reorderable('sort');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index'  => ListAdSlots::route('/'),
            'create' => CreateAdSlot::route('/create'),
            'edit'   => EditAdSlot::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete', 'delete_any'];
    }
}
