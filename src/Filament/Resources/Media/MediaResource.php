<?php

namespace FilamentAdmin\Filament\Resources\Media;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use FilamentAdmin\Filament\Resources\Media\Pages\ListMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use UnitEnum;

/**
 * 媒体库管理 Resource
 *
 * 提供媒体文件的列表、预览、下载、删除功能。
 * 仅列表页，不支持手动新增（文件通过各模块上传组件上传）。
 */
class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-photo';

    protected static ?string $navigationLabel = '媒体库';

    protected static string|UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort  = 30;

    protected static ?string $slug            = 'media';

    protected static ?string $modelLabel      = '媒体文件';

    protected static ?string $pluralModelLabel = '媒体库';

    /**
     * 表格定义
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview_url')
                    ->label('预览')
                    ->size(60),
                Tables\Columns\TextColumn::make('file_name')
                    ->label('文件名')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('collection_name')
                    ->label('Collection')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mime_type')
                    ->label('类型')
                    ->sortable(),
                Tables\Columns\TextColumn::make('human_readable_size')
                    ->label('大小'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('上传时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('collection_name')
                    ->label('Collection')
                    ->options(fn () => Media::query()
                        ->distinct()
                        ->pluck('collection_name', 'collection_name')
                        ->toArray()),
            ])
            ->actions([
                Action::make('download')
                    ->label('下载')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Media $record): string => $record->getUrl())
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * 页面路由注册
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
        ];
    }
}
