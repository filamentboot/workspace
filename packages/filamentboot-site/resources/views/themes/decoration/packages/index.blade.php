{{--
 * 全屋套餐列表页（decoration 浅色主题）
 *
 * 这一页是整个站转化路径的落点：装修者带着「我家三室两厅做下来多少钱」进来，
 * 页面要在一屏之内回答「有没有我家户型」「分几档」「大概多少钱」。
 *
 * 三段结构，顺序不能换：
 *   1. **三档说明** —— 先告诉他档位是怎么分的，否则下面的卡片没有比较基准
 *   2. **户型筛选** —— 走查询参数、不用 Livewire。公开页要能整页缓存就不能起 session，
 *      每个筛选组合各自是一个可缓存的静态 URL（与资讯分类筛选同一套做法）
 *   3. **卡片网格** —— 按「户型小→大、档位低→高」排，同户型的三档一定挨着
 *
 * 筛选条只列**真的有已发布套餐**的户型（控制器算好传进来），列出空档位等于
 * 让访客点进一个空页面。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\HouseLayout;
    use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\PackageTier;
    use Filamentboot\FilamentbootSite\Support\ContentTypeLabels;

    $activeLayout = $layout?->value;
@endphp

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="packages-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-10 max-w-2xl">
                <h1 id="packages-heading" class="text-site-primary text-4xl font-bold mb-4">
                    {{ ContentTypeLabels::package() }}
                </h1>
                @if($siteSettings?->list_intro_packages_zh)
                    <p class="text-site-secondary text-lg leading-relaxed">
                        {{ $siteSettings->list_intro_packages_zh }}
                    </p>
                @endif
            </div>

            {{-- 三档说明。数据来自 PackageTier 枚举，不在视图里硬编码文案——
                 后台下拉、筛选、这里三处必须是同一套说法 --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-12">
                @foreach(PackageTier::ordered() as $tierCase)
                    <div class="bg-site-surface border border-site rounded-2xl p-5">
                        <p class="text-site-primary font-bold text-base mb-2">{{ $tierCase->label() }}</p>
                        <p class="text-site-secondary text-sm leading-relaxed">{{ $tierCase->summary() }}</p>
                    </div>
                @endforeach
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
                <div class="py-16 text-center">
                    <p class="text-site-secondary text-base mb-6">
                        @if($layout !== null)
                            {{ $layout->label() }}暂时没有现成套餐。
                        @else
                            套餐正在整理中。
                        @endif
                    </p>
                    {{-- 空态也要留转化入口：没有现成套餐不代表做不了，
                         这时候的访客反而是最需要人接手的那一批 --}}
                    <div x-data class="inline-block">
                        <button type="button"
                                data-contact-trigger="package-empty"
                                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-full text-sm
                                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
                                @click="$store.contactPanel.show('package-empty')"
                                aria-controls="contact-panel">
                            说一下户型，我们帮你配一套
                        </button>
                    </div>
                </div>
            @endif

        </div>
    </section>
@endsection
