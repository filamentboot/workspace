{{--
 * 全屋套餐详情页（两套主题共用）
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 *
 * 版式顺序对着装修者的追问顺序：
 *   封面 → 户型/面积/工期/质保 → 价格与口径 → **包含清单** → 不含项 → 正文 → CTA
 *
 * **包含清单是这一页的主角**，不是附属信息。装修者判断一份报价靠不靠谱，
 * 看的就是「装什么、几个、干什么用、放在哪儿」这四列填没填满。
 *
 * ⚠️ 清单渲染的是 `normalizedItems()` 而不是裸 `items`：后台 Repeater 可能留下
 * 只填了一半的行，库里也可能躺着结构不对的存量数据，直接遍历会渲染出一排空格子。
 *
 * CTA 用**记录级** source（`pkg-{slug}`）：看完套餐留电话的人，销售第一眼就该知道
 * 他看的是哪一款。只有套餐做记录级——案例 / 资讯 / 产品保持页面类型级，
 * 全都记录级会让后台的来源列变成上百个值。slug 只含 [a-z0-9-]，
 * 与 ContactSubmission::normalizedSource() 的过滤规则相容，不会被剥掉。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $title    = $record->title_zh ?? '';
    $cover    = $record->coverUrl('card');
    $content  = $record->content_zh ?: ($record->description_zh ?? '');
    $items    = $record->normalizedItems();
    $hasPrice = $record->price !== null && (float) $record->price > 0;
    $source   = 'pkg-'.$record->slug;

    // 关键信息行：值为空的整项不渲染，不留「工期：—」这种空槽
    $facts = array_filter([
        '户型'   => $record->house_layout?->label(),
        '档位'   => $record->tier?->label(),
        '面积段' => $record->area_range,
        '工期'   => $record->duration,
        '质保'   => $record->warranty,
    ]);
@endphp

@section('content')

    <div class="max-w-3xl mx-auto py-10 px-4 sm:px-6">

        @include('filamentboot-site::components.breadcrumb')

        <h1 class="text-site-primary text-3xl font-bold mb-6 leading-tight">{{ $title }}</h1>

        {{-- 封面放在正文列里、按原比例显示，**不做 16:9 通栏 hero**。
             套餐封面是方图，标题压在画面顶部——通栏 hero 读的是 coverUrl('og')
             （Fit::Crop 1.91:1），会把标题连同上半张图一起切掉。
             这里用 card 档（Fit::Max，只缩不裁），配 aspect-square 容器。 --}}
        @if($cover)
            <div class="mb-8 mx-auto max-w-md rounded-2xl overflow-hidden border border-site bg-site-elevated">
                <img src="{{ $cover }}"
                     alt="{{ $title }} — {{ \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::package() }}"
                     class="w-full h-full object-cover"
                     loading="eager" fetchpriority="high" decoding="sync"
                     width="800" height="800" style="aspect-ratio: 1/1;">
            </div>
        @endif

        @if($record->description_zh)
            <p class="text-site-secondary text-lg leading-relaxed mb-8">{{ $record->description_zh }}</p>
        @endif

        {{-- 关键信息 --}}
        @if($facts !== [])
            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 p-5 rounded-2xl bg-site-surface border border-site mb-8">
                @foreach($facts as $factLabel => $factValue)
                    <div>
                        <dt class="text-site-muted text-xs mb-1">{{ $factLabel }}</dt>
                        <dd class="text-site-primary text-sm font-bold">{{ $factValue }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        {{-- 价格与口径 --}}
        <div class="flex flex-wrap items-baseline gap-3 pb-6 mb-8 border-b border-site">
            @if($hasPrice)
                <span class="text-site-primary font-bold text-3xl">¥{{ number_format((float) $record->price, 0) }}</span>
                <span class="text-site-muted text-sm">起</span>
            @else
                <span class="text-site-primary font-bold text-2xl">咨询价格</span>
            @endif
            @if($record->price_note)
                <span class="text-site-secondary text-sm">{{ $record->price_note }}</span>
            @endif
        </div>

        {{-- 包含清单 --}}
        @if($items !== [])
            @php
                // 数量 / 用途 / 摆放位置三列**只在至少有一行填了它的时候才出现**。
                // 套餐主图那种只标了设备名的来源，渲染三列「—」看着像数据坏了，
                // 而实际是「这一项因家而异」。整列不出现比整列占位诚实。
                $itemColumns = $record->itemColumns();
                $columnLabels = ['quantity' => '数量', 'purpose' => '用途', 'location' => '摆放位置'];
            @endphp
            <section class="mb-10" aria-labelledby="package-items-heading">
                <h2 id="package-items-heading" class="text-site-primary text-xl font-bold mb-4">包含清单</h2>
                {{-- 宽表在自己的容器里横滚，页面本身不出现横向滚动条 --}}
                <div class="overflow-x-auto rounded-2xl border border-site">
                    <table class="w-full text-sm" style="border-collapse: collapse;">
                        <thead>
                            <tr class="bg-site-elevated">
                                <th class="text-left text-site-primary font-bold px-4 py-3 whitespace-nowrap">名称</th>
                                @foreach($itemColumns as $column)
                                    <th class="text-left text-site-primary font-bold px-4 py-3 @if($column === 'quantity') whitespace-nowrap @endif">{{ $columnLabels[$column] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                <tr class="border-t border-site">
                                    <td class="text-site-primary px-4 py-3 align-top">{{ $item['name'] }}</td>
                                    @foreach($itemColumns as $column)
                                        <td class="text-site-secondary px-4 py-3 align-top @if($column === 'quantity') whitespace-nowrap @endif">{{ $item[$column] ?: '—' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- 不含项 --}}
        @if($record->excludes)
            <section class="mb-10 p-5 rounded-2xl bg-site-subtle border border-site" aria-labelledby="package-excludes-heading">
                <h2 id="package-excludes-heading" class="text-site-primary font-bold text-base mb-2">不含</h2>
                <p class="text-site-secondary text-sm leading-relaxed whitespace-pre-line">{{ $record->excludes }}</p>
            </section>
        @endif

        {{-- 富文本内容（必须经 purifier 过滤，T-10-05-01 安全硬要求） --}}
        @if($content)
            <div class="prose text-site-primary leading-relaxed" style="word-break: break-word;">
                {!! \Filamentboot\FilamentbootSite\Support\RichText::purify($content) !!}
            </div>
        @endif

        {{-- 标签 --}}
        @include('filamentboot-site::components.tag-list', ['tags' => $record->tags])

        {{-- 针对本套餐的咨询 CTA（记录级来源） --}}
        <div class="mt-12 p-6 rounded-2xl bg-site-surface border border-site flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <p class="text-site-primary font-bold text-base mb-1">这套用在我家，具体多少钱？</p>
                <p class="text-site-secondary text-sm">留个电话，我们按你家的实际户型和现有条件出一份详细报价单。</p>
            </div>
            <button
                type="button"
                data-contact-trigger="{{ $source }}"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-2 rounded-full text-sm whitespace-nowrap
                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
                @click="$store.contactPanel.show('{{ $source }}')"
                aria-controls="contact-panel">
                获取本套餐报价单
            </button>
        </div>

        {{-- 同户型的其它档位（控制器已按档位排好） --}}
        @if($related->isNotEmpty())
            <section class="mt-16 pt-12 border-t border-site" aria-labelledby="related-packages-heading">
                <h2 id="related-packages-heading" class="text-site-primary text-xl font-bold mb-6">
                    {{ $record->house_layout?->label() ? $record->house_layout->label().'的其它档位' : '其它套餐' }}
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($related as $relatedPackage)
                        @include('filamentboot-site::components.package-card', ['package' => $relatedPackage])
                    @endforeach
                </div>
            </section>
        @endif

        <div class="mt-8">
            <a href="{{ route('site.packages.index') }}"
               class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                &larr; 返回套餐列表
            </a>
        </div>
    </div>

@endsection
