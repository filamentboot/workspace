<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources;

use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource\Pages\ListContactMessages;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource\Pages\ViewContactMessage;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * 询盘后台资源（只读 + 状态流转）
 *
 * 前台访客通过 Livewire 询盘表单直接写 DB，后台不允许新建（canCreate=false）。
 * 列表内联 SelectColumn 支持状态流转 unread→contacted→closed（D-10-15）。
 * 导航 Badge 显示未读询盘数，保护 PII（T-10-03-04）需授权角色。
 */
class ContactMessageResource extends Resource
{
    /** @var class-string<ContactMessage> */
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = '询盘';

    protected static ?string $pluralModelLabel = '询盘';

    /**
     * 导航 Badge 显示未读数
     */
    public static function getNavigationBadge(): ?string
    {
        try {
            $count = ContactMessage::where('status', ContactMessageStatus::UNREAD->value)->count();

            return $count > 0 ? (string) $count : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 导航 Badge 颜色：未读时显示警告色
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * 禁止后台新建（前台表单直接写 DB，T-10-03-01）
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * 详情页 Infolist 定义（ViewContactMessage 页面展示询盘完整信息）
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextEntry::make('name')->label('姓名'),
                TextEntry::make('phone')->label('电话'),
            ]),
            TextEntry::make('message')
                ->label('留言')
                ->placeholder('-')
                ->columnSpanFull(),
            Grid::make(2)->schema([
                TextEntry::make('status')
                    ->label('状态')
                    ->formatStateUsing(
                        fn (mixed $state): string => $state instanceof ContactMessageStatus
                            ? $state->label()
                            : (string) $state
                    ),
                TextEntry::make('ip')->label('IP 地址')->placeholder('-'),
            ]),
            TextEntry::make('created_at')
                ->label('提交时间')
                ->dateTime('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 列表表格定义（只读 + SelectColumn 状态流转）
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('姓名')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('电话')
                    ->searchable(),
                TextColumn::make('message')
                    ->label('留言')
                    ->limit(50)
                    ->placeholder('-'),
                SelectColumn::make('status')
                    ->label('状态')
                    ->options(
                        collect(ContactMessageStatus::cases())
                            ->mapWithKeys(fn (ContactMessageStatus $s): array => [$s->value => $s->label()])
                            ->all()
                    ),
                TextColumn::make('ip')
                    ->label('IP')
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(
                        collect(ContactMessageStatus::cases())
                            ->mapWithKeys(fn (ContactMessageStatus $s): array => [$s->value => $s->label()])
                            ->all()
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * 路由页面映射（仅 List + View，无 Create/Edit）
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view'  => ViewContactMessage::route('/{record}'),
        ];
    }

    /**
     * 询盘列表页无需覆盖 getEloquentQuery（无软删除）
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
