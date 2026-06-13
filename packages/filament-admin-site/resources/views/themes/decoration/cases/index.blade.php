{{--
 * 案例列表页
 *
 * 标题 + Livewire CaseFilter（含筛选 pills + 网格，UI-SPEC §Component 11）
 --}}
@extends('filament-admin-site::layouts.app')

@php
    $isZh = app()->getLocale() !== 'en';
@endphp

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="cases-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-12">
                <h1 id="cases-heading"
                    class="text-site-primary text-4xl font-bold mb-4">
                    {{ $isZh ? '装修案例' : 'Cases' }}
                </h1>
                <p class="text-site-secondary text-lg">
                    {{ $isZh ? '探索我们的精选智能家居装修案例，感受设计与科技的完美融合。' : 'Explore our curated smart home renovation cases — where design meets technology.' }}
                </p>
            </div>

            {{-- Livewire 筛选组件（含网格 + 分页，UI-SPEC §Component 11） --}}
            <livewire:filament-admin-site::case-filter />

        </div>
    </section>
@endsection
