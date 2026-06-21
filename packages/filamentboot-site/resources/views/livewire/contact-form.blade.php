{{--
 * 询盘表单 Livewire 视图（UI-SPEC §Component 6 PART B）
 *
 * 三字段（姓名/电话/留言）+ wire:loading 提交中状态 + $submitted 成功态
 * + 速率限制错误红字（UI-SPEC §Livewire Interaction States）。
 * 由 ContactForm Livewire 组件渲染（10-04 落地组件逻辑）。
 --}}
@php
    $isZh = app()->getLocale() !== 'en';
@endphp

<div>
    @if($submitted)
        {{-- 成功状态：check-circle + 感谢文案 --}}
        <div class="flex flex-col items-center justify-center py-12 text-center">
            {{-- Heroicons check-circle --}}
            <svg class="w-12 h-12 mb-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                 stroke="currentColor" aria-hidden="true"
                 style="color: var(--color-success);">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
            </svg>
            <h3 class="text-site-primary font-bold text-xl mb-3">
                {{ $isZh ? '感谢您的留言！' : 'Thank you!' }}
            </h3>
            <p class="text-site-secondary text-base">
                {{ $isZh ? '我们会尽快与您联系。' : "We'll be in touch shortly." }}
            </p>
        </div>
    @else
        {{-- 表单 --}}
        <form wire:submit.prevent="submit" novalidate>

            {{-- 姓名 --}}
            <div class="mb-6">
                <label for="contact-name"
                       class="block text-site-secondary text-sm font-normal mb-2">
                    {{ $isZh ? '姓名 *' : 'Name *' }}
                </label>
                <input
                    id="contact-name"
                    type="text"
                    wire:model="name"
                    required
                    autocomplete="name"
                    class="w-full bg-site-base border border-site rounded-xl px-4 py-2 text-site-primary
                           placeholder:text-site-muted text-base min-h-[44px]
                           focus:outline-none focus:border-site-glow focus:ring-1
                           @error('name') border-[--color-destructive] @enderror"
                    style="--tw-ring-color: rgba(0, 212, 255, 0.3);"
                    placeholder="{{ $isZh ? '请输入您的姓名' : 'Your name' }}"
                    aria-required="true"
                    aria-describedby="{{ $errors->has('name') ? 'name-error' : '' }}">
                @error('name')
                    <span id="name-error" class="mt-1 text-xs block" style="color: var(--color-destructive);" role="alert">
                        {{ $message }}
                    </span>
                @enderror
            </div>

            {{-- 电话 --}}
            <div class="mb-6">
                <label for="contact-phone"
                       class="block text-site-secondary text-sm font-normal mb-2">
                    {{ $isZh ? '电话 *' : 'Phone *' }}
                </label>
                <input
                    id="contact-phone"
                    type="tel"
                    wire:model="phone"
                    required
                    autocomplete="tel"
                    pattern="[0-9\+\-\s]{7,20}"
                    class="w-full bg-site-base border border-site rounded-xl px-4 py-2 text-site-primary
                           placeholder:text-site-muted text-base min-h-[44px]
                           focus:outline-none focus:border-site-glow focus:ring-1
                           @error('phone') border-[--color-destructive] @enderror"
                    placeholder="{{ $isZh ? '请输入联系电话' : 'Your phone number' }}"
                    aria-required="true"
                    aria-describedby="{{ $errors->has('phone') ? 'phone-error' : '' }}">
                @error('phone')
                    <span id="phone-error" class="mt-1 text-xs block" style="color: var(--color-destructive);" role="alert">
                        {{ $message }}
                    </span>
                @enderror
            </div>

            {{-- 留言 --}}
            <div class="mb-6">
                <label for="contact-message"
                       class="block text-site-secondary text-sm font-normal mb-2">
                    {{ $isZh ? '留言' : 'Message' }}
                </label>
                <textarea
                    id="contact-message"
                    wire:model="message"
                    rows="4"
                    class="w-full bg-site-base border border-site rounded-xl px-4 py-2 text-site-primary
                           placeholder:text-site-muted text-base
                           focus:outline-none focus:border-site-glow focus:ring-1
                           @error('message') border-[--color-destructive] @enderror"
                    placeholder="{{ $isZh ? '请简要描述您的需求（选填）' : 'Briefly describe your needs (optional)' }}"
                    aria-describedby="{{ $errors->has('message') ? 'message-error' : '' }}"></textarea>
                @error('message')
                    <span id="message-error" class="mt-1 text-xs block" style="color: var(--color-destructive);" role="alert">
                        {{ $message }}
                    </span>
                @enderror
            </div>

            {{-- 提交按钮 --}}
            <button
                type="submit"
                class="btn-site-primary w-full inline-flex items-center justify-center min-h-[44px] py-4 rounded-xl font-bold text-base
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-surface] focus-visible:outline-none"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-70 cursor-not-allowed">

                {{-- 正常状态 --}}
                <span wire:loading.remove>
                    {{ $isZh ? '提交留言' : 'Submit Message' }}
                </span>

                {{-- 加载状态（wire:loading.delay 防止快速响应闪烁） --}}
                <span wire:loading wire:loading.delay class="inline-flex items-center gap-2">
                    {{-- CSS spinner --}}
                    <svg class="animate-spin w-4 h-4 border-t-2 border-current rounded-full" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    {{ $isZh ? '提交中...' : 'Submitting...' }}
                </span>
            </button>

        </form>
    @endif
</div>
