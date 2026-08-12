<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建全屋智能套餐内容表
 *
 * 骨架照 `site_solutions` 抄（双语字段对、独立 SEO 三列、发布状态、置顶、软删除），
 * 加上套餐特有的四组列：
 *
 *   - **户型 × 档位** —— 套餐存在的理由就是「按我家户型能横向比」，两列都建索引
 *   - **面积段 / 参考价 / 价格口径** —— 价格可空，空就是「咨询价格」
 *   - **包含清单（JSON）** —— 名称 / 数量 / 用途 / 摆放位置四列
 *   - **不含项 / 工期 / 质保** —— 报价单上真正会被追问的三件事
 *
 * ## price 用 decimal 而不是 string
 *
 * `site_solutions.price_range` 是字符串（「5-10万」这种区间），套餐给的是**一个
 * 参考价**，要能排序、能比大小，所以用 decimal(10,2)。口径那句话单独放
 * `price_note`，不和数字混在一列里——混在一起就再也排不了序了。
 *
 * ## items 是包里第一个 JSON 重复结构列
 *
 * 六类内容此前清一色 string / text / richtext。这一条记进七期「可配置内容类型」
 * 的账：「一组带列的清单」不该每个模块自己造一次。
 *
 * ⚠️ JSON 列里的中文被 Eloquent 存成 `\uXXXX` 转义序列，**`LIKE '%中文%'` 命不中**
 * （同 `site_pages.blocks`，见 `Cms\Services\SiteSearch` 的类注释）。所以站内搜索
 * 只吃标题 / 简介 / 正文，清单里的设备名搜不到。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_packages')) {
            return;
        }

        Schema::create('site_packages', function (Blueprint $table) {
            $table->id();

            // 双语标题
            $table->string('title_zh')->comment('套餐标题（中文）');
            $table->string('title_en')->nullable()->comment('套餐标题（英文）');

            $table->string('slug')->unique()->comment('URL 友好标识');

            // 双语描述与富文本内容
            $table->text('description_zh')->nullable()->comment('套餐简介（中文）');
            $table->text('description_en')->nullable()->comment('套餐简介（英文）');
            $table->longText('content_zh')->nullable()->comment('套餐详情富文本（中文）');
            $table->longText('content_en')->nullable()->comment('套餐详情富文本（英文）');

            // 套餐维度：列表页按这两列筛与排（Enums\HouseLayout / Enums\PackageTier）
            $table->string('house_layout', 32)->nullable()->index()->comment('户型（HouseLayout 枚举值）');
            $table->string('tier', 32)->nullable()->index()->comment('档位（PackageTier 枚举值）');
            $table->string('area_range', 50)->nullable()->comment('适用面积段，如 60-90㎡');

            // 价格：空 = 不显示数字，前台走「咨询价格」分支
            $table->decimal('price', 10, 2)->nullable()->comment('参考价，空表示咨询价格');
            $table->string('price_note')->nullable()->comment('价格口径，如「参考价，最终以实际量房与选型为准」');

            // 包含清单：[{name, quantity, purpose, location}]
            $table->json('items')->nullable()->comment('包含清单（名称/数量/用途/摆放位置）');

            $table->text('excludes')->nullable()->comment('不含项');
            $table->string('duration')->nullable()->comment('工期');
            $table->string('warranty')->nullable()->comment('质保');

            // 独立 SEO 三列（D-10-04）
            $table->string('seo_title')->nullable()->comment('SEO 标题');
            $table->string('seo_description')->nullable()->comment('SEO 描述');
            $table->string('seo_keywords')->nullable()->comment('SEO 关键词');

            // 置顶与排序
            $table->boolean('is_featured')->default(false)->index()->comment('是否置顶/精选');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重');

            // 发布状态
            $table->timestamp('published_at')->nullable()->index()->comment('发布时间（null 为草稿）');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_packages')) {
            return;
        }

        Schema::dropIfExists('site_packages');
    }
};
