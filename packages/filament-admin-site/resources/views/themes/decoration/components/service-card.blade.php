{{--
 * 服务亮点卡片组件（UI-SPEC §Component 3）
 *
 * 用于首页服务亮点 3 列网格，接受 $icon（Heroicons SVG path）/$title/$body 属性。
 * 默认提供智能家居三大服务亮点文案（中英双语）。
 --}}
@php
    $isZh = app()->getLocale() !== 'en';
@endphp

@if(!isset($icon))
    {{-- 独立使用模式：渲染默认 3 张服务卡 --}}
    @php
        $defaultCards = $isZh ? [
            [
                'title' => '智能家居设计',
                'body'  => '为您量身定制智能家居解决方案，将最前沿的科技融入日常生活，让家更智能、更舒适。',
                'iconPath' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
            ],
            [
                'title' => '智能控制系统',
                'body'  => '一键掌控灯光、温控、安防、窗帘等全屋设备，通过手机 App 或语音随时随地管理您的家。',
                'iconPath' => 'M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3',
            ],
            [
                'title' => '专业施工服务',
                'body'  => '从设计到施工，全程专业团队负责，严格把控每一个细节，确保智能系统高质量、高稳定性落地。',
                'iconPath' => 'M11.42 15.17 17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z',
            ],
        ] : [
            [
                'title' => 'Smart Home Design',
                'body'  => 'Tailored smart home solutions that integrate cutting-edge technology into everyday life for greater comfort and intelligence.',
                'iconPath' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
            ],
            [
                'title' => 'Smart Control Systems',
                'body'  => 'Control lighting, climate, security, and more with one tap — manage your home via app or voice from anywhere.',
                'iconPath' => 'M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3',
            ],
            [
                'title' => 'Professional Installation',
                'body'  => 'End-to-end professional team from design to installation, ensuring every smart system is delivered with quality and reliability.',
                'iconPath' => 'M11.42 15.17 17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z',
            ],
        ];
    @endphp

    @foreach($defaultCards as $card)
        <div class="bg-site-surface border border-site rounded-2xl p-6 card-hover">
            {{-- 图标容器 --}}
            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-6"
                 style="background: var(--color-primary-glow);">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                     stroke="currentColor" aria-hidden="true"
                     style="color: var(--color-primary);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['iconPath'] }}" />
                </svg>
            </div>
            <h3 class="text-site-primary text-xl font-bold mb-2">{{ $card['title'] }}</h3>
            <p class="text-site-secondary text-base leading-relaxed">{{ $card['body'] }}</p>
        </div>
    @endforeach
@else
    {{-- 单卡模式 --}}
    <div class="bg-site-surface border border-site rounded-2xl p-6 card-hover">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-6"
             style="background: var(--color-primary-glow);">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                 stroke="currentColor" aria-hidden="true"
                 style="color: var(--color-primary);">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
            </svg>
        </div>
        <h3 class="text-site-primary text-xl font-bold mb-2">{{ $title ?? '' }}</h3>
        <p class="text-site-secondary text-base leading-relaxed">{{ $body ?? '' }}</p>
    </div>
@endif
