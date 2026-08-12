{{--
 * 面包屑导航（两套主题共用，B3）
 *
 * 数据由 SiteFrontController::breadcrumbs() 构建，同一个数组也用于生成
 * BreadcrumbList 结构化数据（seo-meta 组件输出），此处只负责渲染。
 *
 * 末项 url 为 null 表示当前页：不出链接，改用 aria-current="page" 标注。
 *
 * 不带宽度容器与左右内边距——各调用页的正文区宽度不一（max-w-3xl / 5xl /
 * screen-xl），组件自带容器会让面包屑与正文左边缘对不齐。放进调用方已有的
 * 容器里即可。
 *
 * 期望变量：
 *   $breadcrumbs — list<array{label: string, url: string|null}>，为空则整块不渲染
 --}}
@php
    $breadcrumbs = $breadcrumbs ?? [];
@endphp

@if(count($breadcrumbs) > 1)
    <nav aria-label="面包屑" class="mb-6">
        <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            @foreach($breadcrumbs as $index => $crumb)
                <li class="flex items-center gap-x-2 min-w-0">
                    @if($index > 0)
                        {{-- 分隔符对读屏无意义，隐藏后由列表语义承担层级关系 --}}
                        <span class="text-(--color-primary) select-none" aria-hidden="true">›</span>
                    @endif

                    @if($crumb['url'])
                        <a href="{{ $crumb['url'] }}"
                           class="text-site-muted hover:text-site-accent transition-colors duration-200 truncate max-w-[12rem]
                                  focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                            {{ $crumb['label'] }}
                        </a>
                    @else
                        <span class="text-site-secondary truncate max-w-[16rem]" aria-current="page">
                            {{ $crumb['label'] }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
