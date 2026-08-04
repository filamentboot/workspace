{{--
 * 富文本内容区块（decoration 深色主题，#13）
 *
 * content 是页面里唯一允许 HTML 的字段，必须经 RichText::purify() 过滤
 * （§0.3 第 2 条，安全硬要求 T-10-05-01）。禁止裸 {!! !!}，也不要退回
 * app('purifier')->clean()——那会用 mews/purifier 的 default 画像，
 * 把标题、引用、代码块、表格全部静默剥掉。
 *
 * .prose 由 resources/css/themes/decoration.css 手写提供（未装 typography 插件）。
 --}}
@php
    $title   = (string) ($data['title'] ?? '');
    $content = \Filamentboot\FilamentbootSite\Support\RichText::purify($data['content'] ?? null);

    $headingId = 'block-rich-content-' . $index;
@endphp

@if($content !== '' || $title !== '')
    <section class="py-16 bg-site-base" @if($title !== '') aria-labelledby="{{ $headingId }}" @endif>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            @if($title !== '')
                <h2 id="{{ $headingId }}"
                    class="text-site-primary text-2xl md:text-3xl font-bold mb-8 flex items-center gap-3">
                    <span class="inline-block w-1 h-8 rounded-full shrink-0" style="background: var(--color-primary);"></span>
                    {{ $title }}
                </h2>
            @endif

            @if($content !== '')
                <div class="prose text-site-primary leading-relaxed" style="word-break: break-word;">
                    {!! $content !!}
                </div>
            @endif
        </div>
    </section>
@endif
