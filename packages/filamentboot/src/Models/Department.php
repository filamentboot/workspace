<?php

namespace FilamentAdmin\Models;

use FilamentAdmin\Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 部门模型
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $code
 * @property int|null $leader_admin_user_id
 * @property int $sort
 * @property bool $is_active
 */
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
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
     * 上级部门
     *
     * @return BelongsTo<Department, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 下级部门
     *
     * @return HasMany<Department, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    /**
     * 部门负责人
     *
     * @return BelongsTo<AdminUser, $this>
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'leader_admin_user_id');
    }

    /**
     * 部门下管理员
     *
     * @return HasMany<AdminUser, $this>
     */
    public function admins(): HasMany
    {
        return $this->hasMany(AdminUser::class, 'department_id');
    }

    /**
     * 启用部门作用域
     *
     * @param  Builder<Department>  $query
     * @return Builder<Department>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
