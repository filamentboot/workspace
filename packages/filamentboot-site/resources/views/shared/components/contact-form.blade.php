{{--
 * 询盘表单（纯 Alpine + fetch，#29）
 *
 * 取代原先的 Livewire ContactForm。改动的理由不是嫌 Livewire 重，而是它注入的 livewire.js
 * 带 data-csrf，渲染时会调 csrf_token() → 起 session → 公开页必然带 Set-Cookie，整页缓存无从谈起。
 *
 * 提交打的是 route('site.contact.store')：一条不挂 web 组的无状态路由，没有 CSRF token 可带，
 * 也不需要——防刷靠蜜罐 + 耗时 + IP 限流（都在 Services\ContactSubmission 里）。
 *
 * 归因随请求体发出（$store.siteAttribution.payload()），不再靠 session 跨请求传递。
 *
 * ⚠️ 本文件是**唯一一份**表单标记，放在 shared/ 而不是双主题各一份：它此前就是 Livewire 组件
 * 的单份视图，位置没变。表单控件的观感由两套主题各自的 CSS 变量与 .btn-site-primary 决定。
 *
 * 额外问题（$extraFields）由询盘表单区块配置，答案以「问题文本 => 答案」进请求体的 extra。
 * 服务端只做边界约束、不校验必填——理由见 Cms\Blocks\ContactFormBlock 的类注释。
 *
 * 资料索取（gated content）时传 $assetKey：一个不透明 key，服务端据此在登记表里查
 * 真实文件路径并回一条限时签名链接。**前台从头到尾不出现文件路径**——出现了这道门就没用了。
 *
 * @param  string|null  $source            固定来源标识（#13 的区块内联实例用），传了就不跟随面板 store
 * @param  bool         $tracksPanelSource 是否跟随 $store.contactPanel.source
 * @param  list<array{label: string, type: string, required: bool, options: list<string>}>  $extraFields
 * @param  string|null  $assetKey  资料索取的不透明 key（Cms\Blocks\GatedDownloadBlock::assetKey）
 --}}
@php
    $source = $source ?? '';
    $tracksPanelSource = $tracksPanelSource ?? ($source === '');
    $formId = 'contact-form-'.($formKey ?? \Illuminate\Support\Str::random(6));

    $extraFields = $extraFields ?? [];
    $assetKey = $assetKey ?? '';
    // 提交成功后要把额外字段一并清空，先备一份空白模板
    $extraBlank = array_fill_keys(array_column($extraFields, 'label'), '');
@endphp

