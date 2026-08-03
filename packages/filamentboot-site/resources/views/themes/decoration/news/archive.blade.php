{{--
 * 资讯归档页（按年月）
 *
 * 归档页不入站点地图（内容与列表页重复），只作站内浏览入口。
 *
 * 本主题与 tech-product 主题各自持有一份完整视图，刻意不抽公共层：
 * 宿主装机后常会只留一套主题并删掉另一套目录，共享层会让删除留下断链。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $currentMonth = $start->format('Y-m');
    $heading      = $start->format('Y').' 年 '.(int) $start->format('m').' 月';
@endphp

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="news-archive-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-10">
                <p class="text-site-muted text-sm mb-2">资讯归档</p>
                <h1 id="news-archive-heading" class="text-site-primary text-4xl font-bold">{{ $heading }}</h1>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

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
                            <p class="text-site-secondary text-base mb-4">这个月没有发布资讯</p>
                            <a href="{{ route('site.news.index') }}"
                               class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                                查看全部资讯 &rarr;
                            </a>
                        </div>
                    @endif
                </div>

                {{-- 归档侧栏（当前月高亮） --}}
                @if($archiveMonths->isNotEmpty())
                    <aside class="lg:col-span-1" aria-labelledby="archive-nav-heading">
                        <div class="bg-site-surface rounded-2xl border border-site p-5 lg:sticky lg:top-24">
                            <h2 id="archive-nav-heading" class="text-site-muted text-xs uppercase tracking-widest mb-4">
                                按月归档
                            </h2>
                            <ul class="list-none space-y-1">
                                @foreach($archiveMonths as $month => $count)
                                    @php [$archiveYear, $archiveMonth] = explode('-', $month); @endphp
                                    <li>
                                        <a href="{{ route('site.news.archive', ['year' => $archiveYear, 'month' => $archiveMonth]) }}"
                                           class="flex items-center justify-between min-h-[36px] px-2 rounded-lg text-sm transition-colors duration-200
                                                  focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none
                                                  {{ $month === $currentMonth
                                                      ? 'bg-site-elevated text-site-accent'
                                                      : 'text-site-secondary hover:text-site-accent hover:bg-site-elevated' }}"
                                           @if($month === $currentMonth) aria-current="page" @endif>
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
