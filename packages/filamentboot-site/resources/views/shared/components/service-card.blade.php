{{--
 * 服务亮点卡片组件（UI-SPEC §Component 3）
 *
 * 用于首页服务亮点 3 列网格，接受 $icon（Heroicons SVG path）/$title/$body 属性。
 * 默认提供智能家居三大服务亮点文案。
 *
 * 三张卡对应的是**这家公司提供的三件服务**：方案设计 → 产品选型配套 → 施工交付。
 * 中间那张原来写的是「智能控制系统：一键掌控灯光、温控…」，那是在描述**设备能力**，
 * 不是服务——把它换成「品牌产品选型」，三张卡才连成一条完整的交付链路。
 * 措辞保持通用（不写具体品牌、不写公司名），下游换行业也不用改。
 --}}
@if(!isset($icon))
    {{-- 独立使用模式：渲染默认 3 张服务卡 --}}
    @php
        $defaultCards = [
            [
                'title' => '智能家居设计',
                'body'  => '为您量身定制智能家居解决方案，将最前沿的科技融入日常生活，让家更智能、更舒适。',
                'iconPath' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
            ],
            [
                'title' => '品牌产品选型',
                'body'  => '按空间条件与预算从各品牌里选型配套，给出设备清单与替代方案，兼容性与售后渠道都先谈清楚。',
                'iconPath' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
            ],
            [
                'title' => '专业施工服务',
                'body'  => '从设计到施工，全程专业团队负责，严格把控每一个细节，确保智能系统高质量、高稳定性落地。',
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
