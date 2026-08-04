{{--
 * 地图区块（decoration 深色主题）
 *
 * iframe 的 src 由 MapEmbed::sanitize() 过闸：只放行 https 且 host 在
 * config 白名单内，不通过则**整个 iframe 不渲染**，只留文字地址——
 * 不降级成空框（同 SafeUrl 的契约，「框在但是空的」比不显示更让人困惑）。
 *
 * 文字地址不是地图的说明而是它的降级路径：广告拦截插件、企业网络策略与爬虫
 * 都会把 iframe 丢掉，那时候地址文字是唯一还看得到的信息。
 *
 * loading="lazy" 是刻意的：地图 iframe 会拉几百 KB 的瓦片与脚本，
 * 联系页通常要滚下去才看到它，没必要参与首屏竞争。
 --}}
@php
    $title    = (string) ($data['title'] ?? '');
    $address  = (string) ($data['address'] ?? '');
    $embedUrl = \Filamentboot\FilamentbootSite\Support\MapEmbed::sanitize($data['embed_url'] ?? null);

    // 高度只取白名单档位，库里存着别的值时回落到中档
    $height    = in_array((int) ($data['height'] ?? 420), [320, 420, 560], true) ? (int) $data['height'] : 420;
    $headingId = 'block-map-' . $index;
@endphp

<section class="py-16 bg-site-base" @if($title !== '') aria-labelledby="{{ $headingId }}" @else aria-label="地图与地址" @endif>
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

        @if($title !== '')
            <h2 id="{{ $headingId }}" class="text-site-primary text-2xl md:text-3xl font-bold leading-tight mb-6">
                {{ $title }}
            </h2>
        @endif

        @if($address !== '')
            <p class="text-site-secondary text-base leading-relaxed mb-6 whitespace-pre-line">{{ $address }}</p>
        @endif

        @if($embedUrl !== null)
            <div class="rounded-2xl overflow-hidden border border-site bg-site-elevated">
                <iframe
                    src="{{ $embedUrl }}"
                    title="{{ $title !== '' ? $title : '位置地图' }}"
                    class="w-full block"
                    style="height: {{ $height }}px; border: 0;"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen></iframe>
            </div>
        @endif

    </div>
</section>
