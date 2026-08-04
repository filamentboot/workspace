{{--
 * 移动端底部三段式操作条（tech-product 浅色主题，C1）
 *
 * 一键拨号 / 微信咨询 / 在线留言——国内营销站的标准转化形态。
 *
 * 只在 sm 以下出现，且此时悬浮气泡已隐藏（见 shared/components/floating-contact）：
 * 两个入口同屏是重复噪音，气泡还会压在操作条上。
 *
 * 缺数据的段落整段不渲染，三段自动收敛为两段甚至一段，不留死按钮。
 *
 * 底部安全间距用 env(safe-area-inset-bottom)，与悬浮气泡同一处理；
 * 内容区避让由 layouts/app.blade.php 的 pb-32 提供。
 --}}
@php
    $barPhone  = $siteSettings?->phone ?: '';
    $barQrcode = $siteSettings?->wechat_qrcode;

    // 拨号号码去掉分隔符：tel: 里带空格和横杠在部分安卓拨号盘上会解析失败
    $barPhoneDial = preg_replace('/[^0-9+]/', '', $barPhone);
@endphp

<div x-data="{ qrOpen: false }" class="sm:hidden">

    {{-- 微信二维码弹层 --}}
    @if($barQrcode)
        <div
            class="fixed inset-0 z-[70] bg-slate-900/50 flex items-center justify-center px-8"
            x-show="qrOpen"
            x-transition.opacity
            @click="qrOpen = false"
            @keydown.escape.window="qrOpen = false"
            style="display: none;"
            role="dialog"
            aria-modal="true"
            aria-label="微信咨询二维码">
            <div class="bg-site-surface border border-site rounded-xl shadow-xl p-6 flex flex-col items-center gap-4" @click.stop>
                <img src="{{ $barQrcode }}" alt="微信咨询二维码" class="w-56 h-56 object-contain rounded-lg">
                <p class="text-site-secondary text-sm">长按识别二维码，添加微信咨询</p>
                <button
                    type="button"
                    class="text-site-muted hover:text-site-primary text-sm min-h-[44px] px-4
                           focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-lg"
                    @click="qrOpen = false">
                    关闭
                </button>
            </div>
        </div>
    @endif

    {{-- 底部固定条 --}}
    <nav
        class="fixed bottom-0 left-0 right-0 z-[45] bg-site-surface border-t border-site shadow-[0_-1px_3px_rgba(0,0,0,0.06)]
               flex items-stretch"
        style="padding-bottom: env(safe-area-inset-bottom, 0px);"
        aria-label="快捷联系">

        @if($barPhoneDial !== '')
            <a href="tel:{{ $barPhoneDial }}"
               class="flex-1 min-h-[56px] flex flex-col items-center justify-center gap-0.5
                      text-site-muted hover:text-site-accent transition-colors duration-200
                      focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[--color-primary] focus-visible:outline-none"
               aria-label="拨打电话 {{ $barPhone }}">
                {{-- Heroicons phone --}}
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                </svg>
                <span class="text-xs tracking-wide">电话咨询</span>
            </a>
        @endif

        @if($barQrcode)
            <button
                type="button"
                class="flex-1 min-h-[56px] flex flex-col items-center justify-center gap-0.5
                       text-site-muted hover:text-site-accent transition-colors duration-200
                       border-l border-site
                       focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[--color-primary] focus-visible:outline-none"
                @click="qrOpen = true"
                aria-label="打开微信咨询二维码">
                {{-- Heroicons chat-bubble-left-right --}}
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                </svg>
                <span class="text-xs tracking-wide">微信咨询</span>
            </button>
        @endif

        <button
            type="button"
            data-contact-trigger="mobile-bar"
            class="flex-[1.2] min-h-[56px] btn-site-primary flex flex-col items-center justify-center gap-0.5 rounded-none
                   focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[--color-primary] focus-visible:outline-none"
            @click="$store.contactPanel.show('mobile-bar')"
            aria-label="打开在线留言表单">
            {{-- Heroicons pencil-square --}}
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
            </svg>
            <span class="text-xs font-medium tracking-wide">在线留言</span>
        </button>
    </nav>
</div>
