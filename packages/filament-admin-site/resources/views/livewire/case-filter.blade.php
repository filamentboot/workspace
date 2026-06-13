{{--
 * 案例筛选器 Livewire 视图（UI-SPEC §Component 11）
 *
 * 筛选 pills（style/house_type + 全部）、网格（@include case-card 复用）、
 * wire:loading.delay 半透明、prev/next 分页控件。
 * 由 CaseFilter Livewire 组件渲染（10-04 落地组件逻辑）。
 --}}
@php
    $isZh = app()->getLocale() !== 'en';
    $allLabel = $isZh ? '全部' : 'All';
    $prevLabel = $isZh ? '上一页' : 'Previous';
    $nextLabel = $isZh ? '下一页' : 'Next';
    $styleLabel = $isZh ? '风格' : 'Style';
    $houseTypeLabel = $isZh ? '户型' : 'Type';
@endphp

<div>
    {{-- 筛选 pills 栏 --}}
    <div class="flex flex-wrap gap-3 mb-8" role="group" aria-label="{{ $isZh ? '案例筛选' : 'Filter Cases' }}">

        {{-- 风格筛选 --}}
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-site-muted text-xs uppercase tracking-wide mr-1">{{ $styleLabel }}</span>

            <button
                type="button"
                wire:click="$set('style', '')"
                class="inline-flex items-center min-h-[44px] px-4 py-2 rounded-full text-sm border transition-colors duration-150
                       {{ !$style ? 'border-site-glow text-site-accent font-bold' : 'bg-site-elevated text-site-secondary border-site hover:border-site-glow hover:text-site-primary' }}"
                style="{{ !$style ? 'background: var(--color-primary-glow);' : '' }}"
                aria-pressed="{{ !$style ? 'true' : 'false' }}">
                {{ $allLabel }}
            </button>

            @foreach($this->styleOptions() as $value => $label)
                <button
                    type="button"
                    wire:click="$set('style', '{{ $value }}')"
                    class="inline-flex items-center min-h-[44px] px-4 py-2 rounded-full text-sm border transition-colors duration-150
                           {{ $style === $value ? 'border-site-glow text-site-accent font-bold' : 'bg-site-elevated text-site-secondary border-site hover:border-site-glow hover:text-site-primary' }}"
                    style="{{ $style === $value ? 'background: var(--color-primary-glow);' : '' }}"
                    aria-pressed="{{ $style === $value ? 'true' : 'false' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- 户型筛选 --}}
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-site-muted text-xs uppercase tracking-wide mr-1">{{ $houseTypeLabel }}</span>

            <button
                type="button"
                wire:click="$set('houseType', '')"
                class="inline-flex items-center min-h-[44px] px-4 py-2 rounded-full text-sm border transition-colors duration-150
                       {{ !$houseType ? 'border-site-glow text-site-accent font-bold' : 'bg-site-elevated text-site-secondary border-site hover:border-site-glow hover:text-site-primary' }}"
                style="{{ !$houseType ? 'background: var(--color-primary-glow);' : '' }}"
                aria-pressed="{{ !$houseType ? 'true' : 'false' }}">
                {{ $allLabel }}
            </button>

            @foreach($this->houseTypeOptions() as $value => $label)
                <button
                    type="button"
                    wire:click="$set('houseType', '{{ $value }}')"
                    class="inline-flex items-center min-h-[44px] px-4 py-2 rounded-full text-sm border transition-colors duration-150
                           {{ $houseType === $value ? 'border-site-glow text-site-accent font-bold' : 'bg-site-elevated text-site-secondary border-site hover:border-site-glow hover:text-site-primary' }}"
                    style="{{ $houseType === $value ? 'background: var(--color-primary-glow);' : '' }}"
                    aria-pressed="{{ $houseType === $value ? 'true' : 'false' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- 案例网格（wire:loading.delay 半透明防闪烁） --}}
    <div class="relative">
        {{-- 加载指示器（wire:loading.delay = 默认 200ms） --}}
        <div wire:loading wire:loading.delay
             class="absolute inset-0 z-10 flex items-center justify-center"
             aria-live="polite"
             aria-label="{{ $isZh ? '加载中' : 'Loading' }}">
            <svg class="w-8 h-8 border-2 border-site rounded-full animate-spin"
                 viewBox="0 0 24 24"
                 style="border-top-color: var(--color-primary);"
                 aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" class="opacity-25"></circle>
            </svg>
        </div>

        {{-- 网格区域（加载时半透明） --}}
        <div wire:loading.class="opacity-50 pointer-events-none"
             class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 transition-opacity duration-150">
            @forelse($cases as $case)
                @include('filament-admin-site::components.case-card', ['case' => $case])
            @empty
                <div class="col-span-full py-16 text-center">
                    <p class="text-site-muted text-base">
                        {{ $isZh ? '暂无案例，敬请期待' : 'No cases yet — check back soon' }}
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- 分页控件（前/后翻页） --}}
    @if($cases->hasPages())
        <div class="flex items-center justify-center gap-4 mt-10">
            <button
                type="button"
                wire:click="previousPage"
                class="btn-site-outline inline-flex items-center justify-center min-h-[44px] px-4 py-2 rounded-xl text-sm
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none
                       {{ !$cases->onFirstPage() ? '' : 'opacity-40 cursor-not-allowed' }}"
                {{ $cases->onFirstPage() ? 'disabled' : '' }}
                aria-label="{{ $prevLabel }}">
                {{ $prevLabel }}
            </button>

            <span class="text-site-muted text-sm" aria-live="polite">
                {{ $cases->currentPage() }} / {{ $cases->lastPage() }}
            </span>

            <button
                type="button"
                wire:click="nextPage"
                class="btn-site-outline inline-flex items-center justify-center min-h-[44px] px-4 py-2 rounded-xl text-sm
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none
                       {{ $cases->hasMorePages() ? '' : 'opacity-40 cursor-not-allowed' }}"
                {{ !$cases->hasMorePages() ? 'disabled' : '' }}
                aria-label="{{ $nextLabel }}">
                {{ $nextLabel }}
            </button>
        </div>
    @endif
</div>
