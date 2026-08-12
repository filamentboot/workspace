{{--
 * 首屏横幅区块（两套主题共用，#13）
 *
 * 与 components/hero.blade.php 的区别：那个是首页专用、文案硬编码在主题里；
 * 这个是页面区块，全部内容由内容编辑在后台填。视觉语言保持一致（圆角按钮、
 * 同一套蒙版），但高度收敛到 60vh——页面区块之上通常还有面包屑与标题，
 * 再占满整屏会把正文推到首屏之外。
 *
 * 除富文本区块外所有字段一律 {{ }} 转义（#13 安全要点）。
 * cta_url 过 SafeUrl scheme 白名单，被拦下则不渲染按钮而非渲染成 #——
 * 给访客一个点了没反应的按钮比不显示更糟。
 --}}
@php
    $title    = (string) ($data['title'] ?? '');
    $subtitle = (string) ($data['subtitle'] ?? '');
    $imagePath = (string) ($data['image'] ?? '');
    $imageAlt  = (string) ($data['image_alt'] ?? '');
    $ctaLabel  = (string) ($data['cta_label'] ?? '');
    $ctaUrl    = \Filamentboot\FilamentbootSite\Support\SafeUrl::sanitize($data['cta_url'] ?? null);

    $imageUrl = $imagePath !== ''
        ? \Illuminate\Support\Facades\Storage::disk($block->disk())->url($imagePath)
        : null;

    $headingId = 'block-hero-' . $index;
@endphp

{{-- 无背景图时铺 bg-site-surface 而不是 bg-site-base：原来靠青色径向光圈撑住
     纯色底的观感，光圈在白底上会糊成一片脏印已经去掉了，改用一档浅灰把区块
     从页面背景里分出来 --}}
<section class="relative overflow-hidden {{ $imageUrl ? '' : 'bg-site-surface' }} min-h-[60vh] flex items-center"
         @if($imageUrl)
         style="background-image: url('{{ $imageUrl }}'); background-size: cover; background-position: center;"
         @endif
         aria-labelledby="{{ $headingId }}">

    {{-- 背景图存在时压一层浅色蒙版。
         深色文字压在照片上同样读不清，所以蒙版必须有；但浅色主题下不能压暗——
         白底页面里突然出现一块暗区，会让这一段与上下文的明度断裂。
         用背景色本身做半透明蒙版，照片被"提亮"到与页面同一明度带。 --}}
    @if($imageUrl)
        <div class="absolute inset-0 bg-site-surface-82" aria-hidden="true"></div>
        {{-- 背景图以 CSS 呈现，读屏软件取不到，补一个可访问名 --}}
        <span class="sr-only">{{ $imageAlt }}</span>
    @endif

    <div class="relative z-10 max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
        <h2 id="{{ $headingId }}"
            class="text-site-primary text-3xl md:text-4xl lg:text-5xl font-bold leading-tight mb-6">
            {{ $title }}
        </h2>

        @if($subtitle !== '')
            <p class="text-site-secondary text-base md:text-lg leading-relaxed max-w-2xl mx-auto mb-10">
                {{ $subtitle }}
            </p>
        @endif

        @if($ctaLabel !== '' && $ctaUrl !== null)
            <a href="{{ $ctaUrl }}"
               class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                      focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none">
                {{ $ctaLabel }}
            </a>
        @endif
    </div>
</section>
