{{--
 * 案例列表页
 *
 * 标题 + Livewire CaseFilter（含筛选 pills + 网格，UI-SPEC §Component 11）
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="cases-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-12">
                <h1 id="cases-heading" class="text-site-primary text-4xl font-bold mb-4">装修案例</h1>
                <p class="text-site-secondary text-lg">
                    探索我们的精选智能家居装修案例，感受设计与科技的完美融合。
                </p>
            </div>

            {{-- Livewire 筛选组件（含网格 + 分页，UI-SPEC §Component 11） --}}
            @livewire('filamentboot-site::case-filter')

        </div>
    </section>
@endsection
