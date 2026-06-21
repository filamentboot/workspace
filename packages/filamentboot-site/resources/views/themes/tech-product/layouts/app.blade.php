{{--
 * tech-product 主题应用布局骨架（v0.5 最小结构，完整模板 v1.x）
 --}}
@extends('filamentboot-site::layouts.base')

@section('body')
    {{-- 导航占位（v1.x 完整导航）--}}
    <header class="h-16 bg-site-surface border-b border-site flex items-center px-4 sm:px-6 lg:px-8">
        <div class="max-w-screen-xl mx-auto w-full flex items-center justify-between">
            @php
                $isZh = app()->getLocale() !== 'en';
                $companyName = optional($siteSettings ?? null)->{$isZh ? 'company_name_zh' : 'company_name_en'}
                    ?? config('app.name', '晴空妙享科技');
            @endphp
            <a href="{{ $isZh ? url('/') : url('/en') }}"
               class="text-site-primary font-bold text-xl">{{ $companyName }}</a>
            {{-- v1.x: 完整导航链接 --}}
        </div>
    </header>

    {{-- 主内容区 --}}
    <main id="main-content">
        @yield('content')
    </main>

    {{-- 页脚占位（v1.x 完整页脚）--}}
    <footer class="bg-site-surface border-t border-site py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-screen-xl mx-auto text-center">
            <p class="text-site-muted text-xs">
                &copy; {{ date('Y') }} {{ $companyName ?? '' }}
            </p>
        </div>
    </footer>
@endsection
