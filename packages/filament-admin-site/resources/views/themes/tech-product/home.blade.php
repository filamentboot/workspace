{{--
 * tech-product 主题首页骨架（v0.5 最小占位，UI-SPEC §tech-product skeleton requirement）
 *
 * 仅包含 Hero 占位结构，用浅色语义工具类（bg-site-base/text-site-primary）。
 * 完整模板在 v1.x 完善。
 --}}
@extends('filament-admin-site::layouts.app')

@php
    $isZh = app()->getLocale() !== 'en';
@endphp

@section('content')
    {{-- tech-product 主题骨架，完整模板 v1.x --}}
    <section class="min-h-dvh min-h-screen bg-site-base flex items-center justify-center px-4"
             aria-labelledby="tp-hero-heading">
        <div class="max-w-3xl mx-auto text-center py-32">

            <p class="text-site-accent text-sm font-normal tracking-[0.2em] uppercase mb-6">
                {{ $isZh ? '智能家居 · 科技产品' : 'Smart Home · Tech Products' }}
            </p>

            <h1 id="tp-hero-heading"
                class="text-4xl md:text-5xl lg:text-6xl font-bold text-site-primary mb-6 leading-tight">
                {{ $isZh ? '让家更智能，让生活更美好' : 'Making Homes Smarter, Lives Better' }}
            </h1>

            <p class="text-site-secondary text-base md:text-lg leading-relaxed max-w-2xl mx-auto mb-10">
                {{ $isZh ? '我们将智能科技与精致设计融为一体。' : 'We fuse smart technology with refined design.' }}
            </p>

            <div class="inline-flex items-center px-4 py-2 rounded-full bg-site-elevated border border-site text-site-muted text-sm">
                {{ $isZh ? 'tech-product 主题骨架 — 完整模板 v1.x 完善' : 'tech-product theme skeleton — full template in v1.x' }}
            </div>
        </div>
    </section>
@endsection
