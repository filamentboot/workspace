{{--
 * 产品与模块列表页（software 浅色主题，六期批次 3 兼作「生态与插件目录」页）
 *
 * 复用现有 /products 路由与导航项，不新开一条导航——批次 4a 已经种了 6 个真实
 * 一方插件在这里，缺的只是把「生态」讲成一个故事：7 个官方包现状 + 插件市场
 * 白名单机制 + 第三方插件生态的真实进度。decoration 主题没有「生态/插件市场」
 * 这个概念，这段说明与「计划中」区块只加在本文件，不抽公共层。
 *
 * 「计划中」条目直接读 config('official-market.entries')，只取
 * source === official_listed 且 Packagist 查无此包的那几条（2026-08-11 实测：
 * aliyun-sms / huawei-cloud-sms / crm-suite / corporate-site-suite 四条对应的
 * composer 包名在 Packagist 上都不存在，是纯规划占位）——不能和上面已验证可装的
 * official_trusted 条目混在一起当「现有」展示，那是过度承诺。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    // 0 件已发布产品的分类不出筛选按钮，与资讯分类同一套判据（articles_count）
    $pills = $categories->filter(fn ($category): bool => (int) ($category->products_count ?? 0) > 0);

    $plannedMarketEntries = collect(config('official-market.entries', []))
        ->where('source', 'official_listed')
        ->values();
@endphp

@section('content')
    {{-- 投放位幻灯片。没有生效中的就整段不渲染，所以这里无条件 include。 --}}
    @include('filamentboot-site::components.banner-strip', [
        'position' => \Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerPosition::PRODUCT_INDEX_TOP,
    ])

    <section class="py-16 bg-site-base" aria-labelledby="products-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-10">
                <h1 id="products-heading" class="text-site-primary text-4xl font-bold mb-4">
                    {{ $activeCategory?->name_zh ?? '产品与模块' }}
                </h1>
                @if($siteSettings?->list_intro_products_zh)
                    <p class="text-site-secondary text-lg">
                        {{ $siteSettings->list_intro_products_zh }}
                    </p>
                @endif
            </div>

            {{-- 生态说明：官方包现状 + 插件市场机制，只在无分类筛选的总览页显示 --}}
            @if($activeCategory === null)
                <div class="mb-10 p-6 rounded-2xl bg-site-surface border border-site">
                    <p class="text-site-secondary text-sm leading-relaxed">
                        filamentboot 由 1 个后台底座包（<code>filamentboot/filamentboot</code>）与下面
                        6 个官方插件组成，均可独立安装、按需组合。后台内置插件市场，支持从白名单来源
                        一键安装第三方 Filament 插件——当前收录
                        <a href="https://filamentphp.com/plugins/awcodes-tiptap-editor" target="_blank" rel="noopener noreferrer"
                           class="text-site-accent hover:underline">Tiptap 富文本编辑器</a>与
                        <a href="https://filamentphp.com/plugins/filament-spatie-media-library" target="_blank" rel="noopener noreferrer"
                           class="text-site-accent hover:underline">Spatie 媒体库</a>两款。
                    </p>
                </div>
            @endif

            {{-- 分类筛选，与资讯列表页同一套版式 --}}
            @if($pills->isNotEmpty())
                <div class="flex flex-wrap gap-2 mb-10" role="navigation" aria-label="产品分类筛选">
                    <a href="{{ route('site.products.index') }}"
                       class="inline-flex items-center min-h-[36px] px-4 rounded-full text-sm transition-colors duration-200
                              focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none
                              {{ $activeCategory === null
                                  ? 'bg-(--color-primary) text-(--color-bg-base)'
                                  : 'bg-site-surface border border-site text-site-secondary hover:text-site-accent' }}"
                       @if($activeCategory === null) aria-current="page" @endif>
                        全部
                    </a>

                    @foreach($pills as $category)
                        <a href="{{ route('site.products.index', ['category' => $category->slug]) }}"
                           class="inline-flex items-center min-h-[36px] px-4 rounded-full text-sm transition-colors duration-200
                                  focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none
                                  {{ $activeCategory?->id === $category->id
                                      ? 'bg-(--color-primary) text-(--color-bg-base)'
                                      : 'bg-site-surface border border-site text-site-secondary hover:text-site-accent' }}"
                           @if($activeCategory?->id === $category->id) aria-current="page" @endif>
                            {{ $category->name_zh }}
                            <span class="ml-1 opacity-60">{{ $category->products_count }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if(isset($records) && $records->isNotEmpty())
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($records as $product)
                        @include('filamentboot-site::components.product-card', ['product' => $product])
                    @endforeach
                </div>

                {{-- 分页 --}}
                @if($records->hasPages())
                    <div class="mt-12">
                        {{ $records->links() }}
                    </div>
                @endif

            @else
                <div class="py-16 text-center">
                    <p class="text-site-secondary text-base">暂无产品展示，敬请期待</p>
                </div>
            @endif

            {{-- 计划中的市场插件：不是能装的东西，只标「计划中」，不与上面已验证
                 可装的内容混在一起——写法参照 blocks/roadmap.blade.php 同款徽标 --}}
            @if($activeCategory === null && $plannedMarketEntries->isNotEmpty())
                <div class="mt-16 pt-12 border-t border-site">
                    <h2 class="flex items-center gap-3 mb-6">
                        <span class="bg-site-elevated text-site-secondary text-xs px-3 py-1 rounded-full">计划中</span>
                        <span class="text-site-muted text-xs">插件市场收录中，尚未开放安装</span>
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($plannedMarketEntries as $entry)
                            <article class="bg-site-surface rounded-xl border border-site p-5">
                                <h3 class="text-site-primary font-bold text-base mb-2 leading-snug">
                                    {{ $entry['display_name'] ?? '' }}
                                </h3>
                                @if(! empty($entry['summary']))
                                    <p class="text-site-secondary text-sm leading-relaxed">{{ $entry['summary'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </section>
@endsection
