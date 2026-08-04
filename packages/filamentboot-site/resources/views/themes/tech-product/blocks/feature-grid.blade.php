{{--
 * 特性网格区块（tech-product 浅色主题，#13）
 *
 * 列数用固定类名映射而不是拼字符串：Tailwind 扫源码收集类名，
 * "lg:grid-cols-{$n}" 这种拼法编译产物里没有对应规则，线上会退化成一列。
 *
 * 图标是作者手填的 Heroicons 名称，blade-icons 的 svg() 遇到未注册名称会抛
 * SvgNotFound。用 rescue() 兜住并静默降级——一个打错的图标名不该让整页 500，
 * 也不该在页面上留个报错块。
 --}}
@php
    $title   = (string) ($data['title'] ?? '');
    $columns = (int) ($data['columns'] ?? 3);
    $items   = is_array($data['items'] ?? null) ? $data['items'] : [];

    $gridClass = match ($columns) {
        2       => 'grid-cols-1 sm:grid-cols-2',
        4       => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
        default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    };

    $headingId = 'block-feature-grid-' . $index;
@endphp

@if($items !== [])
    <section class="py-16 bg-site-base" @if($title !== '') aria-labelledby="{{ $headingId }}" @endif>
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            @if($title !== '')
                <h2 id="{{ $headingId }}"
                    class="text-site-primary text-2xl md:text-3xl font-bold tracking-tight mb-8">
                    {{ $title }}
                </h2>
            @endif

            <div class="grid {{ $gridClass }} gap-6">
                @foreach($items as $item)
                    @php
                        $itemTitle = (string) ($item['title'] ?? '');
                        $itemDesc  = (string) ($item['description'] ?? '');
                        $iconName  = trim((string) ($item['icon'] ?? ''));
                        // 字符集先卡一道：blade-icons 按名称拼文件路径，带 .. 或 / 的名字
                        // 能读到图标集目录之外的任意 .svg 并原样输出到页面
                        $iconHtml  = preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $iconName) === 1
                            ? rescue(fn (): string => svg($iconName, 'w-6 h-6')->toHtml(), '', report: false)
                            : '';
                    @endphp
                    <article class="bg-site-surface rounded-xl border border-site p-5 card-hover">
                        @if($iconHtml !== '')
                            <div class="text-site-accent mb-3" aria-hidden="true">{!! $iconHtml !!}</div>
                        @endif

                        <h3 class="text-site-primary font-semibold text-base mb-2 leading-snug">{{ $itemTitle }}</h3>

                        @if($itemDesc !== '')
                            <p class="text-site-secondary text-sm leading-relaxed whitespace-pre-line">{{ $itemDesc }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