<div
    x-data="{
        submitted: false,
        sending: false,
        errors: {},
        download: null,
        filename: '',
        readyAt: Date.now(),
        extraBlank: @js($extraBlank),
        form: { name: '', phone: '', message: '', website: '', extra: @js($extraBlank) },

        /** 本次提交归到哪个入口 */
        source() {
            @if($tracksPanelSource)
                return $store.contactPanel?.source || @js($source);
            @else
                return @js($source);
            @endif
        },

        async submit() {
            if (this.sending) {
                return;
            }

            this.sending = true;
            this.errors = {};

            try {
                const response = await fetch(@js(route('site.contact.store')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        ...this.form,
                        source: this.source(),
                        @if($assetKey !== '')
                            asset: @js($assetKey),
                        @endif
                        // 客户端上报的可交互时长，服务端只做宽松下限判断（可伪造，见服务类注释）
                        elapsed: Math.round((Date.now() - this.readyAt) / 1000),
                        ...($store.siteAttribution?.payload() ?? {}),
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (response.ok && payload.ok) {
                    this.submitted = true;
                    this.download = payload.download ?? null;
                    this.filename = payload.filename ?? '';
                    this.form = { name: '', phone: '', message: '', website: '', extra: { ...this.extraBlank } };

                    // A3：统计侧监听这个事件上报转化
                    window.dispatchEvent(new Event('site-contact-submitted'));

                    return;
                }

                this.errors = payload.errors ?? { phone: ['提交失败，请稍后再试。'] };
            } catch (e) {
                this.errors = { phone: ['网络异常，请稍后再试。'] };
            } finally {
                this.sending = false;
            }
        },

        error(field) {
            return this.errors[field]?.[0] ?? null;
        },
    }"
>
    {{-- 成功状态 --}}
    <template x-if="submitted">
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <svg class="w-12 h-12 mb-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                 stroke="currentColor" aria-hidden="true"
                 style="color: var(--color-success);">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
            </svg>
            <h3 class="text-site-primary font-bold text-xl mb-3">感谢您的留言！</h3>
            <p class="text-site-secondary text-base" x-text="download ? '资料已准备好，点下面按钮下载。我们也会尽快与您联系。' : '我们会尽快与您联系。'"></p>

            {{-- 资料索取：链接由服务端现签、有时限，所以只能等提交成功后才拿到。
                 不用 download 属性强制另存：多数资料是 PDF，浏览器内置预览体验更好。 --}}
            <template x-if="download">
                <a :href="download"
                   class="btn-site-primary inline-flex items-center justify-center gap-2 min-h-[44px] mt-6 px-6 py-3 rounded-xl font-bold text-base
                          focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none"
                   target="_blank" rel="noopener">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span x-text="filename ? ('下载' + filename) : '下载资料'"></span>
                </a>
            </template>
        </div>
    </template>

    {{-- 表单 --}}
    <form x-show="! submitted" @submit.prevent="submit()" novalidate>

        {{-- 蜜罐字段（C2）：人类看不见也 Tab 不到，只有按 name 盲填的脚本会写进来。
             用屏外定位而非 display:none —— 后者是已知特征，成熟脚本会跳过。 --}}
        <div aria-hidden="true" style="position: absolute; left: -9999px; top: auto; width: 1px; height: 1px; overflow: hidden;">
            <label for="{{ $formId }}-website">请留空</label>
            <input id="{{ $formId }}-website" name="website" type="text"
                   x-model="form.website" tabindex="-1" autocomplete="off">
        </div>

        {{-- 姓名 --}}
        <div class="mb-6">
            <label for="{{ $formId }}-name" class="block text-site-secondary text-sm font-normal mb-2">姓名 *</label>
            <input
                id="{{ $formId }}-name"
                type="text"
                x-model="form.name"
                required
                autocomplete="name"
                class="w-full bg-site-base border border-site rounded-xl px-4 py-2 text-site-primary
                       placeholder:text-site-muted text-base min-h-[44px]
                       focus:outline-none focus:border-site-glow focus:ring-1"
                :class="error('name') && 'border-[--color-destructive]'"
                style="--tw-ring-color: rgba(0, 212, 255, 0.3);"
                placeholder="请输入您的姓名"
                aria-required="true">
            <template x-if="error('name')">
                <span class="mt-1 text-xs block" style="color: var(--color-destructive);" role="alert" x-text="error('name')"></span>
            </template>
        </div>

        {{-- 电话 --}}
        <div class="mb-6">
            <label for="{{ $formId }}-phone" class="block text-site-secondary text-sm font-normal mb-2">电话 *</label>
            <input
                id="{{ $formId }}-phone"
                type="tel"
                x-model="form.phone"
                required
                autocomplete="tel"
                pattern="[0-9\+\-\s]{7,20}"
                class="w-full bg-site-base border border-site rounded-xl px-4 py-2 text-site-primary
                       placeholder:text-site-muted text-base min-h-[44px]
                       focus:outline-none focus:border-site-glow focus:ring-1"
                :class="error('phone') && 'border-[--color-destructive]'"
                placeholder="请输入联系电话"
                aria-required="true">
            <template x-if="error('phone')">
                <span class="mt-1 text-xs block" style="color: var(--color-destructive);" role="alert" x-text="error('phone')"></span>
            </template>
        </div>

        {{-- 额外问题（询盘表单区块配置，见 Cms\Blocks\ContactFormBlock）
             排在留言之前：自由填写的留言留在最后，中间夹着结构化问题会打断填写节奏。
             required 只落成 HTML 属性，服务端不二次校验（理由见文件头注释）。 --}}
        @foreach($extraFields as $extraIndex => $extraField)
            @php $extraId = $formId.'-extra-'.$extraIndex; @endphp
            <div class="mb-6">
                <label for="{{ $extraId }}" class="block text-site-secondary text-sm font-normal mb-2">
                    {{ $extraField['label'] }}@if($extraField['required']) *@endif
                </label>

                @if($extraField['type'] === 'select')
                    <select
                        id="{{ $extraId }}"
                        x-model="form.extra[@js($extraField['label'])]"
                        @if($extraField['required']) required aria-required="true" @endif
                        class="w-full bg-site-base border border-site rounded-xl px-4 py-2 text-site-primary text-base min-h-[44px]
                               focus:outline-none focus:border-site-glow focus:ring-1">
                        <option value="">请选择</option>
                        @foreach($extraField['options'] as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                @elseif($extraField['type'] === 'textarea')
                    <textarea
                        id="{{ $extraId }}"
                        x-model="form.extra[@js($extraField['label'])]"
                        rows="3"
                        maxlength="{{ \Filamentboot\FilamentbootSite\Services\ContactSubmission::EXTRA_VALUE_LENGTH }}"
                        @if($extraField['required']) required aria-required="true" @endif
                        class="w-full bg-site-base border border-site rounded-xl px-4 py-2 text-site-primary
                               placeholder:text-site-muted text-base
                               focus:outline-none focus:border-site-glow focus:ring-1"></textarea>
                @else
                    <input
                        id="{{ $extraId }}"
                        type="text"
                        x-model="form.extra[@js($extraField['label'])]"
                        maxlength="{{ \Filamentboot\FilamentbootSite\Services\ContactSubmission::EXTRA_VALUE_LENGTH }}"
                        @if($extraField['required']) required aria-required="true" @endif
                        class="w-full bg-site-base border border-site rounded-xl px-4 py-2 text-site-primary
                               placeholder:text-site-muted text-base min-h-[44px]
                               focus:outline-none focus:border-site-glow focus:ring-1">
                @endif
            </div>
        @endforeach

        {{-- 留言 --}}
        <div class="mb-6">
            <label for="{{ $formId }}-message" class="block text-site-secondary text-sm font-normal mb-2">留言</label>
            <textarea
                id="{{ $formId }}-message"
                x-model="form.message"
                rows="4"
                class="w-full bg-site-base border border-site rounded-xl px-4 py-2 text-site-primary
                       placeholder:text-site-muted text-base
                       focus:outline-none focus:border-site-glow focus:ring-1"
                :class="error('message') && 'border-[--color-destructive]'"
                placeholder="请简要描述您的需求（选填）"></textarea>
            <template x-if="error('message')">
                <span class="mt-1 text-xs block" style="color: var(--color-destructive);" role="alert" x-text="error('message')"></span>
            </template>
        </div>

        {{-- 提交按钮 --}}
        <button
            type="submit"
            class="btn-site-primary w-full inline-flex items-center justify-center gap-2 min-h-[44px] py-4 rounded-xl font-bold text-base
                   focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-surface] focus-visible:outline-none"
            :disabled="sending"
            :class="sending && 'opacity-70 cursor-not-allowed'">
            <template x-if="sending">
                <svg class="animate-spin w-4 h-4 border-t-2 border-current rounded-full" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                </svg>
            </template>
            <span x-text="sending ? '提交中...' : '提交留言'"></span>
        </button>

    </form>
</div>
