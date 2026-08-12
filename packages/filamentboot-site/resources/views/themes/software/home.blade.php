{{--
 * 首页（software 主题，五期批次 4c 按《官网对标》§5.1 重排为七屏）
 *
 * 导航（layout 提供）→ 首屏 → 功能矩阵 → 在线演示引导 → 扩展生态 → 文档/快速上手 → 页脚（layout 提供）
 *
 * 此前是 decoration 首页的机械复制（批次 2 只搭主题骨架，没换内容）。这里只重写
 * software 自己的版式，decoration/home.blade.php 不受影响——两套主题各存完整
 * 副本，不许抽公共层。
 *
 * 数据来源：功能矩阵与在线演示引导是写死的真实文案（来自 packages/filamentboot/
 * README.md），扩展生态屏读 App\Site\SoftwareHomeSectionProvider 提供的
 * $extensionProducts（映射官网信息架构表「插件与扩展目录 → SiteProduct」，
 * 批次 4a 已播种 6 个真实一方插件）。不新增第 9 类内容模块。
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')

    {{-- 首屏：有启用中的 HOME_TOP 幻灯片就用它，否则降级回单图 hero。
         降级分支不能删——没配幻灯片的下游站首页不能空一块。 --}}
    @php
        $heroBanners = app(\Filamentboot\FilamentbootSite\Modules\Corporate\Banners\BannerProvider::class)
            ->forPosition(\Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerPosition::HOME_TOP);
    @endphp
    @if($heroBanners->isNotEmpty())
        @include('filamentboot-site::components.banner-hero', ['banners' => $heroBanners])
    @else
        @include('filamentboot-site::components.hero')
    @endif

    {{-- 功能矩阵（Section 2，《官网对标》§5.1 第 3 屏）
         图标 + 一句话的 4 栏网格，复用包内 feature-grid 区块视图保持样式一致。
         内容摘自 packages/filamentboot/README.md 的真实功能清单，不是新造的宣传语；
         带状态徽标的完整功能表是批次 4d 的 Roadmap 页，这里只是首页概览。 --}}
    @php
        $homeFeatureGrid = [
            'title'   => '开箱即用的后台底座',
            'columns' => 4,
            'items'   => [
                [
                    'icon'        => 'shield-check',
                    'title'       => '认证与安全',
                    'description' => "账号名/邮箱双模式登录\n防枚举攻击与登录限流\nTOTP 双因素认证、登录日志自动记录",
                ],
                [
                    'icon'        => 'user-group',
                    'title'       => 'RBAC 权限与部门数据权限',
                    'description' => "基于 Spatie Permission 的角色权限体系\n5 种数据权限范围可选\n超级管理员一键绕过",
                ],
                [
                    'icon'        => 'squares-2x2',
                    'title'       => '菜单与操作日志',
                    'description' => "数据库驱动的树形菜单，拖拽排序\nSpatie ActivityLog 自动记录模型变更",
                ],
                [
                    'icon'        => 'puzzle-piece',
                    'title'       => '插件化架构',
                    'description' => "以包形式发布，可扩展可覆盖\n官方插件市场一键安装存储/编辑器/官网等模块",
                ],
            ],
        ];
    @endphp
    @include('filamentboot-site::blocks.feature-grid', ['data' => $homeFeatureGrid, 'index' => 'home-feature-matrix'])

    <div class="text-center py-10 bg-site-subtle">
        <a href="{{ route('site.page', 'roadmap') }}"
           class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
            查看完整功能列表与 Roadmap →
        </a>
    </div>

    {{-- 在线演示引导（Section 3，第 4 屏）
         账号真实，来自 packages/filamentboot/README.md：演示环境每日凌晨 4 点重置、
         高危操作已屏蔽。演示站是能力最直接的证明，不能像 YZNCMS 那样有 demo 却不摆。 --}}
    <section class="py-20 bg-site-base" aria-labelledby="demo-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 id="demo-heading" class="text-site-primary text-3xl font-bold inline-flex items-center gap-3 mb-6">
                <span class="inline-block w-1 h-8 rounded-full" style="background: var(--color-primary);"></span>
                看看后台长什么样
            </h2>
            <p class="text-site-secondary text-lg max-w-2xl mx-auto mb-2">
                在线体验完整后台，演示账号
                <code class="text-site-accent font-mono">demo@example.com</code>
                /
                <code class="text-site-accent font-mono">demo123</code>
            </p>
            <p class="text-site-secondary text-sm max-w-2xl mx-auto mb-10">
                演示环境每日凌晨 4:00 重置，高危操作已屏蔽，随便点。
            </p>
            <a href="https://demo.xitongapp.com"
               target="_blank" rel="noopener noreferrer"
               class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-10 py-4 rounded-full font-bold text-lg
                      focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
               aria-label="打开在线演示（在新窗口打开）">
                打开在线演示
            </a>
        </div>
    </section>

    {{-- 扩展生态（Section 4，第 5 屏）：官网信息架构表「插件与扩展目录 → SiteProduct」 --}}
    <section class="py-20 bg-site-subtle" aria-labelledby="ecosystem-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-12">
                <h2 id="ecosystem-heading"
                    class="text-site-primary text-3xl font-bold flex items-center gap-3">
                    <span class="inline-block w-1 h-8 rounded-full" style="background: var(--color-primary);"></span>
                    插件与扩展
                </h2>
                <a href="{{ route('site.products.index') }}"
                   class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                    查看全部
                </a>
            </div>

            @if(isset($extensionProducts) && $extensionProducts->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($extensionProducts as $product)
                        <article class="bg-site-surface rounded-xl border border-site p-6 card-hover">
                            <h3 class="text-site-primary font-bold text-lg mb-2 leading-snug">
                                <a href="{{ route('site.products.show', $product->slug) }}"
                                   class="hover:text-site-accent transition-colors duration-200 focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                                    {{ $product->title_zh }}
                                </a>
                            </h3>
                            @if($product->description_zh)
                                <p class="text-site-secondary text-sm leading-relaxed line-clamp-3">
                                    {{ $product->description_zh }}
                                </p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <div class="py-16 text-center">
                    <p class="text-site-secondary text-base">暂无扩展插件展示</p>
                </div>
            @endif
        </div>
    </section>

    {{-- 文档 / 快速上手（Section 5，第 6 屏） --}}
    <section class="py-20 bg-site-base" aria-labelledby="quickstart-heading">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 text-center">
            <h2 id="quickstart-heading" class="text-site-primary text-3xl font-bold mb-6">一行命令开始</h2>

            <div class="bg-site-elevated border border-site rounded-xl px-6 py-4 mb-8 text-left overflow-x-auto">
                <code class="text-site-accent text-sm font-mono">composer require filamentboot/filamentboot</code>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('site.page', 'services') }}"
                   class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                          focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none">
                    快速开始文档
                </a>
                <a href="https://github.com/filamentboot/filamentboot/blob/main/wiki/installation.md"
                   target="_blank" rel="noopener noreferrer"
                   class="btn-site-outline inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                          focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
                   aria-label="详细安装指南（在新窗口打开）">
                    详细安装指南
                </a>
            </div>
        </div>
    </section>

@endsection
