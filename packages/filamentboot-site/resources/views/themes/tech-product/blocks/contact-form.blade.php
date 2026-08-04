{{--
 * 内嵌询盘表单区块（tech-product 浅色主题，#13）
 *
 * 复用已有的 filamentboot-site::contact-form Livewire 组件，不新增组件
 * （§0.3 第 5 条：公开页只用 Alpine，不新增 Livewire）。source 作为挂载参数传入，
 * 组件据此关掉与悬浮面板 store 的同步，保住落地页归因。
 *
 * source 为空时不传参，组件退回跟随面板 store 的默认行为——内联表单与
 * 悬浮按钮共用同一份线索来源，虽不精确但比记成空值好。
 --}}
@php
    $title       = (string) ($data['title'] ?? '');
    $description = (string) ($data['description'] ?? '');
    // 与 ContactForm::normalizedSource() 同一套字符集过滤：区块 rules() 已限制，
    // 但存量 payload 与直接写库的数据不受表单约束
    $source = preg_replace('/[^a-z0-9\-]/', '', mb_strtolower((string) ($data['source'] ?? ''))) ?? '';

    $headingId = 'block-contact-form-' . $index;
@endphp

<section class="py-16 bg-site-surface" @if($title !== '') aria-labelledby="{{ $headingId }}" @endif>
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- 浅色主题下表单与说明左右并排：浅底上窄长单栏表单显得孤立，
             左侧留出说明文字的位置也更符合产品站的转化页结构 --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-start">
            <div>
                @if($title !== '')
                    <h2 id="{{ $headingId }}" class="text-site-primary text-2xl md:text-3xl font-bold tracking-tight mb-3">
                        {{ $title }}
                    </h2>
                @endif

                @if($description !== '')
                    <p class="text-site-secondary text-base leading-relaxed whitespace-pre-line">{{ $description }}</p>
                @endif
            </div>

            <div class="bg-site-base rounded-xl border border-site p-5 sm:p-6">
                @livewire('filamentboot-site::contact-form', ['source' => $source], key('block-contact-form-' . $index))
            </div>
        </div>
    </div>
</section>
