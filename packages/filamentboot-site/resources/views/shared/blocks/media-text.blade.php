{{--
 * 图文分栏区块（两套主题共用，#13）
 *
 * media_position 决定图片在左还是在右。移动端一律图上文下——两栏挤在
 * 窄屏上谁都读不清，顺序由 DOM 决定，图片始终先出。
 *
 * 图片缺失走 image-placeholder 降级，不出破图（§0.5）。虽然 MediaTextBlock
 * 的 rules() 把 image 标成 required，存量 payload 与直接写库的数据不受表单约束。
 --}}
@php
    $title    = (string) ($data['title'] ?? '');
    $body     = (string) ($data['body'] ?? '');
    $imagePath = (string) ($data['image'] ?? '');
    $imageAlt  = (string) ($data['image_alt'] ?? '');
    $mediaRight = ($data['media_position'] ?? 'left') === 'right';

    $imageUrl = $imagePath !== ''
        ? \Illuminate\Support\Facades\Storage::disk($block->disk())->url($imagePath)
        : null;

    $headingId = 'block-media-text-' . $index;
@endphp

<section class="py-16 bg-site-base" aria-labelledby="{{ $headingId }}">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center">

            {{-- 图片栏：lg 以上按 media_position 换序，移动端始终在上 --}}
            <div class="{{ $mediaRight ? 'lg:order-2' : 'lg:order-1' }}">
                <div class="aspect-[4/3] rounded-2xl overflow-hidden bg-site-elevated border border-site">
                    @if($imageUrl)
                        <img src="{{ $imageUrl }}"
                             alt="{{ $imageAlt }}"
                             class="w-full h-full object-cover img-blur-up"
                             loading="lazy"
                             decoding="async"
                             x-on:load="$el.classList.add('loaded')">
                    @else
                        @include('filamentboot-site::components.image-placeholder', ['label' => $title])
                    @endif
                </div>
            </div>

            {{-- 文字栏 --}}
            <div class="{{ $mediaRight ? 'lg:order-1' : 'lg:order-2' }}">
                <h2 id="{{ $headingId }}"
                    class="text-site-primary text-2xl md:text-3xl font-bold mb-6 flex items-center gap-3">
                    <span class="inline-block w-1 h-8 rounded-full shrink-0" style="background: var(--color-primary);"></span>
                    {{ $title }}
                </h2>

                {{-- Textarea 存的是纯文本，换行靠 whitespace-pre-line 保留，不能用 nl2br（那要 {!! !!}） --}}
                <p class="text-site-secondary text-base leading-relaxed whitespace-pre-line">{{ $body }}</p>
            </div>
        </div>
    </div>
</section>
