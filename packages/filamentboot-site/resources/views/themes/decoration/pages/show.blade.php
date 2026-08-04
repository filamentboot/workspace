{{--
 * 静态页详情（关于我们/联系我们等，UI-SPEC §Component 9 风格）
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 * 区块 HTML 由 SiteFrontController 经 BlockRenderer 渲染后传入（#13）。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $title   = $record->title_zh ?? '';
    $content = $record->content_zh ?? '';

    // 预览路由与正式渲染共用同一份数据组装，这里只负责输出位置
    $hasBlocks = filled((string) ($blocksHtml ?? ''));
@endphp

@section('content')
    <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-16 {{ $hasBlocks ? 'pb-8' : 'pb-16' }}">

        @include('filamentboot-site::components.breadcrumb')

        <h1 class="text-site-primary text-3xl font-bold mb-8 leading-tight">{{ $title }}</h1>

        {{-- 富文本内容（必须经 purifier 过滤，T-10-05-01 安全硬要求） --}}
        @if($content)
            <div class="prose text-site-primary leading-relaxed" style="word-break: break-word;">
                {!! \Filamentboot\FilamentbootSite\Support\RichText::purify($content) !!}
            </div>
        @endif
    </div>

    {{-- 页面区块：接在富文本正文**之后**，两者并存不是二选一——
         存量页面全靠 content_zh，新页面可以只用区块。
         放在上面那层 max-w-3xl 容器之外：各区块视图自带 section 与横向留白，
         套进窄容器会让 hero / feature-grid 一类通栏区块铺不满。 --}}
    @if($hasBlocks)
        {!! $blocksHtml !!}
    @endif
@endsection
