<?php

namespace Filamentboot\FilamentbootSite\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * 封面图与图集读取能力
 *
 * 后台通过 SpatieMediaLibraryFileUpload 把封面写入 'cover' 集合、图集写入
 * 'gallery' 集合，但前台此前读的是并不存在的 $record->cover_image 属性，
 * 导致上传了真实图片也永远取不到、只能落到外部占位图。本 trait 提供统一的
 * 读取入口，前台视图一律经由这里取图。
 *
 * 使用方需 implements HasMedia 并 use InteractsWithMedia。
 *
 * @phpstan-require-implements HasMedia
 */
trait HasCoverImage
{
    /**
     * 封面图 URL
     *
     * @param  string|null  $conversion  转换名（thumb/card/og），null 取原图
     * @return string|null 无封面时返回 null，由视图决定渲染占位图还是留空
     */
    public function coverUrl(?string $conversion = null): ?string
    {
        return $this->mediaUrl('cover', $conversion);
    }

    /**
     * 图集 URL 列表
     *
     * @param  string|null  $conversion  转换名（thumb/card/og），null 取原图
     * @return list<string>
     */
    public function galleryUrls(?string $conversion = null): array
    {
        return $this->getMedia('gallery')
            ->map(fn (Media $media): string => $conversion !== null && $media->hasGeneratedConversion($conversion)
                ? $media->getUrl($conversion)
                : $media->getUrl())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Open Graph 图片 URL
     *
     * 取封面的 og 转换（1200x630），无封面时返回 null，
     * 由调用方回退到站点全局默认 OG 图。
     */
    public function ogImageUrl(): ?string
    {
        return $this->mediaUrl('cover', 'og');
    }

    /**
     * 是否已配置封面图
     */
    public function hasCoverImage(): bool
    {
        return $this->getFirstMedia('cover') !== null;
    }

    /**
     * 读取指定集合首个媒体的 URL
     *
     * 转换尚未生成时回退到原图 URL，避免指向不存在的转换文件（线上表现为图片 404）。
     */
    protected function mediaUrl(string $collection, ?string $conversion): ?string
    {
        $media = $this->getFirstMedia($collection);

        if ($media === null) {
            return null;
        }

        if ($conversion !== null && $media->hasGeneratedConversion($conversion)) {
            return $media->getUrl($conversion);
        }

        $url = $media->getUrl();

        return $url !== '' ? $url : null;
    }

    /**
     * 注册封面图转换尺寸
     *
     * thumb：后台表格缩略图；card：前台列表卡片与详情主图；og：社交分享图。
     * 此前 Resource 表格已引用 ->conversion('thumb') 但模型从未注册任何转换，
     * 缩略图 URL 指向不存在的文件。
     *
     * thumb / card 用 Fit::Max 的**正方形框**，不是固定比例
     * ------------------------------------------------------
     * 原先两档都是 Fit::Crop 到 4:3（400x300 / 800x600），而前台三类卡片容器
     * 比例各不相同且都带 object-cover：产品 aspect-square、案例与方案
     * aspect-[4/3]、资讯 aspect-[16/9]。于是一张产品图会被裁两次——服务端先
     * 硬裁成 4:3 切掉两侧，浏览器再按正方容器切掉上下，上传时框好的构图两道
     * 都保不住。
     *
     * Fit::Max 的语义是 PreserveAspectRatio + DoNotUpsize 且不改画布
     * （Spatie\Image\Enums\Fit::calculateSize()），传正方形框等于「最长边不
     * 超过 N，比例原样保留，小图不放大」。各 Resource 的
     * imageEditorAspectRatios() 已把上传比例对齐到各自的卡片容器，所以前台的
     * object-cover 退化成空操作，用户框的构图就是最终构图。
     *
     * 800 这一档同时要喂产品详情页的主图轮播（aspect-square，视图里已经写着
     * width="800" height="800"），所以框取 800x800 而不是 800x600。
     *
     * og 是刻意的例外，保持 Fit::Crop
     * -------------------------------
     * Open Graph 要求固定 1.91:1，任何单一上传比例都喂不了它，裁切无法回避。
     * 另外它不只用于社交卡片：案例 / 方案 / 资讯三个详情页的顶部大图也读
     * coverUrl('og')，容器是 16/9 + object-cover，与 1.91:1 只差一点侧边裁切。
     * **别把这条也改成 Max**，那会让详情页大图退回原始比例、在 16/9 容器里
     * 被二次裁切。
     *
     * nonQueued()：官网内容量小，同步生成避免未跑队列时前台长期无图。
     *
     * 不直接命名为 registerMediaConversions()：该名称与 InteractsWithMedia
     * 的同名方法冲突，PHP 会拒绝应用 trait。改由各模型在自己的
     * registerMediaConversions() 中显式调用本方法。
     */
    public function registerCoverConversions(): void
    {
        // 链式顺序：Conversion 自有方法在前、图像操作在后。
        // Conversion 的图像操作经 __call 转发（运行时仍返回 Conversion），
        // 但类上的 @mixin ImageDriver 注解会让静态分析把 ->fit() 的返回推断成
        // ImageDriver，导致后续 ->performOnCollections() 被判为未定义方法。
        $this->addMediaConversion('thumb')
            ->performOnCollections('cover', 'gallery')
            ->nonQueued()
            ->fit(Fit::Max, 400, 400);

        $this->addMediaConversion('card')
            ->performOnCollections('cover', 'gallery')
            ->nonQueued()
            ->fit(Fit::Max, 800, 800);

        $this->addMediaConversion('og')
            ->performOnCollections('cover')
            ->nonQueued()
            ->fit(Fit::Crop, 1200, 630);
    }
}
