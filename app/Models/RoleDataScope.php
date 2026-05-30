<?php

namespace App\Models;

use App\Enums\DataScope;
use Database\Factories\RoleDataScopeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * 角色数据权限模型
 *
 * @property int $id
 * @property int $role_id
 * @property DataScope $scope
 * @property array<int, int>|null $department_ids
 */
class RoleDataScope extends Model
{
    /** @use HasFactory<RoleDataScopeFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope'          => DataScope::class,
            'department_ids' => 'array',
        ];
    }

    /**
     * 关联角色
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
