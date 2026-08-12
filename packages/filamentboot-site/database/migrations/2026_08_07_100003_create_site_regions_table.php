<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建行政区划表
 *
 * **纯区划，零行业字段。** 气候、供暖、户型均价这些是装修行业才关心的东西，
 * 它们属于站点数据、进 `site_city_pages.profile`（字段表由宿主 config 声明），
 * 不该变成本表的固定列——包不认识「梅雨季」这三个字。
 *
 * 数据由 `filamentboot-site:import-regions` 从宿主给的 JSON 导入，
 * **包不随身携带区划数据**。
 *
 * ## 三个字段值得单独说
 *
 * ### `parent_code` 用空串表示顶级，不是 NULL
 *
 * 因为下面那条联合唯一索引：MySQL 的唯一索引**对 NULL 不去重**，
 * `(NULL, 'jilin')` 能插进去两次，顶级 slug 的唯一性就白约束了。
 *
 * ### slug 只在同一个父下唯一，不是全局唯一
 *
 * 中国区划里同名同音的地方很多，全局唯一根本建不出来：
 *
 *   - 江苏泰州 / 浙江台州 —— 拼音都是 taizhou，分属两省，两条都合法
 *   - 吉林省 / 吉林市 —— `/city/jilin` 与 `/city/jilin/jilin`，层级不同不冲突
 *   - 海南省 / 青海海南藏族自治州 —— 同上
 *
 * ### 县级的 slug 是 NULL
 *
 * 县级不建页、没有 URL。给它 slug 等于承诺以后会有区县页，
 * 而那是个要重新想清楚的决定（3000 页的量级与 300 页完全不是一回事）。
 * 县级导进来只为一件事：城市页要列「下辖区县」——那是**逐城不同的真实内容**，
 * 不用编也不会重复。
 *
 * ## 不加软删除
 *
 * 区划是参考数据不是内容。删了就是删了，回收站对它没有意义。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_regions')) {
            return;
        }

        Schema::create('site_regions', function (Blueprint $table) {
            $table->id();

            $table->string('code', 6)->unique()->comment('行政区划代码（GB/T 2260，6 位）');
            $table->string('parent_code', 6)->default('')->index()->comment('上级区划代码，顶级为空串（不是 NULL，见类注释）');
            $table->unsignedTinyInteger('level')->index()->comment('层级：1 省级 / 2 地级 / 3 县级');

            $table->string('name')->comment('官方全称，如「武汉市」');
            $table->string('short_name')->nullable()->comment('简称，如「武汉」。县级留空，列表里用全名');
            $table->string('slug', 64)->nullable()->comment('URL 段。只有省级与地级有，县级为 NULL');

            $table->unsignedInteger('sort')->default(0)->comment('排序权重，同值时按 code 排（code 序即官方序）');

            $table->timestamps();

            // slug 在同一个父下唯一。县级 slug 为 NULL，MySQL 不对 NULL 去重，
            // 因此同一个市底下的多个县不会互相撞——这正是想要的行为
            $table->unique(['parent_code', 'slug'], 'site_regions_parent_slug_unique');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('site_regions');
    }
};
