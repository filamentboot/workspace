{{--
 * 图片占位组件（跨主题共享）
 *
 * 内容未配置封面图时的兜底渲染。使用内联 SVG 而非外部占位服务
 * （此前用 picsum.photos，线上表现为图片模糊、依赖第三方可用性、
 * 且会向外部泄露访问来源），也不依赖 vendor:publish 后的静态文件。
 *
 * 期望变量：
 *   $label — 占位文案（如 '装修案例'），可选
 *   $class — 附加到容器的样式类，可选
 --}}
@php
    $label = $label ?? '';
    $class = $class ?? 'w-full h-full';
@endphp

<div class="{{ $class }} flex items-center justify-center bg-site-elevated border border-site"
     role="img"
     aria-label="{{ $label !== '' ? $label . '（暂无图片）' : '暂无图片' }}">
    <div class="flex flex-col items-center gap-2 text-site-muted px-4 text-center">
        {{-- Heroicons photo --}}
        <svg class="w-10 h-10 opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
        </svg>
        @if($label !== '')
            <span class="text-xs">{{ $label }}</span>
        @endif
    </div>
</div>
