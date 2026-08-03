{{--
 * 资讯列表页
 *
 * 分类筛选走查询参数（?category=slug）而非 Livewire：阶段 4 要把公开页做成
 * 整页缓存，每个筛选组合各自是一个可缓存的静态 URL，比动态组件更划算。
 *
 * 本主题与 tech-product 主题各自持有一份完整视图，刻意不抽公共层：
 * 宿主装机后常会只留一套主题并删掉另一套目录，共享层会让删除留下断链。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    // 0 篇文章的分类不出筛选按钮：点进去只有空列表
    $pills = $categories->filter(fn ($category): bool => (int) ($category->articles_count ?? 0) > 0);
@endphp

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="news-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-10">
                <h1 id="news-heading" class="text-site-primary text-4xl font-bold mb-4">
                    {{ $activeCategory?->name_zh ?? '智能家居资讯' }}
                </h1>
                <p class="text-site-secondary text-lg">
                    选型经验、施工细节、踩坑复盘——我们把项目里学到的东西写下来。
                </p>
            </div>

            {{-- 分类筛选 --}}
            @if($pills->isNotEmpty())
                <div class="flex flex-wrap gap-2 mb-10" role="navigation" aria-label="资讯分类筛选">
                    <a href="{{ route('site.news.index') }}"
                       class="inline-flex items-center min-h-[36px] px-4 rounded-full text-sm transition-colors duration-200
                              focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none
                              {{ $activeCategory === null
                                  ? 'bg-[--color-primary] text-white'
                                  : 'bg-site-surface border border-site text-site-secondary hover:text-site-accent' }}"
                       @if($activeCategory === null) aria-current="page" @endif>
                        全部
                    </a>

                    @foreach($pills as $category)
                        <a href="{{ route('site.news.index', ['category' => $category->slug]) }}"
                           class="inline-flex items-center min-h-[36px] px-4 rounded-full text-sm transition-colors duration-200
                                  focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none
                                  {{ $activeCategory?->id === $category->id
                                      ? 'bg-[--color-primary] text-white'
                                      : 'bg-site-surface border border-site text-site-secondary hover:text-site-accent' }}"
                           @if($activeCategory?->id === $category->id) aria-current="page" @endif>
                            {{ $category->name_zh }}
                            <span class="ml-1 opacity-60">{{ $category->articles_count }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

                {{-- 文章列表 --}}
                <div class="lg:col-span-3">
                    @if($records->isNotEmpty())
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach($records as $article)
                                @include('filamentboot-site::components.news-card', ['article' => $article])
                            @endforeach
                        </div>

                        @if($records->hasPages())
                            <div class="mt-12">
                                {{ $records->links() }}
                            </div>
                        @endif
                    @else
                        <div class="py-16 text-center">
                            <p class="text-site-secondary text-base">暂无资讯，敬请期待</p>
                        </div>
                    @endif
                </div>

                {{-- 归档侧栏（无文章时整块不渲染） --}}
                @if($archiveMonths->isNotEmpty())
                    <aside class="lg:col-span-1" aria-labelledby="news-archive-heading">
                        <div class="bg-site-surface rounded-2xl border border-site p-5 lg:sticky lg:top-24">
                            <h2 id="news-archive-heading" class="text-site-muted text-xs uppercase tracking-widest mb-4">
                                按月归档
                            </h2>
                            <ul class="list-none space-y-1">
                                @foreach($archiveMonths as $month => $count)
                                    @php [$archiveYear, $archiveMonth] = explode('-', $month); @endphp
                                    <li>
                                        <a href="{{ route('site.news.archive', ['year' => $archiveYear, 'month' => $archiveMonth]) }}"
                                           class="flex items-center justify-between min-h-[36px] px-2 rounded-lg text-sm text-site-secondary
                                                  hover:text-site-accent hover:bg-site-elevated transition-colors duration-200
                                                  focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none">
                                            <span>{{ (int) $archiveYear }} 年 {{ (int) $archiveMonth }} 月</span>
                                            <span class="text-site-muted text-xs">{{ $count }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </aside>
                @endif
            </div>
        </div>
    </section>
@endsection
