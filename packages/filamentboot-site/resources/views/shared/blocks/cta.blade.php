{{--
 * 行动号召区块（两套主题共用，#13）
 *
 * button_url 留空时按钮改为打开询盘面板，来源标识 page-cta（CtaBlock 的设计意图，
 * 已登记进 config 的 contact.sources）——这样区块本身就是一个可归因的转化入口。
 * 面板由 $store.contactPanel 管理，与悬浮按钮、导航 CTA 共用同一个（§0.5）。
 *
 * 填了 button_url 但被 SafeUrl 拦下（javascript: 一类）时**不降级成询盘面板**：
 * 那会让作者以为链接生效了，实际点出来是另一个东西。宁可不渲染按钮。
 --}}
@php
    $title       = (string) ($data['title'] ?? '');
    $description = (string) ($data['description'] ?? '');
    $buttonLabel = (string) ($data['button_label'] ?? '');
    $rawUrl      = trim((string) ($data['button_url'] ?? ''));
    $buttonUrl   = \Filamentboot\FilamentbootSite\Support\SafeUrl::sanitize($rawUrl);

    // 空链接 → 询盘面板；非空但不安全 → 什么都不渲染
    $opensPanel = $rawUrl === '';
    $showButton = $buttonLabel !== '' && ($opensPanel || $buttonUrl !== null);

    $subtle    = ($data['style'] ?? 'primary') === 'subtle';
    $headingId = 'block-cta-' . $index;
@endphp

<section class="py-16 {{ $subtle ? 'bg-site-base' : 'bg-site-subtle' }}" aria-labelledby="{{ $headingId }}">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">

        <div class="{{ $subtle ? '' : 'rounded-2xl border border-site-glow bg-site-surface px-6 py-12 sm:px-12' }}">
            <h2 id="{{ $headingId }}"
                class="text-site-primary text-2xl md:text-3xl font-bold leading-tight mb-4">
                {{ $title }}
            </h2>

            @if($description !== '')
                <p class="text-site-secondary text-base leading-relaxed mb-8 whitespace-pre-line">{{ $description }}</p>
            @endif

            @if($showButton)
                @if($opensPanel)
                    {{-- 必须带 x-data：Alpine 只初始化 x-data 根之内的元素，
                         裸 <button> 上的 @click 不会被处理，点了不会有反应 --}}
                    <div x-data class="inline-block">
                        <button type="button"
                                data-contact-trigger="page-cta"
                                class="{{ $subtle ? 'btn-site-outline' : 'btn-site-primary' }} inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
                                @click="$store.contactPanel.show('page-cta')"
                                aria-controls="contact-panel">
                            {{ $buttonLabel }}
                        </button>
                    </div>
                @else
                    <a href="{{ $buttonUrl }}"
                       class="{{ $subtle ? 'btn-site-outline' : 'btn-site-primary' }} inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                              focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none">
                        {{ $buttonLabel }}
                    </a>
                @endif
            @endif
        </div>
    </div>
</section>
