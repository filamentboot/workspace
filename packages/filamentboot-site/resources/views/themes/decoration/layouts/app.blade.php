{{--
 * decoration 主题应用布局
 *
 * extends base，包含：导航栏、主内容区、浮动询盘入口、页脚。
 * 页面通过 @section('content') 填充主体内容。
 *
 * main 底部 pb-24：为固定悬浮询盘按钮预留滚动避让空间，
 * 避免移动端按钮遮挡末条卡片或页脚首行。
 --}}
@extends('filamentboot-site::layouts.base')

@section('body')
    {{-- 顶部导航栏 --}}
    @include('filamentboot-site::components.nav')

    {{-- 主内容区（id="main-content" 供 skip-nav 锚点定位） --}}
    <main id="main-content" class="pb-24">
        @yield('content')
    </main>

    {{-- 浮动询盘入口 + 滑入面板（包含 Livewire ContactForm 组件） --}}
    @include('filamentboot-site::components.floating-contact')

    {{-- 页脚 --}}
    @include('filamentboot-site::components.footer')
@endsection
