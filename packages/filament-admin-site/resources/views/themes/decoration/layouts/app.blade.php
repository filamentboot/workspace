{{--
 * decoration 主题应用布局
 *
 * extends base，包含：导航栏、主内容区、浮动询盘入口、页脚。
 * 页面通过 @section('content') 填充主体内容。
 --}}
@extends('filament-admin-site::layouts.base')

@section('body')
    {{-- 顶部导航栏 --}}
    @include('filament-admin-site::components.nav')

    {{-- 主内容区（id="main-content" 供 skip-nav 锚点定位） --}}
    <main id="main-content">
        @yield('content')
    </main>

    {{-- 浮动询盘入口 + 滑入面板（包含 Livewire ContactForm 组件） --}}
    @include('filament-admin-site::components.floating-contact')

    {{-- 页脚 --}}
    @include('filament-admin-site::components.footer')
@endsection
