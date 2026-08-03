<?php

namespace Filamentboot\FilamentbootSite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use SolutionForest\FilamentTree\Concern\ModelTree;

/**
 * 前台导航菜单项模型（#11，供 #17 菜单管理使用）
 *
 * 树形操作复用主包 Menu 已验证的 solution-forest/filament-tree ModelTree trait。
 * 三个列名与 trait 默认约定不同，必须显式覆盖：
 * - 排序列是 sort（默认 order）
 * - 标题列是 label（默认 title）
 * - 根节点 parent_id 为 0（默认由 Utils::defaultParentId() 决定）
 *
 * @property int $id
 * @property int $menu_id
 * @property int $parent_id
 * @property string $type
 * @property string $label
 * @property string|null $target
 * @property int $sort
 * @property bool $open_in_new
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property SiteMenu $menu
 */
class SiteMenuItem extends Model
{
    use ModelTree;

    /** @var string */
    protected $table = 'site_menu_items';

    /**
     * 可批量赋值的字段白名单
     *
     * @var list<string>
     */
    protected $fillable = [
        'menu_id',
        'parent_id',
        'type',
        'label',
        'target',
        'sort',
        'open_in_new',
    ];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'open_in_new' => 'boolean',
        ];
    }

    /**
     * 所属菜单
     *
     * @return BelongsTo<SiteMenu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(SiteMenu::class, 'menu_id');
    }

    /**
     * 排序列名（本表用 sort，与主包 Menu 一致）
     */
    public function determineOrderColumnName(): string
    {
        return 'sort';
    }

    /**
     * 标题列名（本表用 label 而非默认的 title）
     */
    public function determineTitleColumnName(): string
    {
        return 'label';
    }

    /**
     * 根节点的 parent_id
     *
     * 用 0 而非 null：filament-tree 以此判断根节点，
     * 也正因为 0 不可能是任何一行的主键，parent_id 才不能加外键约束。
     */
    public static function defaultParentKey(): int
    {
        return 0;
    }
}
