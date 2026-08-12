<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Models;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Revisions\HasRevisions;
use Filamentboot\FilamentbootSite\Cms\Revisions\Revisionable;
use Filamentboot\FilamentbootSite\Concerns\HasCoverImage;
use Filamentboot\FilamentbootSite\Database\Factories\SitePackageFactory;
use Filamentboot\FilamentbootSite\Models\SiteTag;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\HouseLayout;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\PackageTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * 全屋智能套餐内容模型
 *
 * 与「智能方案」的分工：**方案讲「这类需求怎么解决」，套餐讲「我家这个户型做下来
 * 是什么配置、多少钱」**。装修者真正在找的是后者——按户型 × 档位组织、能横向比、
 * 包含清单摊开给你看。方案页上的 `price_range` 撑不起这件事：它没有户型维度、
 * 没有档位、也没有可枚举的包含项。
 *
 * 支持软删除、媒体库（cover）、发布 scope、置顶 scope，关联多态标签（MorphToMany）。
 *
 * ## `items` 是包里第一个重复结构字段
 *
 * 六类内容此前的字段清一色 string / text / richtext，`items` 是第一个 JSON 数组
 * （后台用 Filament `Repeater` 编辑）。这条要记进七期「可配置内容类型」的账：
 * 「一组带列的清单」不该每个模块自己造一次。
 *
 * ⚠️ **`items` 里的内容站内搜索搜不到。** 与 `site_pages.blocks` 同一个原因
 * （见 `Cms\Services\SiteSearch` 的类注释）：JSON 列里的非 ASCII 被存成 `\uXXXX`
 * 转义序列，`LIKE '%中文%'` 永远命不中。所以套餐的检索靠标题 / 简介 / 正文，
 * **别把只出现在清单里的关键信息当成能被搜到的内容**。
 *
 * @property int $id
 * @property string $title_zh
 * @property string $slug
 * @property string|null $description_zh
 * @property string|null $content_zh
 * @property HouseLayout|null $house_layout
 * @property PackageTier|null $tier
 * @property string|null $area_range
 * @property string|null $price
 * @property string|null $price_note
 * @property list<array{name: string, quantity: string|null, purpose: string|null, location: string|null}>|null $items
 * @property string|null $excludes
 * @property string|null $duration
 * @property string|null $warranty
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_keywords
 * @property bool $is_featured
 * @property int $sort
 * @property PageStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SitePackage extends Model implements HasMedia, Revisionable
{
    use HasCoverImage;

    /** @use HasFactory<SitePackageFactory> */
    use HasFactory;

    use HasRevisions;
    use InteractsWithMedia;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): SitePackageFactory
    {
        return SitePackageFactory::new();
    }

    /**
     * 属性类型转换
     *
     * `price` **不转 float**：decimal 走浮点会在「9999.00 显示成 9999.0000000001」
     * 这类地方翻车，前台也只需要原样展示。取值仍是字符串，格式化交给视图。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured'  => 'boolean',
            'status'       => PageStatus::class,
            'house_layout' => HouseLayout::class,
            'tier'         => PackageTier::class,
            'items'        => 'array',
        ];
    }

    /**
     * 注册媒体库集合
     *
     * cover：单文件封面图。
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile();
    }

    /**
     * 注册媒体转换尺寸（thumb/card/og）
     *
     * @param  Media|null  $media  触发转换的媒体实例（Spatie 回调签名要求）
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerCoverConversions();
    }

    /**
     * 关联标签（多态正向关系，与案例/方案/产品共用 site_taggables）
     *
     * @return MorphToMany<SiteTag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(SiteTag::class, 'taggable', 'site_taggables', 'taggable_id', 'tag_id');
    }

    /**
     * 进入快照的字段（批次 1.5c）
     *
     * @return list<string>
     */
    public static function revisionTrackedFields(): array
    {
        return [
            'title_zh',
            'slug',
            'description_zh',
            'content_zh',
            'house_layout',
            'tier',
            'area_range',
            'price',
            'price_note',
            'items',
            'excludes',
            'duration',
            'warranty',
            'seo_title',
            'seo_description',
            'seo_keywords',
            'status',
            'published_at',
        ];
    }

    /**
     * 回滚时会被恢复的字段（批次 1.5c）
     *
     * @return list<string>
     */
    public static function revisionRestorableFields(): array
    {
        return [
            'title_zh',
            'slug',
            'description_zh',
            'content_zh',
            'house_layout',
            'tier',
            'area_range',
            'price',
            'price_note',
            'items',
            'excludes',
            'duration',
            'warranty',
            'seo_title',
            'seo_description',
            'seo_keywords',
        ];
    }

    /**
     * 字段名 → 中文标签（批次 1.5c）
     *
     * @return array<string, string>
     */
    public static function revisionFieldLabels(): array
    {
        return [
            'title_zh'        => '标题',
            'slug'            => 'URL Slug',
            'description_zh'  => '简介',
            'content_zh'      => '正文',
            'house_layout'    => '户型',
            'tier'            => '档位',
            'area_range'      => '面积区间',
            'price'           => '价格',
            'price_note'      => '价格备注',
            'items'           => '包含清单',
            'excludes'        => '不包含说明',
            'duration'        => '施工周期',
            'warranty'        => '质保说明',
            'seo_title'       => 'SEO 标题',
            'seo_description' => 'SEO 描述',
            'seo_keywords'    => 'SEO 关键词',
            'status'          => '发布状态',
            'published_at'    => '发布时间',
        ];
    }

    /**
     * 作用域：仅返回已发布内容
     *
     * @param  Builder<SitePackage>  $query
     * @return Builder<SitePackage>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PageStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * 作用域：仅返回置顶/精选内容
     *
     * @param  Builder<SitePackage>  $query
     * @return Builder<SitePackage>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * 作用域：按「户型从小到大、档位从低到高」排
     *
     * 套餐列表是拿来横向比的，按发布时间倒序会让同户型的三档散在不同位置。
     * 户型顺序取 `HouseLayout` 的声明顺序，档位取 `PackageTier::weight()`——
     * 两处都不靠 `sort` 字段人工维护，新增一条不用回头调所有人的排序值。
     *
     * 用 `FIELD()` 而不是 join 一张顺序表：MySQL / MariaDB 都支持，
     * 而这张表的量级（几十条）根本谈不上索引失效的代价。
     *
     * @param  Builder<SitePackage>  $query
     * @return Builder<SitePackage>
     */
    public function scopeOrderedForCompare(Builder $query): Builder
    {
        return $query
            ->orderByRaw($this->fieldOrderExpression('house_layout', array_column(HouseLayout::cases(), 'value')))
            ->orderByRaw($this->fieldOrderExpression('tier', array_column(PackageTier::ordered(), 'value')))
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * 拼一条 `FIELD(col, 'a', 'b', ...)` 排序表达式
     *
     * 值全部来自枚举、不来自请求，但仍逐个走 quote：这段会原样进 SQL，
     * 日后有人把它改成吃外部参数时，转义已经在这儿了。
     *
     * @param  list<string>  $values
     */
    protected function fieldOrderExpression(string $column, array $values): string
    {
        $pdo = $this->getConnection()->getPdo();

        $quoted = array_map(static fn (string $value): string => $pdo->quote($value), $values);

        return 'FIELD('.$column.', '.implode(', ', $quoted).')';
    }

    /**
     * 归一后的包含清单
     *
     * 直接把 `items` 交给视图不安全也不好用：后台 Repeater 可能留下只填了一半的行，
     * 库里也可能躺着结构不对的存量数据。这里统一成「四个键都在、都是字符串」，
     * 名称为空的整行丢掉——一行没有名称的清单项在页面上就是一条空白格。
     *
     * @return list<array{name: string, quantity: string, purpose: string, location: string}>
     */
    public function normalizedItems(): array
    {
        $items = is_array($this->items) ? $this->items : [];

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $normalized[] = [
                'name'     => $name,
                'quantity' => trim((string) ($item['quantity'] ?? '')),
                'purpose'  => trim((string) ($item['purpose'] ?? '')),
                'location' => trim((string) ($item['location'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * 清单里真正有内容的可选列
     *
     * 名称是必填、永远显示；数量 / 用途 / 摆放位置三列**只在至少有一行填了它的时候
     * 才渲染**。
     *
     * 为什么要这一层：套餐的来源不一定给得齐。店铺的套餐主图只标了设备名，
     * 数量和点位要按实际户型定——那种情况下渲染三列「—」，看起来像数据坏了，
     * 而实际上是「这一项本来就因家而异」。整列不出现比整列占位诚实，也好看。
     *
     * ⚠️ 这不是「隐藏空数据」的通用做法：**名称为空的行是直接丢掉的**
     * （见 normalizedItems）。这里处理的是「这一列对本套餐不适用」，
     * 不是「这一格忘了填」。
     *
     * @return list<string> 'quantity' / 'purpose' / 'location' 的子集，按表格顺序
     */
    public function itemColumns(): array
    {
        $columns = [];

        foreach (['quantity', 'purpose', 'location'] as $column) {
            foreach ($this->normalizedItems() as $item) {
                if ($item[$column] !== '') {
                    $columns[] = $column;

                    break;
                }
            }
        }

        return $columns;
    }
}
