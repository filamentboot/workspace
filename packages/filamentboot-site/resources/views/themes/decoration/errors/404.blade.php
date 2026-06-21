{{--
 * 404 错误页（UI-SPEC §Component 12）
 *
 * 渐变 "404" 大字 + 标题 + 返回首页 CTA。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $isZh = app()->getLocale() !== 'en';
@endphp

@section('content')
    <section class="min-h-screen bg-site-base flex items-center justify-center px-4"
             aria-labelledby="error-heading">
        <div class="text-center max-w-lg mx-auto">

            {{-- 大字 "404"（渐变文字） --}}
            <p class="text-7xl font-bold mb-6"
               style="background: linear-gradient(to right, #00d4ff, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                404
            </p>

            <h1 id="error-heading"
                class="text-site-primary text-2xl font-bold mb-4">
                {{ $isZh ? '页面不存在' : 'Page Not Found' }}
            </h1>

            <p class="text-site-secondary text-base mb-10">
                {{ $isZh ? '您访问的页面不存在，请返回首页' : "The page you're looking for doesn't exist." }}
            </p>

            <a href="{{ $isZh ? url('/') : url('/en') }}"
               class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                      focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none">
                {{ $isZh ? '返回首页' : 'Back to Home' }}
            </a>
        </div>
    </section>
@endsection
