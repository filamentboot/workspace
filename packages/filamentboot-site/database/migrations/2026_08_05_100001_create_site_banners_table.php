<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建幻灯片表
 *
 * 此前官网**只有产品详情页的图集轮播**，首页与四个列表页的主视觉是写死在
 * `components/hero.blade.php` 里的一句 slogan——编辑改不了，也放不了第二张图。
 *
 * 为什么不做成 block：`home.blade.php` 是硬编码模板、不吃 blocks 系统
 * （第 11 行直接 `@include components.hero`），加一个 slider block 首页吃不到。
 * 首页的可组装性是后续架构抽象期的题，这里先用独立模型 + 投放位置解决。
 *
 * slug 是**内部稳定键，不是网址**
 * ------------------------------
 * 幻灯片没有自己的前台地址。这一列存在只为两件事：让演示种子能复用
 * `Concerns\SeedsBySlug`（`firstOrCreate` + `withTrashed()`，「已有的不动、缺的补上」），
 * 以及与包内其余五类内容的 Resource 表单保持同一形状。后台标签已注明用途。
 *
 * 排序列叫 `sort` 而不是 `sort_order`：`site_cases` / `site_products` /
 * `site_pages` / `site_news_articles` 全都是 `sort`，多一种命名只会让以后写
 * 跨表排序逻辑的人踩空。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_banners')) {
            return;
        }

        Schema::create('site_banners', function (Blueprint $table) {
            $table->id();

            // 内部标识：种子幂等与后台识别用，不参与路由
            $table->string('slug')->unique()->comment('内部标识（不是网址），演示种子按它幂等写入');

            // 文案
            $table->string('title')->comment('主标题');
            $table->string('subtitle')->nullable()->comment('副标题');

            // 行动按钮：action 决定渲染成链接、询盘面板触发器还是整行不渲染
            $table->string('cta_label')->nullable()->comment('按钮文字');
            $table->string('cta_url')->nullable()->comment('按钮链接（仅 cta_action=link 时使用）');
            $table->string('cta_action')->default('none')->comment('按钮行为：link 跳转 / inquiry 打开询盘面板 / none 不显示');

            // 投放位置：BannerPosition 枚举值，前台按它取图
            $table->string('position')->index()->comment('投放位置（BannerPosition 枚举值）');

            // 展示控制
            $table->unsignedInteger('sort')->default(0)->comment('排序权重，同一投放位置内升序');
            $table->timestamp('starts_at')->nullable()->comment('生效开始时间（null 为立即生效）');
            $table->timestamp('ends_at')->nullable()->comment('生效结束时间（null 为长期有效）');
            $table->boolean('is_enabled')->default(true)->index()->comment('是否启用');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_banners')) {
            return;
        }

        Schema::dropIfExists('site_banners');
    }
};
