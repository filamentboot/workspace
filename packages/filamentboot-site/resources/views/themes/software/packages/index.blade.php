{{--
 * 版本与定价页（software 浅色主题）
 *
 * ⚠️ 2026-08-11（六期批次 4）实测发现：本页被 `SiteFrontMenuSeeder::softwareMenus()`
 * 的「版本与定价」导航项直接链到，**不是死路由**——批次 2.5 曾把 `packages/*` 判成
 * 「software 不建 SitePackage 数据、没人访问」而整体跳过换皮，那个判断只对
 * `show.blade.php`（真要有套餐记录才会被访问）成立，对本文件不成立。
 *
 * 原版是装修转化落地页：三档说明（PackageTier 枚举，「定制/舒适/豪华」+「影音/
 * 安防/传感网络」这类装修概念，不依赖任何 SitePackage 数据、无条件渲染）→ 户型
 * 筛选 → 卡片网格；空态 CTA 是「说一下户型，我们帮你配一套」。filamentboot 完全
 * 开源、MIT 协议、不做付费分级（`$siteSettings->list_intro_packages_zh` 已经这么
 * 写），这三样跟这句话直接矛盾——访客点「版本与定价」看到的是「三档说明」加一句
 * 「说不做分级」，自相矛盾。
 *
 * software 版本去掉三档说明与户型相关 CTA，只留标题 + 说明文案 + （若真有
 * SitePackage 记录时）卡片网格——SitePackage 本身仍是[六期判定的「包多候选」]
 * (../../../../../../docs/cms/03-6-filamentboot官网二.md)，不做深度参数化，
 * 这里只是不让空态显示自相矛盾的内容。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\HouseLayout;

    $activeLayout = $layout?->value;
@endphp

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="packages-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-10 max-w-2xl">
                <h1 id="packages-heading" class="text-site-primary text-4xl font-bold mb-4">
                    版本与定价
                </h1>
                @if($siteSettings?->list_intro_packages_zh)
                    <p class="text-site-secondary text-lg leading-relaxed">
                        {{ $siteSettings->list_intro_packages_zh }}
                    </p>
                @endif
            </div>

            {{-- 户型筛选 --}}
            @if($layouts !== [])
                <nav class="flex flex-wrap gap-2 mb-10" aria-label="按户型筛选">
                    <a href="{{ route('site.packages.index') }}"
                       @if($activeLayout === null) aria-current="page" @endif
                       class="px-4 py-2 rounded-full text-sm min-h-[44px] inline-flex items-center transition-colors duration-200
                              focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none
                              {{ $activeLayout === null ? 'btn-site-primary' : 'bg-site-surface border border-site text-site-secondary hover:text-site-accent' }}">
                        全部户型
                    </a>
                    @foreach($layouts as $layoutValue)
                        @php $layoutCase = $layoutValue instanceof HouseLayout ? $layoutValue : HouseLayout::tryFrom((string) $layoutValue); @endphp
                        @continue($layoutCase === null)
                        <a href="{{ route('site.packages.index', ['layout' => $layoutCase->value]) }}"
                           @if($activeLayout === $layoutCase->value) aria-current="page" @endif
                           class="px-4 py-2 rounded-full text-sm min-h-[44px] inline-flex items-center transition-colors duration-200
                                  focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none
                                  {{ $activeLayout === $layoutCase->value ? 'btn-site-primary' : 'bg-site-surface border border-site text-site-secondary hover:text-site-accent' }}">
                            {{ $layoutCase->label() }}
                        </a>
                    @endforeach
                </nav>
            @endif

            @if($records->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($records as $package)
                        @include('filamentboot-site::components.package-card', ['package' => $package])
                    @endforeach
                </div>

                @if($records->hasPages())
                    <div class="mt-12">
                        {{ $records->links() }}
                    </div>
                @endif
            @else
                {{-- 上面的说明文案已经答完「版本与定价」——完全开源、不分级，
                     不需要再补一句「正在整理中」（暗示以后会有付费分级）或
                     装修那句「说一下户型」CTA，跳到 GitHub 看真实源码即可。 --}}
                <div class="py-16 text-center">
                    <a href="https://github.com/filamentboot/filamentboot"
                       target="_blank" rel="noopener noreferrer"
                       class="btn-site-outline inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-full text-sm
                              focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none">
                        在 GitHub 查看源码
                    </a>
                </div>
            @endif

        </div>
    </section>
@endsection
