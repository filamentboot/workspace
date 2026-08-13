<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders\Demo;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Database\Seeders\Concerns\SeedsBySlug;
use Filamentboot\FilamentbootSite\Database\Seeders\Concerns\SeedsMediaImages;
use Filamentboot\FilamentbootSite\Models\SiteTag;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * decoration 主题资讯演示内容种子（批次 3）
 *
 * 由 SiteNewsSeeder 按 SiteServiceProvider::resolveActiveTheme() 分发到这里；
 * 不要在测试或代码里直接依赖这个类名，应该 seed SiteNewsSeeder。
 *
 * 选题不是凭空拟的：来自 docs/素材采集/批次-20260804-京东SKU调研/reviews-insight.json 里聚合出的
 * 真实购前疑虑（「不买存储卡能回看？」「4 米多买一个能行吗」「镇上的能不能做」
 * 「安装服务方便吗」），一条疑虑对应一篇文章。营销站的资讯流要吃到长尾搜索，
 * 选题必须是用户真的会搜的问句，而不是行业黑话。
 *
 * 时间分布刻意跨 6 个月：归档页按年月分组，全堆在同一个月侧栏就只有一行，
 * 看不出功能是否正常。
 *
 * 内容里含一篇草稿（published_at = null），用于验证前台不泄露未发布内容
 * （T-10-04-04）以及后台的状态筛选。
 *
 * 富文本只使用 Support\RichText 白名单内的标签。写这批内容时前台还在用
 * purifier 的 default 画像（没有 h2/h3），所以标题层级用 <p><strong> 表达；
 * 画像已修好，新写的内容可以直接用 <h3>。
 *
 * 幂等：可反复执行，按 slug 增量补种，见 Concerns\SeedsBySlug。
 * 标签是按 slug 显式指定的（不像案例那样随机取），重复 sync 结果稳定。
 *
 * 标签与 DecorationDemoSeeder 共用两个 slug（smart-home / full-custom），
 * 按 slug 取用已存在的行——两边都用 firstOrCreateBySlug，谁先跑都一样。
 */
class DecorationNewsSeeder extends Seeder
{
    use SeedsBySlug;
    use SeedsMediaImages;

    /**
     * 执行资讯演示数据播种
     */
    public function run(): void
    {
        $categories = $this->categories();
        $tags       = $this->tags();

        foreach ($this->articlesData($categories) as $data) {
            /** @var list<string> $tagSlugs */
            $tagSlugs = $data['tag_slugs'];
            unset($data['tag_slugs']);

            $data['seo_title']       = $data['title_zh'].' - 示例装修智能家居';
            $data['seo_description'] = $data['description_zh'];
            $data['status']          = $data['published_at'] !== null ? PageStatus::PUBLISHED : PageStatus::DRAFT;

            $article = $this->firstOrCreateBySlug(NewsArticle::class, $data);

            // 封面图：仅取本地图片，无图时由前台占位组件兜底（D-11-11）
            $this->addCoverImage($article, 'site/demo/decoration/news/'.$data['slug'].'.jpg');

            $article->tags()->syncWithoutDetaching(
                $tags->whereIn('slug', $tagSlugs)->pluck('id')->all()
            );
        }
    }

    /**
     * 本 Seeder 会写入的资讯 slug 清单（批次 3，供后台「清空演示数据」使用）
     *
     * 分类（NewsCategory）与标签不在清单内，理由与 DecorationDemoSeeder::seededSlugs()
     * 一致——共享词表，不按本清单清空。
     *
     * @return array<class-string, list<string>>
     */
    public static function seededSlugs(): array
    {
        return [
            NewsArticle::class => [
                'when-to-involve-smart-home-installer', 'camera-storage-options',
                'gateway-single-or-dual-protocol', 'no-main-light-illuminance',
                'do-you-need-ethernet', 'curtain-motor-for-wide-windows',
                'smart-lock-buying-pitfalls', 'is-voice-control-useful',
                'handover-checklist', 'service-coverage', 'h1-selection-review-draft',
            ],
        ];
    }

