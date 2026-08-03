{{--
 * 案例列表页（tech-product 浅色主题）
 *
 * 标题 + Livewire CaseFilter（筛选 pills + 网格 + 分页）
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="cases-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-10 max-w-2xl">
                <h1 id="cases-heading" class="text-site-primary text-4xl font-bold tracking-tight mb-3">装修案例</h1>
                <p class="text-site-secondary text-base leading-relaxed">
                    真实交付的项目，含户型、预算区间与落地的智能配置。
                </p>
            </div>

            @livewire('filamentboot-site::case-filter')

        </div>
    </section>
@endsection
