{{--
 * 城市总索引（两套主题共用）
 *
 * **一页平铺全部已发布城市**，按省分组，不做「先选省再选市」的两步式。
 * 城市页最怕入口深：三百多页要是都藏在省页底下，抓取器要多走一跳，
 * 而这一页把它们全拉到离首页两跳的位置。
 *
 * 用纯文字链而不是卡片：这一页的作用是**导航**不是展示，几百张卡片
 * 既撑不满信息也让人找不到自己的城市。
 *
 * 直辖市那组没有城市列表——它的省页就是市页（见 SiteFrontController::cityProvince），
 * 省名本身就是那个链接，底下再列一个同地址的条目是重复。
 *
 * 七期批次 1（2026-08-11）起两套主题共用这份视图，落在 resources/views/shared/。
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="city-index-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('filamentboot-site::components.breadcrumb')

            <div class="mb-12 max-w-2xl">
                <h1 id="city-index-heading" class="text-site-primary text-4xl font-bold mb-4">
                    服务城市
                </h1>
                <p class="text-site-secondary text-lg leading-relaxed">
                    各地的气候、供暖与房型条件不一样，全屋智能的做法也就不一样。
                    选一个城市，看当地该注意什么。
                </p>
            </div>

            <div class="space-y-10">
                @foreach($groups as $group)
                    @php $province = $group['region']; @endphp
                    <section aria-labelledby="city-province-{{ $province->code }}">
                        <h2 id="city-province-{{ $province->code }}"
                            class="text-site-primary text-lg font-bold mb-4 pb-2 border-b border-site">
                            <a href="{{ route('site.city.province', ['province' => $province->slug]) }}"
                               class="hover:text-site-accent transition-colors duration-200
                                      focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                                {{ $province->displayName() }}
                            </a>
                        </h2>

                        @if($group['pages'] !== [])
                            <ul class="flex flex-wrap gap-x-6 gap-y-3">
                                @foreach($group['pages'] as $cityPage)
                                    <li>
                                        <a href="{{ $cityPage->url() }}"
                                           class="text-site-secondary text-sm hover:text-site-accent transition-colors duration-200
                                                  focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                                            {{ $cityPage->region?->displayName() }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach
            </div>

        </div>
    </section>
@endsection
