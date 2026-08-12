{{--
 * 页脚组件（UI-SPEC §Component 8）
 *
 * 三列布局：品牌/快速链接/联系方式 + 底栏版权 + ICP 备案。
 * ICP 链接指向 beian.miit.gov.cn，target _blank rel="noopener"（安全）。
 *
 * 联系方式整列在电话/地址/二维码全部未配置时不渲染，
 * 避免线上出现「联系我们」标题下方空无一物的情况。
 --}}
@php
    $companyName  = ($siteSettings?->company_name_zh ?: '') ?: config('app.name', '');
    $phone        = $siteSettings?->phone ?: '';
    $address      = $siteSettings?->address_zh ?: '';
    $icpNumber    = $siteSettings?->icp_number ?: '';
    $privacyUrl   = $siteSettings?->privacy_url ?: '';
    $logoPath     = $siteSettings?->logoUrl();
    $wechatQrcode = $siteSettings?->wechatQrcodeUrl();

    // 三项联系方式全空时整列不渲染
    $hasContactInfo = $phone !== '' || $address !== '' || ! empty($wechatQrcode);

    // 后台配了 footer 菜单就用它，没配则回退下面这份硬编码列表（#17）。
    // 兜底数组留在各主题的 blade 里而不是抽进 PHP：抽出去会把两个主题的
    // 页脚结构焊死。删光菜单必须回退而不是白屏，这是升级安全的硬要求。
    // 内容类型条目的文案取自 ContentTypeLabels（七期批次 2），非内容类型的项
    // （服务城市之后那几项）不属于这套词表，仍然留字面量。
    $quickLinks = app(\Filamentboot\FilamentbootSite\Cms\Services\MenuResolver::class)->resolveFlat('footer') ?? [
        ['href' => route('site.cases.index'),     'label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::case()],
        ['href' => route('site.solutions.index'), 'label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::solution()],
        ['href' => route('site.packages.index'),  'label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::package()],
        ['href' => route('site.products.index'),  'label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::product()],
        ['href' => route('site.news.index'),      'label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::news()],
        // 城市页的枢纽。三百多个城市页只靠站点地图被抓到不够——爬虫按内链权重
        // 分配抓取预算，没有站内入口的整片子树会被当成低价值区（三期批次 6）
        ['href' => route('site.city.index'),      'label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::city()],
        // services 是 SiteDemoSeeder 建的五个静态页之一，却一直没有任何入口——
        // 全站零内链，只能靠站点地图被抓到。补进来，孤岛清零（三期批次 3）
        ['href' => route('site.page', 'services'), 'label' => '我们的服务'],
        ['href' => route('site.page', 'about'),   'label' => '关于我们'],
        ['href' => route('site.page', 'faq'),     'label' => '常见问题'],
        ['href' => route('site.page', 'contact'), 'label' => '联系我们'],
    ];
@endphp

<footer class="bg-site-surface border-t border-site py-12">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- 主体列（联系方式缺省时收敛为两列） --}}
        <div class="grid grid-cols-1 {{ $hasContactInfo ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }} gap-8">

            {{-- 第一列：品牌 --}}
            <div>
                @if($logoPath)
                    <img src="{{ $logoPath }}" alt="{{ $companyName }}" class="max-h-10 w-auto mb-4" loading="lazy">
                @else
                    <div class="text-site-accent font-bold text-lg mb-4">{{ $companyName }}</div>
                @endif

                @if($siteSettings?->footer_intro_zh)
                    <p class="text-site-secondary text-sm leading-relaxed">
                        {{ $siteSettings->footer_intro_zh }}
                    </p>
                @endif
            </div>

            {{-- 第二列：快速链接 --}}
            <div>
                <nav role="navigation" aria-label="页脚导航">
                    <h3 class="text-site-muted text-xs uppercase tracking-widest mb-4">快速链接</h3>
                    <ul class="list-none space-y-2">
                        @foreach($quickLinks as $link)
                            <li>
                                <a href="{{ $link['href'] }}"
                                   @if($link['target'] ?? null) target="{{ $link['target'] }}" rel="noopener noreferrer" @endif
                                   class="text-site-secondary text-sm hover:text-site-accent transition-colors duration-200">
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>

            {{-- 第三列：联系方式（全部未配置时整列不渲染） --}}
            @if($hasContactInfo)
                <div>
                    <h3 class="text-site-muted text-xs uppercase tracking-widest mb-4">联系我们</h3>

                    @if($phone !== '')
                        <div class="flex items-center gap-2 mb-3">
                            {{-- Heroicons phone --}}
                            <svg class="w-4 h-4 text-site-accent flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                            </svg>
                            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                               class="text-site-secondary text-sm hover:text-site-accent transition-colors duration-200">{{ $phone }}</a>
                        </div>
                    @endif

                    @if($address !== '')
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
                            <p class="text-site-muted text-xs mb-2">微信扫码咨询</p>
                            <img src="{{ $wechatQrcode }}"
                                 alt="{{ $companyName }} 微信二维码"
                                 class="w-[100px] h-[100px] rounded-lg border border-site object-cover"
                                 loading="lazy"
                                 width="100"
                                 height="100">
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- 底栏：版权 + 备案 + 隐私 --}}
        <div class="border-t border-site mt-8 pt-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-site-muted text-xs">
                    &copy; {{ date('Y') }} {{ $companyName }}
                </p>
                <div class="flex items-center gap-4">
                    @if($privacyUrl !== '')
                        <a href="{{ $privacyUrl }}"
                           class="text-site-muted text-xs hover:text-site-secondary transition-colors duration-200">
                            隐私政策
                        </a>
                    @endif
                    @if($icpNumber !== '')
                        <a href="https://beian.miit.gov.cn"
                           target="_blank"
                           rel="noopener"
                           class="text-site-muted text-xs hover:text-site-secondary transition-colors duration-200">
                            {{ $icpNumber }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</footer>
