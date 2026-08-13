<?php

use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 资讯摘要字段改名：excerpt_zh → description_zh（九期批次 6）
 *
 * 六类内容里资讯是唯一一个用 excerpt_zh 而不是 description_zh 的例外，
 * SiteSearch::news() / TagContent::news() / SitemapController::llmsItems() /
 * SiteFrontController::buildSeo() 因此各多一段专门绕这个例外的分支。硬改，
 * 不留 accessor 兼容——下游若在覆盖视图里直接读 $article->excerpt_zh，
 * 迁移后会静默变成 null（Blade `??` 不报错），升级说明见两份 README/CHANGELOG。
 *
 * ⚠️ **只改列名不够**：NewsArticle::revisionTrackedFields() 把这个字段计入
 * site_revisions 快照，已存快照的 payload JSON 里键仍是 excerpt_zh。只改列名
 * 不改快照，RevisionsRelationManager::rollbackTo() 用新字段名
 * （revisionRestorableFields() 已改成 description_zh）去 array_key_exists()
 * 旧快照的 payload，取不到键就直接跳过——回滚到改名前的历史版本时
 * description_zh 悄悄不被恢复（不是清空，是恢复不生效），对比表也会把历史值
 * 误显示成「（空）」。与 CLAUDE.md 记的 model_type 多态外键腐烂同类：
 * 测试库每次新建天然只有新键，抓不到这类「存量数据随改名腐烂」的缺陷。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_news_articles') && Schema::hasColumn('site_news_articles', 'excerpt_zh')) {
            Schema::table('site_news_articles', function (Blueprint $table): void {
                $table->renameColumn('excerpt_zh', 'description_zh');
            });
        }

        $this->renamePayloadKey('excerpt_zh', 'description_zh');
    }

    /**
     * 改回旧列名与旧快照键
     *
     * 与「删列」那条不同（见 2026_08_08_130000），这里是纯改名、没有丢信息，
     * 回滚能完整复原成改名前的状态。
     */
    public function down(): void
    {
        $this->renamePayloadKey('description_zh', 'excerpt_zh');

        if (Schema::hasTable('site_news_articles') && Schema::hasColumn('site_news_articles', 'description_zh')) {
            Schema::table('site_news_articles', function (Blueprint $table): void {
                $table->renameColumn('description_zh', 'excerpt_zh');
            });
        }
    }

    /**
     * 把 site_revisions 里资讯快照 payload 的字段键整体改名
     *
     * 逐行 decode/encode：payload 是 JSON 列，键改名没有能一次 UPDATE 完成的
     * SQL 写法（不同驱动的 JSON 函数也不通用），行数级别是「一篇资讯的历史版本数」，
     * 企业站规模不需要分块。
     */
    private function renamePayloadKey(string $from, string $to): void
    {
        if (! Schema::hasTable('site_revisions')) {
            return;
        }

        DB::table('site_revisions')
            ->where('revisionable_type', NewsArticle::class)
            ->get(['id', 'payload'])
            ->each(function (object $row) use ($from, $to): void {
                $payload = json_decode((string) $row->payload, true);

                if (! is_array($payload) || ! array_key_exists($from, $payload)) {
                    return;
                }

                $payload[$to] = $payload[$from];
                unset($payload[$from]);

                DB::table('site_revisions')
                    ->where('id', $row->id)
                    ->update(['payload' => json_encode($payload)]);
            });
    }
};