    /**
     * 资讯分类（按内容意图分，不按产品品类分）
     *
     * @return Collection<int, NewsCategory>
     */
    protected function categories(): Collection
    {
        return collect([
            ['name_zh' => '选型指南', 'slug' => 'xuan-xing-zhi-nan', 'sort' => 1],
            ['name_zh' => '装修攻略', 'slug' => 'zhuang-xiu-gong-lue', 'sort' => 2],
            ['name_zh' => '使用答疑', 'slug' => 'shi-yong-da-yi', 'sort' => 3],
            ['name_zh' => '公司动态', 'slug' => 'gong-si-dong-tai', 'sort' => 4],
        ])->map(
            fn (array $data): NewsCategory => $this->firstOrCreateBySlug(NewsCategory::class, $data)
        );
    }

    /**
     * 标签（前两个与 DecorationDemoSeeder 共用 slug，按 slug 取用已存在的行）
     *
     * @return Collection<int, SiteTag>
     */
    protected function tags(): Collection
    {
        return collect([
            ['name_zh' => '智能家居', 'slug' => 'smart-home'],
            ['name_zh' => '全屋定制', 'slug' => 'full-custom'],
            ['name_zh' => '照明设计', 'slug' => 'lighting-design'],
            ['name_zh' => '家庭安防', 'slug' => 'home-security'],
            ['name_zh' => '竣工验收', 'slug' => 'acceptance'],
        ])->map(
            fn (array $data): SiteTag => $this->firstOrCreateBySlug(SiteTag::class, $data)
        );
    }

