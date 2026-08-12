{{--
 * Hero 组件（UI-SPEC §Component 2）
 *
 * min-h-site-screen 全屏高度，eyebrow 标签，响应式 H1，品牌渐变关键词，副标题，CTA 行，scroll indicator。
 *
 * 背景图支持（D-11-10 SITE-THEME-01）：
 * 读取 config('filamentboot-site.hero_background_image')，存在时叠加背景图（底层）
 * 并压一层浅色蒙版；无配置时回落到 bg-site-base 纯色，不报错。
 *
 * 二期浅色化时去掉了原来的两层青色径向光圈（含 .hero-glow-pulse 脉冲）——
 * 亮青在白底上糊成脏印。纯色底不再靠光圈撑观感，改由二期的幻灯片承担主视觉。
 --}}
@php
    $eyebrow  = '智能家居 · 设计驱动改造';
    $slogan1  = '让家更智能，';
    $slogan2  = '让生活更美好';
    $subline  = '我们将智能科技与精致设计融为一体，为您打造真正属于未来的家居空间';
    $ctaLabel = '预约咨询';

    // 背景图支持（D-11-10，SITE-THEME-01），无配置时回落纯色背景
    $heroBg = config('filamentboot-site.hero_background_image');
@endphp

{{-- 高度用 min-h-site-screen 而不是 dvh 的内建类：`dvh` 要 Chrome 108 /
     Safari 15.4，低于门槛整条 min-height 被丢弃、首屏塌成内容高度。
     那个类在同一条规则里先写 vh 回退再写 dvh，见 decoration.css。 --}}
<section class="relative min-h-site-screen overflow-hidden bg-site-base flex items-center justify-center"
         @if($heroBg)
         style="background-image: url('{{ $heroBg }}'); background-size: cover; background-position: center;"
         @endif
         aria-labelledby="hero-heading">

    {{-- 有背景图时压一层浅色蒙版，让深色标题压得住照片。
         与 blocks/hero.blade.php 同一套做法：提亮而非压暗，白底页面里不能突然
         出现一块暗区。原来这里是两层青色径向光圈（0.10 / 0.08 透明度）——
         浅色化后它们在白底上会糊成一片脏青印，一并去掉。 --}}
    @if($heroBg)
        <div class="absolute inset-0 bg-site-surface-80" aria-hidden="true"></div>
    @endif

    {{-- 主体内容 --}}
    <div class="relative z-10 max-w-4xl mx-auto text-center px-4 md:px-8 py-32">

        {{-- Eyebrow 标签 --}}
        <p class="text-site-accent text-sm font-normal tracking-[0.2em] uppercase mb-6">
            {{ $eyebrow }}
        </p>

        {{-- H1 响应式标题
             关键词用纯色强调，不再做渐变。原本是硬编码 #00d4ff → #3b82f6 的
             bg-clip-text 渐变文字，白底上亮青只有 1.77:1、整段不可读；而本主题
             的用色纪律是全站只有一个强调色，渐变文字等于凭空多一个色相。
             改成 text-site-accent 纯色（克莱因蓝，白底 10.69:1）。 --}}
        <h1 id="hero-heading"
            class="text-4xl md:text-5xl lg:text-6xl xl:text-7xl font-bold text-site-primary leading-tight lg:leading-[1.1] mb-6">
            {{ $slogan1 }}<span class="text-site-accent">{{ $slogan2 }}</span>
        </h1>

        {{-- 副标题 --}}
        <p class="text-site-secondary text-base md:text-lg leading-relaxed max-w-2xl mx-auto mb-12">
            {{ $subline }}
        </p>

        {{-- CTA 行 --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <button
                type="button"
                data-contact-trigger="hero"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
                @click="$store.contactPanel.show('hero')"
                aria-controls="contact-panel"
                aria-label="{{ $ctaLabel }}">
                {{ $ctaLabel }}
            </button>
            <a href="{{ route('site.cases.index') }}"
               class="btn-site-outline inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                      focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none">
                查看案例
            </a>
        </div>
    </div>

    {{-- 向下滚动指示器 --}}
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2" aria-hidden="true">
        <svg class="w-6 h-6 text-site-secondary animate-bounce" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </div>
</section>
