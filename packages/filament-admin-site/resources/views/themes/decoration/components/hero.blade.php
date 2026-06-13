{{--
 * Hero 组件（UI-SPEC §Component 2）
 *
 * min-h-dvh 全屏高度，eyebrow 标签，响应式 H1，品牌渐变关键词，副标题，CTA 行，scroll indicator。
 * 文案来自 UI-SPEC §Copywriting，支持中英双语。
 --}}
@php
    $isZh = app()->getLocale() !== 'en';
    $eyebrow  = $isZh ? '智能家居 · 设计驱动改造' : 'Smart Home · Design-Driven Renovation';
    $slogan1  = $isZh ? '让家更智能，' : 'Making Homes';
    $slogan2  = $isZh ? '让生活更美好' : 'Smarter, Lives Better';
    $subline  = $isZh
        ? '我们将智能科技与精致设计融为一体，为您打造真正属于未来的家居空间'
        : 'We fuse smart technology with refined design to build living spaces that belong to the future.';
    $ctaLabel     = $isZh ? '预约咨询' : 'Book a Consultation';
    $ctaSecondary = $isZh ? '查看案例' : 'View Cases';
    $casesUrl     = $isZh ? url('/cases') : url('/en/cases');
@endphp

<section class="relative min-h-dvh min-h-screen overflow-hidden bg-site-base flex items-center justify-center"
         aria-labelledby="hero-heading">

    {{-- 背景装饰光圈（径向渐变，只在支持 motion 时动画） --}}
    <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
        <div class="absolute inset-0"
             style="background: radial-gradient(ellipse 80% 60% at 50% 100%, rgba(0, 212, 255, 0.10), transparent);">
        </div>
        @media (prefers-reduced-motion: no-preference) {}
        <div class="hero-glow-pulse absolute left-1/2 bottom-0 w-96 h-96 -translate-x-1/2 translate-y-1/2 rounded-full"
             style="background: radial-gradient(circle, rgba(0, 212, 255, 0.08), transparent 70%);">
        </div>
    </div>

    {{-- 主体内容 --}}
    <div class="relative z-10 max-w-4xl mx-auto text-center px-4 md:px-8 py-32">

        {{-- Eyebrow 标签 --}}
        <p class="text-site-accent text-sm font-normal tracking-[0.2em] uppercase mb-6">
            {{ $eyebrow }}
        </p>

        {{-- H1 响应式标题 --}}
        <h1 id="hero-heading"
            class="text-4xl md:text-5xl lg:text-6xl xl:text-7xl font-bold text-site-primary leading-tight lg:leading-[1.1] mb-6">
            {{ $slogan1 }}<span class="bg-gradient-to-r from-[#00d4ff] to-[#3b82f6] bg-clip-text"
                 style="-webkit-text-fill-color: transparent;">{{ $slogan2 }}</span>
        </h1>

        {{-- 副标题 --}}
        <p class="text-site-secondary text-base md:text-lg leading-relaxed max-w-2xl mx-auto mb-12">
            {{ $subline }}
        </p>

        {{-- CTA 行 --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <button
                type="button"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none"
                onclick="document.getElementById('contact-panel')?.classList.remove('hidden')"
                aria-label="{{ $ctaLabel }}">
                {{ $ctaLabel }}
            </button>
            <a href="{{ $casesUrl }}"
               class="btn-site-outline inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                      focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none">
                {{ $ctaSecondary }}
            </a>
        </div>
    </div>

    {{-- 向下滚动指示器 --}}
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2" aria-hidden="true">
        <svg class="w-6 h-6 text-site-muted animate-bounce" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </div>
</section>
