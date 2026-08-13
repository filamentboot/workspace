<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders\Demo;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Database\Seeders\Concerns\SeedsBySlug;
use Filamentboot\FilamentbootSite\Database\Seeders\Concerns\SeedsMediaImages;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Models\SiteTag;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerCtaAction;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerPosition;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Models\SiteBanner;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCaseCategory;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteCityPage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteRegion;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\HouseLayout;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\PackageTier;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Models\SitePackage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProductCategory;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * decoration 主题演示内容种子（批次 3）
 *
 * 植入虚构的「示例装修有限公司」演示数据：装修案例/智能方案/产品/静态页面（D-10-18）。
 * 主体与文案原为 qkznj 交付时的真实客户内容，五期批次 3 起改为虚构主体——
 * 本仓库是 filamentboot 开源包的唯一开发地，下游 `composer require` 装完包
 * 点一下「种入演示数据」，种出来的不该是别人的真实公司（CLAUDE.md 硬约束）。
 *
 * 由 SiteDemoSeeder 按 SiteServiceProvider::resolveActiveTheme() 分发到这里；
 * 不要在测试或代码里直接依赖这个类名，应该 seed SiteDemoSeeder。
 *
 * 图片：仅使用本地 storage/app/public/site/ 目录图片（D-11-11）；
 * 图片不存在时不写入任何媒体，由前台 image-placeholder 组件渲染空态。
 * 播种数据禁止引入 picsum.photos 等外部图片服务。
 * diskRelPath 形如 'site/demo/decoration/cases/modern-3bed-smart.jpg'，相对 public disk（Pitfall 5）。
 * 禁止使用已关闭的 Unsplash source 接口（per RESEARCH Pitfall 7）。
 *
 * 文案来源：docs/素材采集/批次-20260804-京东SKU调研/ 的竞品结构调研 + reviews-insight.json 的真实关注维度，
 * 一律改写不照搬（理由见 productsData() 注释）。资讯内容在 Demo\DecorationNewsSeeder。
 *
 * 幂等：可反复执行，按 slug 增量补种——已有的记录一概不动（用户改过的文案
 * 不会被覆盖），缺的补上。软删除过的记录不复活，见 Concerns\SeedsBySlug。
 *
 * 封面图每次都会重新尝试挂载：图片是后补的，放进 storage/app/public/site/
 * 之后重跑一遍种子即可挂上，已有媒体的记录会被跳过。
 */
class DecorationDemoSeeder extends Seeder
{
    use SeedsBySlug;
    use SeedsMediaImages;

