<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 清掉双语时代的 *_en 死列与 site_cases.gallery
 *
 * CMS v1 只维护中文内容流：后台表单没有任何一个英文输入框，前台视图从不读
 * 任何 `_en` 字段，站内搜索的可检索列表里也没有它们。这 21 列自建表起就只被
 * 演示 seeder 与 factory 写过——它们躺在那里唯一的作用是让每个打开表结构的人
 * 重新判断一次「这是不是还在用」。
 *
 * `site_cases.gallery` 是另一种死法：它与 Spatie Media Library 的 `gallery`
 * 集合**同名同义**，而前台图集走的是 `galleryUrls()`（读 media 表）。后台那个
 * SpatieMediaLibraryFileUpload 字段虽然也叫 gallery，但组件自身是
 * `dehydrated(false)` 的，从来不会把值写进这一列——所以它建表至今全表为 NULL。
 * 留着的风险不是占空间，是**下一个人看到同名字段会以为图集存在这里**。
 *
 * ## 三处必须同步改的代码，漏一处就是运行期报错
 *
 * 1. `SitePageObserver::TRACKED` / `RESTORABLE` —— 前者决定快照写哪些字段，
 *    后者决定回滚恢复哪些字段。**RESTORABLE 是承重的**：回滚遍历的是这个常量
 *    而不是 payload 的键，留着 `title_en` 就会 `update(['title_en' => ...])`
 *    打一个不存在的列，回滚任意一条历史快照当场 500。
 * 2. `RevisionsRelationManager::FIELD_LABELS` —— 对比表遍历它，留着只会在
 *    版本对比里显示一个已经不存在的字段。
 * 3. seeder 与 factory 里的 `_en` 键 —— 批量赋值打不存在的列会直接抛 SQL 异常。
 *
 * ## 历史快照的 payload 有意不动
 *
 * `site_page_revisions.payload` 里的旧 JSON 仍带着 `title_en` / `content_en` 键。
 * 不改写它们：快照是审计链路，SitePageRevision 只有 created_at、连 updated_at
 * 都没有，「能改就不成其为历史」。去掉上面两个常量之后这些键自然成为惰性数据，
 * 既不会被回滚写回、也不会在对比表里露面。
 */
return new class extends Migration
{
    /**
     * 待删列：表名 => 列名清单
     *
     * @return array<string, list<string>>
     */
    protected function targets(): array
    {
        return [
            'site_case_categories'    => ['name_en'],
            'site_cases'              => ['title_en', 'description_en', 'content_en', 'gallery'],
            'site_news_articles'      => ['title_en', 'excerpt_en', 'content_en'],
            'site_news_categories'    => ['name_en'],
            'site_packages'           => ['title_en', 'description_en', 'content_en'],
            'site_pages'              => ['title_en', 'content_en'],
            'site_product_categories' => ['name_en'],
            'site_products'           => ['title_en', 'description_en', 'content_en'],
            'site_solutions'          => ['title_en', 'description_en', 'content_en'],
            'site_tags'               => ['name_en'],
        ];
    }

    /**
     * 逐表删列
     *
     * 表与列都先判存在：本包的表分几期陆续建起来（site_packages 是第三期才有的），
     * 下游按不同版本装过的库不一定每张表都在。命不中就跳过，不报错。
     */
    public function up(): void
    {
        foreach ($this->targets() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn($table, $column)
            ));

            if ($existing === []) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($existing): void {
                $blueprint->dropColumn($existing);
            });
        }
    }

    /**
     * 建回空列
     *
     * 能还原的只有表结构，**列里的内容还原不回来**——那些是演示数据，
     * 且本迁移不做任何备份。回滚后得到的是一组全 NULL 的列。
     *
     * 仍然写了 down()（而不是像多态外键那条那样留空）：这里反向操作不制造
     * 新的损坏，只是把形状恢复成上一条迁移之后的样子；而多态外键那条的反向
     * 改写会主动把封面与标签重新弄断，两者性质不同。
     */
    public function down(): void
    {
        /** @var array<string, array<string, string>> 列名 => 类型 */
        $types = [
            'name_en'        => 'string',
            'title_en'       => 'string',
            'excerpt_en'     => 'text',
            'description_en' => 'text',
            'content_en'     => 'longText',
            'gallery'        => 'json',
        ];

        foreach ($this->targets() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns, $types): void {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        continue;
                    }

                    $blueprint->{$types[$column]}($column)->nullable();
                }
            });
        }
    }
};
