{{--
 * 404 错误页（UI-SPEC §Component 12）
 *
 * 渐变 "404" 大字 + 标题 + 返回首页 CTA。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    /*
     * 错误页自带一份 $seoData。不给的话 seo-meta 会退回站点默认标题，
     * 于是每个 404 都和首页同名、还自指一个 canonical——对着一个 404 声明
     * 「这是本页的规范地址」是自相矛盾的信号。
     */
    $seoData = [
        'title'     => '页面不存在',
        'canonical' => false,
    ];
@endphp

@section('content')
    <section class="min-h-screen bg-site-base flex items-center justify-center px-4"
             aria-labelledby="error-heading">
        <div class="text-center max-w-lg mx-auto">

            {{-- 大字 "404"
                 与 components/hero.blade.php 同一处理：去掉 bg-clip-text 渐变，
                 改纯色强调。原本硬编码 #00d4ff → #3b82f6，亮青当文字用在白底上
                 只有 1.77:1，整个数字会看不见；而本主题只允许一个强调色。 --}}
            <p class="text-site-accent text-7xl font-bold mb-6">
                404
            </p>

            <h1 id="error-heading" class="text-site-primary text-2xl font-bold mb-4">页面不存在</h1>

            <p class="text-site-secondary text-base mb-10">您访问的页面不存在，请返回首页</p>

            <a href="{{ route('site.home') }}"
               class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                      focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none">
                返回首页
            </a>
        </div>
    </section>
@endsection
