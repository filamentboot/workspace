{{--
 * 标签聚合页（两套主题共用）
 *
 * 版式照搬站内搜索结果页：标题 + 一句摘要 + 链接。聚合页要的就是这个密度——
 * 用封面卡片的话，五类内容各有各的卡片组件（案例、套餐、产品各一套，方案压根没有），
 * 一页里混排四种卡片既难看也没必要。
 *
 * 每个分组底部都留一条「查看全部」指向该类型的列表页：标签页本身处在
 * 「详情 → 标签 → ？」这条路径的中间，不给出口就成了新的断头路。
 *
 * 七期批次 1（2026-08-11）起两套主题共用这份视图，落在 resources/views/shared/。
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="tag-heading">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('filamentboot-site::components.breadcrumb')

            <h1 id="tag-heading" class="text-site-primary text-3xl md:text-4xl font-bold tracking-tight mb-3">
                {{ $record->name_zh }}
            </h1>

            <p class="text-site-muted text-sm mb-10">
                共 {{ $totalCount }} 条内容标注了「{{ $record->name_zh }}」
            </p>

            @foreach($groups as $group)
                <section class="mb-10" aria-labelledby="tag-group-{{ $group['key'] }}">
                    <h2 id="tag-group-{{ $group['key'] }}"
                        class="text-site-accent text-xs font-bold tracking-widest uppercase mb-4">
                        {{ $group['label'] }}
                    </h2>

                    <ul class="space-y-4">
                        @foreach($group['hits'] as $hit)
                            <li class="bg-site-surface rounded-2xl border border-site p-5 card-hover">
                                <a href="{{ $hit['url'] }}"
                                   class="block focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-xl">
                                    <h3 class="text-site-primary font-bold text-base leading-snug mb-2">{{ $hit['title'] }}</h3>
                                    @if($hit['excerpt'] !== '')
                                        <p class="text-site-secondary text-sm leading-relaxed line-clamp-2">{{ $hit['excerpt'] }}</p>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-3">
                        <a href="{{ $group['indexUrl'] }}"
                           class="text-site-accent text-sm hover:underline
                                  focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded">
                            @if($group['hasMore'])
                                还有更多{{ $group['label'] }}，看全部 →
                            @else
                                查看全部{{ $group['label'] }} →
                            @endif
                        </a>
                    </p>
                </section>
            @endforeach

        </div>
    </section>
@endsection
