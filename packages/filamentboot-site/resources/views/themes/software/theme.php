<?php

/**
 * software 主题清单（#28）
 *
 * 由 Cms\Themes\ThemeManifest 读取，Cms\Themes\ThemeSwitchCheck 用它在切换主题
 * **之前**算出哪些已发布页面会掉内容。
 *
 * ⚠️ 新增 blocks/*.blade.php 或 pages/templates/*.blade.php 之后要同步这里，
 * 否则预检查会把已经支持的东西报成不支持（宁可误报也不漏报：漏报意味着
 * 切完主题内容悄悄消失）。
 */
return [
    'label' => '软件产品（浅色）',

    // pages/templates/{key}.blade.php；default 走 pages/show.blade.php
    'templates' => [
        'default',
        'landing',
    ],

    // blocks/{key}.blade.php，key 即 BlockContract::key()
    'blocks' => [
        'hero',
        'rich-content',
        'media-text',
        'feature-grid',
        'cta',
        'faq',
        'contact-form',
        'map',
        'gated-download',
        'roadmap',
    ],

    'features' => [
        // 桌面导航有下拉版式、移动导航有缩进子列表，所以放开二级
        'nested_menu' => true,
    ],
];
