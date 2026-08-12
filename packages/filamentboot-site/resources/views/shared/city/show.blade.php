{{--
 * 城市页（两套主题共用）
 *
 * 省级（直辖市）与地级两条路由共用这一份，区别只在 $province 是不是 null。
 *
 * ## 版式的取舍：共用文字要克制
 *
 * ⚠️ 这一页会被复制三百多次，**每多一句写死在模板里的话，就在三百多个页面上
 * 多一段一模一样的正文**。三期批次 5 有一条硬关卡是「任意两个城市页正文差异
 * >10%，相邻城市对单独测」——套餐卡片、服务承诺、公司介绍这类通用块放进来，
 * 那条线就永远过不了。所以这里只有一个 CTA，没有推荐位。
 *
 * 逐城不同的部分按信息量排：概况表（采集来的事实）→ 可选正文 → 下辖区县
 *（一个字都不用编，武汉 13 个区、黄石 6 个，名字完全不同）→ 同省其它城市。
 *
 * ## 空的块整个不渲染
 *
 * 概况没采到就不出概况表，不出「暂无数据」。采不到是常态（三期采集口径里
 * 写死了「拿不到权威来源就留空，不许估算」），渲染成「—」看起来像数据坏了。
 *
 * 七期批次 1（2026-08-11）起两套主题共用这份视图，落在 resources/views/shared/。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $cityName = $region->displayName();
    $content  = $record->content_zh;

    /*
     * CTA 用**记录级** source（`city-{区划代码}`），不是页面类型级的 city-detail。
     *
     * 这一页存在的全部理由是「要不要继续铺城市页」，而那个决定只能由
     * **哪些城市真的带来了询盘**来回答——所有城市共用一个 city-detail
     * 的话，后台看到的只有「城市页共 37 条」，一个城市都区分不出来。
     *
     * 后缀取区划代码而不是 slug：slug 改得动，代码不会变，历史询盘因此
     * 永远对得上。解析成中文名的是 Services\ContactSourceLabel。
     */
    $source = Filamentboot\FilamentbootSite\Services\ContactSourceLabel::CITY_PREFIX.$region->code;
@endphp

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="city-heading">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('filamentboot-site::components.breadcrumb')

            <h1 id="city-heading" class="text-site-primary text-3xl md:text-4xl font-bold tracking-tight mb-4 leading-tight">
                {{ $record->title_zh }}
            </h1>

            @if($record->description_zh)
                <p class="text-site-secondary text-lg leading-relaxed mb-10">{{ $record->description_zh }}</p>
            @endif

            {{-- 城市概况：字段表来自 config，控制器只把「确实有值」的行传进来 --}}
            @if($profileRows !== [])
                <section class="mb-12" aria-labelledby="city-profile-heading">
                    <h2 id="city-profile-heading" class="text-site-primary text-xl font-bold mb-5">
                        {{ $cityName }}的装修环境
                    </h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($profileRows as $row)
                            <div class="bg-site-surface border border-site rounded-2xl p-4">
                                <dt class="text-site-muted text-xs mb-1">{{ $row['label'] }}</dt>
                                <dd class="text-site-primary text-base font-bold">
                                    {{ $row['value'] }}{{ $row['unit'] }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            {{-- 可选正文覆写。富文本输出必须经 mews/purifier 过滤（T-10-05-01） --}}
            @if($content)
                <div class="prose text-site-primary leading-relaxed mb-12" style="word-break: break-word;">
                    {!! \Filamentboot\FilamentbootSite\Support\RichText::purify($content) !!}
                </div>
            @endif

            {{-- 下辖区县 --}}
            @if($counties->isNotEmpty())
                <section class="mb-12" aria-labelledby="city-counties-heading">
                    <h2 id="city-counties-heading" class="text-site-primary text-xl font-bold mb-3">
                        {{ $cityName }}下辖区县
                    </h2>
                    <p class="text-site-secondary text-sm mb-4">
                        {{ $region->name }}下辖 {{ $counties->count() }} 个区县，都在服务范围内。
                    </p>
                    <ul class="flex flex-wrap gap-x-5 gap-y-2">
                        @foreach($counties as $county)
                            <li class="text-site-secondary text-sm">{{ $county->name }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- 转化入口。**这一页只留这一个共用块**，理由见文件头 --}}
            <div class="p-6 rounded-2xl bg-site-surface border border-site flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="flex-1">
                    <p class="text-site-primary font-bold text-base mb-1">在{{ $cityName }}想做全屋智能？</p>
                    <p class="text-site-secondary text-sm">说一下户型和所在区，我们按当地情况给一版配置与报价。</p>
                </div>
                <button
                    type="button"
                    data-contact-trigger="{{ $source }}"
                    class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-2 rounded-full text-sm whitespace-nowrap
                           focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
                    @click="$store.contactPanel.show('{{ $source }}')"
                    aria-controls="contact-panel">
                    预约咨询
                </button>
            </div>

            {{-- 同省其它城市。跨省不推：「我要在本地找人装修」这个意图下，
                 隔壁省的城市页是噪音，所以同省没有别的页面时整块不渲染 --}}
            @if($siblings !== [])
                <section class="mt-16 pt-12 border-t border-site" aria-labelledby="city-siblings-heading">
                    <h2 id="city-siblings-heading" class="text-site-primary text-xl font-bold mb-5">
                        {{ $province?->displayName() }}其它城市
                    </h2>
                    <ul class="flex flex-wrap gap-x-6 gap-y-3">
                        @foreach($siblings as $sibling)
                            <li>
                                <a href="{{ $sibling->url() }}"
                                   class="text-site-secondary text-sm hover:text-site-accent transition-colors duration-200
                                          focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                                    {{ $sibling->region?->displayName() }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="mt-8">
                @if($province !== null)
                    <a href="{{ route('site.city.province', ['province' => $province->slug]) }}"
                       class="text-site-accent text-sm hover:underline
                              focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                        &larr; 返回{{ $province->displayName() }}
                    </a>
                @else
                    <a href="{{ route('site.city.index') }}"
                       class="text-site-accent text-sm hover:underline
                              focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                        &larr; 看全部服务城市
                    </a>
                @endif
            </div>

        </div>
    </section>
@endsection
