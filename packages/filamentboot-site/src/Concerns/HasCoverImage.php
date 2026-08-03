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
     * thumb：后台表格缩略图；card：前台列表卡片；og：社交分享图（1200x630 是
     * Open Graph 通行比例）。此前 Resource 表格已引用 ->conversion('thumb')
     * 但模型从未注册任何转换，缩略图 URL 指向不存在的文件。
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
            ->fit(Fit::Crop, 400, 300);

        $this->addMediaConversion('card')
            ->performOnCollections('cover', 'gallery')
            ->nonQueued()
            ->fit(Fit::Crop, 800, 600);

        $this->addMediaConversion('og')
            ->performOnCollections('cover')
            ->nonQueued()
            ->fit(Fit::Crop, 1200, 630);
    }
}