    /**
     * 执行演示数据播种
     */
    public function run(): void
    {
        // 1. 创建装修案例分类
        $caseCategoryData = [
            ['name_zh' => '现代简约', 'slug' => 'modern-minimalist', 'sort' => 1],
            ['name_zh' => '智能家居全屋', 'slug' => 'full-smart-home', 'sort' => 2],
            ['name_zh' => '局部改造', 'slug' => 'partial-renovation', 'sort' => 3],
            ['name_zh' => '新房装修', 'slug' => 'new-home', 'sort' => 4],
            ['name_zh' => '别墅豪装', 'slug' => 'luxury-villa', 'sort' => 5],
        ];

        $caseCategories = collect($caseCategoryData)->map(
            fn (array $data) => $this->firstOrCreateBySlug(SiteCaseCategory::class, $data)
        );

        // 2. 创建产品分类
        $productCategoryData = [
            ['name_zh' => '智能照明', 'slug' => 'smart-lighting', 'sort' => 1],
            ['name_zh' => '智能安防', 'slug' => 'smart-security', 'sort' => 2],
            ['name_zh' => '智能家电', 'slug' => 'smart-appliances', 'sort' => 3],
        ];

        $productCategories = collect($productCategoryData)->map(
            fn (array $data) => $this->firstOrCreateBySlug(SiteProductCategory::class, $data)
        );

        // 3. 创建标签
        $tagData = [
            ['name_zh' => '智能家居', 'slug' => 'smart-home'],
            ['name_zh' => '全屋定制', 'slug' => 'full-custom'],
            ['name_zh' => '节能环保', 'slug' => 'eco-friendly'],
            ['name_zh' => '豪华精装', 'slug' => 'luxury-finish'],
            ['name_zh' => '性价比', 'slug' => 'value'],
        ];

        $tags = collect($tagData)->map(
            fn (array $data) => $this->firstOrCreateBySlug(SiteTag::class, $data)
        );

        // 4. 创建装修案例（6 个）
        // 图片：仅取本地 site/cases/{slug}.jpg，缺失时不写媒体（D-11-11）
        $casesData = [
            [
                'title_zh'       => '现代简约三居室全屋智能改造',
                'slug'           => 'modern-3bed-smart',
                'style'          => 'modern',
                'house_type'     => 'three_bedroom',
                'area'           => '120㎡',
                'budget_range'   => '25-35万',
                'smart_features' => '全屋智能灯光控制、智能窗帘、中央空调联动、智能门锁',
                'description_zh' => '武汉某小区三居室，业主的智能预算不多，要求很明确：只花在每天都会用到的地方。灯光分 12 路，玄关与卫生间走存在传感器，没做中控屏。',
                'content_zh'     => <<<'HTML'
                    <p>业主是一对刚接房的年轻夫妻，装修预算里留给智能的份额不多，要求很明确：钱要花在每天都会用到的地方。</p>
                    <p><strong>我们做了什么</strong></p>
                    <ul>
                    <li>全屋灯光分 12 路，客厅按沙发区、餐桌、过道分开控制</li>
                    <li>玄关与卫生间用存在传感器，夜间自动 10% 亮度</li>
                    <li>主卧床头双控面板，一键关全屋灯</li>
                    <li>客厅落地窗 3.6m，双电机对开</li>
                    </ul>
                    <p><strong>没做什么</strong></p>
                    <p>没有装中控屏，也没做影音联动。三居室日常动线短，面板加手机已经够用，省下的预算加在了灯光回路和窗帘上。</p>
                    <p><strong>工期</strong></p>
                    <p>水电阶段介入 1 天定点位，主体施工与装修同步，末端安装与调试 2 天，验收 1 天。</p>
                    HTML,
                'customer_name'   => '张先生',
                'customer_meta'   => '光谷保利时区 · 入住 8 个月',
                'customer_quote'  => '最实用的其实是玄关那盏感应灯，抱着孩子进门不用摸开关。当时觉得可有可无，现在天天在用。',
                'seo_title'       => '现代简约三居室全屋智能改造案例 - 示例装修',
                'seo_description' => '示例装修为武汉业主打造现代简约智能家居，全屋智能灯光、窗帘、门锁一体控制，免费上门量房设计。',
                'seo_keywords'    => '智能家居,现代简约,全屋改造,武汉,示例装修',
                'category_id'     => $caseCategories->get(0)?->id,
                'is_featured'     => true,
                'sort'            => 1,
                'published_at'    => now()->subDays(10),
            ],
            [
                'title_zh'       => '别墅豪宅全屋定制智能系统',
                'slug'           => 'villa-full-smart',
                'style'          => 'modern',
                'house_type'     => 'villa',
                'area'           => '450㎡',
                'budget_range'   => '80-120万',
                'smart_features' => '全屋影音系统、智能安防监控、地暖控制、电动窗帘、场景联动',
                'description_zh' => '别墅在基础建设阶段就介入，弱电箱、家庭网络与末端控制一次规划到位，避免分期改造反复拆装吊顶。',
                'content_zh'     => <<<'HTML'
                    <p>四层 450㎡。业主此前自己买过一批设备，二楼常掉线。我们接手后先重做组网，再谈功能——顺序反了，功能加得越多越不稳。</p>
                    <p><strong>先解决稳定性</strong></p>
                    <ul>
                    <li>每层各一台双模网关，全部有线上行回弱电间</li>
                    <li>四台吸顶 AP 走 PoE 供电，取代原来的无线桥接</li>
                    <li>地暖与新风接入分区控制，按楼层独立设定</li>
                    </ul>
                    <p><strong>再加功能</strong></p>
                    <ul>
                    <li>负一层影音室：投影、幕布、灯光、遮光帘一键联动</li>
                    <li>院子与入户四路室外机，人形侦测联动照明</li>
                    <li>楼梯间与电梯厅存在感应，夜间不用摸黑找开关</li>
                    </ul>
                    <p><strong>一个教训</strong></p>
                    <p>原有设备里有三台私有协议的开关无法接入，最终替换。这也是我们坚持先定协议底座再买设备的原因。</p>
                    HTML,
                'customer_name'   => '陈先生',
                'customer_meta'   => '东湖高新别墅 · 入住 1 年',
                'customer_quote'  => '以前二楼总掉线，家里人都不愿意用。重做完组网这半年没再手动重启过网关，这是最大的变化。',
                'seo_title'       => '别墅豪宅全屋定制智能系统案例 - 示例装修',
                'seo_description' => '示例装修为豪华别墅提供全屋定制智能系统，影音安防地暖一体控制，专业团队上门服务。',
                'seo_keywords'    => '别墅智能家居,全屋定制,影音系统,地暖,示例装修',
                'category_id'     => $caseCategories->get(1)?->id,
                'is_featured'     => true,
                'sort'            => 2,
                'published_at'    => now()->subDays(20),
            ],
            [
                'title_zh'       => '老房改造—智能灯光场景升级',
                'slug'           => 'old-apt-lighting',
                'style'          => 'nordic',
                'house_type'     => 'two_bedroom',
                'area'           => '88㎡',
                'budget_range'   => '8-12万',
                'smart_features' => '智能灯光场景控制、语音助手接入、手机远程控制',
                'description_zh' => '老房局部改造项目，重点升级灯光控制系统，实现多场景一键切换。示例装修提供专业方案设计和施工安装。',
                'content_zh'     => <<<'HTML'
                    <p>2008 年的房子，墙里没有零线，业主又不想砸墙重走电。这类项目的第一件事不是选设备，是确认单火开关能不能带得动现有灯具。</p>
                    <p><strong>约束条件</strong></p>
                    <ul>
                    <li>无零线，全部使用单火版开关</li>
                    <li>灯具功率偏低，需逐路测试是否出现微亮与关不断</li>
                    <li>不动吊顶，不开线槽</li>
                    </ul>
                    <p><strong>最终方案</strong></p>
                    <ul>
                    <li>客厅、卧室、走廊共 6 路单火开关，替换原面板</li>
                    <li>两盏功率过低的灯换成可调光光源，解决关不断</li>
                    <li>灯带嵌进原有电视柜背板，补一层氛围照明</li>
                    </ul>
                    <p><strong>成本</strong></p>
                    <p>末端设备加人工全部在 1 万以内，两个工作日完成。老房改造的性价比集中在灯光，安防和窗帘可以后续再加。</p>
                    HTML,
                'customer_name'   => '刘女士',
                'customer_meta'   => '武昌老小区 · 入住 6 个月',
                'customer_quote'  => '本来以为老房子做不了，最后没砸一块墙就把灯光换掉了。两天就装完，比我想的省事。',
                'seo_title'       => '老房局部改造智能灯光升级案例 - 示例装修',
                'seo_description' => '老房智能改造，灯光场景控制，语音助手，手机远程操作，性价比之选，联系示例装修免费咨询。',
                'seo_keywords'    => '老房改造,智能灯光,语音控制,场景模式,武汉',
                'category_id'     => $caseCategories->get(2)?->id,
                'is_featured'     => false,
                'sort'            => 3,
                'published_at'    => now()->subDays(30),
            ],
            [
                'title_zh'       => '新房精装—智能安防一体化方案',
                'slug'           => 'new-home-security',
                'style'          => 'modern',
                'house_type'     => 'three_bedroom',
                'area'           => '135㎡',
                'budget_range'   => '15-20万',
                'smart_features' => '智能门锁、视频门铃、室内摄像头、烟雾/燃气报警联动',
                'description_zh' => '新房装修同步集成智能安防系统，让家更安全可靠。精选主流品牌智能安防产品，品质保障。',
                // 这一单刻意不填业主见证：首页见证轮播与案例页见证卡片都要求姓名与引言齐备，
                // 留一条空的才能验证「信息不全不渲染空壳」的降级路径
                'content_zh' => <<<'HTML'
                    <p>业主常出差，安防是这一单的核心诉求。方案按「入户—室内—隐患」三层展开，不堆设备数量。</p>
                    <p><strong>三层防护</strong></p>
                    <ul>
                    <li>入户：可视门铃加智能门锁，开锁记录推送到手机</li>
                    <li>室内：客厅云台摄像头一台，设定时隐私模式，卧室不装</li>
                    <li>隐患：厨房燃气与烟感联动机械手关阀</li>
                    </ul>
                    <p><strong>联动逻辑</strong></p>
                    <ul>
                    <li>离家后检测到人形移动：推送 + 录像 + 语音警戒</li>
                    <li>门锁试错超过 5 次：立即推送</li>
                    <li>燃气报警：关阀 + 全家手机推送 + 客厅灯闪烁</li>
                    </ul>
                    <p><strong>隐私边界</strong></p>
                    <p>摄像头点位与拍摄角度在安装前与业主一起确认，避开邻居门窗与公共通道；卧室和卫生间不安装，这一条我们不接受例外。</p>
                    HTML,
                'seo_title'       => '新房精装智能安防一体化方案 - 示例装修',
                'seo_description' => '新房安装智能门锁视频门铃摄像头，烟雾报警联动，全方位保护家庭安全，示例装修专业安装。',
                'seo_keywords'    => '新房装修,智能安防,智能门锁,视频门铃,武汉',
                'category_id'     => $caseCategories->get(3)?->id,
                'is_featured'     => false,
                'sort'            => 4,
                'published_at'    => now()->subDays(15),
            ],
            [
                'title_zh'       => '复式楼层—中式智能雅居',
                'slug'           => 'duplex-chinese-smart',
                'style'          => 'chinese',
                'house_type'     => 'duplex',
                'area'           => '280㎡',
                'budget_range'   => '45-60万',
                'smart_features' => '中控屏集中管理、电动升降桌、智能茶室系统、背景音乐',
                'description_zh' => '中式家具与智能设备并存的复式改造。面板与灯具按木作配色选型，控制方式尽量不露痕迹——业主的原话是「别让家里看起来像机房」。',
                'content_zh'     => <<<'HTML'
                    <p>280㎡ 复式，中式吊顶造型复杂，灯光是难点：既要够亮，又不能破坏木饰面的线条。</p>
                    <p><strong>照明</strong></p>
                    <ul>
                    <li>主要空间用磁吸线性灯藏进吊顶凹槽，不见灯只见光</li>
                    <li>茶室单独一路，色温固定 3000K，配合窗帘做午后场景</li>
                    <li>楼梯踏步灯感应控制，夜间 10% 亮度</li>
                    </ul>
                    <p><strong>中控</strong></p>
                    <ul>
                    <li>玄关与二楼起居各一台中控屏，长辈不用手机也能操作</li>
                    <li>背景音乐分 6 区，茶室与庭院可独立播放</li>
                    </ul>
                    <p><strong>难点</strong></p>
                    <p>木饰面无法后期开孔，所有灯位必须在木工进场前定稿。这一单的点位图改了四版，全部在木工之前完成。</p>
                    HTML,
                'customer_name'   => '周先生',
                'customer_meta'   => '中式复式 · 入住 4 个月',
                'customer_quote'  => '点位图改了四版，当时觉得麻烦，装完发现一个孔都没白开。老人用中控屏比用手机顺手。',
                'seo_title'       => '复式楼层中式智能雅居案例 - 示例装修',
                'seo_description' => '中式风格复式楼层智能系统，中控屏集中管理，背景音乐，智能茶室，示例装修精工打造。',
                'seo_keywords'    => '复式智能家居,中式风格,背景音乐,智能茶室,武汉',
                'category_id'     => $caseCategories->get(4)?->id,
                'is_featured'     => true,
                'sort'            => 5,
                'published_at'    => now()->subDays(5),
            ],
            [
                'title_zh'       => '一居室—北欧风格智能小窝',
                'slug'           => 'studio-nordic',
                'style'          => 'nordic',
                'house_type'     => 'one_bedroom',
                'area'           => '55㎡',
                'budget_range'   => '5-8万',
                'smart_features' => '智能插座、空气质量监测、远程家电控制',
                'description_zh' => '小户型也能享受智能生活，经济实惠的入门级智能家居方案。示例装修提供免费上门评估服务。',
                'content_zh'     => <<<'HTML'
                    <p>55㎡ 一居室，预算 5 万以内。业主是租户，第一条要求是「退租能拆走带回去」，这个约束反而让方案变得干净。</p>
                    <p><strong>全部选可逆方案</strong></p>
                    <ul>
                    <li>开关不改线，使用无线贴墙面板，撕下即走</li>
                    <li>计量插座三只，分别接热水器、空调、洗衣机</li>
                    <li>灯带走明装铝槽，胶贴固定不打孔</li>
                    </ul>
                    <p><strong>做了哪些场景</strong></p>
                    <ul>
                    <li>起床：窗帘半开 + 热水器加热</li>
                    <li>回家：灯光暖光 40% + 空调按室温启停</li>
                    <li>离家：三只插座断电</li>
                    </ul>
                    <p><strong>可带走清单</strong></p>
                    <p>交付时给了一份可带走设备清单和一份复原说明，退租时按清单拆即可，墙上不留孔位。</p>
                    HTML,
                'customer_name'   => '王先生',
                'customer_meta'   => '租住一居室 · 使用 5 个月',
                'customer_quote'  => '最看重的是能带走。装的时候师傅特意问了我退租的事，还留了一份复原说明，这个挺意外。',
                'seo_title'       => '一居室北欧风格智能家居案例 - 示例装修',
                'seo_description' => '小户型智能家居入门方案，智能插座空气监测，经济实惠，示例装修免费上门咨询。',
                'seo_keywords'    => '小户型,北欧风格,入门智能家居,智能插座,武汉',
                'category_id'     => $caseCategories->get(0)?->id,
                'is_featured'     => false,
                'sort'            => 6,
                'published_at'    => now()->subDays(7),
            ],
        ];

        foreach ($casesData as $data) {
            $data['status'] = PageStatus::PUBLISHED;

            $case = $this->firstOrCreateBySlug(SiteCase::class, $data);

            // 封面图：仅取本地图片，无图时由前台占位组件兜底（D-11-11）
            $this->addCoverImage($case, 'site/demo/decoration/cases/'.$data['slug'].'.jpg');

            // 标签只在新建时挂：取的是随机标签，对已有记录重复 sync 会越挂越多
            if ($case->wasRecentlyCreated) {
                $case->tags()->syncWithoutDetaching(
                    $tags->random(min(2, $tags->count()))->pluck('id')->toArray()
                );
            }
        }

        // 5. 创建智能方案（4 个）
        $solutionsData = [
            [
                'title_zh'        => '全屋智能家居一体化方案',
                'slug'            => 'full-smart-solution',
                'description_zh'  => '从点位规划到末端调试的全屋方案，覆盖照明、安防、影音、暖通四类场景，含设备清单与预算区间，可以直接改成你家的版本。',
                'content_zh'      => '<p>全屋智能家居方案包含以下核心模块：</p><ul><li>智能照明系统：多场景一键切换</li><li>安防监控系统：24小时守护</li><li>影音娱乐系统：沉浸式体验</li><li>暖通空调联动：舒适恒温</li></ul><p>联系示例装修，预约免费上门量房：400-800-6688</p>',
                'price_range'     => '20-100万',
                'seo_title'       => '全屋智能家居一体化方案 - 示例装修',
                'seo_description' => '示例装修全屋智能家居一体化方案，照明安防影音暖通全覆盖，专业设计施工，武汉地区上门服务。',
                'seo_keywords'    => '全屋智能,智能家居方案,一体化,照明安防,武汉',
                'is_featured'     => true,
                'sort'            => 1,
                'published_at'    => now()->subDays(30),
            ],
            [
                'title_zh'        => '智能灯光场景定制方案',
                'slug'            => 'smart-lighting-solution',
                'description_zh'  => '根据不同空间和使用场景，定制多模式智能灯光控制方案，一键切换家居氛围，示例装修专业定制。',
                'content_zh'      => '<p>我们的灯光方案涵盖：起居场景、工作场景、就寝场景、影院模式等多种预设方案。</p><p>支持手机 App 远程控制及语音助手接入。</p>',
                'price_range'     => '3-15万',
                'seo_title'       => '智能灯光场景定制方案 - 示例装修',
                'seo_description' => '示例装修智能灯光场景定制，多模式预设，一键切换家居氛围，节能环保，武汉专业上门安装。',
                'seo_keywords'    => '智能灯光,场景控制,调光,节能,武汉',
                'is_featured'     => true,
                'sort'            => 2,
                'published_at'    => now()->subDays(20),
            ],
            [
                'title_zh'        => '家庭安防全覆盖方案',
                'slug'            => 'home-security-solution',
                'description_zh'  => '360度无死角安防监控方案，智能门锁、视频门铃、摄像头、报警系统一体联动。示例装修安防产品，品质可靠。',
                'content_zh'      => '<p>安防方案核心组件：智能门锁+视频门铃+室内摄像头+户外摄像头+烟感/燃气报警器。</p><p>联动控制：门铃响起自动推送手机通知，烟感报警联动拨打预设电话。</p>',
                'price_range'     => '1-8万',
                'seo_title'       => '家庭安防全覆盖方案 - 示例装修',
                'seo_description' => '家庭安防全方位覆盖，智能门锁视频门铃摄像头报警联动，保护家人安全，示例装修专业安装。',
                'seo_keywords'    => '家庭安防,智能门锁,摄像头,报警系统,武汉',
                'is_featured'     => false,
                'sort'            => 3,
                'published_at'    => now()->subDays(15),
            ],
            [
                'title_zh'        => '影音娱乐沉浸体验方案',
                'slug'            => 'av-entertainment-solution',
                'description_zh'  => '打造家庭影院级别的沉浸式影音体验，4K投影、环绕音响、智能遮光帘联动控制，示例装修影音专项服务。',
                'content_zh'      => '<p>影音方案包含：高清投影或大尺寸电视、环绕立体声音响系统、电动遮光帘、场景化智能联动。</p><p>影院模式：一键拉帘降光，环境音效自动激活。</p>',
                'price_range'     => '5-30万',
                'seo_title'       => '家庭影音娱乐沉浸体验方案 - 示例装修',
                'seo_description' => '家庭影院沉浸影音体验，4K投影环绕音响电动遮光帘智能联动，示例装修专业影音安装。',
                'seo_keywords'    => '家庭影院,智能影音,4K投影,环绕音响,武汉',
                'is_featured'     => false,
                'sort'            => 4,
                'published_at'    => now()->subDays(25),
            ],
        ];

        foreach ($solutionsData as $data) {
            $data['status'] = PageStatus::PUBLISHED;

            $solution = $this->firstOrCreateBySlug(SiteSolution::class, $data);

            // 封面图：仅取本地图片，无图时由前台占位组件兜底（D-11-11）
            $this->addCoverImage($solution, 'site/demo/decoration/solutions/'.$data['slug'].'.jpg');

            // 同案例：随机标签只在新建时挂，否则重跑会越挂越多
            if ($solution->wasRecentlyCreated) {
                $solution->tags()->syncWithoutDetaching(
                    $tags->random(min(2, $tags->count()))->pluck('id')->toArray()
                );
            }
        }

        // 6. 创建智能产品（18 个，覆盖三个分类）
        //
        // brand 与 price 由 productsData() 逐条给，不在这里统一赋值：
        // 这是一家装修公司，卖的是各品牌的智能产品 + 装修服务 + 装修方案，
        // 公司名不是产品品牌。原先这里统一写 $data['brand'] = 公司名，
        // 会被 SiteFrontController 当成 schema.org/Brand 喂给搜索引擎。
        foreach ($this->productsData($productCategories) as $data) {
            $data['status']          = PageStatus::PUBLISHED;
            $data['published_at']    = now();
            $data['seo_title']       = $data['title_zh'].' - 示例装修智能家居';
            $data['seo_description'] = $data['description_zh'];

            $product = $this->firstOrCreateBySlug(SiteProduct::class, $data);

            // 封面图：仅取本地图片，无图时由前台占位组件兜底（D-11-11）
            $this->addCoverImage($product, 'site/demo/decoration/products/'.$data['slug'].'.jpg');

            // 图集：site/products/{slug}/gallery-NN.jpg，UI 出图后原样落盘即可被拾取
            foreach (glob(storage_path('app/public/site/demo/decoration/products/'.$data['slug'].'/gallery-*.jpg')) ?: [] as $file) {
                $this->addCoverImage(
                    $product,
                    'site/demo/decoration/products/'.$data['slug'].'/'.basename($file),
                    'gallery'
                );
            }
        }

        // 7. 创建静态页面（5 个：about/contact/services/faq/privacy）
        //
        // about / services 的正文在二期 D 段重写过：原来那两段只说「专注智能家居方案
        // 设计与落地实施」，看不出这是一家**卖各品牌产品 + 做装修服务 + 出装修方案**的
        // 公司——与 C 段把 brand 改成真实品牌是同一件事的两面，口径得在文案里也对上。
        //
        // 只有中文正文：站点是中文单语言（见包元数据）。双语时代的 `_en` 列已于
        // 2026-08-08 整批删除，见 drop_legacy_english_and_gallery_columns 迁移。
        $pagesData = [
            [
                'title_zh'   => '关于示例装修',
                'slug'       => 'about',
                'content_zh' => <<<'HTML'
                    <p>示例装修有限公司，位于武汉，做的是智能家居的<strong>方案设计、产品配套与施工落地</strong>三件事。</p>
                    <p><strong>我们不是设备厂商，也不是纯装修队</strong></p>
                    <p>智能家居这件事上，最常见的两种坑都来自角色缺位：只买设备没人管点位与布线，装完发现该有开关的地方没有回路；只找施工队没人管选型，几个品牌的设备各说各话、联动做不起来。我们把这两头一起接下来——设备从各品牌里选，施工与调试自己做，验收之后的问题也找我们。</p>
                    <p><strong>三条业务线</strong></p>
                    <ul>
                    <li><strong>装修方案</strong>：按空间条件与预算出全屋方案，含点位图、设备清单、预算区间与工期</li>
                    <li><strong>品牌产品</strong>：代理与配套主流品牌的智能设备，兼容性与售后渠道在选型阶段就谈清楚</li>
                    <li><strong>装修服务</strong>：水电阶段介入定点位，布线、安装、调试、验收与使用交底，以及交付后的维保</li>
                    </ul>
                    <p>服务范围是武汉及周边城市，含周边县镇。公司地址：湖北省武汉市，预约咨询电话：400-800-6688。</p>
                    HTML,
                'seo_title'       => '关于示例装修智能家居 - 示例装修有限公司',
                'seo_description' => '示例装修有限公司，武汉智能家居服务商：出装修方案、配套各品牌智能产品、自己施工交付，免费上门量房。',
                'seo_keywords'    => '示例装修,湖北智能家居,武汉智能家居,关于我们',
                'sort'            => 1,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
            [
                'title_zh'        => '联系我们',
                'slug'            => 'contact',
                'content_zh'      => '<p>欢迎致电或在线留言，我们将在 24 小时内回复您的咨询。</p><p><strong>公司名称：</strong>示例装修有限公司</p><p><strong>咨询热线：</strong>400-800-6688</p><p><strong>服务地区：</strong>武汉及周边城市</p><p><strong>工作时间：</strong>周一至周六 9:00-18:00</p>',
                'seo_title'       => '联系示例装修智能家居 - 预约免费设计咨询',
                'seo_description' => '联系示例装修，预约免费上门量房和智能家居方案设计咨询，400-800-6688，武汉专业智能家居服务。',
                'seo_keywords'    => '联系我们,预约咨询,智能家居设计,400-800-6688,武汉',
                'sort'            => 2,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
            [
                'title_zh'   => '我们的服务',
                'slug'       => 'services',
                'content_zh' => <<<'HTML'
                    <p>从方案设计到产品配套再到施工交付，三段都由我们自己做，不转包。</p>
                    <p><strong>一、方案设计</strong></p>
                    <ul>
                    <li>免费上门量房，出全屋点位图与回路规划</li>
                    <li>按预算给设备清单，同一档位提供替代方案，报价即交付范围</li>
                    <li>与装修方（水电、吊顶、木作）对接施工顺序，避免返工</li>
                    </ul>
                    <p><strong>二、品牌产品配套</strong></p>
                    <ul>
                    <li>智能照明：吸顶灯、灯带、无主灯照明、开关面板与调光回路</li>
                    <li>智能安防：门锁、可视门铃、室内外摄像头、传感器与报警联动</li>
                    <li>影音娱乐：客厅与独立影音室的音响、投影与背景音乐</li>
                    <li>暖通与电动：空调与地暖联动、窗帘电机、新风与晾衣架</li>
                    <li>网关与网络：多协议网关、面板中控、家庭网络与弱电箱整理</li>
                    </ul>
                    <p><strong>三、施工与售后</strong></p>
                    <ul>
                    <li>水电阶段介入定点位，布线与预埋随主体施工同步</li>
                    <li>末端安装、场景调试、竣工验收与使用交底</li>
                    <li>设备台账与配置备份随项目归档，故障定位不用重新摸排</li>
                    <li>7×12 小时售后响应，武汉及周边县镇可上门</li>
                    </ul>
                    HTML,
                'seo_title'       => '示例装修服务项目 - 智能家居一站式解决方案',
                'seo_description' => '示例装修智能家居服务：免费上门量房出方案、各品牌设备选型配套、水电阶段介入施工、竣工调试与 7×12 售后。',
                'seo_keywords'    => '智能家居服务,方案设计,施工安装,售后,武汉',
                'sort'            => 3,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
            [
                // 问题清单取自 docs/素材采集/批次-20260804-京东SKU调研/reviews-insight.json 聚合出的真实购前疑虑，
                // 不是拍脑袋拟的「常见问题」。「镇上的能不能做」这类问句照原样保留，
                // 因为用户就是这么搜的。
                'title_zh'   => '常见问题',
                'slug'       => 'faq',
                'content_zh' => <<<'HTML'
                    <p>下面这些问题来自我们后台真实收到的咨询，按被问到的频次排列。</p>
                    <p><strong>服务与安装</strong></p>
                    <p><strong>装修到哪一步该找你们？</strong></p>
                    <p>水电进场之前。灯光回路、网线点位、窗帘盒电源都要在这一步定，封槽之后再加只能走明线。设计阶段就介入最好。</p>
                    <p><strong>周边县镇能不能上门？</strong></p>
                    <p>能。武汉三环内当周排期；周边地市市区提前 2-3 天预约；下辖县镇单程超过 100 公里的，我们会先确认工程量——只装两三个开关的小单，建议改成远程指导加本地电工配合，比让业主等一周实在。</p>
                    <p><strong>量房和方案设计收费吗？</strong></p>
                    <p>不收费，也不绑定后续下单。出图内容包括点位图、回路表和设备清单。</p>
                    <p><strong>老房子没有零线，还能做智能开关吗？</strong></p>
                    <p>能，用单火版开关。但灯具功率过低时可能出现微亮或关不断，量房时会逐路测试，必要时更换光源。</p>
                    <p><strong>使用与稳定性</strong></p>
                    <p><strong>断网了还能用吗？</strong></p>
                    <p>本地场景可以。开关直控、感应灯、面板场景都在网关本地执行；远程控制和云端录像会不可用。</p>
                    <p><strong>语音控制实用吗？</strong></p>
                    <p>手上有东西、已经躺下、需要批量操作这三种场景很好用；调亮度调色温这类微调不如手动。我们不会把语音设成唯一入口，每个语音动作都有对应的面板路径。</p>
                    <p><strong>摄像头不买存储卡能回看吗？</strong></p>
                    <p>能，走云存储或 NVR。三种存储方式的成本与丢录风险不同，资讯中心有一篇专门的对比。</p>
                    <p><strong>4 米以上的窗帘一个电机够吗？</strong></p>
                    <p>看帘布重量而不是长度。双层遮光布 4 米宽通常已在单电机的临界点上，我们默认双电机对开。</p>
                    <p><strong>产品与售后</strong></p>
                    <p><strong>东西质量怎么样，会不会坏？</strong></p>
                    <p>会坏。我们的做法是把易损件的质保单列：驱动电源、电机、锁体各有独立质保期，设备清单上逐项写明起算日期。</p>
                    <p><strong>坏了怎么报修？</strong></p>
                    <p>不需要上门就能解决的（配网、场景失效、账号迁移）远程处理不收费；需要换件上门的按质保条款执行，超出范围的先报价再动手。</p>
                    <p><strong>账号在谁手上？</strong></p>
                    <p>交付时主账号移交业主，施工账号从设备中移除。这一条写在验收清单里，不是口头承诺。</p>
                    HTML,
                'seo_title'       => '常见问题 - 示例装修智能家居',
                'seo_description' => '智能家居常见问题：什么时候介入装修、周边县镇能否上门、断网能不能用、摄像头不买存储卡能否回看、质保与报修流程。',
                'seo_keywords'    => '常见问题,智能家居FAQ,上门安装,服务范围,质保',
                'sort'            => 4,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
            [
                // 这一页与上面四页性质不同：它不是演示文案，是**合规要件**。
                //
                // 询盘表单除了访客自己填的内容，还会自动记 ip / source / landing_url /
                // referer（`2026_08_03_100001_add_attribution_to_site_contact_messages_table`）。
                // 收了这些就必须有告知入口，否则页脚 privacy_url 一配上就是空指向。
                //
                // 正文刻意**不写公司名、不写域名、不写联系方式** —— 一律说「本站」，
                // 具体主体信息由页脚的 company_name_zh / icp_number / phone 渲染。
                // 这样它能原样回流开源仓库，任何下游站点装上就是一份可用的初稿。
                //
                // 章节骨架照小米（privacy.mi.com）与华为（consumer.huawei.com/cn/privacy）
                // 的通行版式排：收集使用 / Cookie / 共享转让披露 / 保存保护 / 您的权利 /
                // 未成年人 / 第三方链接 / 跨境传输 / 政策更新 / 联系我们，开头带更新日期与
                // 适用范围。这套十章结构是《个人信息保护法》催生的行业惯例，不是谁家的独创。
                //
                // **但正文一个字都不抄它们的**：一来那是受版权保护的文本；二来更要命，
                // 那两份写的是设备遥测、账号体系、SDK 与关联公司全球调取，而本站只收一个
                // 电话号和一个 referer——照搬等于把不存在的数据处理行为写进自己的合规文件，
                // 比没有还糟。每一章的内容都按本包代码的实际行为写：
                //   - 公开页零 session、不发 Cookie 是硬约束（SiteCacheHeaders + 公开路由
                //     不进 StartSession），不是承诺性措辞
                //   - 统计脚本只有在后台填了对应设置项时才注入
                //   - 没有账号体系、没有跨境传输、没有第三方 SDK——这三章都据实写「没有」，
                //     写「没有」也是必要披露，不是可以省掉的章节
                'title_zh'   => '隐私政策',
                'slug'       => 'privacy',
                'content_zh' => <<<'HTML'
                    <p><strong>最近更新：2026 年 8 月</strong></p>
                    <p>本政策适用于本网站（下称「本站」）向您提供的全部服务。运营主体、备案信息与联系方式见页脚。本站没有账号体系，您无需注册即可浏览全部内容。</p>
                    <p>请您在使用本站前完整阅读本政策。如果您不同意其中任何内容，请不要提交咨询表单——仅浏览网页不会产生任何个人信息收集。</p>

                    <p><strong>一、我们如何收集和使用您的个人信息</strong></p>
                    <p><strong>1.1 您主动提供的信息</strong></p>
                    <p>仅在您提交咨询表单时产生：</p>
                    <ul>
                    <li><strong>称呼、联系电话</strong>——必填项。这是回访的唯一途径，不填我们无法联系您</li>
                    <li><strong>咨询内容</strong>，以及表单中可选问题的回答（如房屋面积、所处装修阶段）</li>
                    </ul>
                    <p><strong>1.2 您使用服务过程中我们收集的信息</strong></p>
                    <p>在您<strong>点击提交</strong>的那一刻，随表单一并记录：</p>
                    <ul>
                    <li><strong>IP 地址</strong>——用于识别重复提交与恶意灌水</li>
                    <li><strong>来源标识、落地页地址、来源页面地址（referer）</strong>——用于判断咨询从哪个渠道来</li>
                    </ul>
                    <p>这些字段<strong>只在提交那一刻记录一次</strong>。浏览网页本身不会产生任何记录，我们也不做跨站跟踪、不构建访客画像、不进行自动化决策或个性化推送。</p>
                    <p><strong>1.3 来源于第三方的信息</strong></p>
                    <p>没有。我们不从任何第三方获取关于您的个人信息。</p>
                    <p><strong>1.4 服务器日志</strong></p>
                    <p>Web 服务器按运维常规记录请求时间、路径、状态码、IP 与 User-Agent，用于故障排查与防范攻击。这类日志不与咨询记录关联。</p>
                    <p><strong>1.5 使用目的</strong></p>
                    <ul>
                    <li>回复咨询、安排上门量房与后续业务沟通</li>
                    <li>内部统计咨询来源，判断哪些内容对访客真正有用</li>
                    <li>保障站点安全、防范滥用</li>
                    <li>依照法律法规配合监管要求</li>
                    </ul>
                    <p>超出上述目的使用您的信息，我们会再次征得您的同意。</p>

                    <p><strong>二、我们如何使用 Cookie 和同类技术</strong></p>
                    <p>本站<strong>公开页面不使用 Cookie，也不建立会话</strong>。这不是承诺性措辞，是站点的技术约束：公开页面走整页缓存，一旦产生会话就会退出缓存机制，因此不存在「顺手加一个」的可能。您可以自行核验——查看任一公开页面的响应头，其中不含 <code>Set-Cookie</code>。</p>
                    <p>后台管理区域（需登录）使用必要的会话 Cookie，仅用于维持管理员登录状态，与访客无关。</p>
                    <p>若本站启用了第三方网站统计服务，相应脚本可能写入其自有 Cookie。是否启用可在页面源码中直接核对。您可以通过浏览器设置拒绝或清除 Cookie，这不影响本站公开内容的正常浏览。</p>

                    <p><strong>三、我们如何共享、转让、公开披露您的个人信息</strong></p>
                    <p><strong>3.1 共享</strong>——我们<strong>不向第三方出售、出租或交换</strong>您的个人信息。仅在以下情形共享，且共享范围限于完成目的所必需：</p>
                    <ul>
                    <li>事先取得您的明确同意</li>
                    <li>为完成您所要求的服务而必需（例如安排施工人员按约定地址上门）</li>
                    <li>法律法规要求，或应司法、行政机关依法定程序提出的要求</li>
                    </ul>
                    <p><strong>3.2 转让</strong>——不进行转让。若因合并、分立、解散等原因需要转移，我们会要求承接方继续受本政策约束，否则将重新征求您的同意。</p>
                    <p><strong>3.3 公开披露</strong>——不进行公开披露，法律强制要求的情形除外。</p>

                    <p><strong>四、我们如何保存和保护您的个人信息</strong></p>
                    <p><strong>4.1 存放地点</strong>——全部数据存储于中华人民共和国境内的服务器。</p>
                    <p><strong>4.2 保存期限</strong></p>
                    <ul>
                    <li>咨询记录：业务往来结束后保留不超过两年，用于售后追溯与纠纷处理，超期清理</li>
                    <li>服务器日志：按运维需要保留，通常不超过六个月</li>
                    </ul>
                    <p>法律法规对保存期限另有强制要求的，从其规定。</p>
                    <p><strong>4.3 安全措施</strong>——站点全程 HTTPS 传输；后台采用账号密码登录、按角色分配数据访问权限，并留存操作日志。</p>
                    <p><strong>4.4 安全事件处置</strong>——一旦发生个人信息泄露等安全事件，我们将按法律要求及时告知您事件情况、可能影响与已采取的措施，并向监管部门报告。</p>

                    <p><strong>五、您如何管理您的个人信息</strong></p>
                    <p>您有权要求我们：</p>
                    <ul>
                    <li><strong>查阅</strong>您向本站提交过的信息</li>
                    <li><strong>更正</strong>其中不准确或不完整的部分</li>
                    <li><strong>删除</strong>您的咨询记录</li>
                    <li><strong>撤回</strong>此前给出的同意</li>
                    <li><strong>获取副本</strong>，或要求我们将其转移至您指定的接收方</li>
                    </ul>
                    <p>通过页脚的联系方式提出即可。我们会在核实您的身份后 <strong>15 个工作日内</strong>处理并答复。</p>
                    <p>撤回同意或要求删除，不影响此前基于您的同意已经进行的处理；但撤回后我们将不再基于该同意继续处理，也可能因此无法继续为您提供相应服务。</p>
                    <p>若您对我们的处理结果不满意，可以向网信、市场监管等主管部门投诉举报。</p>

                    <p><strong>六、我们如何处理未成年人的个人信息</strong></p>
                    <p>本站面向成年访客提供服务，不主动向未成年人收集个人信息。若您是不满十四周岁未成年人的监护人，且发现该未成年人未经您同意向本站提交了信息，请通过页脚联系方式与我们联系，我们将尽快删除。</p>

                    <p><strong>七、第三方链接及其产品与服务</strong></p>
                    <p>本站部分内容可能链接至第三方网站。这些网站有各自独立的隐私政策，其信息处理行为不受本政策约束，也不在我们的控制范围内。建议您在向其提供个人信息前先阅读对方的政策。</p>

                    <p><strong>八、您的个人信息如何在全球范围内传输</strong></p>
                    <p>不涉及跨境传输。本站收集的个人信息全部在中华人民共和国境内存储和处理，不向境外提供。</p>

                    <p><strong>九、本政策如何更新</strong></p>
                    <p>本政策如有修改，将直接在本页更新，并同步更新页首的「最近更新」日期。若变更涉及信息收集范围或使用目的的实质性调整，我们会在页面显著位置提示，必要时重新征求您的同意。</p>

                    <p><strong>十、如何联系我们</strong></p>
                    <p>关于本政策或您的个人信息，请通过页脚公示的电话或地址与我们联系。我们会在核实身份后 15 个工作日内答复。</p>
                    HTML,
                'seo_title'       => '隐私政策',
                'seo_description' => '本站收集哪些信息、为什么收集、保存多久，以及您如何查阅、更正或删除。公开页面不使用 Cookie、不建立会话。',
                'seo_keywords'    => '隐私政策,个人信息保护,Cookie,信息收集',
                'sort'            => 5,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
        ];

        foreach ($pagesData as $data) {
            $this->firstOrCreateBySlug(SitePage::class, $data);
        }

        // 8. 创建示例询盘（2 条）
        $messagesData = [
            [
                'name'    => '张先生',
                'phone'   => '13812345678',
                'message' => '您好，我想了解全屋智能家居方案，我家是三居室，面积约120平，请问大概需要多少费用？能上门量房吗？',
                'status'  => 'unread',
                'ip'      => '127.0.0.1',
            ],
            [
                'name'    => '李女士',
                'phone'   => '18987654321',
                'message' => '我家别墅想做全套智能系统，包括安防、影音和灯光控制，能上门量房吗？地址在武汉光谷。',
                'status'  => 'unread',
                'ip'      => '127.0.0.1',
            ],
        ];

        foreach ($messagesData as $data) {
            ContactMessage::firstOrCreate(
                ['name' => $data['name'], 'phone' => $data['phone']],
                $data
            );
        }

        // 9. 创建首页幻灯片（3 张，二期 B1）
        //
        // 排在最后只是为了不给前面八步重新编号——幻灯片不依赖任何前置数据。
        foreach ($this->bannersData() as $data) {
            $banner = $this->firstOrCreateBySlug(SiteBanner::class, $data);

            $this->addCoverImage($banner, 'site/demo/decoration/banners/'.$data['slug'].'.jpg');
        }

        // 10. 创建全屋智能套餐（6 个：两种户型 × 三档，批次 3 新增）
        //
        // 四期基线实测：包内此前没有任何 SitePackage 种子，下游装完包 /packages
        // 页是空的。不依赖案例/产品的分类或标签，可以独立于前面几步创建。
        foreach ($this->packagesData() as $data) {
            $data['status'] = PageStatus::PUBLISHED;

            $package = $this->firstOrCreateBySlug(SitePackage::class, $data);

            $this->addCoverImage($package, 'site/demo/decoration/packages/'.$data['slug'].'.jpg');
        }

        // 11. 城市页演示数据（批次 3 新增，仅在宿主已导入区划时才创建）
        //
        // `site_regions` 由 `filamentboot-site:import-regions` 从宿主给的 JSON 导入，
        // 包本身不随身携带区划数据（见建表迁移类注释）。全新装包、还没跑过导入命令
        // 的站点这里天然没有区划可挂，直接跳过——这不是漏播，是这一类内容本来的前置条件。
        $this->seedCityPages();

        // 导航/页脚菜单（SiteFrontMenuSeeder）与列表页导语（SiteIntroCopySeeder）
        // 两套主题共用，且都依赖本方法建好的静态页，改由外层 SiteDemoSeeder
        // 在分发到具体主题之后统一调用一次，不在这里重复调用。
    }

    /**
     * 本 Seeder 会写入的内容 slug 清单，按模型分组（批次 3，供后台「清空演示数据」使用）
     *
     * 只列按 slug 幂等写入、且需要精确删除的内容模型。分类（SiteCaseCategory/
     * SiteProductCategory）与标签（SiteTag）不在清单内——它们是共享词表，演示内容
     * 与用户日后自建的内容可能复用同一个 slug（如 smart-home），删标签会连带断开
     * 用户内容的关联，风险大于留着不清空。城市页（SiteCityPage）按 region_code
     * 动态生成、没有固定 slug；示例询盘（ContactMessage）本身没有 slug 字段——
     * 两者都不适合按本清单清空，见 seedCityPages() 与 run() 对应段落的注释。
     *
     * 与 casesData()/solutionsData()/productsData()/pagesData()/bannersData()/
     * packagesData() 里的字面量各自独立维护（换取不用把这些方法签名改成能在
     * 播种之外单独调用），改其中任一处 slug 时记得同步这里。
     *
     * @return array<class-string, list<string>>
     */
    public static function seededSlugs(): array
    {
        return [
            SiteCase::class => [
                'modern-3bed-smart', 'villa-full-smart', 'old-apt-lighting',
                'new-home-security', 'duplex-chinese-smart', 'studio-nordic',
            ],
            SiteSolution::class => [
                'full-smart-solution', 'smart-lighting-solution',
                'home-security-solution', 'av-entertainment-solution',
            ],
            SiteProduct::class => [
                'qk-ceiling-light-pro', 'smart-panel-switch', 'linear-magnetic-light',
                'smart-downlight', 'rgb-led-strip', 'smart-fingerprint-lock',
                'video-doorbell', 'indoor-hd-camera', 'outdoor-ptz-camera',
                'presence-sensor', 'gas-smoke-alarm', 'smart-control-panel',
                'multimode-gateway', 'smart-curtain-motor', 'smart-ac-controller',
                'ir-remote-hub', 'metering-socket', 'ac-companion',
            ],
            SitePage::class    => ['about', 'contact', 'services', 'faq', 'privacy'],
            SiteBanner::class  => ['home-hero', 'home-cases', 'home-solutions'],
            SitePackage::class => [
                'three-one-custom', 'three-one-comfort', 'three-one-deluxe',
                'three-two-custom', 'three-two-comfort', 'three-two-deluxe',
            ],
        ];
    }

    /**
     * 城市页演示数据：仅在已有区划（省级/地级、且有 slug）时才创建
     *
     * 取前 3 个即可——这是「demo 长什么样」的样本，不是要把区划表铺满。
     * `profile` 留空：气候/供暖这类字段表由宿主 config 声明（见模型类注释），
     * 包自己的默认值是空数组，塞装修行业的口径进去在软件站上就是错的数据。
     */
    protected function seedCityPages(): void
    {
        $regions = SiteRegion::query()
            ->whereIn('level', [SiteRegion::LEVEL_PROVINCE, SiteRegion::LEVEL_CITY])
            ->whereNotNull('slug')
            ->orderBy('sort')
            ->limit(3)
            ->get();

        foreach ($regions as $region) {
            SiteCityPage::withTrashed()->firstOrCreate(
                ['region_code' => $region->code],
                [
                    'title_zh'        => $region->displayName().'全屋智能装修',
                    'description_zh'  => '示例装修有限公司在'.$region->displayName().'的服务信息与案例入口。',
                    'profile'         => [],
                    'seo_title'       => $region->displayName().'全屋智能装修 - 示例装修有限公司',
                    'seo_description' => '示例装修有限公司提供'.$region->displayName().'地区的全屋智能装修方案与上门服务。',
                    'seo_keywords'    => $region->displayName().',全屋智能,装修服务',
                    'sort'            => $region->sort,
                    'status'          => PageStatus::PUBLISHED,
                    'published_at'    => now(),
                ]
            );
        }
    }

    /**
     * 首页幻灯片数据（3 张，全部投放 HOME_TOP）
     *
     * 三张各承担一条业务线：预约（询盘面板）→ 案例 → 方案。第一张用 INQUIRY
     * 而不是链接，是为了不丢掉原来那个单图 hero 的主转化入口——`components/hero`
     * 的「预约咨询」打开的是询盘面板，幻灯片替掉它之后按钮得能做同一件事。
     *
     * 图片来自 docs/素材采集/批次-20260805-CC0装修场景图/（CC0，出处见该批次的
     * provenance.json），落盘 1920×1080，由 SiteBanner 的 hero 转换消费。
     *
     * 与其余演示内容一样按 slug 幂等：已有的不动，缺的补上。slug 在这里纯粹是
     * 内部键，不参与路由。
     *
     * @return list<array<string, mixed>>
     */
    protected function bannersData(): array
    {
        return [
            [
                'slug'       => 'home-hero',
                'title'      => '让家更智能，让生活更美好',
                'subtitle'   => '从方案设计到施工验收，我们把各品牌的智能设备装成一套真正好用的系统。武汉及周边免费上门量房。',
                'cta_label'  => '预约免费量房',
                'cta_url'    => null,
                'cta_action' => BannerCtaAction::INQUIRY,
                'position'   => BannerPosition::HOME_TOP,
                'sort'       => 1,
                'is_enabled' => true,
            ],
            [
                'slug'       => 'home-cases',
                'title'      => '看看我们交付过的家',
                'subtitle'   => '每个案例都写清了户型、预算区间和实际落地的智能配置，也写了哪些没做、为什么没做。',
                'cta_label'  => '查看案例',
                'cta_url'    => '/cases',
                'cta_action' => BannerCtaAction::LINK,
                'position'   => BannerPosition::HOME_TOP,
                'sort'       => 2,
                'is_enabled' => true,
            ],
            [
                'slug'       => 'home-solutions',
                'title'      => '按场景挑方案，不用从零开始',
                'subtitle'   => '灯光、安防、影音、暖通各有一套打包方案，含设备清单与预算区间，可以直接改成你家的版本。',
                'cta_label'  => '浏览方案',
                'cta_url'    => '/solutions',
                'cta_action' => BannerCtaAction::LINK,
                'position'   => BannerPosition::HOME_TOP,
                'sort'       => 3,
                'is_enabled' => true,
            ],
        ];
    }

    /**
     * 产品数据（18 条，分摊到智能照明 / 智能安防 / 智能家电三个分类）
     *
     * 文案取自 docs/素材采集/批次-20260804-京东SKU调研/ 的调研产物，但一律改写而非
     * 照搬：卖点段落对着 reviews-insight.json 里的真实关注维度写（「亮度很充足」
     * 「运行超安静」「指纹超灵敏」「暗处也能用」），安装段落回答真实购前疑虑
     * （「安装服务方便吗」「一定要插电用吗」「4 米多买一个能行吗」）。
     *
     * brand 是真实品牌，这是刻意的
     * ---------------------------
     * 演示数据设定的是**装修公司卖各品牌智能产品**这一业态（渠道商 / 系统集成商），
     * 所以 brand 填产品实际所属品牌，而不是站点公司名——渠道商展示所代理品牌属行业
     * 惯例，把自己公司名填进 brand 反而是错的（`SiteFrontController::productSchema()`
     * 会把它当 schema.org/Brand 输出）。取值来自
     * `docs/素材采集/批次-20260805-京东产品图/slug-map.json`，只做展示层归一
     * （小米（MI）→ 小米），米家（MIKA）不并进米家——那是另一个借名品牌。
     * 认不出对应款的 4 条 brand 为 null，前台不渲染品牌行。
     *
     * ⚠️ 真实第三方品牌名是否合适出现在开源包演示数据里，五期批次 3（虚构主体化）
     * 评估后判定本批不动——公司名/电话已换成虚构的「示例装修有限公司」，
     * 品牌名是否也要换成中性占位留给日后单独评估，不在本批范围内。
     *
     * price 一律 null
     * ---------------
     * 不上第三方价格：那是别人的经营数据、会过期，站上挂着等于替对方报价。前台
     * 卡片与详情页在 price 为空时渲染「咨询报价」。`price` 列保留，下游用得上。
     *
     * 刻意不写的：备案型号、认证型号、原品牌型号。那些是真实产品的注册标识，配上
     * 本文件自拟的参数等于伪造认证信息。自拟的 QK- 编号也不叫「型号」——真实品牌的
     * 产品不会有本公司型号——改称「选配编号」，即装修方案里的选配项编号。
     *
     * 只填中文正文：站点是中文单语言（见包元数据）。双语时代的 `_en` 列已于
     * 2026-08-08 整批删除，见 drop_legacy_english_and_gallery_columns 迁移。
     *
     * 富文本只用 config/purifier.php 白名单内的标签，小标题用 <p><strong> 表达——
     * 白名单里没有 h2、h3，写了会被前台静默过滤掉。
     *
     * @param  Collection<int, SiteProductCategory>  $categories
     * @return list<array<string, mixed>>
     */
    protected function productsData(Collection $categories): array
    {
        $lighting  = $categories->firstWhere('slug', 'smart-lighting')?->id;
        $security  = $categories->firstWhere('slug', 'smart-security')?->id;
        $appliance = $categories->firstWhere('slug', 'smart-appliances')?->id;

        return [
            // ---------- 智能照明 ----------
            [
                'title_zh'       => '客厅智能吸顶灯',
                'slug'           => 'qk-ceiling-light-pro',
                'brand'          => '米家',
                'price'          => null,
                'category_id'    => $lighting,
                'is_featured'    => true,
                'sort'           => 1,
                'seo_keywords'   => '智能吸顶灯,全光谱,无极调光,防蓝光,客厅照明',
                'description_zh' => '130W 全光谱光源，色温 2700-6000K 无极可调，主灯与线光双路独立控制。适配 30-50㎡ 客厅，沿用原底盘不动吊顶。',
                'content_zh'     => <<<'HTML'
                    <p>客厅是全屋唯一要同时满足「看得清」和「待得住」的空间。亮度不够，晚上看书费眼；一味堆瓦数，客厅又会亮得像办公室。这一款把光源拆成主灯和线光两路，白天用主灯保证照度，夜里只留线光当过道灯。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>11000lm 光通量、显色指数 Ra95，30-50㎡ 客厅不必再补射灯</li>
                    <li>色温 2700-6000K 无极调节，暖黄到正白连续过渡，没有档位跳变</li>
                    <li>RG0 豁免级防蓝光、UGR19 防眩，抬头看灯不刺眼</li>
                    <li>线光可单独点亮，起夜 5% 亮度，不惊动同屋的人</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-CL130，额定功率 130W</li>
                    <li>尺寸 960×620×88mm，沿用常规吸顶灯底盘</li>
                    <li>协议 Zigbee 3.0，需搭配网关；也可单机遥控使用</li>
                    <li>控制方式：面板 / App / 语音 / 场景联动</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>不改吊顶、不动线，两小时内完成更换</li>
                    <li>武汉三环内下单后 48 小时上门，含调光调色与场景绑定</li>
                    <li>整灯质保 3 年，驱动电源单独质保 3 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '智能场景面板开关',
                'slug'           => 'smart-panel-switch',
                'brand'          => '米家（MIKA）',
                'price'          => null,
                'category_id'    => $lighting,
                'is_featured'    => true,
                'sort'           => 2,
                'seo_keywords'   => '智能开关,零火开关,单火开关,场景面板,86型',
                'description_zh' => '零火 / 单火双版本，86 型底盒直接替换。按键本地直控，断网与网关离线时依然能按亮，每个键位都可绑场景。',
                'content_zh'     => <<<'HTML'
                    <p>开关是全屋唯一每天要碰几十次的智能设备，手感和响应速度比参数表更重要。它也是最需要留退路的一件——所以这款的按键走本地直控，网关掉线、路由重启、断网，按下去灯照样亮。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>本地直控优先，云端只负责远程和联动，不参与「按一下亮不亮」</li>
                    <li>零火版为首选；老房没有零线时可选单火版，不砸墙</li>
                    <li>每个键位都能绑场景，长按执行「离家」这类批量动作</li>
                    <li>夜间指示灯自动降亮度，卧室门口不晃眼</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-SW01，1 / 2 / 3 / 4 键可选</li>
                    <li>接线方式：零火版（推荐）/ 单火版</li>
                    <li>协议 Zigbee 3.0，兼作 Zigbee 网络中继</li>
                    <li>面板适配 86 型标准底盒</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>沿用原 86 型底盒，不开线槽</li>
                    <li>单火版对灯具功率有下限要求，量房时逐路测试是否有微亮或关不断</li>
                    <li>质保 2 年，按键失灵免费换新</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '无主灯磁吸线性灯',
                'slug'           => 'linear-magnetic-light',
                'brand'          => null,
                'price'          => null,
                'category_id'    => $lighting,
                'is_featured'    => false,
                'sort'           => 3,
                'seo_keywords'   => '无主灯,磁吸轨道,线性灯,嵌入式,照明设计',
                'description_zh' => '48V 低压磁吸轨道，线性灯与射灯同轨混装。灯具位置后期可随意挪动、按需增减，不用重新开孔。',
                'content_zh'     => <<<'HTML'
                    <p>磁吸轨道最大的价值不是好看，是后期能改。书桌挪了位置、墙上加了一幅画，把灯往旁边推一段就行，不用重新开孔补漆——这一点住进去两三年后才显出来。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>同轨可混装线性灯、射灯、泛光灯，基础照明与重点照明一条轨解决</li>
                    <li>48V 低压供电，轨道裸露部分不带市电</li>
                    <li>深藏光源配遮光格栅，光线柔和不刺眼</li>
                    <li>低温工艺，开箱无异味，进场即可安装</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-LT48，嵌入式轨道，可现场裁切</li>
                    <li>单条线性灯 600mm / 12W，同轨射灯 7W 可选</li>
                    <li>色温 3000K / 4000K，显色指数 Ra95</li>
                    <li>调光 0-100% 无频闪，支持场景联动</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>吊顶需预留 35mm 嵌入深度，必须在木工进场前定稿</li>
                    <li>含照度计算与轨道排布图，按房间给数量而不是按间估</li>
                    <li>轨道与灯具质保 3 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '智能防眩筒灯',
                'slug'           => 'smart-downlight',
                'brand'          => '小米',
                'price'          => null,
                'category_id'    => $lighting,
                'is_featured'    => false,
                'sort'           => 4,
                'seo_keywords'   => '筒灯,防眩光,UGR19,无主灯,可调光',
                'description_zh' => '深藏 25mm、UGR 低于 19，躺在沙发上看不进光源。75mm 通用开孔，0-100% 无频闪调光。',
                'content_zh'     => <<<'HTML'
                    <p>筒灯是无主灯方案里数量最多的一件，也是最容易在防眩上省钱的一件。深藏光源多花的十几块，换来的是躺在沙发上抬头不刺眼——这个差别装完当天就能感觉到。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>光源深藏 25mm，配蜂窝格栅，UGR 低于 19</li>
                    <li>75mm 通用开孔，与主流吊顶开孔尺寸一致</li>
                    <li>0-100% 无频闪调光，手机拍摄不出现横纹</li>
                    <li>可单独成路，配合场景做重点照明</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-DL07，功率 7W / 光通量 800lm</li>
                    <li>开孔 75mm，嵌入深度 55mm</li>
                    <li>色温 3000K / 4000K，显色指数 Ra95</li>
                    <li>光束角 24° / 36° 可选</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>按房间照度计算给数量与排布，不按「一间四只」估</li>
                    <li>开孔尺寸需与吊顶施工同步确认</li>
                    <li>质保 3 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '全彩智能灯带',
                'slug'           => 'rgb-led-strip',
                'brand'          => '小米',
                'price'          => null,
                'category_id'    => $lighting,
                'is_featured'    => false,
                'sort'           => 5,
                'seo_keywords'   => '智能灯带,RGB,氛围照明,可裁剪,24V',
                'description_zh' => 'RGB+CCT 五通道，白光也能当基础照明用。每米 96 颗灯珠，嵌入铝槽后无颗粒感，可按单元随意裁剪。',
                'content_zh'     => <<<'HTML'
                    <p>灯带在方案里承担氛围层，但真正决定观感的是藏得好不好。我们只做嵌槽安装，不做明贴——明贴的灯带三个月后开始翘边，比没装更难看。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>RGB+CCT 五通道，除了变色还能出标准白光，可以当基础照明的一层</li>
                    <li>每米 96 颗灯珠，配导光罩后是连续光带，看不出灯珠颗粒</li>
                    <li>可按 25mm 单元裁剪，异形柜体也能贴合</li>
                    <li>支持观影、就寝场景的渐变与延时熄灭</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-LS96，电压 24V，功率 14W/m</li>
                    <li>长度 2m / 5m 可选，可级联</li>
                    <li>防护等级 IP20，仅适用室内干区</li>
                    <li>协议 Zigbee 3.0</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>需在吊顶或柜体预留铝槽，位置在木工阶段确定</li>
                    <li>单条超过 5m 需双端供电，避免末端偏暗</li>
                    <li>质保 2 年</li>
                    </ul>
                    HTML,
            ],

            // ---------- 智能安防 ----------
            [
                'title_zh'       => '可视指纹智能门锁',
                'slug'           => 'smart-fingerprint-lock',
                'brand'          => '米家（MIKA）',
                'price'          => null,
                'category_id'    => $security,
                'is_featured'    => true,
                'sort'           => 6,
                'seo_keywords'   => '智能门锁,指纹锁,可视猫眼,C级锁芯,天地钩',
                'description_zh' => '半导体指纹 0.3 秒识别，暗处也能用。C 级锁芯全金属锁体，支持天地钩联动，保留机械钥匙应急孔。',
                'content_zh'     => <<<'HTML'
                    <p>门锁是唯一「装错了要换门」的智能设备。所以我们把量尺寸放在下单之前：门厚、锁体开孔间距、有没有天地钩，三个数确认完才发货。这一步跳过去，退货率会高得惊人。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>半导体指纹 0.3 秒识别，手指干燥或暗处也能开</li>
                    <li>猫眼可视加门内对讲，不开门就能看清来人</li>
                    <li>C 级锁芯 + 全金属锁体，支持天地钩联动，防盗等级不打折</li>
                    <li>保留机械钥匙应急孔，电池耗尽也进得去门</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-DL60，适配门厚 40-120mm</li>
                    <li>开门方式：指纹 / 密码 / 卡 / App / 机械钥匙</li>
                    <li>供电 8 节 5 号电池，日常开合约 10 个月</li>
                    <li>异常告警：试错超限、门未关好、电量偏低推送到手机</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>必须上门安装。先量三个尺寸再发货，需要换锁体的提前报价</li>
                    <li>武汉三环内一般 48 小时内排期</li>
                    <li>装完当场录指纹、试反锁、演示机械钥匙应急开法，这三步不做完不算交付</li>
                    <li>锁体质保 3 年，电子部分质保 2 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '电池版可视门铃',
                'slug'           => 'video-doorbell',
                'brand'          => '小米',
                'price'          => null,
                'category_id'    => $security,
                'is_featured'    => false,
                'sort'           => 7,
                'seo_keywords'   => '可视门铃,免布线,双向通话,2K,IP65',
                'description_zh' => '2K 画面，免布线，磁吸支架取下即充。双向通话，人在外地也能应门；本地存储与云存储二选一。',
                'content_zh'     => <<<'HTML'
                    <p>门铃装在门外，没有现成电源是常态。电池版免布线解决了这个问题，但续航和唤醒速度天生矛盾——灵敏度调高就费电。我们把侦测灵敏度做成三档，安装时按楼道人流量一起选。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>2K 分辨率，人脸区域可放大，不是「知道有人」而是「看清是谁」</li>
                    <li>免布线，3M 胶或膨胀螺丝二选一，不破坏门框</li>
                    <li>磁吸支架，充电时直接取下，不用拆螺丝</li>
                    <li>双向通话，不在家也能回应快递</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-DB20，分辨率 2K</li>
                    <li>供电 5200mAh 可拆电池，按日常人流约 3-4 个月一充</li>
                    <li>夜视 红外 5m，防护等级 IP65</li>
                    <li>存储 microSD 或云存储，二者可同时开</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>安装高度建议 1.4-1.5m，含侦测区域与灵敏度调试</li>
                    <li>装前一起确认拍摄角度，避开邻居门窗与公共通道</li>
                    <li>整机质保 2 年，电池质保 1 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '室内云台摄像头',
                'slug'           => 'indoor-hd-camera',
                'brand'          => '小米',
                'price'          => null,
                'category_id'    => $security,
                'is_featured'    => false,
                'sort'           => 8,
                'seo_keywords'   => '室内摄像头,云台,隐私模式,人形识别,2K',
                'description_zh' => '355° 云台一台覆盖整个客厅。隐私模式下镜头物理转向底座，站在客厅里能看见它确实闭着眼。',
                'content_zh'     => <<<'HTML'
                    <p>室内摄像头的争议从来不在画质，在隐私。这一款把遮蔽做成了机械动作：进入隐私模式时镜头物理转向底座，肉眼可辨——比一个写着「已关闭」的软件开关让人放心。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>355° 水平云台巡航，一台覆盖整个客厅，不用装两台</li>
                    <li>隐私模式镜头物理归位，状态看得见</li>
                    <li>哭声、人形、异响分别识别，只在该提醒时推送</li>
                    <li>存储卡与 NVR 双路可选，不强制订阅云服务</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-IC21，分辨率 2K</li>
                    <li>云台 355° 水平 / 110° 垂直</li>
                    <li>夜视 红外 10m，支持全彩夜视切换</li>
                    <li>存储 microSD 最大 256GB，或接入 NVR</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>桌面摆放或吸顶倒装两种方式，含侦测区域与灵敏度调试</li>
                    <li>我们建议卧室与卫生间不装；确有需要的用定时隐私模式</li>
                    <li>质保 2 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '室外全彩变焦摄像头',
                'slug'           => 'outdoor-ptz-camera',
                'brand'          => '小米',
                'price'          => null,
                'category_id'    => $security,
                'is_featured'    => false,
                'sort'           => 9,
                'seo_keywords'   => '室外摄像头,全彩夜视,光学变焦,IP66,人形侦测',
                'description_zh' => 'F1.0 大光圈加双光补光，夜间保留颜色而非黑白剪影。3 倍光学变焦，IP66 防护，-20℃ 至 50℃ 可用。',
                'content_zh'     => <<<'HTML'
                    <p>室外机的考验不是白天，是夜里和雨天。全彩夜视靠的是大光圈加补光灯，不是把红外调亮——红外能看清人影，看不清衣服颜色，而事后调取录像时，颜色恰恰是最有用的信息。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>F1.0 大光圈配双光补光，夜间画面保留颜色</li>
                    <li>3 倍光学变焦，十米外的车牌与人脸能看清</li>
                    <li>IP66 防护，-20℃ 至 50℃ 工作，武汉的梅雨和寒潮都撑得住</li>
                    <li>人形侦测联动照明与语音警戒，不是拍完就完</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-OC30，分辨率 4MP</li>
                    <li>变焦 3 倍光学，全彩夜视有效距离约 30m</li>
                    <li>供电 DC12V 或 PoE 网线供电</li>
                    <li>存储 microSD 或 NVR</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>需在水电阶段预埋电源或网线，安装高度建议 2.8-3.5m</li>
                    <li>拍摄范围应避开邻居门窗与公共通道，安装前一起确认角度并设置遮蔽区</li>
                    <li>质保 2 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '毫米波人体存在传感器',
                'slug'           => 'presence-sensor',
                'brand'          => null,
                'price'          => null,
                'category_id'    => $security,
                'is_featured'    => false,
                'sort'           => 10,
                'seo_keywords'   => '人体传感器,毫米波,存在检测,感应灯,Zigbee',
                'description_zh' => '检测「在不在」而不是「动没动」，坐着看书两小时灯也不会灭。距离与角度可调，配光照阈值白天不点灯。',
                'content_zh'     => <<<'HTML'
                    <p>红外感应只认「动」，人坐着不动灯就灭了——这是感应灯装完又被拆掉的头号原因。毫米波认的是「在」，坐着看书、躺着刷手机都算，灯不会突然黑掉。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>存在检测而非移动检测，静坐、静卧都能持续判定</li>
                    <li>检测距离与角度可调，走廊装也不会隔墙误触</li>
                    <li>配合光照阈值，白天有自然光时不点灯</li>
                    <li>可上报「无人」状态，用来做离房自动关灯关空调</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-PS24，工作频段 24GHz</li>
                    <li>检测距离 0.5-6m 可调，检测角度 ±60°</li>
                    <li>供电 USB-C 常供电或 3 节 7 号电池</li>
                    <li>协议 Zigbee 3.0</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>吸顶正下方效果最好，避免正对窗帘和空调出风口</li>
                    <li>含误触发排查：宠物走动、窗帘飘动、出风摆叶逐项试过再交付</li>
                    <li>质保 2 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '燃气烟感联动报警套装',
                'slug'           => 'gas-smoke-alarm',
                'brand'          => null,
                'price'          => null,
                'category_id'    => $security,
                'is_featured'    => false,
                'sort'           => 11,
                'seo_keywords'   => '燃气报警器,烟雾报警器,自动关阀,机械手,厨房安全',
                'description_zh' => '燃气与烟感双探头，报警同时由机械手切断燃气阀，并推送到全家人的手机，不只是响一声。',
                'content_zh'     => <<<'HTML'
                    <p>报警器最怕两件事：该响的时候没响，和响了没人知道。套装把两个探头和一只机械手接在一起——报警的同时关阀，并把消息推到全家人的手机上，家里没人也来得及处置。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>燃气 + 烟感双探头，覆盖厨房两类主要风险</li>
                    <li>联动机械手自动关阀，不依赖有人在家去关</li>
                    <li>推送到全部绑定手机，同时可联动客厅灯闪烁提示</li>
                    <li>探头自检，寿命到期与电量不足会提前告知</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-GS02，含燃气探头、烟感探头、阀门机械手</li>
                    <li>燃气探头为半导体式，传感元件寿命约 5 年</li>
                    <li>烟感为光电式，电池供电，低电量提醒</li>
                    <li>协议 Zigbee 3.0，需搭配网关</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>燃气探头装在灶具上方 30cm 范围内，烟感装吊顶中央</li>
                    <li>机械手需匹配阀门型号，量房时拍照确认后订货</li>
                    <li>探头临近寿命我们会主动提醒更换，不等它静默失效</li>
                    </ul>
                    HTML,
            ],

            // ---------- 智能家电与中控 ----------
            [
                'title_zh'       => '全屋智能中控屏',
                'slug'           => 'smart-control-panel',
                'brand'          => '小米',
                'price'          => null,
                'category_id'    => $appliance,
                'is_featured'    => true,
                'sort'           => 12,
                'seo_keywords'   => '中控屏,场景面板,内置网关,可视对讲,入墙安装',
                'description_zh' => '6 英寸入墙触控屏，内置双模网关。断网仍可执行本地场景；家里长辈不装 App 也能用。',
                'content_zh'     => <<<'HTML'
                    <p>中控屏的价值不在屏，在「不用找手机」。装在玄关和主卧床头这两个位置，全家人不装 App 也能操作——这一点对家里的长辈和孩子，比任何功能都重要。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>6 英寸触控，常用场景一屏直达，不用翻菜单</li>
                    <li>内置双模网关，屏本身就是本地场景引擎，断网照样执行</li>
                    <li>可与可视门铃直连，来人时屏上直接看画面并对讲</li>
                    <li>86 型入墙安装，不占玄关台面</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-CP60，屏幕 6 英寸 IPS 触控</li>
                    <li>协议 Zigbee 3.0 + 蓝牙 Mesh + Wi-Fi</li>
                    <li>内置网关支持 128 台子设备</li>
                    <li>供电 PoE 或 220V 入墙</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>需在水电阶段预埋 86 底盒与电源，玄关加床头各一台是常见配置</li>
                    <li>含场景编排与一次完整讲解，长辈和孩子都要会用</li>
                    <li>质保 2 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '多协议智能网关',
                'slug'           => 'multimode-gateway',
                'brand'          => null,
                'price'          => null,
                'category_id'    => $appliance,
                'is_featured'    => true,
                'sort'           => 13,
                'seo_keywords'   => '智能网关,双模,Zigbee,蓝牙Mesh,本地场景',
                'description_zh' => 'Zigbee 3.0 与蓝牙 Mesh 双模，千兆网口有线上行。本地场景引擎断网仍执行开关与感应逻辑，单台带 128 台子设备。',
                'content_zh'     => <<<'HTML'
                    <p>网关是全屋智能里唯一「买错了要拆面板」的设备。设备清单能随时加，协议底座换一次却要把已装好的开关全拆下来，所以我们默认给双模——多出来的两三百块，比日后返工便宜太多。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>Zigbee 3.0 + 蓝牙 Mesh 双模，传感器与开关同网共存</li>
                    <li>千兆网口有线上行，不挂在无线中继下面</li>
                    <li>本地场景引擎：断网时开关直控与感应逻辑照常执行</li>
                    <li>单台支持 128 个子设备，常规三居一台够用</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-GW02</li>
                    <li>协议 Zigbee 3.0 / 蓝牙 Mesh / Wi-Fi 2.4GHz</li>
                    <li>上行千兆有线，供电 DC5V</li>
                    <li>子设备容量 128 台</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>建议装在弱电箱外侧或走廊吊顶，金属箱体内会屏蔽信号</li>
                    <li>跨层住宅按楼层加装，避免穿楼板丢包</li>
                    <li>质保 3 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '智能电动窗帘电机',
                'slug'           => 'smart-curtain-motor',
                'brand'          => '米家',
                'price'          => null,
                'category_id'    => $appliance,
                'is_featured'    => false,
                'sort'           => 14,
                'seo_keywords'   => '电动窗帘,窗帘电机,静音,双电机对开,行程自学习',
                'description_zh' => '运行噪音低于 30dB，额定拉力 30N。手拉即走，断电可手动开合，行程自学习装完自动记忆两端。',
                'content_zh'     => <<<'HTML'
                    <p>窗帘电机的噪音九成来自轨道，不是电机。所以我们把「轨道吊平」写成了安装标准的第一条——静音参数标得再好，轨道下垂 3mm 也会咯咯响。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>运行噪音低于 30dB，卧室夜间开合不吵醒人</li>
                    <li>额定拉力 30N，配双电机可做 4 米以上落地窗</li>
                    <li>手拉即走，断电时也能像普通窗帘一样手动拉</li>
                    <li>行程自学习，装完自动记忆两端位置，不用手工标定</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-CM30，额定拉力 30N</li>
                    <li>噪音低于 30dB，协议 蓝牙 Mesh + Wi-Fi</li>
                    <li>轨道为拼接轨，可现场裁切</li>
                    <li>支持定时、日出日落与场景联动</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>需在水电阶段预留窗帘盒电源，位置在量房时定</li>
                    <li>4 米以上默认双电机对开；单层纱帘可评估单电机方案</li>
                    <li>含轨道吊平与行程校准，噪音不达标返工不另收费</li>
                    <li>电机质保 3 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '智能中央空调控制器',
                'slug'           => 'smart-ac-controller',
                'brand'          => null,
                'price'          => null,
                'category_id'    => $appliance,
                'is_featured'    => false,
                'sort'           => 15,
                'seo_keywords'   => '中央空调,线控器,分房控温,场景联动,86型',
                'description_zh' => '替换原线控器，一室一台，界面统一。让每个房间的温度进入场景——「离家」一次关掉所有内机。',
                'content_zh'     => <<<'HTML'
                    <p>中央空调的线控器是全屋唯一没被智能化的老古董：不能远程、不进场景、每个房间还得单独跑一趟。控制器接在原线控位置，把温度这件事拉进场景里。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>替换原线控器，沿用原底盒，全屋面板风格统一</li>
                    <li>接入场景：离家关机、回家预冷、就寝调温</li>
                    <li>分房间独立控温与定时，不再是一开全开</li>
                    <li>可与人体存在传感器联动，房间无人一段时间自动停机</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-AC01，面板 86 型</li>
                    <li>控制项：温度 / 模式 / 风速 / 定时</li>
                    <li>协议 Zigbee 3.0</li>
                    <li>适配多品牌中央空调线控接口，下单前需确认</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>需先确认空调品牌与线控协议再订货，不能盲买</li>
                    <li>沿用原线控底盒，含通讯调试与场景绑定</li>
                    <li>质保 2 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '万能红外遥控中枢',
                'slug'           => 'ir-remote-hub',
                'brand'          => null,
                'price'          => null,
                'category_id'    => $appliance,
                'is_featured'    => false,
                'sort'           => 16,
                'seo_keywords'   => '红外遥控,万能遥控,老电视,挂机空调,场景联动',
                'description_zh' => '把只认红外遥控的老电视、挂机空调、风扇拉进场景。360° 发射，有效半径约 8m，USB 供电常在线。',
                'content_zh'     => <<<'HTML'
                    <p>老电视、挂机空调、落地扇这些只认红外遥控的设备，是智能家居里最容易被直接放弃的一批。一台红外中枢能把它们拉进场景，成本不到换新的十分之一。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>覆盖主流品牌红外码库；码库里没有的可用学习模式逐键录入</li>
                    <li>一台管一个房间的全部红外设备，不用一台一个遥控器</li>
                    <li>可进场景联动：开空调的同时拉窗帘、关灯</li>
                    <li>体积小，USB 口常供电，随时在线</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-IR02，发射角度 360°，有效半径约 8m</li>
                    <li>供电 USB-C 5V，需常供电（不是电池款）</li>
                    <li>协议 Wi-Fi 2.4GHz</li>
                    <li>支持学习模式录入自定义按键</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>需与被控设备处于同一房间且无遮挡，隔墙无效</li>
                    <li>只走蓝牙或厂商私有协议的家电控不了，量房时先试一遍再定</li>
                    <li>质保 1 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '计量型智能插座',
                'slug'           => 'metering-socket',
                'brand'          => null,
                'price'          => null,
                'category_id'    => $appliance,
                'is_featured'    => false,
                'sort'           => 17,
                'seo_keywords'   => '智能插座,电量统计,16A,过载保护,定时',
                'description_zh' => '16A 大功率，实时功率与日月电量统计。能回答两个常见问题：老冰箱该不该换，热水器该不该改定时。',
                'content_zh'     => <<<'HTML'
                    <p>插座的智能不在于远程开关，在于知道那台设备到底费不费电。装三只在冰箱、热水器和空调上，一个月的数据就能回答两个反复纠缠的问题：老冰箱该不该换，热水器该不该改定时。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>实时功率与日 / 月电量统计，是计量而不是估算</li>
                    <li>16A 规格，热水器和空调挂机可以直接用</li>
                    <li>可设定时与断电记忆，跳闸恢复后回到原状态</li>
                    <li>过载自动断电，老房线路上多一层保险</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-SP16，额定 250V / 16A，最大 3500W</li>
                    <li>计量精度 ±1%</li>
                    <li>协议 Wi-Fi 2.4GHz</li>
                    <li>支持定时、倒计时与场景联动</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>免安装，即插即用</li>
                    <li>不要串联使用，也不要接在插排上带大功率设备</li>
                    <li>质保 2 年</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'       => '空调伴侣（挂机版）',
                'slug'           => 'ac-companion',
                'brand'          => null,
                'price'          => null,
                'category_id'    => $appliance,
                'is_featured'    => false,
                'sort'           => 18,
                'seo_keywords'   => '空调伴侣,挂机空调,红外控制,电量统计,免布线',
                'description_zh' => '红外控制加电量计量二合一，替换原空调插座即可。回传室温，可按温度条件自动启停。',
                'content_zh'     => <<<'HTML'
                    <p>挂机空调改智能有两条路：换线控或者接伴侣。伴侣走的是插座位，装完既能控空调，又顺手把这一路的用电量算清楚——夏天过完，一眼看到空调花了多少电。</p>
                    <p><strong>为什么选它</strong></p>
                    <ul>
                    <li>红外控制与电量计量二合一，不用再单独装插座和遥控中枢</li>
                    <li>回传室温，可设「高于 28℃ 自动开机」这类条件启停</li>
                    <li>断电记忆，跳闸恢复后回到原设定</li>
                    <li>免布线，替换原空调插座即可</li>
                    </ul>
                    <p><strong>关键参数</strong></p>
                    <ul>
                    <li>选配编号 QK-ACC16，额定 250V / 16A</li>
                    <li>控制方式：内置红外码库 + 学习模式</li>
                    <li>计量：实时功率与日 / 月电量</li>
                    <li>协议 Wi-Fi 2.4GHz</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <ul>
                    <li>需原空调插座为 16A 规格，量房时确认</li>
                    <li>不适用中央空调，中央空调请选线控替换款</li>
                    <li>质保 2 年</li>
                    </ul>
                    HTML,
            ],
        ];
    }

    /**
     * 全屋智能套餐数据（6 条：三室一厅 / 三室两厅 各三档，批次 3 新增）
     *
     * 与「智能方案」的分工见 SitePackage 类注释：方案讲怎么解决，套餐讲这个户型
     * 做下来是什么配置、多少钱。价格与清单都是**按常规配置估的示例数字**，不对应
     * 任何真实报价单——`price_note` 已经说明这一点，前台不会把它当承诺文案渲染。
     *
     * @return list<array<string, mixed>>
     */
    protected function packagesData(): array
    {
        return [
            [
                'title_zh'       => '三室一厅 · 定制款全屋智能套餐',
                'slug'           => 'three-one-custom',
                'description_zh' => '按需要挑几件，先把最想解决的问题解决掉。适合预算有限、想从灯光和门锁起步的三室一厅。',
                'content_zh'     => '<p>三室一厅定制款覆盖最高频的两类场景——灯光分路控制与入户安全，不含影音、暖通这类可以后置的部分，方便后续按需追加。</p>',
                'house_layout'   => HouseLayout::THREE_ONE,
                'tier'           => PackageTier::CUSTOM,
                'area_range'     => '70-90㎡',
                'price'          => '12800',
                'price_note'     => '按常规点位估算的示例价，实际报价以上门量房为准',
                'items'          => [
                    ['name' => '智能开关面板', 'quantity' => '8 个', 'purpose' => '灯光分路控制', 'location' => '客厅 / 卧室 / 厨卫'],
                    ['name' => '人体存在传感器', 'quantity' => '2 个', 'purpose' => '过道与玄关感应联动', 'location' => '玄关 / 走廊'],
                    ['name' => '智能门锁', 'quantity' => '1 把', 'purpose' => '入户防盗与开锁记录', 'location' => '入户门'],
                ],
                'excludes'        => '不含影音系统、不含中央空调联动、不含摄像头',
                'duration'        => '3-4 个工作日（不含水电改造）',
                'warranty'        => '整套质保 2 年，人工质保 1 年',
                'seo_title'       => '三室一厅定制款全屋智能套餐 - 示例装修有限公司',
                'seo_description' => '三室一厅入门级全屋智能套餐，智能开关、存在传感器、智能门锁，按需定制，示例装修有限公司提供。',
                'seo_keywords'    => '全屋智能套餐,三室一厅,定制款,智能开关,智能门锁',
                'is_featured'     => false,
                'sort'            => 1,
                'published_at'    => now()->subDays(20),
            ],
            [
                'title_zh'       => '三室一厅 · 舒适款全屋智能套餐',
                'slug'           => 'three-one-comfort',
                'description_zh' => '常用场景一次做齐，日常使用的舒适度都覆盖到。在定制款基础上加了网关、可视门铃和电动窗帘。',
                'content_zh'     => '<p>舒适款把三室一厅的门、窗、网关三件事一次做齐：来人先看得见，网关统一底座避免协议不通，窗帘电动化省掉每天手动开合。</p>',
                'house_layout'   => HouseLayout::THREE_ONE,
                'tier'           => PackageTier::COMFORT,
                'area_range'     => '70-90㎡',
                'price'          => '22800',
                'price_note'     => '按常规点位估算的示例价，实际报价以上门量房为准',
                'items'          => [
                    ['name' => '智能开关面板', 'quantity' => '10 个', 'purpose' => '灯光分路控制', 'location' => '全屋'],
                    ['name' => '智能门锁', 'quantity' => '1 把', 'purpose' => '入户防盗与开锁记录', 'location' => '入户门'],
                    ['name' => '可视门铃', 'quantity' => '1 个', 'purpose' => '来访提醒与远程查看', 'location' => '入户门外'],
                    ['name' => '多协议智能网关', 'quantity' => '1 台', 'purpose' => '统一控制底座，断网仍可本地执行', 'location' => '弱电箱'],
                    ['name' => '智能窗帘电机', 'quantity' => '2 套', 'purpose' => '客厅与主卧遮光帘电动化', 'location' => '客厅 / 主卧'],
                ],
                'excludes'        => '不含中央空调联动、不含影音系统',
                'duration'        => '4-5 个工作日（不含水电改造）',
                'warranty'        => '整套质保 2 年，核心设备质保 3 年',
                'seo_title'       => '三室一厅舒适款全屋智能套餐 - 示例装修有限公司',
                'seo_description' => '三室一厅舒适款全屋智能套餐，网关、可视门铃、电动窗帘一次配齐，示例装修有限公司提供上门安装。',
                'seo_keywords'    => '全屋智能套餐,三室一厅,舒适款,智能网关,电动窗帘',
                'is_featured'     => true,
                'sort'            => 2,
                'published_at'    => now()->subDays(15),
            ],
            [
                'title_zh'       => '三室一厅 · 豪华款全屋智能套餐',
                'slug'           => 'three-one-deluxe',
                'description_zh' => '全屋联动做满，包含安防与更完整的传感网络。中控屏加多路摄像头，长辈和孩子都能直接用面板操作。',
                'content_zh'     => '<p>豪华款在舒适款基础上补齐中控与安防：玄关和主卧各一台中控屏，出入口与阳台加装摄像头，厨房联动燃气报警，覆盖面从「够用」升级到「放心」。</p>',
                'house_layout'   => HouseLayout::THREE_ONE,
                'tier'           => PackageTier::DELUXE,
                'area_range'     => '70-90㎡',
                'price'          => '38800',
                'price_note'     => '按常规点位估算的示例价，实际报价以上门量房为准',
                'items'          => [
                    ['name' => '全屋智能中控屏', 'quantity' => '2 台', 'purpose' => '场景集中管理，长辈孩子都能用', 'location' => '玄关 / 主卧'],
                    ['name' => '多协议智能网关', 'quantity' => '1 台', 'purpose' => '统一控制底座', 'location' => '弱电箱'],
                    ['name' => '智能门锁 + 可视门铃', 'quantity' => '各 1 个', 'purpose' => '入户安全与来访提醒', 'location' => '入户门'],
                    ['name' => '智能窗帘电机', 'quantity' => '3 套', 'purpose' => '客厅、主卧、次卧遮光帘电动化', 'location' => '客厅 / 主卧 / 次卧'],
                    ['name' => '室内外摄像头', 'quantity' => '3 个', 'purpose' => '出入口与阳台安防监控', 'location' => '入户 / 客厅 / 阳台'],
                    ['name' => '燃气烟感联动套装', 'quantity' => '1 套', 'purpose' => '厨房燃气与烟雾报警自动关阀', 'location' => '厨房'],
                ],
                'excludes'        => '不含中央空调本机、不含影音设备本体（含联动改造）',
                'duration'        => '5-7 个工作日（不含水电改造）',
                'warranty'        => '整套质保 3 年',
                'seo_title'       => '三室一厅豪华款全屋智能套餐 - 示例装修有限公司',
                'seo_description' => '三室一厅豪华款全屋智能套餐，中控屏、多路摄像头、燃气联动一次做满，示例装修有限公司提供上门安装。',
                'seo_keywords'    => '全屋智能套餐,三室一厅,豪华款,中控屏,智能安防',
                'is_featured'     => false,
                'sort'            => 3,
                'published_at'    => now()->subDays(10),
            ],
            [
                'title_zh'       => '三室两厅 · 定制款全屋智能套餐',
                'slug'           => 'three-two-custom',
                'description_zh' => '按需要挑几件，先把最想解决的问题解决掉。两个厅意味着多一组开关面板和一段感应覆盖，配置比三室一厅略多。',
                'content_zh'     => '<p>三室两厅比三室一厅多一个厅，灯光回路和过道感应也多一组，其余思路与三室一厅定制款一致：先做灯光与入户安全，其余按需后置。</p>',
                'house_layout'   => HouseLayout::THREE_TWO,
                'tier'           => PackageTier::CUSTOM,
                'area_range'     => '90-110㎡',
                'price'          => '15800',
                'price_note'     => '按常规点位估算的示例价，实际报价以上门量房为准',
                'items'          => [
                    ['name' => '智能开关面板', 'quantity' => '10 个', 'purpose' => '灯光分路控制', 'location' => '客厅 / 餐厅 / 卧室 / 厨卫'],
                    ['name' => '人体存在传感器', 'quantity' => '3 个', 'purpose' => '过道与玄关感应联动', 'location' => '玄关 / 走廊'],
                    ['name' => '智能门锁', 'quantity' => '1 把', 'purpose' => '入户防盗与开锁记录', 'location' => '入户门'],
                ],
                'excludes'        => '不含影音系统、不含中央空调联动、不含摄像头',
                'duration'        => '3-4 个工作日（不含水电改造）',
                'warranty'        => '整套质保 2 年，人工质保 1 年',
                'seo_title'       => '三室两厅定制款全屋智能套餐 - 示例装修有限公司',
                'seo_description' => '三室两厅入门级全屋智能套餐，智能开关、存在传感器、智能门锁，按需定制，示例装修有限公司提供。',
                'seo_keywords'    => '全屋智能套餐,三室两厅,定制款,智能开关,智能门锁',
                'is_featured'     => false,
                'sort'            => 4,
                'published_at'    => now()->subDays(20),
            ],
            [
                'title_zh'       => '三室两厅 · 舒适款全屋智能套餐',
                'slug'           => 'three-two-comfort',
                'description_zh' => '常用场景一次做齐，日常使用的舒适度都覆盖到。餐厅独立一路窗帘，两个厅的使用体验都照顾到。',
                'content_zh'     => '<p>三室两厅的舒适款在三室一厅的配置上，把窗帘电机加到 3 套，客厅和餐厅各自独立控制，避免「一个厅拉帘另一个厅跟着暗」。</p>',
                'house_layout'   => HouseLayout::THREE_TWO,
                'tier'           => PackageTier::COMFORT,
                'area_range'     => '90-110㎡',
                'price'          => '26800',
                'price_note'     => '按常规点位估算的示例价，实际报价以上门量房为准',
                'items'          => [
                    ['name' => '智能开关面板', 'quantity' => '12 个', 'purpose' => '灯光分路控制', 'location' => '全屋'],
                    ['name' => '智能门锁', 'quantity' => '1 把', 'purpose' => '入户防盗与开锁记录', 'location' => '入户门'],
                    ['name' => '可视门铃', 'quantity' => '1 个', 'purpose' => '来访提醒与远程查看', 'location' => '入户门外'],
                    ['name' => '多协议智能网关', 'quantity' => '1 台', 'purpose' => '统一控制底座，断网仍可本地执行', 'location' => '弱电箱'],
                    ['name' => '智能窗帘电机', 'quantity' => '3 套', 'purpose' => '客厅、餐厅、主卧遮光帘电动化', 'location' => '客厅 / 餐厅 / 主卧'],
                ],
                'excludes'        => '不含中央空调联动、不含影音系统',
                'duration'        => '4-5 个工作日（不含水电改造）',
                'warranty'        => '整套质保 2 年，核心设备质保 3 年',
                'seo_title'       => '三室两厅舒适款全屋智能套餐 - 示例装修有限公司',
                'seo_description' => '三室两厅舒适款全屋智能套餐，网关、可视门铃、三套电动窗帘一次配齐，示例装修有限公司提供上门安装。',
                'seo_keywords'    => '全屋智能套餐,三室两厅,舒适款,智能网关,电动窗帘',
                'is_featured'     => true,
                'sort'            => 5,
                'published_at'    => now()->subDays(15),
            ],
            [
                'title_zh'       => '三室两厅 · 豪华款全屋智能套餐',
                'slug'           => 'three-two-deluxe',
                'description_zh' => '全屋联动做满，包含安防与更完整的传感网络。两个厅各有独立场景，客餐厅联动待客模式一键切换。',
                'content_zh'     => '<p>三室两厅豪华款在三室一厅豪华款的基础上，给客厅和餐厅分别配置独立场景开关，待客时一键切换灯光与窗帘，不用两个厅分别操作。</p>',
                'house_layout'   => HouseLayout::THREE_TWO,
                'tier'           => PackageTier::DELUXE,
                'area_range'     => '90-110㎡',
                'price'          => '45800',
                'price_note'     => '按常规点位估算的示例价，实际报价以上门量房为准',
                'items'          => [
                    ['name' => '全屋智能中控屏', 'quantity' => '2 台', 'purpose' => '场景集中管理，长辈孩子都能用', 'location' => '玄关 / 主卧'],
                    ['name' => '多协议智能网关', 'quantity' => '1 台', 'purpose' => '统一控制底座', 'location' => '弱电箱'],
                    ['name' => '智能门锁 + 可视门铃', 'quantity' => '各 1 个', 'purpose' => '入户安全与来访提醒', 'location' => '入户门'],
                    ['name' => '智能窗帘电机', 'quantity' => '4 套', 'purpose' => '客厅、餐厅、主卧、次卧遮光帘电动化', 'location' => '客厅 / 餐厅 / 主卧 / 次卧'],
                    ['name' => '室内外摄像头', 'quantity' => '3 个', 'purpose' => '出入口与阳台安防监控', 'location' => '入户 / 客厅 / 阳台'],
                    ['name' => '燃气烟感联动套装', 'quantity' => '1 套', 'purpose' => '厨房燃气与烟雾报警自动关阀', 'location' => '厨房'],
                ],
                'excludes'        => '不含中央空调本机、不含影音设备本体（含联动改造）',
                'duration'        => '5-7 个工作日（不含水电改造）',
                'warranty'        => '整套质保 3 年',
                'seo_title'       => '三室两厅豪华款全屋智能套餐 - 示例装修有限公司',
                'seo_description' => '三室两厅豪华款全屋智能套餐，中控屏、多路摄像头、燃气联动一次做满，示例装修有限公司提供上门安装。',
                'seo_keywords'    => '全屋智能套餐,三室两厅,豪华款,中控屏,智能安防',
                'is_featured'     => false,
                'sort'            => 6,
                'published_at'    => now()->subDays(10),
            ],
        ];
    }
}
