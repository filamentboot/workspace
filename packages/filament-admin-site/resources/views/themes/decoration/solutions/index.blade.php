{{--
 * 智能方案列表页
 --}}
@extends('filament-admin-site::layouts.app')

@php
    $isZh = app()->getLocale() !== 'en';
@endphp

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="solutions-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-12">
                <h1 id="solutions-heading"
                    class="text-site-primary text-4xl font-bold mb-4">
                    {{ $isZh ? '智能解决方案' : 'Smart Solutions' }}
                </h1>
                <p class="text-site-secondary text-lg">
                    {{ $isZh ? '针对不同场景提供专业智能家居解决方案，满足您的个性化需求。' : 'Professional smart home solutions tailored to different scenarios and individual needs.' }}
                </p>
            </div>

            @if(isset($records) && $records->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($records as $solution)
                        @php
                            $sTitle = $isZh ? ($solution->title_zh ?? '') : ($solution->title_en ?? $solution->title_zh ?? '');
                            $sDesc  = $isZh ? ($solution->description_zh ?? '') : ($solution->description_en ?? $solution->description_zh ?? '');
                            $sCover = $solution->cover_image ?? 'https://picsum.photos/seed/solution-' . ($solution->slug ?? '') . '/800/600';
                            $sUrl   = $isZh ? url('/solutions/' . $solution->slug) : url('/en/solutions/' . $solution->slug);
                            $altText = $sTitle . ($isZh ? ' — 智能方案' : ' — Solution');
                        @endphp
                        <article class="bg-site-surface rounded-2xl overflow-hidden border border-site card-hover">
                            <div class="aspect-[4/3] overflow-hidden bg-site-elevated">
                                <img src="{{ $sCover }}"
                                     alt="{{ $altText }}"
                                     class="w-full h-full object-cover img-blur-up"
                                     loading="lazy"
                                     decoding="async"
                                     width="800"
                                     height="600"
                                     x-on:load="$el.classList.add('loaded')">
                            </div>
                            <div class="p-6">
                                @if($solution->price_range)
                                    <span class="bg-site-elevated text-site-muted text-xs px-2 py-1 rounded-full mb-3 inline-block">
                                        {{ $solution->price_range }}
                                    </span>
                                @endif
                                <h2 class="text-site-primary font-bold text-xl mb-3 leading-tight">{{ $sTitle }}</h2>
                                @if($sDesc)
                                    <p class="text-site-secondary text-sm leading-relaxed line-clamp-3 mb-4">{{ $sDesc }}</p>
                                @endif
                                <a href="{{ $sUrl }}"
                                   class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm"
                                   aria-label="{{ ($isZh ? '查看详情：' : 'View: ') . $sTitle }}">
                                    {{ $isZh ? '查看详情' : 'View Details' }}
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- 分页 --}}
                @if($records->hasPages())
                    <div class="mt-12">
                        {{ $records->links() }}
                    </div>
                @endif

            @else
                <div class="py-16 text-center">
                    <p class="text-site-muted text-base">
                        {{ $isZh ? '暂无方案，敬请期待' : 'No solutions listed yet' }}
                    </p>
                </div>
            @endif

        </div>
    </section>
@endsection
