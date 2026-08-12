{{--
 * 路线图区块（两套主题共用，五期批次 4d）
 *
 * 按状态分三组渲染，固定顺序「已有 → 开发中 → 计划中」；某一档没有条目就
 * 整组不渲染（不用空标题占位凑数）。三档徽标复用已验证过对比度的既有语义类
 * （btn-site-primary / btn-site-outline / bg-site-elevated+text-site-secondary），
 * 不新增颜色——本主题「全站只有一个强调色」的用色纪律同样适用于这里，
 * 三档靠实心/描边/灰底三种质感区分，不靠新增色相。
 --}}
@php
    $title = (string) ($data['title'] ?? '');
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];

    $groups = [
        'available'   => ['label' => '已有', 'badgeClass' => 'btn-site-primary', 'items' => []],
        'in_progress' => ['label' => '开发中', 'badgeClass' => 'btn-site-outline font-semibold', 'items' => []],
        'planned'     => ['label' => '计划中', 'badgeClass' => 'bg-site-elevated text-site-secondary', 'items' => []],
    ];

    foreach ($items as $item) {
        $status = (string) ($item['status'] ?? '');

        if (isset($groups[$status])) {
            $groups[$status]['items'][] = $item;
        }
    }

    $headingId = 'block-roadmap-' . $index;
@endphp

@if(collect($groups)->pluck('items')->flatten(1)->isNotEmpty())
    <section class="py-16 bg-site-subtle" @if($title !== '') aria-labelledby="{{ $headingId }}" @endif>
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            @if($title !== '')
                <h2 id="{{ $headingId }}"
                    class="text-site-primary text-2xl md:text-3xl font-bold mb-10 flex items-center gap-3">
                    <span class="inline-block w-1 h-8 rounded-full shrink-0" style="background: var(--color-primary);"></span>
                    {{ $title }}
                </h2>
            @endif

            <div class="space-y-12">
                @foreach($groups as $group)
                    @continue(empty($group['items']))

                    <div>
                        <h3 class="flex items-center gap-3 mb-5">
                            <span class="{{ $group['badgeClass'] }} text-xs px-3 py-1 rounded-full">
                                {{ $group['label'] }}
                            </span>
                            <span class="text-site-muted text-xs">{{ count($group['items']) }} 项</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($group['items'] as $item)
                                @php
                                    $itemTitle = (string) ($item['title'] ?? '');
                                    $itemDesc  = (string) ($item['description'] ?? '');
                                @endphp
                                <article class="bg-site-surface rounded-xl border border-site p-5">
                                    <h4 class="text-site-primary font-bold text-base mb-2 leading-snug">{{ $itemTitle }}</h4>
                                    @if($itemDesc !== '')
                                        <p class="text-site-secondary text-sm leading-relaxed whitespace-pre-line">{{ $itemDesc }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
