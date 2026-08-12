{{--
 * 浮动询盘入口 + 滑入面板（跨主题共享，UI-SPEC §Component 6）
 *
 * 开合状态由全局 $store.contactPanel 管理（见 contact-panel-store.blade.php），
 * 导航栏、移动菜单、列表页和详情页的 CTA 共用同一个打开动作。
 *
 * 移动端（sm 以下）**不出悬浮按钮**：那里改由底部三段式操作条承接（C1），
 * 两个入口同屏是重复噪音，气泡还会压在操作条上。滑入面板本身仍然共用——
 * 操作条的「在线留言」调的就是同一个 $store.contactPanel.show()。
 *
 * 桌面端悬浮按钮仍带 safe-area-inset-bottom 安全间距；页面内容区避让由各主题
 * 的 layouts/app.blade.php 提供（移动端 pb-32、桌面端 pb-24）。
 --}}
@php
    $triggerLabel = '打开询盘表单';
    $panelTitle   = '预约咨询';
    $closeLabel   = '关闭表单';

    // 面板的额外问题走配置（filamentboot-site.contact.panel_fields），默认空。
    //
    // 为什么面板需要这个：$extraFields 此前只有页面里内嵌的询盘表单区块会传，
    // 而**大部分线索是从这个面板进来的**（导航栏、移动端操作条、各详情页 CTA 全开它），
    // 结果销售拿到的永远只有姓名 / 电话 / 留言，没有任何上下文。
    //
    // 归一复用 ContactFormBlock::normalizedFields()，不在这里另写一份：配置项的形状
    // 与区块的「额外问题」完全一致，两处各解析一遍迟早漂移成两套「什么算合法问题」。
    $panelFields = config('filamentboot-site.contact.panel_fields', []);
    $panelFields = is_array($panelFields) ? $panelFields : [];

    $panelExtraFields = app(\Filamentboot\FilamentbootSite\Cms\Blocks\ContactFormBlock::class)
        ->normalizedFields(['fields' => $panelFields]);
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
               w-14 h-14 rounded-full shadow-lg hidden sm:inline-flex items-center justify-center
               focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
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

    {{-- 背景遮罩。本视图两套主题共用，所以遮罩色不能写死在这里——
         `bg-site-scrim` 由各主题的 CSS 自己声明取值（当前 software 是
         decoration 的复制，取值一致；换皮后可能分化）。原先写的是调色板的
         纯黑加斜杠透明度，既是 decoration 的取色泄漏进共用视图，也把
         Tailwind 默认调色板拖进产物。
         （刻意不写出原类名的完整形态：v4 的内容探测扫原始文本，注释也算。） --}}
    <div
        class="fixed inset-0 z-[55] bg-site-scrim"
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
                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-lg"
                @click="$store.contactPanel.hide()"
                aria-label="{{ $closeLabel }}">
                {{-- Heroicons x-mark --}}
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- 面板内容：询盘表单（#29 起是纯 Alpine + fetch，不再是 Livewire 组件） --}}
        <div class="flex-1 overflow-y-auto p-6">
            @include('filamentboot-site::components.contact-form', [
                'formKey'     => 'panel',
                'extraFields' => $panelExtraFields,
            ])
        </div>
    </div>
</div>
