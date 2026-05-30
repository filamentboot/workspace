<?php

namespace App\Models;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 后台菜单规则模型
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $icon
 * @property string|null $route_name
 * @property string|null $url
 * @property string|null $permission_name
 * @property int $sort
 * @property bool $is_active
 * @property string $target
 * @property string $source
 */
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

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
     * 下级菜单
     *
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
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
