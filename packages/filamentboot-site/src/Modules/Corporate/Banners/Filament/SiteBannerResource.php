<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Filament;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerCtaAction;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerPosition;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Filament\SiteBannerResource\Pages\CreateSiteBanner;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Filament\SiteBannerResource\Pages\EditSiteBanner;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Filament\SiteBannerResource\Pages\ListSiteBanners;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Models\SiteBanner;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * 幻灯片后台资源
 *
 * 表单不分 Tab：字段只有三组（文案 / 按钮 / 投放），分 Tab 会让编辑为了确认
 * 一条幻灯片配全了而来回点。改用三个 Section 平铺。
 *
 * @extends \Filament\Resources\Resource<SiteBanner>
 */
class SiteBannerResource extends Resource
{
    /** @var class-string<SiteBanner> */
    protected static ?string $model = SiteBanner::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    /**
     * 排在设置页（1）之后、装修案例（10）之前
     *
     * 幻灯片是首页第一屏，改站的人最先要找的就是它。
     */
    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = '幻灯片';

    protected static ?string $pluralModelLabel = '幻灯片';

    /**
     * 表单定义（图片 + 文案 + 按钮 + 投放）
     */
    public static function form(Schema $schema): Schema
    {
        $defaultDisk = static::resolveDefaultDisk();

        return $schema->components([
            Section::make('图片与文案')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('cover_image')
                        ->label('幻灯片图片')
                        ->collection('cover')
                        ->disk($defaultDisk)
                        ->image()
                        ->imageEditor()
                        ->imageEditorAspectRatios(['16:9'])
                        ->helperText('建议不小于 1920×1080，比例 16:9。裁剪框内就是最终构图，系统只做等比缩放、不再自动裁切；小屏会用更高的容器裁掉两侧，主体请留在画面中间三分之一。'),
                    TextInput::make('title')
                        ->label('主标题')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('subtitle')
                        ->label('副标题')
                        ->maxLength(255),
                ]),

            Section::make('行动按钮')
                ->description('选「不显示按钮」时整行不渲染，不会在图上留一个点不动的按钮')
                ->schema([
                    Select::make('cta_action')
                        ->label('按钮行为')
                        ->options(
                            collect(BannerCtaAction::cases())
                                ->mapWithKeys(fn (BannerCtaAction $a): array => [$a->value => $a->label()])
                                ->all()
                        )
                        ->default(BannerCtaAction::NONE->value)
                        ->required()
                        ->live(),
                    TextInput::make('cta_label')
                        ->label('按钮文字')
                        ->placeholder('预约咨询')
                        ->maxLength(50)
                        ->required(fn (Get $get): bool => $get('cta_action') !== BannerCtaAction::NONE->value)
                        ->visible(fn (Get $get): bool => $get('cta_action') !== BannerCtaAction::NONE->value),
                    TextInput::make('cta_url')
                        ->label('按钮链接')
                        ->placeholder('/cases 或 https://…')
                        ->maxLength(255)
                        ->required(fn (Get $get): bool => $get('cta_action') === BannerCtaAction::LINK->value)
                        ->visible(fn (Get $get): bool => $get('cta_action') === BannerCtaAction::LINK->value),
                ]),

            Section::make('投放')
                ->schema([
                    Select::make('position')
                        ->label('投放位置')
                        ->options(
                            collect(BannerPosition::cases())
                                ->mapWithKeys(fn (BannerPosition $p): array => [$p->value => $p->label()])
                                ->all()
                        )
                        ->default(BannerPosition::HOME_TOP->value)
                        ->required(),
                    TextInput::make('slug')
                        ->label('标识（内部用，不出现在网址里）')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->rules(['alpha_dash'])
                        ->maxLength(255)
                        ->helperText('只用于演示种子幂等写入与后台识别，前台不暴露'),
                    TextInput::make('sort')
                        ->label('排序')
                        ->numeric()
                        ->default(0)
                        ->helperText('同一投放位置内数字越小越靠前，也可以在列表里直接拖'),
                    DateTimePicker::make('starts_at')
                        ->label('生效开始（留空=立即生效）'),
                    DateTimePicker::make('ends_at')
                        ->label('生效结束（留空=长期有效）'),
                    Toggle::make('is_enabled')
                        ->label('启用')
                        ->default(true),
                ]),
        ]);
    }

    /**
     * 列表表格定义
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover_image')
                    ->label('图片')
                    ->collection('cover')
                    ->conversion('thumb'),
                TextColumn::make('title')
                    ->label('主标题')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('position')
                    ->label('投放位置')
                    ->formatStateUsing(fn (BannerPosition $state): string => $state->label()),
                TextColumn::make('cta_action')
                    ->label('按钮')
                    ->formatStateUsing(fn (BannerCtaAction $state): string => $state->label()),
                IconColumn::make('is_enabled')
                    ->label('启用')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('starts_at')
                    ->label('生效开始')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('立即'),
                TextColumn::make('ends_at')
                    ->label('生效结束')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('长期'),
                TextColumn::make('sort')
                    ->label('排序')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('position')
                    ->label('投放位置')
                    ->options(
                        collect(BannerPosition::cases())
                            ->mapWithKeys(fn (BannerPosition $p): array => [$p->value => $p->label()])
                            ->all()
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->defaultSort('sort', 'asc')
            ->reorderable('sort');
    }

    /**
     * 覆盖 Eloquent 查询，去除软删除作用域使已删除记录在 TrashedFilter 下可见
     *
     * @return Builder<SiteBanner>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /**
     * 路由页面映射
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index'  => ListSiteBanners::route('/'),
            'create' => CreateSiteBanner::route('/create'),
            'edit'   => EditSiteBanner::route('/{record}/edit'),
        ];
    }

    /**
     * 解析默认上传磁盘（SITE-04 跨切：读 UploadSettings，降级 'public'）
     */
    protected static function resolveDefaultDisk(): string
    {
        try {
            return app(UploadSettings::class)->default_disk;
        } catch (\Throwable) {
            return 'public';
        }
    }
}
