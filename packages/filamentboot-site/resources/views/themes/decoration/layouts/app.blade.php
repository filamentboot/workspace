{{--
 * decoration 主题应用布局
 *
 * extends base，包含：导航栏、主内容区、浮动询盘入口、页脚。
 * 页面通过 @section('content') 填充主体内容。
 *
 * main 底部避让：桌面端 pb-24 让开悬浮询盘按钮；移动端 pb-32 让开底部三段式
 * 操作条（56px 条高 + 安全区），否则末条卡片或页脚首行会被压住。
 --}}
@extends('filamentboot-site::layouts.base')

@section('body')
    {{-- 顶部导航栏 --}}
    @include('filamentboot-site::components.nav')

    {{-- 主内容区（id="main-content" 供 skip-nav 锚点定位） --}}
    <main id="main-content" class="pb-32 sm:pb-24">
        @yield('content')
    </main>

    {{-- 浮动询盘入口 + 滑入面板（包含 Livewire ContactForm 组件） --}}
    @include('filamentboot-site::components.floating-contact')

    {{-- 移动端底部三段式操作条（sm 以下，与悬浮气泡互斥） --}}
    @include('filamentboot-site::components.mobile-action-bar')

    {{-- 页脚 --}}
    @include('filamentboot-site::components.footer')
@endsection
