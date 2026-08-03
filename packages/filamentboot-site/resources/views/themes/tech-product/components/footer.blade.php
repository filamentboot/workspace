{{--
 * 页脚（tech-product 浅色主题）
 *
 * 与 decoration 相同的数据契约：联系方式三项全空时整列不渲染，
 * 避免出现只有标题没有内容的空栏目。
 --}}
@php
    $companyName  = ($siteSettings?->company_name_zh ?: '') ?: config('app.name', '');
    $phone        = $siteSettings?->phone ?: '';
    $address      = $siteSettings?->address_zh ?: '';
    $icpNumber    = $siteSettings?->icp_number ?: '';
    $privacyUrl   = $siteSettings?->privacy_url ?: '';
    $logoPath     = $siteSettings?->logo;
    $wechatQrcode = $siteSettings?->wechat_qrcode;

    $hasContactInfo = $phone !== '' || $address !== '' || ! empty($wechatQrcode);

    $quickLinks = [
        ['href' => route('site.cases.index'),     'label' => '装修案例'],
        ['href' => route('site.solutions.index'), 'label' => '智能方案'],
        ['href' => route('site.products.index'),  'label' => '智能产品'],
        ['href' => route('site.page', 'about'),   'label' => '关于我们'],
        ['href' => route('site.page', 'contact'), 'label' => '联系我们'],
    ];
@endphp

<footer class="bg-site-surface border-t border-site py-12">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 {{ $hasContactInfo ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }} gap-8">

            <div>
                @if($logoPath)
                    <img src="{{ $logoPath }}" alt="{{ $companyName }}" class="max-h-9 w-auto mb-4" loading="lazy">
                @else
                    <div class="text-site-primary font-bold text-lg mb-4">{{ $companyName }}</div>
                @endif
                <p class="text-site-secondary text-sm leading-relaxed">
                    以产品化的方式交付全屋智能：标准化选型、可复制的施工流程、可追溯的售后。
                </p>
            </div>

            <div>
                <nav role="navigation" aria-label="页脚导航">
                    <h3 class="text-site-secondary text-xs font-semibold uppercase tracking-widest mb-4">快速链接</h3>
                    <ul class="list-none space-y-2">
                        @foreach($quickLinks as $link)
                            <li>
                                <a href="{{ $link['href'] }}"
                                   class="text-site-secondary text-sm hover:text-site-accent transition-colors duration-200">
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>

            @if($hasContactInfo)
                <div>
                    <h3 class="text-site-secondary text-xs font-semibold uppercase tracking-widest mb-4">联系我们</h3>

                    @if($phone !== '')
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-4 h-4 text-site-accent flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                            </svg>
                            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                               class="text-site-secondary text-sm hover:text-site-accent transition-colors duration-200">{{ $phone }}</a>
                        </div>
                    @endif

                    @if($address !== '')
                        <div class="flex items-start gap-2 mb-4">
                            <svg class="w-4 h-4 text-site-accent flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                            <span class="text-site-secondary text-sm leading-relaxed">{{ $address }}</span>
                        </div>
                    @endif

                    @if($wechatQrcode)
                        <div>
                            <p class="text-site-secondary text-xs mb-2">微信扫码咨询</p>
                            <img src="{{ $wechatQrcode }}"
                                 alt="{{ $companyName }} 微信二维码"
                                 class="w-[100px] h-[100px] rounded-lg border border-site object-cover"
                                 loading="lazy" width="100" height="100">
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="border-t border-site mt-8 pt-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-site-secondary text-xs">&copy; {{ date('Y') }} {{ $companyName }}</p>
                <div class="flex items-center gap-4">
                    @if($privacyUrl !== '')
                        <a href="{{ $privacyUrl }}" class="text-site-secondary text-xs hover:text-site-accent transition-colors duration-200">隐私政策</a>
                    @endif
                    @if($icpNumber !== '')
                        <a href="https://beian.miit.gov.cn" target="_blank" rel="noopener"
                           class="text-site-secondary text-xs hover:text-site-accent transition-colors duration-200">{{ $icpNumber }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</footer>
