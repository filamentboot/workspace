<?php

namespace Filamentboot\FilamentbootSite\Cms\Models;

use Filamentboot\FilamentbootSite\Cms\Services\MenuResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 前台导航菜单模型（#11，供 #17 菜单管理使用）
 *
 * 注意与 Database\Seeders\SiteMenuSeeder 区分：那个 Seeder 管的是**后台侧边栏**
 * 菜单（写主包 Menu 模型 / menus 表），本模型是**前台导航**菜单（nav / footer）。
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SiteMenu extends Model
{
    /** @var string */
    protected $table = 'site_menus';

    /**
     * 可批量赋值的字段白名单
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
    ];

    /**
     * 模型事件注册
     *
     * 菜单缓存是 rememberForever（菜单每页都读，不缓存等于全站每请求多两条查询），
     * 因此必须在写入侧主动失效，否则「改了菜单前台不变」。
     *
     * key 被改名时旧键的缓存也要清：不清就会留一条永不过期的孤儿缓存，
     * 万一日后又建了同名菜单，读到的是几个月前的旧结构。
     */
    protected static function booted(): void
    {
        static::saved(function (self $menu): void {
            MenuResolver::forget($menu->key);

            $originalKey = $menu->getOriginal('key');

            if (is_string($originalKey) && $originalKey !== $menu->key) {
                MenuResolver::forget($originalKey);
            }
        });

        static::deleted(function (self $menu): void {
            MenuResolver::forget($menu->key);
        });
    }

    /**
     * 全部菜单项（含各层级，树形组装由 ModelTree 负责）
     *
     * @return HasMany<SiteMenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SiteMenuItem::class, 'menu_id')->orderBy('sort');
    }

    /**
     * 根级菜单项
     *
     * @return HasMany<SiteMenuItem, $this>
     */
    public function rootItems(): HasMany
    {
        return $this->items()->where('parent_id', SiteMenuItem::defaultParentKey());
    }
}
