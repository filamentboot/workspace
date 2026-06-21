<?php

namespace Filamentboot\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 插件运行时状态模型
 *
 * 存储已扫描/已安装插件的元数据与启用状态。
 * 写入仅经 plugin:scan / PluginManager，本模型仅提供数据契约。
 *
 * @property int $id
 * @property string $package_name vendor/package 格式
 * @property string $slug
 * @property string $name
 * @property string $kind package | solution_plugin
 * @property string $source official_trusted | official_listed | community
 * @property string|null $plugin_class Filament Plugin 接口实现类名
 * @property string|null $settings_page_slug Filament settings 页 slug
 * @property string|null $service_provider ServiceProvider 子类名（供 vendor:publish 使用）
 * @property string|null $installed_version
 * @property string|null $description
 * @property array<string, mixed>|null $post_install_data extra.filament-admin.post_install 声明块
 * @property string $compatibility_status compatible | incompatible | unknown（由 plugin:scan 写入，CR-04）
 * @property bool $is_enabled
 * @property string $init_status pending | running | done | failed
 * @property string|null $init_log
 * @property Carbon|null $installed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
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
        'settings_page_slug',
        'service_provider',
        'installed_version',
        'description',
        'post_install_data',
        'compatibility_status',
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
            'post_install_data' => 'array',
            'is_enabled'        => 'boolean',
            'installed_at'      => 'datetime',
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
