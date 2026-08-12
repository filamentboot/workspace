<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\FriendLinks\Filament;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Modules\Corporate\FriendLinks\Filament\FriendLinkResource\Pages\CreateFriendLink;
use Filamentboot\FilamentbootSite\Modules\Corporate\FriendLinks\Filament\FriendLinkResource\Pages\EditFriendLink;
use Filamentboot\FilamentbootSite\Modules\Corporate\FriendLinks\Filament\FriendLinkResource\Pages\ListFriendLinks;
use Filamentboot\FilamentbootSite\Modules\Corporate\FriendLinks\Models\FriendLink;
use UnitEnum;

/**
 * 友情链接后台资源
 *
 * 由 filamentboot-site:content-type:sync 按「friend_link」内容类型声明生成。
 *
 * @extends resource<FriendLink>
 */
class FriendLinkResource extends Resource
{
    /** @var class-string<FriendLink> */
    protected static ?string $model = FriendLink::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?string $modelLabel = '友情链接';

    protected static ?string $pluralModelLabel = '友情链接';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('名称')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('url')->label('链接')->url()->maxLength(255)->required(),
            FileUpload::make('logo')->label('Logo')->image()->disk('public'),
            Toggle::make('is_enabled')->label('启用')->default(true),
            TextInput::make('sort')->label('排序')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('名称'),
                TextColumn::make('url')->label('链接'),
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
            'index'  => ListFriendLinks::route('/'),
            'create' => CreateFriendLink::route('/create'),
            'edit'   => EditFriendLink::route('/{record}/edit'),
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
