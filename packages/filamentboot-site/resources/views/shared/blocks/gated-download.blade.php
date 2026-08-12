{{--
 * 资料索取区块（两套主题共用）
 *
 * 「手册换联系方式」：左边讲清这份资料值什么，右边留联系方式，提交成功后
 * 表单原位换成下载按钮（链接由服务端现签、有时限，见 Cms\Blocks\GatedDownloadBlock）。
 *
 * ⚠️ 这里**不输出文件路径**，只传一个不透明 key。输出了路径这道门就没用了——
 * 看一眼网页源码就能直接下走。
 *
 * 文件没上传时 assetKey 为空串，组件退化成普通询盘表单：不出下载按钮，
 * 但联系方式照收。比渲染一个点了拿不到东西的按钮好。
 --}}
@php
    $title       = (string) ($data['title'] ?? '');
    $description = (string) ($data['description'] ?? '');
    $buttonLabel = trim((string) ($data['button_label'] ?? '')) ?: '提交后下载';
    $source      = preg_replace('/[^a-z0-9\-]/', '', mb_strtolower((string) ($data['source'] ?? ''))) ?? '';
    $assetKey    = $block->assetKey($data);

    $headingId = 'block-gated-' . $index;
@endphp

<section class="py-16 bg-site-subtle" @if($title !== '') aria-labelledby="{{ $headingId }}" @endif>
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-start">

            <div>
                <p class="text-site-accent text-xs font-bold tracking-widest uppercase mb-3">免费资料</p>

                @if($title !== '')
                    <h2 id="{{ $headingId }}" class="text-site-primary text-2xl md:text-3xl font-bold leading-tight mb-4">
                        {{ $title }}
                    </h2>
                @endif

                @if($description !== '')
                    <p class="text-site-secondary text-base leading-relaxed whitespace-pre-line">{{ $description }}</p>
                @endif

                @if($assetKey !== '')
                    <p class="text-site-muted text-xs mt-6">
                        提交后立即获得下载链接，链接有效期
                        {{ (int) config('filamentboot-site.gated.link_ttl', 30) }} 分钟。
                    </p>
                @endif
            </div>

            <div class="bg-site-surface rounded-2xl border border-site p-6 sm:p-8">
                <p class="text-site-primary font-bold text-base mb-6">{{ $buttonLabel }}</p>

                @include('filamentboot-site::components.contact-form', [
                    'source' => $source !== '' ? $source : 'gated-download',
                    'tracksPanelSource' => false,
                    'formKey' => 'gated-'.$index,
                    'assetKey' => $assetKey,
                ])
            </div>

        </div>
    </div>
</section>
