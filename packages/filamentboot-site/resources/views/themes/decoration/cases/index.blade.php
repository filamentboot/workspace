{{--
 * 案例列表页（decoration 主题，UI-SPEC §Component 11）
 *
 * #29 起筛选与网格**直接写在本主题里**，不再走 Livewire CaseFilter。两个理由：
 *   1. Livewire 组件会把 livewire.js 拉进页面，那个 script 标签带 data-csrf → 起 session
 *      → 公开页整页缓存失效。改成查询串后每个筛选组合各自是一个可缓存的静态 URL。
 *   2. 原先筛选 pills + 卡片网格 + 分页整块在 resources/views/livewire/case-filter.blade.php 里，
 *      是一份**跨主题共享的视觉视图**——本来就违反「双主题完全独立」。这次顺带拆开。
 *
 * 筛选项渲染成 <a>：每个组合都是可收录、可分享、可后退的地址。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    // 保留另一维筛选、丢掉 page：避免「换了风格还停在第 7 页」
    $linkTo = function (array $overrides) use ($style, $houseType): string {
        $query = array_filter([
            'style'      => $style,
            'house_type' => $houseType,
            ...$overrides,
        ], fn ($value): bool => $value !== null && $value !== '');

        return route('site.cases.index', $query);
    };

    $filterGroups = [
        ['label' => '风格', 'key' => 'style', 'active' => $style, 'options' => $styleOptions],
        ['label' => '户型', 'key' => 'house_type', 'active' => $houseType, 'options' => $houseTypeOptions],
    ];
@endphp

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="cases-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-12">
                <h1 id="cases-heading" class="text-site-primary text-4xl font-bold mb-4">装修案例</h1>
                <p class="text-site-secondary text-lg">
                    探索我们的精选智能家居装修案例，感受设计与科技的完美融合。
                </p>
            </div>

            {{-- 筛选 pills --}}
            <div class="flex flex-wrap gap-3 mb-8" role="group" aria-label="案例筛选">
                @foreach($filterGroups as $group)
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-site-muted text-xs uppercase tracking-wide mr-1">{{ $group['label'] }}</span>

                        <a href="{{ $linkTo([$group['key'] => null]) }}"
                           class="inline-flex items-center min-h-[44px] px-4 py-2 rounded-full text-sm border transition-colors duration-150
                                  {{ ! $group['active'] ? 'border-site-glow text-site-accent font-bold' : 'bg-site-elevated text-site-secondary border-site hover:border-site-glow hover:text-site-primary' }}"
                           style="{{ ! $group['active'] ? 'background: var(--color-primary-glow);' : '' }}"
                           @if(! $group['active']) aria-current="true" @endif>
                            全部
                        </a>

                        @foreach($group['options'] as $value => $label)
                            <a href="{{ $linkTo([$group['key'] => $value]) }}"
                               class="inline-flex items-center min-h-[44px] px-4 py-2 rounded-full text-sm border transition-colors duration-150
                                      {{ $group['active'] === $value ? 'border-site-glow text-site-accent font-bold' : 'bg-site-elevated text-site-secondary border-site hover:border-site-glow hover:text-site-primary' }}"
                               style="{{ $group['active'] === $value ? 'background: var(--color-primary-glow);' : '' }}"
                               @if($group['active'] === $value) aria-current="true" @endif>
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>

            {{-- 案例网格 --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @forelse($records as $case)
                    @include('filamentboot-site::components.case-card', ['case' => $case])
                @empty
                    <div class="col-span-full py-16 text-center">
                        <p class="text-site-muted text-base">
                            {{ ($style || $houseType) ? '没有符合筛选条件的案例，换个条件试试' : '暂无案例，敬请期待' }}
                        </p>
                    </div>
                @endforelse
            </div>

            {{-- 分页 --}}
            @if($records->hasPages())
                <div class="mt-10">
                    {{ $records->links() }}
                </div>
            @endif

        </div>
    </section>
@endsection
