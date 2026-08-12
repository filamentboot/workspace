{{--
 * 常见问题区块（两套主题共用，#13）
 *
 * 用原生 <details>/<summary> 而不是 Alpine 手写折叠：键盘操作、读屏软件语义、
 * 浏览器内查找（Ctrl+F 能命中折叠内容并自动展开）全部免费，且不依赖 JS——
 * #29 要把公开页做成整页缓存，少一处 JS 状态就少一处水合成本。
 *
 * 答案存纯文本（FaqBlock 的类注释已说明），因此 {{ }} 转义 + whitespace-pre-line。
 * 同一份数据由 BlockRenderer::structuredData() 生成 FAQPage 结构化数据，
 * 页面上看到的和搜索引擎读到的是同一批问答。
 --}}
@php
    $title = (string) ($data['title'] ?? '');
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];

    $headingId = 'block-faq-' . $index;
@endphp

@if($items !== [])
    <section class="py-16 bg-site-base" @if($title !== '') aria-labelledby="{{ $headingId }}" @endif>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            @if($title !== '')
                <h2 id="{{ $headingId }}"
                    class="text-site-primary text-2xl md:text-3xl font-bold mb-10 flex items-center gap-3">
                    <span class="inline-block w-1 h-8 rounded-full shrink-0" style="background: var(--color-primary);"></span>
                    {{ $title }}
                </h2>
            @endif

            <div class="space-y-4">
                @foreach($items as $item)
                    @php
                        $question = (string) ($item['question'] ?? '');
                        $answer   = (string) ($item['answer'] ?? '');
                    @endphp
                    @if($question !== '' && $answer !== '')
                        <details class="group bg-site-surface rounded-2xl border border-site overflow-hidden">
                            <summary class="flex items-center justify-between gap-4 px-6 py-4 min-h-[44px] cursor-pointer
                                            text-site-primary font-semibold text-base
                                            hover:text-site-accent transition-colors duration-200
                                            focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none">
                                <span>{{ $question }}</span>
                                {{-- Heroicons chevron-down --}}
                                <svg class="w-5 h-5 shrink-0 text-site-muted transition-transform duration-200 group-open:rotate-180"
                                     fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </summary>
                            <div class="px-6 pb-5 -mt-1">
                                <p class="text-site-secondary text-sm leading-relaxed whitespace-pre-line">{{ $answer }}</p>
                            </div>
                        </details>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endif
