{{--
 * 浮动询盘入口 + 滑入面板（UI-SPEC §Component 6 PART A）
 *
 * 固定触发按钮（aria-expanded/aria-controls）+ 滑入面板（内嵌 Livewire ContactForm）。
 * Alpine 控制开合 + ESC 关闭 + focus trap + backdrop（UI-SPEC §Accessibility）。
 --}}
@php
    $isZh       = app()->getLocale() !== 'en';
    $triggerLabel = $isZh ? '打开询盘表单' : 'Open inquiry form';
    $panelTitle   = $isZh ? '预约咨询' : 'Book a Consultation';
    $closeLabel   = $isZh ? '关闭表单' : 'Close form';
@endphp

<div
    x-data="{ contactPanelOpen: false }"
    @keydown.escape.window="contactPanelOpen = false">

    {{-- 浮动触发按钮（PART A） --}}
    <button
        id="floating-contact-btn"
        type="button"
        class="btn-site-primary fixed right-6 bottom-6 lg:right-8 lg:bottom-8 z-50
               w-14 h-14 rounded-full shadow-lg inline-flex items-center justify-center
               focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none"
        @click="contactPanelOpen = true"
        :aria-expanded="contactPanelOpen.toString()"
        aria-controls="contact-panel"
        :aria-label="contactPanelOpen ? '{{ $closeLabel }}' : '{{ $triggerLabel }}'">
        {{-- Heroicons chat-bubble-oval-left-ellipsis --}}
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
        </svg>
    </button>

    {{-- 背景遮罩 --}}
    <div
        class="fixed inset-0 z-30 bg-black/40"
        x-show="contactPanelOpen"
        @click="contactPanelOpen = false"
        style="display: none;"
        aria-hidden="true">
    </div>

    {{-- 滑入面板（PART B） --}}
    <div
        id="contact-panel"
        role="dialog"
        aria-modal="true"
        :aria-label="'{{ $panelTitle }}'"
        class="fixed right-0 top-0 bottom-0 z-40 w-full sm:w-96 bg-site-surface border-l border-site shadow-2xl
               flex flex-col"
        x-show="contactPanelOpen"
        x-trap="contactPanelOpen"
        style="display: none;">

        {{-- 面板头部 --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-site h-16">
            <h2 class="text-site-primary font-bold text-xl">{{ $panelTitle }}</h2>
            <button
                type="button"
                class="inline-flex items-center justify-center min-w-[44px] min-h-[44px]
                       text-site-muted hover:text-site-primary transition-colors duration-200
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-lg"
                @click="contactPanelOpen = false"
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
