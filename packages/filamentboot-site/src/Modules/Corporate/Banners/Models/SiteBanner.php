<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Models;

use Filamentboot\FilamentbootSite\Concerns\HasCoverImage;
use Filamentboot\FilamentbootSite\Database\Factories\SiteBannerFactory;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerCtaAction;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * 幻灯片模型
 *
 * 按投放位置（BannerPosition）分组、按 sort 升序展示，带生效时间窗与启用开关。
 * 图片走媒体库 'cover' 单文件集合，前台读 hero 转换。
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $cta_label
 * @property string|null $cta_url
 * @property BannerCtaAction $cta_action
 * @property BannerPosition $position
 * @property int $sort
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SiteBanner extends Model implements HasMedia
{
    use HasCoverImage;

    /** @use HasFactory<SiteBannerFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): SiteBannerFactory
    {
        return SiteBannerFactory::new();
    }

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position'   => BannerPosition::class,
            'cta_action' => BannerCtaAction::class,
            'starts_at'  => 'datetime',
            'ends_at'    => 'datetime',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * 注册媒体库集合
     *
     * cover：单文件主图。幻灯片没有图集需求——一条记录就是一张幻灯片，
     * 多张图靠多条记录 + sort 表达，这样每张图才能各自带文案与按钮。
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile();
    }

    /**
     * 注册媒体转换尺寸（thumb/hero）
     *
     * **刻意不调 `registerCoverConversions()`**
     * -------------------------------------
     * 那套是给内容卡片准备的，最大一档 card 只到 800×800（`Fit::Max`，不放大）。
     * 幻灯片是全屏主视觉，1440 逻辑宽的屏上按 2x DPR 要 2880 物理像素，拿 800
     * 的图去铺会被浏览器放大成糊图。而 `Fit::Max` 不会替你放大文件，只会让
     * 一张 800 宽的图在全屏容器里被拉开——症状是"图存在但很脏"，不报错。
     *
     * 同时那套还会生成一个 og（1200×630）转换，幻灯片不进社交卡片，纯属白占磁盘。
     *
     * 所以这里自己注册两档：thumb 给后台表格，hero 给前台。读取仍走
     * `HasCoverImage::coverUrl('hero')`——那个 trait 只提供读入口，
     * 不强制使用它的转换清单。
     *
     * 1920×1080 用 `Fit::Max`（等比缩到框内、不放大、不裁切）而非 `Fit::Crop`：
     * 上传侧已经用 `imageEditorAspectRatios(['16:9'])` 把构图交给编辑决定，
     * 服务端再裁一刀就等于把 B2 修掉的双重裁切重新引回来。
     *
     * nonQueued()：官网内容量小，同步生成避免未跑队列时前台长期无图。
     *
     * @param  Media|null  $media  触发转换的媒体实例（Spatie 回调签名要求）
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // 链式顺序：Conversion 自有方法在前、图像操作在后（理由见 HasCoverImage）
        $this->addMediaConversion('thumb')
            ->performOnCollections('cover')
            ->nonQueued()
            ->fit(Fit::Max, 400, 400);

        $this->addMediaConversion('hero')
            ->performOnCollections('cover')
            ->nonQueued()
            ->fit(Fit::Max, 1920, 1080);
    }

    /**
     * 作用域：仅返回当前生效的幻灯片
     *
     * 生效 = 已启用 且 落在时间窗内。时间窗两端各自可空：starts_at 空表示
     * 立即生效、ends_at 空表示长期有效，所以两个条件都要带上"或为 null"，
     * 否则不填时间窗的幻灯片会一条都取不到。
     *
     * @param  Builder<SiteBanner>  $query
     * @return Builder<SiteBanner>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_enabled', true)
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>=', now()));
    }

    /**
     * 作用域：按投放位置筛选
     *
     * @param  Builder<SiteBanner>  $query
     * @return Builder<SiteBanner>
     */
    public function scopeForPosition(Builder $query, BannerPosition $position): Builder
    {
        return $query->where('position', $position->value);
    }

    /**
     * 是否要渲染行动按钮
     *
     * 视图据此决定是否输出整个按钮行：cta_action 为 NONE、没填按钮文字、
     * 或选了跳转却没填链接，三种情况都不该留一个点不动的按钮在图上。
     */
    public function hasCallToAction(): bool
    {
        if ($this->cta_action === BannerCtaAction::NONE || blank($this->cta_label)) {
            return false;
        }

        return $this->cta_action !== BannerCtaAction::LINK || filled($this->cta_url);
    }
}
