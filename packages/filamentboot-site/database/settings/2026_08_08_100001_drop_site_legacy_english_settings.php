<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 删掉双语时代残留的 5 个 *_en 站点设置项
 *
 * 与同期那条删 `_en` 数据列的迁移是同一件事的两半：内容表的英文列在
 * 2026_08_08_130000_drop_legacy_english_and_gallery_columns 里删，
 * 站点设置里的这 5 个键在这里删。
 *
 * 它们没有任何消费方——SiteSettingsPage 不渲染，前台视图不读，
 * SiteHealthCheck 不检查。SiteSettings 类的注释此前写着「删除属性需要额外的
 * Spatie Settings 迁移且无实际收益」，本次判断相反：**留着的成本是每个读到
 * 这个类的人都要重新判断一次英文流还在不在**，而这条迁移就是那个「额外的迁移」。
 *
 * 顺序要求：Settings 类的属性必须与本迁移一起改。Spatie 的映射器加载时按类的
 * 属性逐个去仓库取值，**属性还在而仓库里的键没了会抛 MissingSettings**——
 * 只跑迁移不改类，后台站点设置页会直接白屏。
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach ([
            'site.company_name_en',
            'site.phone_en',
            'site.address_en',
            'site.seo_default_title_en',
            'site.seo_default_description_en',
        ] as $name) {
            $this->migrator->deleteIfExists($name);
        }
    }

    /**
     * 建回空串
     *
     * 这 5 个键在删除时全部是空串（英文入口早在 CMS v1 就下线了，之后没人填过），
     * 所以「还原成空串」就是完整还原，没有数据损失。
     */
    public function down(): void
    {
        foreach ([
            'site.company_name_en',
            'site.phone_en',
            'site.address_en',
            'site.seo_default_title_en',
            'site.seo_default_description_en',
        ] as $name) {
            $this->migrator->add($name, '');
        }
    }
};
