<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models;

use Filamentboot\FilamentbootSite\Database\Factories\SiteRegionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * 行政区划模型
 *
 * 参考数据，不是内容：没有软删除、没有发布状态、没有媒体库。
 * 由 `filamentboot-site:import-regions` 从宿主给的 JSON 导入。
 *
 * 三级：1 省级 / 2 地级 / 3 县级。**县级的 `slug` 是 NULL**——它不建页、
 * 没有 URL，导进来只为给城市页渲染「下辖区县」。
 *
 * ⚠️ 关系全部走 `code` / `parent_code` 这对字符串，**不是自增主键**。
 * `id` 只是 Eloquent 用着方便，业务上没有任何地方引用它——
 * 区划代码才是稳定标识，重新导入时 `id` 可能变、`code` 不会。
 *
 * @property int $id
 * @property string $code
 * @property string $parent_code
 * @property int $level
 * @property string $name
 * @property string|null $short_name
 * @property string|null $slug
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SiteRegion extends Model
{
    /** @use HasFactory<SiteRegionFactory> */
    use HasFactory;

    /** 省级 */
    public const LEVEL_PROVINCE = 1;

    /** 地级 */
    public const LEVEL_CITY = 2;

    /** 县级 */
    public const LEVEL_COUNTY = 3;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): SiteRegionFactory
    {
        return SiteRegionFactory::new();
    }

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'sort'  => 'integer',
        ];
    }

    /**
     * 展示用名称：有简称用简称
     *
     * 页面标题、面包屑、卡片都用它。「武汉全屋智能」比「武汉市全屋智能」自然，
     * 而县级没有简称，列表里就该显示「东城区」这样的全名。
     */
    public function displayName(): string
    {
        return ($this->short_name ?? '') !== '' ? (string) $this->short_name : $this->name;
    }

    /**
     * schema.org 的地理类型
     *
     * 地级里有三十多个不是「市」——自治州、地区、盟。把它们一律标成 `City`
     * 是不准确的，`AdministrativeArea` 才是 schema.org 给这类单位准备的类型。
     * 结构化数据宁可粗一点，也不要说一件不成立的事。
     */
    public function schemaAreaType(): string
    {
        return str_ends_with($this->name, '市') ? 'City' : 'AdministrativeArea';
    }

    /**
     * 上级区划
     *
     * @return HasOne<self, $this>
     */
    public function parent(): HasOne
    {
        return $this->hasOne(self::class, 'code', 'parent_code');
    }

    /**
     * 下级区划
     *
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_code', 'code')->orderBy('sort')->orderBy('code');
    }

    /**
     * 对应的城市页（可能没有）
     *
     * @return HasOne<SiteCityPage, $this>
     */
    public function cityPage(): HasOne
    {
        return $this->hasOne(SiteCityPage::class, 'region_code', 'code');
    }

    /**
     * 作用域：按层级筛
     *
     * @param  Builder<SiteRegion>  $query
     * @return Builder<SiteRegion>
     */
    public function scopeLevel(Builder $query, int $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * 作用域：官方顺序（区划代码序）
     *
     * `sort` 留给人工插队，默认全是 0，实际排序落在 code 上——
     * 那正是「京津冀 → 晋蒙 → 东北 → 华东…」的标准排法，不用另建一张顺序表。
     *
     * @param  Builder<SiteRegion>  $query
     * @return Builder<SiteRegion>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('code');
    }
}
