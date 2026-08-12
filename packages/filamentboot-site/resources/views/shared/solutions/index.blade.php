{{--
 * 智能方案列表页
 *
 * 封面图经 HasCoverImage::coverUrl('card') 读取 Media Library，
 * 未上传时渲染内联占位组件，不再请求外部图片服务。
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')
    {{-- 投放位幻灯片。没有生效中的就整段不渲染，所以这里无条件 include。 --}}
    @include('filamentboot-site::components.banner-strip', [
        'position' => \Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerPosition::SOLUTION_INDEX_TOP,
    ])

    <section class="py-16 bg-site-base" aria-labelledby="solutions-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-12">
                <h1 id="solutions-heading" class="text-site-primary text-4xl font-bold mb-4">
                    智能解决方案
                </h1>
                @if($siteSettings?->list_intro_solutions_zh)
                    <p class="text-site-secondary text-lg">
                        {{ $siteSettings->list_intro_solutions_zh }}
                    </p>
                @endif
            </div>

            @if(isset($records) && $records->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($records as $solution)
                        @php
                            $sTitle = $solution->title_zh ?? '';
                            $sDesc  = $solution->description_zh ?? '';
                            $sCover = $solution->coverUrl('card');
                            $sUrl   = route('site.solutions.show', $solution->slug);
                        @endphp
                        <article class="bg-site-surface rounded-2xl overflow-hidden border border-site card-hover">
                            <div class="aspect-[4/3] overflow-hidden bg-site-elevated">
                                @if($sCover)
                                    <img src="{{ $sCover }}"
                                         alt="{{ $sTitle }} — {{ \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::solution() }}"
                                         class="w-full h-full object-cover img-blur-up"
                                         loading="lazy"
                                         decoding="async"
                                         width="800"
                                         height="600"
                                         x-on:load="$el.classList.add('loaded')">
                                @else
                                    @include('filamentboot-site::components.image-placeholder', ['label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::solution()])
                                @endif
                            </div>
                            <div class="p-6">
                                @if($solution->price_range)
                                    <span class="bg-site-elevated text-site-secondary text-xs px-2 py-1 rounded-full mb-3 inline-block">
                                        {{ $solution->price_range }}
                                    </span>
                                @endif
                                <h2 class="text-site-primary font-bold text-xl mb-3 leading-tight">{{ $sTitle }}</h2>
                                @if($sDesc)
                                    <p class="text-site-secondary text-sm leading-relaxed line-clamp-3 mb-4">{{ $sDesc }}</p>
                                @endif
                                <a href="{{ $sUrl }}"
                                   class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm"
                                   aria-label="查看详情：{{ $sTitle }}">
                                    查看详情
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
                    <p class="text-site-secondary text-base">暂无方案，敬请期待</p>
                </div>
            @endif

        </div>
    </section>
@endsection
