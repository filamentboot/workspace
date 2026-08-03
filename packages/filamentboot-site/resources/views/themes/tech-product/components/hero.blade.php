{{--
 * Hero（tech-product 浅色主题）
 *
 * 产品化叙事：能力短语 + 主标题 + 副标题 + 双 CTA + 关键指标条。
 * 咨询 CTA 统一走 $store.contactPanel。
 --}}
@php
    $heroBg = config('filamentboot-site.hero_background_image');

    $metrics = [
        ['value' => '200+', 'label' => '交付项目'],
        ['value' => '48h',  'label' => '方案响应'],
        ['value' => '7×12', 'label' => '售后支持'],
    ];
@endphp

<section class="relative overflow-hidden bg-site-base border-b border-site"
         @if($heroBg)
         style="background-image: url('{{ $heroBg }}'); background-size: cover; background-position: center;"
         @endif
         aria-labelledby="hero-heading">

    {{-- 背景装饰光晕 --}}
    <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
        <div class="absolute inset-0"
             style="background: radial-gradient(ellipse 70% 50% at 50% 0%, rgba(99, 102, 241, 0.10), transparent);">
        </div>
    </div>

    <div class="relative z-10 max-w-4xl mx-auto text-center px-4 md:px-8 py-24 md:py-32">

        <p class="inline-flex items-center gap-2 rounded-full border border-site bg-site-surface px-4 py-1.5 text-xs font-medium text-site-accent mb-8">
            <span class="inline-block w-1.5 h-1.5 rounded-full" style="background: var(--color-primary);"></span>
            全屋智能 · 标准化交付
        </p>

        <h1 id="hero-heading"
            class="text-4xl md:text-5xl lg:text-6xl font-bold text-site-primary leading-tight tracking-tight mb-6">
            把智能家居做成<span class="bg-gradient-to-r from-[#6366f1] to-[#0ea5e9] bg-clip-text"
                 style="-webkit-text-fill-color: transparent;">可交付的产品</span>
        </h1>

        <p class="text-site-secondary text-base md:text-lg leading-relaxed max-w-2xl mx-auto mb-10">
            从选型、设计、施工到验收售后，用一套标准流程替你把控每个环节，结果可预期、成本可核算。
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mb-16">
            <button
                type="button"
                data-contact-trigger="hero"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-3 rounded-lg font-bold text-base
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:outline-none"
                @click="$store.contactPanel.show('hero')"
                aria-controls="contact-panel"
                aria-label="预约咨询">
                预约咨询
            </button>
            <a href="{{ route('site.solutions.index') }}"
               class="btn-site-outline inline-flex items-center justify-center min-h-[44px] px-8 py-3 rounded-lg font-bold text-base
                      hover:border-site-glow transition-colors duration-200
                      focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:outline-none">
                查看方案
            </a>
        </div>

        {{-- 关键指标条 --}}
        <dl class="grid grid-cols-3 gap-4 max-w-lg mx-auto">
            @foreach($metrics as $metric)
                <div class="text-center">
                    <dt class="sr-only">{{ $metric['label'] }}</dt>
                    <dd>
                        <span class="block text-site-primary text-2xl md:text-3xl font-bold">{{ $metric['value'] }}</span>
                        <span class="block text-site-secondary text-xs mt-1">{{ $metric['label'] }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
