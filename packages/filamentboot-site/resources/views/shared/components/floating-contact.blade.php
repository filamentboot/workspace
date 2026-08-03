{{--
 * 浮动询盘入口 + 滑入面板（跨主题共享，UI-SPEC §Component 6）
 *
 * 开合状态由全局 $store.contactPanel 管理（见 contact-panel-store.blade.php），
 * 导航栏、移动菜单、列表页和详情页的 CTA 共用同一个打开动作。
 *
 * 移动端：悬浮按钮加入 safe-area-inset-bottom 安全间距，避免在带手势条的
 * 机型上贴住屏幕底部；页面内容区由各主题预留 pb-24 滚动避让。
 --}}
@php
    $triggerLabel = '打开询盘表单';
    $panelTitle   = '预约咨询';
    $closeLabel   = '关闭表单';
@endphp

{{-- 必须带 x-data：Alpine 只初始化 x-data 根之内的元素，
     裸 <div> 里的 x-show / @click 不会被处理，面板将永远打不开。
     此处不需要局部状态，空 x-data 仅用于建立 Alpine 根。 --}}
<div x-data>
    {{-- 浮动触发按钮 --}}
    <button
        id="floating-contact-btn"
        data-contact-trigger="floating"
        type="button"
        class="btn-site-primary fixed right-4 sm:right-6 lg:right-8 z-50
               w-14 h-14 rounded-full shadow-lg inline-flex items-center justify-center
               focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none"
        style="bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));"
        {{-- 面板打开时隐藏，避免悬浮气泡压在表单上 --}}
        x-show="! $store.contactPanel.open"
        @click="$store.contactPanel.show('floating')"
        :aria-expanded="$store.contactPanel.open.toString()"
        aria-controls="contact-panel"
        :aria-label="$store.contactPanel.open ? '{{ $closeLabel }}' : '{{ $triggerLabel }}'">
        {{-- Heroicons chat-bubble-oval-left-ellipsis --}}
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
        </svg>
    </button>

    {{-- 背景遮罩 --}}
    <div
        class="fixed inset-0 z-[55] bg-black/40"
        x-show="$store.contactPanel.open"
        @click="$store.contactPanel.hide()"
        style="display: none;"
        aria-hidden="true">
    </div>

    {{-- 滑入面板 --}}
    <div
        id="contact-panel"
        role="dialog"
        aria-modal="true"
        aria-label="{{ $panelTitle }}"
        class="fixed right-0 top-0 bottom-0 z-[60] w-full sm:w-96 bg-site-surface border-l border-site shadow-2xl
               flex flex-col"
        x-show="$store.contactPanel.open"
        x-trap="$store.contactPanel.open"
        @keydown.escape.window="$store.contactPanel.hide()"
        style="display: none;">

        {{-- 面板头部 --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-site h-16">
            <h2 class="text-site-primary font-bold text-xl">{{ $panelTitle }}</h2>
            <button
                type="button"
                class="inline-flex items-center justify-center min-w-[44px] min-h-[44px]
                       text-site-muted hover:text-site-primary transition-colors duration-200
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-lg"
                @click="$store.contactPanel.hide()"
                aria-label="{{ $closeLabel }}">
                {{-- Heroicons x-mark --}}
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- 面板内容：Livewire ContactForm --}}
        <div class="flex-1 overflow-y-auto p-6">
            @livewire('filamentboot-site::contact-form')
        </div>
    </div>
</div>
