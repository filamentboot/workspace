{{--
 * Hero 组件（UI-SPEC §Component 2，五期批次 4c 按《官网对标》§5.1 首屏重写）
 *
 * min-h-site-screen 全屏高度，eyebrow 标签，响应式 H1，副标题，三按钮 CTA 行，scroll indicator。
 *
 * 三按钮取三家同类产品官网的共性（见 docs/cms/竞品调研/官网对标.md §5.1）：
 * 快速开始（能力入口，FastAdmin 的做法）/ 在线演示 / GitHub（源码入口，ThinkCMF 的做法）。
 * 不再是「预约咨询」+「查看案例」——那是装修业务的转化路径，本主题没有咨询/报价
 * 这类业务语义，也没有案例栏目一定有内容的前提（批次 4a 明确本站没有真实第三方案例）。
 *
 * 因此本组件不再挂 $store.contactPanel 触发器：官网层面的联系入口在导航栏 CTA、
 * 页脚与 /contact 页仍在，首屏只是不重复放一个「咨询」按钮。
 * tests/e2e/site-contact-cta.spec.cjs 的「首屏 CTA 可打开面板」用例已改为按钮不存在时跳过。
 *
 * 背景图支持（D-11-10 SITE-THEME-01）：
 * 读取 config('filamentboot-site.hero_background_image')，存在时叠加背景图（底层）
 * 并压一层浅色蒙版；无配置时回落到 bg-site-base 纯色，不报错。
 --}}
@php
    $eyebrow = '开源 · Laravel + Filament';
    $slogan1 = '让 Laravel 后台，';
    $slogan2 = '一次装好';

    // 「开源无加密，可商用」是三家同类产品官网首屏共用的信任句式（官网对标 §5.1 决定），
    // 其余描述来自 packages/filamentboot/README.md 的真实功能清单，不是新造的宣传语。
    $subline = '基于 Laravel 13 + Filament 5，开源无加密，可商用——认证与安全、RBAC 权限、菜单管理、部门数据权限、操作日志开箱即用。';

    // 背景图支持（D-11-10，SITE-THEME-01），无配置时回落纯色背景
    $heroBg = config('filamentboot-site.hero_background_image');
@endphp

{{-- 高度用 min-h-site-screen 而不是 dvh 的内建类：`dvh` 要 Chrome 108 /
     Safari 15.4，低于门槛整条 min-height 被丢弃、首屏塌成内容高度。
     那个类在同一条规则里先写 vh 回退再写 dvh，见 software.css。 --}}
<section class="relative min-h-site-screen overflow-hidden bg-site-base flex items-center justify-center"
         @if($heroBg)
         style="background-image: url('{{ $heroBg }}'); background-size: cover; background-position: center;"
         @endif
         aria-labelledby="hero-heading">

    {{-- 有背景图时压一层浅色蒙版，让深色标题压得住照片。
         与 blocks/hero.blade.php 同一套做法：提亮而非压暗，白底页面里不能突然
         出现一块暗区。 --}}
    @if($heroBg)
        <div class="absolute inset-0 bg-site-surface-80" aria-hidden="true"></div>
    @endif

    {{-- 主体内容 --}}
    <div class="relative z-10 max-w-4xl mx-auto text-center px-4 md:px-8 py-32">

        {{-- Eyebrow 标签 --}}
        <p class="text-site-accent text-sm font-normal tracking-[0.2em] uppercase mb-6">
            {{ $eyebrow }}
        </p>

        {{-- H1 响应式标题，关键词用纯色强调（克莱因蓝，白底 10.69:1），不做渐变——
             用色纪律见 decoration.css 文件头，两套主题共用同一条纪律。 --}}
        <h1 id="hero-heading"
            class="text-4xl md:text-5xl lg:text-6xl xl:text-7xl font-bold text-site-primary leading-tight lg:leading-[1.1] mb-6">
            {{ $slogan1 }}<span class="text-site-accent">{{ $slogan2 }}</span>
        </h1>

        {{-- 副标题 --}}
        <p class="text-site-secondary text-base md:text-lg leading-relaxed max-w-2xl mx-auto mb-12">
            {{ $subline }}
        </p>

        {{-- CTA 行：快速开始（站内页面）/ 在线演示 / GitHub（均为真实地址，来自
             packages/filamentboot/README.md），后两个新窗口打开 --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('site.page', 'services') }}"
               class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                      focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none">
                快速开始
            </a>
            <a href="https://demo.xitongapp.com"
               target="_blank" rel="noopener noreferrer"
               class="btn-site-outline inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                      focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
               aria-label="在线演示（在新窗口打开）">
                在线演示
            </a>
            <a href="https://github.com/filamentboot/filamentboot"
               target="_blank" rel="noopener noreferrer"
               class="btn-site-outline inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                      focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
               aria-label="GitHub（在新窗口打开）">
                GitHub
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
