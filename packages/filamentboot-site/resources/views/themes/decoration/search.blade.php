{{--
 * 站内搜索结果页（decoration 深色主题）
 *
 * 表单是 method="get" 的普通表单：**不要**改成 POST 或加 @csrf——公开页不起 session，
 * csrf_token() 会把 Set-Cookie 带回来，整页缓存全面失效且不会有任何报错（§0.3 第 5 条）。
 * GET 也让每个关键词各自成为一个可缓存、可分享、可加书签的 URL。
 *
 * 摘要文本不做关键词高亮：高亮要输出 <mark> 就得让视图 {!! !!}，
 * 而那段文本混着作者写的富文本内容，为一个视觉效果开 HTML 注入口不值（见 SiteSearch::snippet）。
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="search-heading">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            <h1 id="search-heading" class="text-site-primary text-3xl md:text-4xl font-bold tracking-tight mb-6">
                站内搜索
            </h1>

            <form action="{{ route('site.search') }}" method="get" role="search" class="mb-10">
                <div class="flex gap-3">
                    <label for="site-search-input" class="sr-only">搜索关键词</label>
                    <input
                        id="site-search-input"
                        type="search"
                        name="q"
                        value="{{ $term }}"
                        maxlength="{{ \Filamentboot\FilamentbootSite\Cms\Services\SiteSearch::MAX_TERM_LENGTH }}"
                        placeholder="搜案例、方案、产品或资讯"
                        autocomplete="off"
                        class="flex-1 min-h-[44px] px-4 py-2 rounded-full bg-site-surface border border-site text-site-primary
                               placeholder:text-site-muted focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none">
                    <button type="submit"
                            class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-2 rounded-full text-sm whitespace-nowrap
                                   focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none">
                        搜索
                    </button>
                </div>
            </form>

            @if($term === '')
                <p class="text-site-secondary text-base">输入关键词开始搜索。</p>
            @elseif($groups === [])
                <div class="py-10">
                    <p class="text-site-primary text-base font-bold mb-3">没有找到与「{{ $term }}」相关的内容</p>
                    <p class="text-site-secondary text-sm leading-relaxed mb-6">
                        换个更短的词试试，或者直接告诉我们你要找什么。
                    </p>
                    <div x-data class="inline-block">
                        <button type="button"
                                data-contact-trigger="search-empty"
                                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-2 rounded-full text-sm
                                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none"
                                @click="$store.contactPanel.show('search-empty')"
                                aria-controls="contact-panel">
                            让顾问帮我找
                        </button>
                    </div>
                </div>
            @else
                <p class="text-site-muted text-sm mb-8">
                    找到 {{ $resultCount }} 条与「{{ $term }}」相关的内容
                </p>

                @foreach($groups as $group)
                    <section class="mb-10" aria-labelledby="search-group-{{ $group['key'] }}">
                        <h2 id="search-group-{{ $group['key'] }}"
                            class="text-site-accent text-xs font-bold tracking-widest uppercase mb-4">
                            {{ $group['label'] }}
                        </h2>

                        <ul class="space-y-4">
                            @foreach($group['hits'] as $hit)
                                <li class="bg-site-surface rounded-2xl border border-site p-5 card-hover">
                                    <a href="{{ $hit['url'] }}"
                                       class="block focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-xl">
                                        <h3 class="text-site-primary font-bold text-base leading-snug mb-2">{{ $hit['title'] }}</h3>
                                        @if($hit['excerpt'] !== '')
                                            <p class="text-site-secondary text-sm leading-relaxed line-clamp-2">{{ $hit['excerpt'] }}</p>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>

                        @if($group['hasMore'])
                            <p class="text-site-muted text-xs mt-3">
                                {{ $group['label'] }}下还有更多结果，换个更具体的关键词能看到更准的内容。
                            </p>
                        @endif
                    </section>
                @endforeach
            @endif

        </div>
    </section>
@endsection
