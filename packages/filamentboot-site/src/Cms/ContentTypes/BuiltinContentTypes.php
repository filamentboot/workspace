<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes;

/**
 * 内置内容类型声明清单（批次 5，可配置内容类型系统验收）
 *
 * 友情链接、广告位是四期功能矩阵第二档验收的两个内容类型，用于证明
 * "新增一类内容不用手写 Model/Resource/Policy/视图/Seeder/测试"——本文件
 * 只是声明，真正的 Model/Resource/Policy/迁移由
 * `php artisan filamentboot-site:content-type:sync` 按本文件生成，
 * 生成后的文件与本文件脱钩，改这里不会让已生成的文件跟着变。
 */
final class BuiltinContentTypes
{
    /**
     * @return list<ContentTypeDefinition>
     */
    public static function all(): array
    {
        return [
            self::friendLink(),
            self::adSlot(),
        ];
    }

    /**
     * 友情链接：站点合作方/关联站点入口，通常展示在页脚
     */
    public static function friendLink(): ContentTypeDefinition
    {
        return new ContentTypeDefinition(
            key: 'friend_link',
            label: '友情链接',
            pluralLabel: '友情链接',
            table: 'site_friend_links',
            module: 'Corporate/FriendLinks',
            fields: [
                new FieldDefinition(key: 'name', type: 'text', label: '名称', required: true, maxLength: 100, showInList: true, unique: true),
                new FieldDefinition(key: 'url', type: 'url', label: '链接', required: true, showInList: true),
                new FieldDefinition(key: 'logo', type: 'image', label: 'Logo'),
                new FieldDefinition(key: 'is_enabled', type: 'boolean', label: '启用', default: true, showInList: true),
            ],
            sortable: true,
            navigationIcon: 'heroicon-o-link',
        );
    }

    /**
     * 广告位：按投放位置展示的图片广告，带生效时间窗
     */
    public static function adSlot(): ContentTypeDefinition
    {
        return new ContentTypeDefinition(
            key: 'ad_slot',
            label: '广告位',
            pluralLabel: '广告位',
            table: 'site_ad_slots',
            module: 'Corporate/AdSlots',
            fields: [
                new FieldDefinition(key: 'title', type: 'text', label: '标题', required: true, maxLength: 100, showInList: true),
                new FieldDefinition(key: 'image', type: 'image', label: '图片', required: true),
                new FieldDefinition(key: 'link_url', type: 'url', label: '跳转链接'),
                new FieldDefinition(
                    key: 'position',
                    type: 'select',
                    label: '投放位置',
                    required: true,
                    showInList: true,
                    choices: [
                        'home_top'       => '首页顶部',
                        'home_sidebar'   => '首页侧栏',
                        'product_bottom' => '产品页底部',
                    ],
                ),
                new FieldDefinition(key: 'starts_at', type: 'date', label: '生效开始'),
                new FieldDefinition(key: 'ends_at', type: 'date', label: '生效结束'),
                new FieldDefinition(key: 'is_enabled', type: 'boolean', label: '启用', default: true, showInList: true),
            ],
            sortable: true,
            navigationIcon: 'heroicon-o-megaphone',
        );
    }
}