    /**
     * 文章数据
     *
     * @param  Collection<int, NewsCategory>  $categories
     * @return list<array<string, mixed>>
     */
    protected function articlesData(Collection $categories): array
    {
        $guide      = $categories->firstWhere('slug', 'xuan-xing-zhi-nan')?->id;
        $renovation = $categories->firstWhere('slug', 'zhuang-xiu-gong-lue')?->id;
        $faq        = $categories->firstWhere('slug', 'shi-yong-da-yi')?->id;
        $company    = $categories->firstWhere('slug', 'gong-si-dong-tai')?->id;

        return [
            [
                'title_zh'       => '装修到哪一步该找智能家居公司',
                'slug'           => 'when-to-involve-smart-home-installer',
                'category_id'    => $renovation,
                'is_featured'    => true,
                'sort'           => 1,
                'published_at'   => now()->subDays(2),
                'seo_keywords'   => '智能家居,装修流程,水电阶段,弱电设计,武汉',
                'tag_slugs'      => ['full-custom', 'smart-home'],
                'description_zh' => '答案比大多数人想的都早：在水电进场之前。晚一步，能选的方案就少一半。',
                'content_zh'     => <<<'HTML'
                    <p>这是我们被问得最多的问题，也是最容易错过的时间点。不少业主在软装快收尾时才想起智能，那时候能做的只剩「插在插座上的那一类」。</p>
                    <p><strong>三个关键节点</strong></p>
                    <ul>
                    <li><strong>设计阶段（最佳）</strong>：还能改点位、改回路、改吊顶造型。无主灯照明、隐藏式窗帘盒、中控屏预埋都要在这一步定下来。</li>
                    <li><strong>水电进场前（底线）</strong>：网线、零火线、窗帘盒电源必须此时确认。封槽之后再加，只能走明线。</li>
                    <li><strong>软装阶段（补救）</strong>：还能装无线开关、传感器、摄像头、智能插座，但无主灯和电动窗帘基本告别。</li>
                    </ul>
                    <p><strong>为什么这么早</strong></p>
                    <p>智能家居有一半工作量在弱电和回路设计上，不在设备本身。灯光分几路、哪一路留给感应、窗帘盒留多深，这些都是装修图纸上的事。设备可以晚买，图纸不能重画。</p>
                    <p><strong>我们能配合到什么程度</strong></p>
                    <p>可以直接和装修公司的水电师傅对接点位，出一份标注了回路与预埋要求的弱电图，业主不需要在中间当传声筒。这一步不收费，也不绑定后续下单。</p>
                    HTML,
            ],
            [
                'title_zh'       => '摄像头不买存储卡能回看吗',
                'slug'           => 'camera-storage-options',
                'category_id'    => $faq,
                'is_featured'    => false,
                'sort'           => 2,
                'published_at'   => now()->subDay(),
                'seo_keywords'   => '摄像头存储,云存储,NVR,监控回看,存储卡',
                'tag_slugs'      => ['home-security'],
                'description_zh' => '能，但代价不一样。三种存储方式的实际成本和丢录风险，一次说清。',
                'content_zh'     => <<<'HTML'
                    <p>这是询盘里出现频率最高的问题之一。答案是能回看，但要看你选哪一种存储——三种方式的成本结构差别很大。</p>
                    <p><strong>三种存储怎么选</strong></p>
                    <ul>
                    <li><strong>本机存储卡</strong>：一次性投入，64GB 大约能循环存 7-10 天。缺点很直接：摄像头被拆走，录像也一起走了。</li>
                    <li><strong>云存储</strong>：按月或按年付费，录像不在现场，防拆意义最大。缺点是断网期间的画面会缺失，且长期算下来最贵。</li>
                    <li><strong>NVR 网络硬盘录像机</strong>：多路统一存，单路成本最低，适合四路以上或需要长期留存的场景。缺点是要给它留位置和不断电的电源。</li>
                    </ul>
                    <p><strong>会漏录吗</strong></p>
                    <p>纯移动侦测触发的录制一定会漏。人从画面边缘快速走过、强逆光、夜间红外切换的那一两秒都可能不触发。要真正不漏就得开连续录制，这时候存储容量成了硬约束——这也是我们建议入户门和院子这两个位置直接上 NVR 的原因。</p>
                    <p><strong>一个容易忽略的细节</strong></p>
                    <p>存储卡请用监控专用卡。普通卡是按「偶尔写入」设计的，24 小时连续覆写通常撑不过半年，而坏卡的表现恰恰是「以为在录，其实没录」。</p>
                    HTML,
            ],
            [
                'title_zh'       => '网关选单模还是双模',
                'slug'           => 'gateway-single-or-dual-protocol',
                'category_id'    => $guide,
                'is_featured'    => true,
                'sort'           => 3,
                'published_at'   => now()->subMonthNoOverflow()->startOfMonth()->addDays(11),
                'seo_keywords'   => '智能网关,Zigbee,蓝牙Mesh,协议选型,全屋智能',
                'tag_slugs'      => ['smart-home', 'full-custom'],
                'description_zh' => '协议选错，后面每加一台设备都是一次妥协。先想清楚三年后的规模，再决定网关买哪一款。',
                'content_zh'     => <<<'HTML'
                    <p>装全屋智能，第一个绕不过去的问题是网关。设备清单可以随时加，协议底座换一次却要把已经装好的面板全拆下来——这是我们回访时听到最多的返工原因。</p>
                    <p><strong>三种协议各适合什么</strong></p>
                    <ul>
                    <li><strong>Zigbee</strong>：自组网、低功耗，传感器和开关的主力。设备越多网络越稳，适合做全屋底座。</li>
                    <li><strong>蓝牙 Mesh</strong>：入网快、成本低，单点部署最省事，但节点多了转发延迟会累积。</li>
                    <li><strong>Wi-Fi</strong>：带宽大，摄像头、中控屏这类要传画面的设备只能走它。缺点是每台设备都占一个路由器名额，家用路由通常到 30 台左右就开始掉线。</li>
                    </ul>
                    <p><strong>什么时候必须上双模</strong></p>
                    <p>设备数超过 25 台，或者同时存在电池类传感器和大功率执行器（窗帘电机、地暖阀门）时，单模网关的短板会集中暴露：Zigbee 单模接不了 Wi-Fi 摄像头，蓝牙单模又扛不住几十个节点的转发。双模网关多出来的两三百块，比日后拆墙换面板便宜太多。</p>
                    <p><strong>我们的做法</strong></p>
                    <p>量房时先按房间列出三年内可能装的设备清单，再倒推协议配比。100㎡ 以内、设备 20 台左右的常规三居，一台双模网关加一只有线中继就够；别墅和复式按楼层各放一台，避免跨层穿墙丢包。</p>
                    HTML,
            ],
            [
                'title_zh'       => '无主灯不是拆掉吸顶灯：照度该怎么算',
                'slug'           => 'no-main-light-illuminance',
                'category_id'    => $renovation,
                'is_featured'    => false,
                'sort'           => 4,
                'published_at'   => now()->subMonthNoOverflow()->startOfMonth()->addDays(4),
                'seo_keywords'   => '无主灯,照度计算,射灯,防眩光,灯光设计',
                'tag_slugs'      => ['lighting-design'],
                'description_zh' => '无主灯做失败的十套里有九套是照度不够。先算够不够亮，再谈好不好看。',
                'content_zh'     => <<<'HTML'
                    <p>无主灯这两年被讲成了审美问题，实际上它首先是一道算术题。射灯数量不够、光束角选错，装完就是「氛围有了，看书不行」。</p>
                    <p><strong>先算总光通量</strong></p>
                    <p>居家空间的推荐照度大致是：客厅 100-150lx、书房和厨房 300lx、卧室 75-100lx。面积乘照度再除以灯具效率，就是这个空间需要的总光通量。20㎡ 的客厅按 150lx 算大约需要 4500lm，折成常见的 800lm 射灯是六只左右——这也是为什么四只射灯的方案永远差一档。</p>
                    <p><strong>再分层</strong></p>
                    <ul>
                    <li><strong>基础照明</strong>：筒灯或线性灯打匀，负责「看得清」。</li>
                    <li><strong>重点照明</strong>：射灯打墙面、挂画、餐桌，负责层次。</li>
                    <li><strong>氛围照明</strong>：灯带藏进吊顶或柜体，负责「待得住」。</li>
                    </ul>
                    <p><strong>三个容易踩的坑</strong></p>
                    <ul>
                    <li>防眩深度不够，躺在沙发上正好看进光源。选 UGR19 以下、带深藏遮光罩的型号。</li>
                    <li>色温不统一，同一空间混了 3000K 和 4000K，拍照一半暖一半冷。</li>
                    <li>吊顶高度不足 2.6m 还硬做磁吸轨道，压得人喘不过气。</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '全屋智能到底要不要提前走网线',
                'slug'           => 'do-you-need-ethernet',
                'category_id'    => $guide,
                'is_featured'    => false,
                'sort'           => 5,
                'published_at'   => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(17),
                'seo_keywords'   => '弱电布线,网线预埋,AP面板,水电改造,全屋智能',
                'tag_slugs'      => ['smart-home', 'full-custom'],
                'description_zh' => '无线方案越来越能打，但有三个位置的网线省不掉——省下来的钱，后期会以明线的形式还回来。',
                'content_zh'     => <<<'HTML'
                    <p>「都无线了还走什么网线」是水电阶段最常听到的一句话。无线确实够用了，但有几个位置一旦漏埋，后期只能走明线，或者放弃功能。</p>
                    <p><strong>这三个位置的网线不能省</strong></p>
                    <ul>
                    <li><strong>弱电箱到客厅电视墙</strong>：中控屏、机顶盒、影音设备都挤在这一片，是 Wi-Fi 抢带宽最凶的地方。</li>
                    <li><strong>网关安装位</strong>：网关有线上行才谈得上稳定，挂在无线中继下面等于把整屋设备架在一根摇晃的杆子上。</li>
                    <li><strong>每层的 AP 点位</strong>：吸顶 AP 靠 PoE 供电，一根网线同时解决供电和回传。靠无线桥接的多层组网，二楼的延迟肉眼可见。</li>
                    </ul>
                    <p><strong>可以省的</strong></p>
                    <p>开关面板、传感器、窗帘电机都走无线协议，不需要网线。零火版开关只要有火线和零线就能装，这也是老房改造仍然能做智能照明的前提。</p>
                    <p><strong>时间点</strong></p>
                    <p>网线要在水电进场前定好点位，封槽之后再加就要重新开线槽。这也是我们建议在水电阶段就介入的原因——那一周做的决定，决定了后面三年能装什么。</p>
                    HTML,
            ],
            [
                'title_zh'       => '4 米以上的窗帘，一个电机够不够',
                'slug'           => 'curtain-motor-for-wide-windows',
                'category_id'    => $faq,
                'is_featured'    => false,
                'sort'           => 6,
                'published_at'   => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(7),
                'seo_keywords'   => '电动窗帘,窗帘电机,双电机对开,轨道,拉力',
                'tag_slugs'      => ['smart-home'],
                'description_zh' => '看重量不看长度。同样 4 米宽，遮光布和纱帘对电机的要求差一倍。',
                'content_zh'     => <<<'HTML'
                    <p>客厅落地窗普遍超过 3 米，这个问题几乎每单都会遇到。判断依据不是长度，是帘布总重量和轨道形式。</p>
                    <p><strong>先估重量</strong></p>
                    <p>常见遮光布约 250-400g/㎡，按 2.8m 高、4m 宽双层褶皱估算，帘布重量大约落在 12-18kg。常规窗帘电机的额定拉力在 20-30N，对应顺滑轨道上 10-15kg 的负载——4 米宽的双层遮光帘已经踩在临界点上。</p>
                    <p><strong>三种处理方式</strong></p>
                    <ul>
                    <li><strong>双电机对开</strong>：左右各一台，各拉一半。最稳妥，也是我们对 4 米以上默认的做法。</li>
                    <li><strong>单电机加大扭矩</strong>：适合单层纱帘或轻质布，成本低，但长期接近满负载会缩短电机寿命。</li>
                    <li><strong>分段做</strong>：中间加一根立柱分成两组，适合异形窗和转角窗。</li>
                    </ul>
                    <p><strong>比电机更容易被忽略的</strong></p>
                    <p>轨道的直线度和吊装点间距。轨道有下垂，再好的电机也会卡顿，噪音多半也是从这里来的——不少「电机声音大」的投诉，最后查出来是轨道没吊平。</p>
                    HTML,
            ],
            [
                'title_zh'       => '智能门锁选购避坑：锁体、天地钩与锁芯',
                'slug'           => 'smart-lock-buying-pitfalls',
                'category_id'    => $guide,
                'is_featured'    => false,
                'sort'           => 7,
                'published_at'   => now()->subMonthsNoOverflow(3)->startOfMonth()->addDays(13),
                'seo_keywords'   => '智能门锁,锁体,天地钩,C级锁芯,上门安装',
                'tag_slugs'      => ['home-security'],
                'description_zh' => '门锁是唯一「装错了要换门」的智能设备。下单前先量三个数：门厚、锁体开孔、有没有天地钩。',
                'content_zh'     => <<<'HTML'
                    <p>门锁的退货率在智能家居里排第一，原因几乎都不是功能不好，而是装不上。三个尺寸没量清楚，货到了才发现门开不了孔。</p>
                    <p><strong>下单前先量三个数</strong></p>
                    <ul>
                    <li><strong>门厚</strong>：常规门 40-60mm，入户防盗门可能超过 100mm。超出锁具适配范围要单独订加长螺杆。</li>
                    <li><strong>锁体开孔尺寸</strong>：把原锁体拆下来量导向片间距，这一步决定要不要换锁体、要不要扩孔。</li>
                    <li><strong>天地钩</strong>：带天地钩的防盗门必须选支持联动的锁体，否则上下插销失效，防盗等级直接掉一档。</li>
                    </ul>
                    <p><strong>锁芯别只看等级标签</strong></p>
                    <p>C 级锁芯是底线，但真正的差别在锁体材质和离合结构。全自动锁的电机负载比半自动大，用久了掉力的多半是廉价离合。我们只选提供锁体单独质保的型号。</p>
                    <p><strong>关于上门安装</strong></p>
                    <p>门锁属于必须上门的品类，武汉三环内一般 48 小时内排期，需要换锁体的会提前告知加装费用。装完当场录指纹、试反锁、教一遍机械钥匙的应急开法——这三步不做完不算交付。</p>
                    HTML,
            ],
            [
                'title_zh'       => '语音控制到底实用吗',
                'slug'           => 'is-voice-control-useful',
                'category_id'    => $faq,
                'is_featured'    => false,
                'sort'           => 8,
                'published_at'   => now()->subMonthsNoOverflow(3)->startOfMonth()->addDays(5),
                'seo_keywords'   => '语音控制,智能音箱,场景联动,中控屏,使用体验',
                'tag_slugs'      => ['smart-home'],
                'description_zh' => '装完半年还在用的语音指令，通常不超过五条。知道是哪五条，就知道该不该为它加预算。',
                'content_zh'     => <<<'HTML'
                    <p>演示的时候语音最出彩，日常反而用得最少。这不是产品问题，是场景问题——手能碰到开关的地方，人不会开口说话。</p>
                    <p><strong>语音真正好用的三种场景</strong></p>
                    <ul>
                    <li><strong>手上有东西</strong>：抱着孩子、拎着菜进门，喊一声开灯比摸开关快。</li>
                    <li><strong>已经躺下</strong>：床头没有面板，或者关灯得走到门口。</li>
                    <li><strong>批量操作</strong>：「我出门了」一句话关掉全屋十几路灯和空调，这是面板做不到的。</li>
                    </ul>
                    <p><strong>语音替代不了的</strong></p>
                    <p>调亮度、调色温这类需要反复微调的操作，说三遍不如旋钮转一下。识别失败的挫败感也远大于按错一次开关，所以我们从不把语音设成唯一入口——每一个语音能做的动作，面板或 App 上都要有对应的实体路径。</p>
                    <p><strong>结论</strong></p>
                    <p>值得装，但不值得为它单独加预算。已经有中控屏或音箱的家庭顺带就有了语音；为了语音单独买一圈设备，半年后大概会闲置。</p>
                    HTML,
            ],
            [
                'title_zh'       => '智能家居竣工验收清单：12 项逐条确认',
                'slug'           => 'handover-checklist',
                'category_id'    => $renovation,
                'is_featured'    => false,
                'sort'           => 9,
                'published_at'   => now()->subMonthsNoOverflow(4)->startOfMonth()->addDays(19),
                'seo_keywords'   => '竣工验收,交付清单,场景调试,质保,智能家居',
                'tag_slugs'      => ['acceptance', 'full-custom'],
                'description_zh' => '交付不是「都能用」就算完。这 12 项每一项都对应我们踩过的一次返工。',
                'content_zh'     => <<<'HTML'
                    <p>智能家居的验收和装修验收不一样：功能当场都能演示成功，问题往往两周后才冒出来。下面这份清单是我们自己的交付标准，业主可以照着逐条验。</p>
                    <p><strong>基础</strong></p>
                    <ul>
                    <li>每个开关面板的按键与灯具对应关系，与图纸一致</li>
                    <li>断电再上电后，网关和设备能自动恢复在线</li>
                    <li>路由器重启后，所有 Wi-Fi 设备五分钟内自动回连</li>
                    <li>网关为有线上行，没有挂在无线中继下面</li>
                    </ul>
                    <p><strong>场景</strong></p>
                    <ul>
                    <li>回家、离家、就寝、观影四个基础场景全部实测</li>
                    <li>感应类场景在白天和夜间各测一次（光照阈值经常只调了一种）</li>
                    <li>断网状态下，本地场景（开关、感应灯）仍可执行</li>
                    <li>误触发排查：宠物走动、窗帘飘动、空调出风是否会误触人体感应</li>
                    </ul>
                    <p><strong>交付物</strong></p>
                    <ul>
                    <li>点位图与回路表，标注每个面板对应哪几盏灯</li>
                    <li>账号归属确认：主账号在业主手上，施工账号已移除</li>
                    <li>设备清单与质保期，含每个单品的保修起算日</li>
                    <li>一次完整的使用讲解，家里的老人和孩子也要会用</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '服务范围说明：周边县镇能不能上门',
                'slug'           => 'service-coverage',
                'category_id'    => $company,
                'is_featured'    => false,
                'sort'           => 10,
                'published_at'   => now()->subMonthsNoOverflow(5)->startOfMonth()->addDays(15),
                'seo_keywords'   => '服务范围,上门安装,武汉周边,远程指导,售后',
                'tag_slugs'      => ['smart-home'],
                'description_zh' => '能做，但要提前说清路程和响应时间。把边界讲明白，比含糊接单再拖工期负责。',
                'content_zh'     => <<<'HTML'
                    <p>「镇上的能不能做」是我们后台真实出现过的提问。这里把服务半径和响应时间一次讲清楚，省得来回问。</p>
                    <p><strong>上门服务范围</strong></p>
                    <ul>
                    <li><strong>武汉三环内</strong>：量房与安装均可当周排期，售后一般 24 小时内响应。</li>
                    <li><strong>武汉全域及鄂州、黄冈、孝感、咸宁市区</strong>：量房需提前 2-3 天预约，安装按批次排期。</li>
                    <li><strong>上述城市下辖县镇</strong>：可以做。单程超过 100 公里的，我们会先确认工程量是否值得跑一趟——只装两三个开关的小单，建议改成远程指导加本地电工配合，比让业主等一周实在。</li>
                    </ul>
                    <p><strong>远程能做到什么</strong></p>
                    <p>方案设计、点位图、设备清单、配置调试都可以远程完成；需要动线的部分由本地电工执行，我们提供图纸和视频指导，联调阶段远程接入验收。</p>
                    <p><strong>售后</strong></p>
                    <p>不需要上门就能解决的问题（配网、场景失效、账号迁移）远程处理不收费。需要换件上门的，县镇范围按实际路程另计交通成本，报价前一次说清。</p>
                    HTML,
            ],
            [
                // 草稿：验证前台不泄露未发布内容（T-10-04-04），也给后台状态筛选留一条样本
                'title_zh'       => '上半年选品复盘（未定稿）',
                'slug'           => 'h1-selection-review-draft',
                'category_id'    => $company,
                'is_featured'    => false,
                'sort'           => 11,
                'published_at'   => null,
                'seo_keywords'   => '选品复盘,主推型号,售后回访',
                'tag_slugs'      => ['smart-home'],
                'description_zh' => '半年里换掉了三个品类的主推型号，记录一下换的原因。',
                'content_zh'     => <<<'HTML'
                    <p>本文尚未定稿，正在补充退换与故障数据。</p>
                    <p>上半年主推型号有三处调整：入门网关从单模换成双模、室内摄像头从固定款换成云台款、窗帘电机在 4 米以上默认改为双电机对开。三处调整都来自售后回访，具体数据整理完再发。</p>
                    HTML,
            ],
        ];
    }
}
