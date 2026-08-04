{{--
 * decoration 主题：落地页极简版式（#28）
 *
 * 与 pages/show 的区别是**刻意不 extends layouts.app**：那份布局带完整导航栏与页脚，
 * 而落地页的存在意义就是把访客的出路收敛到一个转化动作上。导航栏上每个栏目链接
 * 都是一条离开转化漏斗的岔路。
 *
 * 因此直接 extends layouts.base（只要 SEO meta、询盘面板 Store、主题 CSS、统计代码），
 * 自己搭一个极简头部与法定信息页脚。
 *
 * 仍然 include floating-contact：滑入式询盘面板本体在那里，不引进来
 * $store.contactPanel.show() 会改了状态却没有面板可显示。它同时就是本页的转化入口。
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 --}}
@extends('filamentboot-site::layouts.base')

@php
    $title   = $record->title_zh ?? '';
    $content = $record->content_zh ?? '';

    $hasBlocks = filled((string) ($blocksHtml ?? ''));

    $companyName = $siteSettings->company_name_zh ?? config('app.name');
    $logoPath    = $siteSettings->logo ?? null;
    $phone       = trim((string) ($siteSettings->phone ?? ''));
    $icp         = trim((string) ($siteSettings->icp_number ?? ''));
@endphp

@section('body')
    {{-- 极简头部：只有品牌与唯一转化动作，没有栏目链接 --}}
    <header class="sticky top-0 z-40 h-16 bg-site-surface/90 backdrop-blur-md border-b border-site">
        <div class="max-w-screen-lg mx-auto px-4 sm:px-6 h-full flex items-center justify-between gap-4">
            <a href="{{ route('site.home') }}" class="flex items-center gap-3 min-h-[44px]">
                @if($logoPath)
                    <img src="{{ $logoPath }}" alt="{{ $companyName }}" class="max-h-10 w-auto">
                @else
                    <span class="text-site-primary font-bold text-lg">{{ $companyName }}</span>
                @endif
            </a>

            <div class="flex items-center gap-2">
                @if($phone !== '')
                    <a href="tel:{{ $phone }}"
                       class="hidden sm:inline-flex items-center min-h-[44px] px-3 text-sm text-site-secondary hover:text-site-accent transition-colors duration-200">
                        {{ $phone }}
                    </a>
                @endif

                <button type="button"
                        data-contact-trigger="landing-header"
                        class="btn-site-primary inline-flex items-center min-h-[44px] px-5 rounded-full text-sm font-bold
                               focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none"
                        @click="$store.contactPanel.show('landing-header')">
                    免费获取方案
                </button>
            </div>
        </div>
    </header>

    <main id="main-content" class="pb-32 sm:pb-24">
        {{-- 不出面包屑：面包屑本身就是一组返回上层的链接 --}}
        @if($title !== '' || $content !== '')
            <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-12 {{ $hasBlocks ? 'pb-6' : 'pb-16' }}">
                @if($title !== '')
                    <h1 class="text-site-primary text-3xl sm:text-4xl font-bold mb-6 leading-tight">{{ $title }}</h1>
                @endif

                @if($content)
                    <div class="prose text-site-primary leading-relaxed" style="word-break: break-word;">
                        {!! \Filamentboot\FilamentbootSite\Support\RichText::purify($content) !!}
                    </div>
                @endif
            </div>
        @endif

        {{-- 区块放在窄容器之外：hero / feature-grid 一类通栏区块自带留白 --}}
        @if($hasBlocks)
            {!! $blocksHtml !!}
        @endif
    </main>

    {{-- 询盘面板本体（同时是本页转化入口） --}}
    @include('filamentboot-site::components.floating-contact')

    {{-- 移动端底部操作条：落地页上它就是常驻的转化条 --}}
    @include('filamentboot-site::components.mobile-action-bar')

    {{-- 法定信息页脚：ICP 备案号是国内站点对外发布的硬要求，不能省 --}}
    <footer class="border-t border-site py-8">
        <div class="max-w-screen-lg mx-auto px-4 sm:px-6 text-center text-xs text-site-muted space-y-1">
            <p>© {{ now()->year }} {{ $companyName }}</p>
            @if($icp !== '')
                <p>{{ $icp }}</p>
            @endif
        </div>
    </footer>
@endsection
