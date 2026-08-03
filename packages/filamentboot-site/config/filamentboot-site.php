<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 前台路由挂载模式
    |--------------------------------------------------------------------------
    |
    | prefix：官网挂在子路径下（默认，宿主已有前台业务时的安全模式），如 /site/about
    | root  ：官网接管根路径（项目本身就是官网时启用），如 /about
    | domain：官网绑定独立域名或子域名，如 www.example.com/about
    |
    | 默认不抢占宿主根路由。需要官网接管根路径时，在 .env 中设置
    | SITE_ROUTE_MODE=root。
    |
    */
    'route' => [
        'mode' => env('SITE_ROUTE_MODE', 'prefix'),

        /*
        | prefix 模式下的路径前缀，仅在 mode=prefix 时生效。
        */
        'prefix' => env('SITE_ROUTE_PREFIX', 'site'),

        /*
        | domain 模式下绑定的域名，仅在 mode=domain 时生效。
        | 未配置时自动降级为 prefix 模式，避免注册出无主机名的路由。
        */
        'domain' => env('SITE_ROUTE_DOMAIN'),

        /*
        |----------------------------------------------------------------------
        | 保留 slug
        |----------------------------------------------------------------------
        |
        | 动态页面路由 /{slug} 不得吞掉的固定路径。root 模式下尤其重要：
        | 没有这份清单，/admin、/sitemap.xml 等会被当作页面 slug 解析。
        |
        */
        'reserved_slugs' => [
            'admin',
            'api',
            'livewire',
            'storage',
            'sitemap.xml',
            'robots.txt',
            'preview',
            'login',
            'logout',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 可选前台主题
    |--------------------------------------------------------------------------
    |
    | 键为主题目录名（resources/views/themes/{key}），值为后台显示名称。
    | 列在这里的主题必须覆盖控制器会请求的全部视图，否则前台会 500。
    | 白名单同时用于防止 active_theme 被写入非法值造成目录穿越。
    |
    */
    'themes' => [
        'decoration'   => '科技装修（深色）',
        'tech-product' => '科技产品（浅色）',
    ],

    /*
    |--------------------------------------------------------------------------
    | 默认主题
    |--------------------------------------------------------------------------
    |
    | 站点设置未配置或配置了非法主题时的兜底值，必须存在于上面的白名单中。
    |
    */
    'default_theme' => 'decoration',

    /*
    |--------------------------------------------------------------------------
    | 前端资源入口
    |--------------------------------------------------------------------------
    |
    | 主题 CSS 的 Vite 入口候选路径，{theme} 会被替换为当前主题目录名。
    | 按顺序在 Vite manifest 中查找，命中哪个用哪个：
    |
    |   1. 真实 Composer 安装（宿主把 vendor 路径加入 vite.config.js input）
    |   2. 宿主执行 vendor:publish --tag=filamentboot-site-assets 之后
    |   3. monorepo path 仓库（vendor/ 为符号链接，Vite 记录真实路径）
    |
    | 宿主用其它路径组织资源时，在此追加或替换即可。
    |
    */
    'assets' => [
        'vite_entries' => [
            'vendor/filamentboot/filamentboot-site/resources/css/themes/{theme}.css',
            'resources/css/vendor/filamentboot-site/themes/{theme}.css',
            'packages/filamentboot-site/resources/css/themes/{theme}.css',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    */
    'seo' => [
        /*
        | 站点设置未填写默认描述时的最终兜底，确保 meta description 永不为空。
        */
        'fallback_description' => env(
            'SITE_SEO_FALLBACK_DESCRIPTION',
            '智能家居整体解决方案提供商，提供全屋智能设计、设备选型、施工落地与售后服务。'
        ),

        /*
        |----------------------------------------------------------------------
        | canonical 需剥离的查询参数
        |----------------------------------------------------------------------
        |
        | canonical 必须保留 page 等真正区分内容的参数，只剥掉广告与统计平台
        | 附加的追踪参数。此前 canonical 直接取 url()->current()（不含查询串），
        | /solutions?page=2 的 canonical 指向 /solutions，等于告诉搜索引擎
        | 列表页第 2 页往后都是第 1 页的副本，深层内容不会被索引。
        |
        | 宿主接入其它投放渠道时在此追加对应参数名即可。
        |
        */
        'canonical_ignored_params' => [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'gclid',
            'fbclid',
            'msclkid',
            'yclid',
            'bd_vid',
            '_bd_vid',
            'spm',
        ],

        /*
        | 站点地图单次输出的每类内容上限，防止内容量大时超时。
        */
        'sitemap_limit' => env('SITE_SITEMAP_LIMIT', 2000),
    ],
];
