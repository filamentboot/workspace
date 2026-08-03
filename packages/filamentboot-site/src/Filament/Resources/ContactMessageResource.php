<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources;

use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource\Pages\ListContactMessages;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource\Pages\ViewContactMessage;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\Models\AdminUser;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * 询盘后台资源（只读 + 状态流转）
 *
 * 前台访客通过 Livewire 询盘表单直接写 DB，后台不允许新建（canCreate=false）。
 * 列表内联 SelectColumn 支持状态流转 unread→contacted→closed（D-10-15）。
 * 导航 Badge 显示未读询盘数，保护 PII（T-10-03-04）需授权角色。
 *
 * 列表与详情展示 A1 的来源与渠道归因（source / landing_url / referer / utm_*），
 * 来源中文名取 config('filamentboot-site.contact.sources')，未登记时回落原始 key。
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
                TextEntry::make('assignee.nickname')
                    ->label('跟进人')
                    ->placeholder('未分配'),
                TextEntry::make('created_at')
                    ->label('提交时间')
                    ->dateTime('Y-m-d H:i:s'),
            ]),

            // 跟进时间线（A4）：谁在什么时候做了什么，避免多人重复联系或集体漏掉
            Section::make('跟进记录')
                ->description('通过页面右上角「添加跟进备注」记录联系进展。')
                ->schema([
                    RepeatableEntry::make('notes')
                        ->hiddenLabel()
                        ->placeholder('暂无跟进记录')
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('时间')
                                ->dateTime('Y-m-d H:i'),
                            TextEntry::make('author.nickname')
                                ->label('记录人')
                                ->placeholder('已删除用户'),
                            TextEntry::make('body')
                                ->label('内容')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ]),

            // 来源与渠道归因（A1）：判断线索从哪个页面、哪个按钮、哪个投放渠道来
            Section::make('来源与渠道')
                ->description('由访客首次落地时采集，用于判断投放效果与转化入口质量。')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('source')
                            ->label('转化入口')
                            ->placeholder('-')
                            ->formatStateUsing(
                                fn (mixed $state, ContactMessage $record): string => $record->sourceLabel() ?? '-'
                            ),
                        TextEntry::make('utm_source')->label('渠道来源')->placeholder('-'),
                        TextEntry::make('utm_medium')->label('渠道媒介')->placeholder('-'),
                        TextEntry::make('utm_campaign')->label('推广活动')->placeholder('-'),
                        TextEntry::make('utm_term')->label('关键词')->placeholder('-'),
                        TextEntry::make('utm_content')->label('创意标识')->placeholder('-'),
                    ]),
                    TextEntry::make('landing_url')
                        ->label('首次落地页')
                        ->placeholder('-')
                        ->columnSpanFull(),
                    TextEntry::make('referer')
                        ->label('来源页')
                        ->placeholder('-')
                        ->columnSpanFull(),
                ]),
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
                TextColumn::make('source')
                    ->label('来源')
                    ->badge()
                    ->placeholder('-')
                    ->formatStateUsing(
                        fn (mixed $state, ContactMessage $record): string => $record->sourceLabel() ?? '-'
                    ),
                TextColumn::make('utm_source')
                    ->label('渠道')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                SelectColumn::make('status')
                    ->label('状态')
                    ->options(
                        collect(ContactMessageStatus::cases())
                            ->mapWithKeys(fn (ContactMessageStatus $s): array => [$s->value => $s->label()])
                            ->all()
                    ),
                SelectColumn::make('assigned_to')
                    ->label('跟进人')
                    ->options(fn (): array => AdminUser::query()
                        ->orderBy('id')
                        ->pluck('nickname', 'id')
                        ->all())
                    ->searchable()
                    ->placeholder('未分配'),
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
                SelectFilter::make('source')
                    ->label('来源')
                    ->options(fn (): array => ContactMessage::sourceFilterOptions()),
                SelectFilter::make('assigned_to')
                    ->label('跟进人')
                    ->relationship('assignee', 'nickname')
                    ->searchable()
                    ->preload(),
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
