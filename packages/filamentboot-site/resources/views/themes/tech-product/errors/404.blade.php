{{--
 * 404 错误页（tech-product 浅色主题）
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')
    <section class="min-h-[60vh] bg-site-base flex items-center justify-center px-4" aria-labelledby="error-heading">
        <div class="text-center max-w-lg mx-auto py-20">

            <p class="text-6xl font-bold mb-6"
               style="background: linear-gradient(to right, #6366f1, #0ea5e9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                404
            </p>

            <h1 id="error-heading" class="text-site-primary text-2xl font-bold tracking-tight mb-3">页面不存在</h1>

            <p class="text-site-secondary text-base mb-8">您访问的页面不存在，请返回首页</p>

            <a href="{{ route('site.home') }}"
               class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-3 rounded-lg font-bold text-base
                      focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:outline-none">
                返回首页
            </a>
        </div>
    </section>
@endsection
