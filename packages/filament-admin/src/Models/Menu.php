<?php

namespace FilamentAdmin\Models;

use FilamentAdmin\Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use SolutionForest\FilamentTree\Concern\ModelTree;

/**
 * 后台菜单规则模型
 *
 * @property int $id
 * @property int $parent_id
 * @property string $title
 * @property string|null $icon
 * @property string|null $route_name
 * @property string|null $url
 * @property string|null $permission_name
 * @property int $sort
 * @property bool $is_active
 * @property string $target
 * @property string $source
 * @property string $type
 * @property Menu|null $parent
 * @property Collection<int, Menu> $children
 */
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    use ModelTree;
    use SoftDeletes;

    protected $guarded = [];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    /**
     * 上级菜单
     *
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 排序字段名（覆盖包默认的 order）
     */
    public function determineOrderColumnName(): string
    {
        return 'sort';
    }

    /**
     * 根节点的 parent_id 标识值（UNSIGNED BIGINT 不能存 -1，用 0 代替）
     */
    public static function defaultParentKey(): int
    {
        return 0;
    }

    /**
     * 启用菜单作用域
     *
     * @param  Builder<Menu>  $query
     * @return Builder<Menu>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
