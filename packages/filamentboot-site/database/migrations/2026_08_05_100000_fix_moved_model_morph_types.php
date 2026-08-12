<?php

use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 修复模型迁进 Modules/ 后残留的多态类名
 *
 * 四个内容模型早先从 Filamentboot\FilamentbootSite\Models\ 搬进了 Modules/ 下的
 * 模块目录，但**已入库的多态外键没跟着改**。存量行里的 model_type / taggable_type
 * 还指向搬迁前的类名，那些类现在一个都不存在。
 *
 * 后果全是静默的，不报错也不告警：
 *
 * 1. `$record->getMedia('cover')` 按 model_type = 当前类名 查询，对不上就返回空集合
 *    —— 封面图上传过、文件还在磁盘上，前台却一路回落到占位图
 * 2. `$record->tags` 同理，标签关联整片消失
 * 3. `media-library:regenerate` 解析不出模型类，**静默跳过**这些记录。所以改了
 *    转换定义之后重生成，看起来跑完了（进度条走满），受影响的图其实还是旧转换
 *
 * 本项目实测：6 个案例 + 4 个方案的封面取不到，site_taggables 35 行里 20 行断链；
 * 资讯那批用的是搬迁后的类名，所以一直正常 —— 这也是它长期没被发现的原因。
 *
 * 只处理这两张表：这四个模型只参与 media（morphMany）与 site_taggables
 * （morphToMany）两个多态关系，分类表走 BelongsTo、不涉及多态。
 */
return new class extends Migration
{
    /**
     * 搬迁前后的类名映射
     *
     * 键是已不存在的旧类名（只能写字面量），值取当前类的 ::class 常量，
     * 将来若再搬一次目录，静态分析能跟着改。
     *
     * @return array<string, class-string>
     */
    private function classMap(): array
    {
        return [
            'Filamentboot\FilamentbootSite\Models\SiteCase'     => SiteCase::class,
            'Filamentboot\FilamentbootSite\Models\SiteSolution' => SiteSolution::class,
            'Filamentboot\FilamentbootSite\Models\SiteProduct'  => SiteProduct::class,
            'Filamentboot\FilamentbootSite\Models\NewsArticle'  => NewsArticle::class,
        ];
    }

    /**
     * 把存量多态类名改写成搬迁后的类名
     *
     * 逐个 update，命不中就是 0 行受影响——下游若从未持有旧数据，整个迁移是空操作。
     */
    public function up(): void
    {
        $targets = [
            'media'          => 'model_type',
            'site_taggables' => 'taggable_type',
        ];

        foreach ($targets as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach ($this->classMap() as $old => $new) {
                DB::table($table)
                    ->where($column, $old)
                    ->update([$column => $new]);
            }
        }
    }

    /**
     * 不回滚
     *
     * 反向改写会把类名还原成**任何持有本迁移的包版本里都不存在**的类，等于主动
     * 把封面图与标签重新弄断。类的搬迁发生在代码里、早于本迁移，回滚这一条并不会
     * 把类搬回去，所以这里没有「正确的旧状态」可还原。
     */
    public function down(): void
    {
        // 有意留空，理由见上
    }
};
