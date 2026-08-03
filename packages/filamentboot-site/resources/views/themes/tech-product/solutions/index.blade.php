{{--
 * 智能方案列表页（tech-product 浅色主题）
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="solutions-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-10 max-w-2xl">
                <h1 id="solutions-heading" class="text-site-primary text-4xl font-bold tracking-tight mb-3">智能解决方案</h1>
                <p class="text-site-secondary text-base leading-relaxed">
                    按场景打包的标准方案，含设备清单、预算区间与交付范围。
                </p>
            </div>

            @if(isset($records) && $records->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($records as $solution)
                        @php
                            $sCover = $solution->coverUrl('card');
                            $sUrl   = route('site.solutions.show', $solution->slug);
                        @endphp
                        <article class="bg-site-base rounded-xl overflow-hidden border border-site card-hover">
                            <div class="aspect-[4/3] overflow-hidden bg-site-elevated">
                                @if($sCover)
                                    <img src="{{ $sCover }}" alt="{{ $solution->title_zh }} — 智能方案"
                                         class="w-full h-full object-cover img-blur-up"
                                         loading="lazy" decoding="async" width="800" height="600"
                                         x-on:load="$el.classList.add('loaded')">
                                @else
                                    @include('filamentboot-site::components.image-placeholder', ['label' => '智能方案'])
                                @endif
                            </div>
                            <div class="p-5">
                                @if($solution->price_range)
                                    <span class="inline-block bg-site-elevated text-site-secondary text-xs px-2 py-0.5 rounded-md mb-3">
                                        {{ $solution->price_range }}
                                    </span>
                                @endif
                                <h2 class="text-site-primary font-semibold text-lg mb-2 leading-snug">{{ $solution->title_zh }}</h2>
                                @if($solution->description_zh)
                                    <p class="text-site-secondary text-sm leading-relaxed line-clamp-3 mb-4">{{ $solution->description_zh }}</p>
                                @endif
                                <a href="{{ $sUrl }}"
                                   class="text-site-accent text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm"
                                   aria-label="查看详情：{{ $solution->title_zh }}">
                                    查看详情 &rarr;
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if($records->hasPages())
                    <div class="mt-12">{{ $records->links() }}</div>
                @endif
            @else
                <div class="py-16 text-center">
                    <p class="text-site-secondary text-base">暂无方案，敬请期待</p>
                </div>
            @endif

        </div>
    </section>
@endsection
