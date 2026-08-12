{{--
 * 省级页（两套主题共用）
 *
 * 只有「该省下有已发布城市页、且该省自己没有城市页」时才会走到这个视图。
 * 直辖市走的是 city/show（省页即市页），控制器里分的岔。
 *
 * 七期批次 1（2026-08-11）起两套主题共用这份视图，落在 resources/views/shared/。
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="city-province-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('filamentboot-site::components.breadcrumb')

            <div class="mb-10 max-w-2xl">
                <h1 id="city-province-heading" class="text-site-primary text-4xl font-bold mb-4">
                    {{ $region->displayName() }}全屋智能装修
                </h1>
                <p class="text-site-secondary text-lg leading-relaxed">
                    {{ $region->name }}目前有 {{ count($pages) }} 个城市的本地情况已经整理好。
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($pages as $cityPage)
                    @include('filamentboot-site::components.city-card', ['page' => $cityPage])
                @endforeach
            </div>

            <div class="mt-10">
                <a href="{{ route('site.city.index') }}"
                   class="text-site-accent text-sm hover:underline
                          focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                    &larr; 看全部服务城市
                </a>
            </div>

        </div>
    </section>
@endsection
