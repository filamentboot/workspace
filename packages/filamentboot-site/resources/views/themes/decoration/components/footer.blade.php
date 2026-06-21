{{--
 * 页脚组件（UI-SPEC §Component 8）
 *
 * 三列布局：品牌/快速链接/联系方式 + 底栏版权 + ICP 备案。
 * ICP 链接指向 beian.miit.gov.cn，target _blank rel="noopener"（安全）。
 --}}
@php
    $isZh        = app()->getLocale() !== 'en';
    $settings    = $siteSettings ?? null;
    $companyName = $isZh
        ? (optional($settings)->company_name_zh ?? '湖北晴空妙享科技有限公司')
        : (optional($settings)->company_name_en ?? 'QKZ Smart Technology Co., Ltd.');
    $phone       = $isZh
        ? (optional($settings)->phone ?? '')
        : (optional($settings)->phone_en ?? optional($settings)->phone ?? '');
    $address     = $isZh
        ? (optional($settings)->address_zh ?? '')
        : (optional($settings)->address_en ?? '');
    $icpNumber   = optional($settings)->icp_number ?? '';
    $logoPath    = optional($settings)->logo;
    $wechatQrcode = optional($settings)->wechat_qrcode;

    $quickLinks = $isZh ? [
        ['href' => url('/cases'),     'label' => '装修案例'],
        ['href' => url('/solutions'), 'label' => '智能方案'],
        ['href' => url('/products'),  'label' => '智能产品'],
        ['href' => url('/about'),     'label' => '关于我们'],
        ['href' => url('/contact'),   'label' => '联系我们'],
    ] : [
        ['href' => url('/en/cases'),     'label' => 'Cases'],
        ['href' => url('/en/solutions'), 'label' => 'Solutions'],
        ['href' => url('/en/products'),  'label' => 'Products'],
        ['href' => url('/en/about'),     'label' => 'About Us'],
        ['href' => url('/en/contact'),   'label' => 'Contact'],
    ];
@endphp

<footer class="bg-site-surface border-t border-site py-12">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- 三列主体 --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- 第一列：品牌 --}}
            <div>
                @if($logoPath)
                    <img src="{{ $logoPath }}" alt="{{ $companyName }}" class="max-h-10 w-auto mb-4" loading="lazy">
                @else
                    <div class="text-site-accent font-bold text-lg mb-4">{{ $companyName }}</div>
                @endif

                <p class="text-site-secondary text-sm leading-relaxed mb-4">
                    {{ $isZh ? '我们将智能科技与精致设计融为一体，为您打造真正属于未来的家居空间。' : 'We fuse smart technology with refined design to build living spaces that belong to the future.' }}
                </p>

                @if($icpNumber)
                    <a href="https://beian.miit.gov.cn"
                       target="_blank"
                       rel="noopener"
                       class="text-site-muted text-xs hover:text-site-secondary transition-colors duration-200"
                       aria-label="{{ $icpNumber }}">
                        {{ $icpNumber }}
                    </a>
                @endif
            </div>

            {{-- 第二列：快速链接 --}}
            <div>
                <nav role="navigation" aria-label="{{ $isZh ? '页脚导航' : 'Footer Navigation' }}">
                    <h3 class="text-site-muted text-xs uppercase tracking-widest mb-4">
                        {{ $isZh ? '快速链接' : 'Quick Links' }}
                    </h3>
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

            {{-- 第三列：联系方式 --}}
            <div>
                <h3 class="text-site-muted text-xs uppercase tracking-widest mb-4">
                    {{ $isZh ? '联系我们' : 'Contact Us' }}
                </h3>

                @if($phone)
                    <div class="flex items-center gap-2 mb-3">
                        {{-- Heroicons phone --}}
                        <svg class="w-4 h-4 text-site-accent flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                        </svg>
                        <span class="text-site-secondary text-sm">{{ $phone }}</span>
                    </div>
                @endif

                @if($address)
                    <div class="flex items-start gap-2 mb-4">
                        {{-- Heroicons map-pin --}}
                        <svg class="w-4 h-4 text-site-accent flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                        </svg>
                        <span class="text-site-secondary text-sm leading-relaxed">{{ $address }}</span>
                    </div>
                @endif

                @if($wechatQrcode)
                    <div>
                        <p class="text-site-muted text-xs mb-2">{{ $isZh ? '微信扫码咨询' : 'WeChat QR' }}</p>
                        <img src="{{ $wechatQrcode }}"
                             alt="{{ $isZh ? '湖北晴空妙享科技有限公司 微信二维码' : 'QKZ Tech WeChat QR Code' }}"
                             class="w-[100px] h-[100px] rounded-lg border border-site object-cover"
                             loading="lazy"
                             width="100"
                             height="100">
                    </div>
                @endif
            </div>
        </div>

        {{-- 底栏：版权 + 语言切换 --}}
        <div class="border-t border-site mt-8 pt-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-site-muted text-xs">
                    &copy; {{ date('Y') }} {{ $companyName }}
                </p>
                <div class="flex items-center gap-4">
                    @if($icpNumber)
                        <a href="https://beian.miit.gov.cn"
                           target="_blank"
                           rel="noopener"
                           class="text-site-muted text-xs hover:text-site-secondary transition-colors duration-200">
                            {{ $icpNumber }}
                        </a>
                    @endif
                    @include('filamentboot-site::components.lang-switcher')
                </div>
            </div>
        </div>
    </div>
</footer>
