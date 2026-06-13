{{--
 * 静态页详情（关于我们/联系我们等，UI-SPEC §Component 9 风格）
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 --}}
@extends('filament-admin-site::layouts.app')

@php
    $isZh    = app()->getLocale() !== 'en';
    $title   = $isZh ? ($record->title_zh ?? '') : ($record->title_en ?? $record->title_zh ?? '');
    $content = $isZh ? ($record->content_zh ?? '') : ($record->content_en ?? $record->content_zh ?? '');
@endphp

@section('content')
    <div class="max-w-3xl mx-auto py-16 px-4 sm:px-6">

        <h1 class="text-site-primary text-3xl font-bold mb-8 leading-tight">{{ $title }}</h1>

        {{-- 富文本内容（必须经 purifier 过滤，T-10-05-01 安全硬要求） --}}
        @if($content)
            <div class="prose text-site-primary leading-relaxed" style="word-break: break-all;">
                {!! app('purifier')->clean($content) !!}
            </div>
        @endif
    </div>
@endsection
