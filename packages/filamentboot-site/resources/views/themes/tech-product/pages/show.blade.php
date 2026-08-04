{{--
 * 静态页详情（tech-product 浅色主题）
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $title   = $record->title_zh ?? '';
    $content = $record->content_zh ?? '';
@endphp

@section('content')
    <div class="max-w-3xl mx-auto py-16 px-4 sm:px-6">

        @include('filamentboot-site::components.breadcrumb')

        <h1 class="text-site-primary text-3xl md:text-4xl font-bold tracking-tight mb-8 leading-tight">{{ $title }}</h1>

        @if($content)
            <div class="prose text-site-primary leading-relaxed" style="word-break: break-word;">
                {!! \Filamentboot\FilamentbootSite\Support\RichText::purify($content) !!}
            </div>
        @endif
    </div>
@endsection
