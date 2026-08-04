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
            'search',
            'downloads',
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
        /*
        | 前台脚本入口（#29）。只有一份、不随主题变，所以模板里没有 {theme}。
        |
        | 它交付的是 Alpine：#29 之前 Alpine 是 Livewire 注入的 livewire.js 捎带进来的，
        | 而那个 script 标签带 data-csrf → 起 session → 公开页必然带 Set-Cookie。
        | 宿主必须把命中的那条路径加进 vite.config.js 的 input 并装 alpinejs，
        | 否则前台所有 x-data 都不工作（导航抽屉、询盘面板、二级下拉、图集轮播）。
        */
        'script_entries' => [
            'vendor/filamentboot/filamentboot-site/resources/js/site.js',
            'resources/js/vendor/filamentboot-site/site.js',
            'packages/filamentboot-site/resources/js/site.js',
        ],

        'vite_entries' => [
            'vendor/filamentboot/filamentboot-site/resources/css/themes/{theme}.css',
            'resources/css/vendor/filamentboot-site/themes/{theme}.css',
            'packages/filamentboot-site/resources/css/themes/{theme}.css',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 公开页缓存
    |--------------------------------------------------------------------------
    |
    | 内容页响应头 Cache-Control: public, max-age=N 的 N（秒）。设 0 或负数即关闭。
    |
    | 内容改动后旧页面最多再存活这么久。官网内容改动频率低，十分钟换取整页缓存划算；
    | 要更实时就调小，或在 CDN 侧配主动刷新。
    |
    | ⚠️ Cms\Routing\SiteCacheHeaders 只在响应确实没有 Set-Cookie 时才打这个头。
    | 一旦某个页面意外起了 session，它会退回不缓存——把带会话 Cookie 的响应标成
    | 公共可缓存，共享缓存会把一个访客的会话发给另一个。
    |
    */
    'cache' => [
        'public_max_age' => env('SITE_PUBLIC_MAX_AGE', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | 询盘
    |--------------------------------------------------------------------------
    */
    'contact' => [
        /*
        | 转化入口标识与中文名的映射，键即前台 CTA 的 data-contact-trigger 取值。
        |
        | 只影响后台展示：未列在这里的来源仍会正常入库，后台按原始 key 显示，
        | 因此新增 CTA 时忘了登记也不会导致询盘筛不出来。
        */
        'sources' => [
            'floating'        => '悬浮按钮',
            'mobile-bar'      => '移动端操作条',
            'hero'            => '首屏 Banner',
            'nav-desktop'     => '导航栏',
            'nav-mobile'      => '移动端菜单',
            'home-cta'        => '首页 CTA',
            'product-card'    => '产品卡片',
            'product-detail'  => '产品详情页',
            'solution-detail' => '方案详情页',
            'case-detail'     => '案例详情页',
            'news-detail'     => '资讯详情页',
            'page-cta'        => '页面 CTA 区块',
            'landing-header'  => '落地页头部按钮',
            'search-empty'    => '搜索无结果',
            'gated-download'  => '资料索取',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 资料索取（gated content）
    |--------------------------------------------------------------------------
    |
    | 「手册换联系方式」：访客提交询盘后才拿到一条限时签名下载链接。
    |
    | ⚠️ disk **必须是非公开磁盘**。默认 local 指向 storage/app，在 Web 根之外，
    | 只能经 Http\Controllers\GatedDownloadController 下发。改成 public 会让文件
    | 多出一个人人可猜的 /storage/... 地址，这道门就形同虚设——而且不会有任何报错，
    | 表现只是「留资率莫名很低」。
    |
    | link_ttl 是下载链接的有效分钟数。给得短是因为链接可能被转发：
    | 三十分钟够一次正常下载，又不足以当成长期分发地址。
    |
    */
    'gated' => [
        'disk'     => env('SITE_GATED_DISK', 'local'),
        'link_ttl' => env('SITE_GATED_LINK_TTL', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | 地图嵌入
    |--------------------------------------------------------------------------
    |
    | 地图区块（Cms\Blocks\MapBlock）允许的 iframe 宿主域名。作者只填嵌入地址，
    | iframe 由前台视图自己拼——不接受整段 HTML，那等于在页面里开任意标签入口。
    |
    | 精确匹配、区分不了子域名：写 map.baidu.com 就只放行它。用「以某域名结尾」
    | 会被 map.baidu.com.evil.com 绕过，写 .baidu.com 又把整棵域名树都放进来。
    | 换服务商时在这里加一行，比放宽匹配规则安全得多。
    |
    | 只放行 https：http 的 iframe 在 https 页面上被当混合内容直接拦掉，
    | 放进来只会让作者以为是我们的 bug（判断在 Support\MapEmbed 里）。
    |
    */
    'map' => [
        'allowed_hosts' => [
            // 百度地图（国内主战场，「地图生成器」产出的 src）
            'map.baidu.com',
            'api.map.baidu.com',
            'j.map.baidu.com',
            // 高德
            'm.amap.com',
            'uri.amap.com',
            'www.amap.com',
            // 腾讯位置服务
            'apis.map.qq.com',
            'map.qq.com',
            // Google Maps（海外副线）
            'www.google.com',
            'maps.google.com',
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

    /*
    |--------------------------------------------------------------------------
    | 页面版式
    |--------------------------------------------------------------------------
    |
    | 键为 site_pages.template 的存储值，值为后台 Select 显示名称。
    |
    | default 走 themes/{theme}/pages/show.blade.php；其它键由控制器解析成
    | themes/{theme}/pages/templates/{key}.blade.php，视图不存在时回退 show——
    | 一个模板缺失不该让已发布页面 404，也不该要求两套主题必须同步支持。
    |
    | landing 是 #28 交付的落地页极简版式：不 extends layouts.app，因此没有导航栏
    | 与完整页脚，出路收敛到询盘面板一个动作上。两套主题各有一份
    | pages/templates/landing.blade.php，并在各自的 theme.php 清单里声明支持。
    |
    | ⚠️ 新增版式必须同步改两套主题的 theme.php，否则主题切换预检查
    | （Cms\Themes\ThemeSwitchCheck）会把已支持的版式报成不支持。
    |
    */
    'page_templates' => [
        'default' => '标准页面',
        'landing' => '落地页（无导航，单一转化目标）',
    ],

    /*
    |--------------------------------------------------------------------------
    | 前台导航菜单
    |--------------------------------------------------------------------------
    */
    'menu' => [
        /*
        | route 型菜单项可指向的命名路由白名单，键为路由名、值为后台显示名称。
        |
        | 用白名单而不是让作者自由填路由名：route() 对未知名称会抛异常，
        | 而导航组件在每个页面都渲染——一个填错的路由名会让全站白屏。
        | 白名单同时让后台能给出下拉而不是让人背路由名。
        |
        | 宿主新增前台列表页时在此追加即可。
        */
        'allowed_routes' => [
            'site.home'            => '首页',
            'site.cases.index'     => '案例列表',
            'site.solutions.index' => '方案列表',
            'site.products.index'  => '产品列表',
            'site.news.index'      => '资讯列表',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 页面版本快照保留条数
    |--------------------------------------------------------------------------
    |
    | 每个页面保留最近 N 条快照，超出的由 SitePageObserver 在写入后删掉。
    |
    | 不加上限，高频编辑的页面会把 site_page_revisions 撑爆——每条快照都存一份
    | 正文全文，一篇长页面几十 KB，几百次编辑就是几十 MB。
    | 设为 0 或负数表示不裁剪（自行承担表膨胀）。
    |
    */
    'revisions_keep' => env('SITE_REVISIONS_KEEP', 50),

    /*
    |--------------------------------------------------------------------------
    | 前台富文本过滤画像
    |--------------------------------------------------------------------------
    |
    | 留空则用包自带的白名单（Support\RichText::defaultProfile()），它与后台
    | RichEditor 的默认工具栏对齐，不依赖宿主的 config/purifier.php 内容。
    |
    | 想自己定过滤策略时，在 config/purifier.php 的 settings 下加一段画像，
    | 再把画像名填到这里，包内白名单即让位。注意画像是整体替换而非合并，
    | 填了之后标签能不能活下来完全由那一段决定。
    |
    */
    'purifier_profile' => env('SITE_PURIFIER_PROFILE'),
];
