<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 插件运行时状态模型
 *
 * 存储已扫描/已安装插件的元数据与状态。
 * 写入仅经 plugin:scan / PluginManager（服务层），本模型仅提供数据契约。
 *
 * @property int                      $id
 * @property string                   $package_name    vendor/package 格式
 * @property string                   $slug
 * @property string                   $name
 * @property string                   $kind            package | solution_plugin
 * @property string                   $source          official_trusted | official_listed | community
 * @property string|null              $plugin_class
 * @property string|null              $installed_version
 * @property string|null              $description
 * @property array<string, mixed>     $requires
 * @property array<string, mixed>     $compatibility
 * @property array<string, mixed>     $config_overrides
 * @property bool                     $is_enabled
 * @property string                   $init_status     pending | running | done | failed
 * @property string|null              $init_log
 * @property \Carbon\Carbon|null      $installed_at
 * @property \Carbon\Carbon           $created_at
 * @property \Carbon\Carbon           $updated_at
 * @property \Carbon\Carbon|null      $deleted_at
 */
class Plugin extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'package_name',
        'slug',
        'name',
        'kind',
        'source',
        'plugin_class',
        'installed_version',
        'description',
        'requires',
        'compatibility',
        'config_overrides',
        'is_enabled',
        'init_status',
        'init_log',
        'installed_at',
    ];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires'         => 'array',
            'compatibility'    => 'array',
            'config_overrides' => 'array',
            'is_enabled'       => 'boolean',
            'installed_at'     => 'datetime',
        ];
    }

    /**
     * 查询已启用的插件
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
