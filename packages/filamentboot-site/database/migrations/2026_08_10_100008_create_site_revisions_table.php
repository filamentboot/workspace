<?php

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 创建多态版本快照表 site_revisions，取代仅服务 SitePage 的 site_page_revisions（批次 1.5c）
 *
 * `revisionable_type` + `revisionable_id` 替代原来的硬外键 `page_id`，让同一张表
 * 服务全部 7 类内容（SitePage 及批次 1.5a 新增状态机的 6 类）。旧表数据原样搬过来，
 * 类型一律标记为 SitePage::class——旧表本就只服务这一种类型。
 *
 * ⚠️ 多态列没有数据库外键可用，级联删除改在 ContentRevisionObserver::forceDeleted()
 * 里手动做（旧表靠 page_id 的 cascadeOnDelete() 免费获得这个行为）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_revisions')) {
            Schema::create('site_revisions', function (Blueprint $table) {
                $table->id();
                $table->string('revisionable_type')->comment('内容类型（模型类名）');
                $table->unsignedBigInteger('revisionable_id')->comment('内容记录 ID');
                $table->json('payload')->comment('内容完整快照（标题/正文/SEO/状态等，各类型字段不同）');
                $table->foreignId('created_by')
                    ->nullable()
                    ->comment('操作人（admin_users.id）')
                    ->constrained('admin_users')
                    ->nullOnDelete();
                $table->timestamp('created_at')->nullable()->comment('快照时间');

                // 自动生成的索引名（含全部三个列名）超过 MySQL 64 字符标识符上限，显式给短名
                $table->index(['revisionable_type', 'revisionable_id', 'created_at'], 'site_revisions_revisionable_index');
            });
        }

        if (! Schema::hasTable('site_page_revisions') || DB::table('site_revisions')->exists()) {
            return;
        }

        // 旧表数据一次性搬过来，全部标记成 SitePage：旧表本就只服务这一种类型
        DB::table('site_page_revisions')->orderBy('id')->chunkById(500, function ($rows): void {
            $now = now();

            DB::table('site_revisions')->insert($rows->map(fn ($row): array => [
                'revisionable_type' => SitePage::class,
                'revisionable_id'   => $row->page_id,
                'payload'           => $row->payload,
                'created_by'        => $row->created_by,
                'created_at'        => $row->created_at ?? $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_revisions');
    }
};
