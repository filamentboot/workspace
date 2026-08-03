<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders;

use Filamentboot\FilamentbootSite\Database\Seeders\Concerns\SeedsBySlug;
use Filamentboot\FilamentbootSite\Database\Seeders\Concerns\SeedsMediaImages;
use Filamentboot\FilamentbootSite\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Models\SiteCase;
use Filamentboot\FilamentbootSite\Models\SiteCaseCategory;
use Filamentboot\FilamentbootSite\Models\SitePage;
use Filamentboot\FilamentbootSite\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Models\SiteProductCategory;
use Filamentboot\FilamentbootSite\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Models\SiteTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 官网演示内容种子
 *
 * 植入湖北晴空妙享科技有限公司官网演示数据：
 * 装修案例/智能方案/产品/静态页面（D-10-18）。
 *
 * 图片：仅使用本地 storage/app/public/site/ 目录图片（D-11-11）；
 * 图片不存在时不写入任何媒体，由前台 image-placeholder 组件渲染空态。
 * 播种数据禁止引入 picsum.photos 等外部图片服务。
 * diskRelPath 形如 'site/cases/modern-3bed-smart.jpg'，相对 public disk（Pitfall 5）。
 * 禁止使用已关闭的 Unsplash source 接口（per RESEARCH Pitfall 7）。
 *
 * 文案来源：docs/cms/jd-assets/ 的竞品结构调研 + reviews-insight.json 的真实关注维度，
 * 一律改写不照搬（理由见 productsData() 注释）。资讯内容在 SiteNewsSeeder。
 *
 * 幂等：可反复执行，按 slug 增量补种——已有的记录一概不动（用户改过的文案
 * 不会被覆盖），缺的补上。软删除过的记录不复活，见 Concerns\SeedsBySlug。
 *
 * 封面图每次都会重新尝试挂载：图片是后补的，放进 storage/app/public/site/
 * 之后重跑一遍种子即可挂上，已有媒体的记录会被跳过。
 */
class SiteDemoSeeder extends Seeder
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
            ['name_zh' => '现代简约', 'name_en' => 'Modern Minimalist', 'slug' => 'modern-minimalist', 'sort' => 1],
            ['name_zh' => '智能家居全屋', 'name_en' => 'Full Smart Home', 'slug' => 'full-smart-home', 'sort' => 2],
            ['name_zh' => '局部改造', 'name_en' => 'Partial Renovation', 'slug' => 'partial-renovation', 'sort' => 3],
            ['name_zh' => '新房装修', 'name_en' => 'New Home Decoration', 'slug' => 'new-home', 'sort' => 4],
            ['name_zh' => '别墅豪装', 'name_en' => 'Luxury Villa', 'slug' => 'luxury-villa', 'sort' => 5],
        ];

        $caseCategories = collect($caseCategoryData)->map(
            fn (array $data) => $this->firstOrCreateBySlug(SiteCaseCategory::class, $data)
        );

        // 2. 创建产品分类
        $productCategoryData = [
            ['name_zh' => '智能照明', 'name_en' => 'Smart Lighting', 'slug' => 'smart-lighting', 'sort' => 1],
            ['name_zh' => '智能安防', 'name_en' => 'Smart Security', 'slug' => 'smart-security', 'sort' => 2],
            ['name_zh' => '智能家电', 'name_en' => 'Smart Appliances', 'slug' => 'smart-appliances', 'sort' => 3],
        ];

        $productCategories = collect($productCategoryData)->map(
            fn (array $data) => $this->firstOrCreateBySlug(SiteProductCategory::class, $data)
        );

        // 3. 创建标签
        $tagData = [
            ['name_zh' => '智能家居', 'name_en' => 'Smart Home', 'slug' => 'smart-home'],
            ['name_zh' => '全屋定制', 'name_en' => 'Full Custom', 'slug' => 'full-custom'],
            ['name_zh' => '节能环保', 'name_en' => 'Eco Friendly', 'slug' => 'eco-friendly'],
            ['name_zh' => '豪华精装', 'name_en' => 'Luxury Finish', 'slug' => 'luxury-finish'],
            ['name_zh' => '性价比', 'name_en' => 'Value', 'slug' => 'value'],
        ];

        $tags = collect($tagData)->map(
            fn (array $data) => $this->firstOrCreateBySlug(SiteTag::class, $data)
        );

        // 4. 创建装修案例（6 个）
        // 图片：仅取本地 site/cases/{slug}.jpg，缺失时不写媒体（D-11-11）
        $casesData = [
            [
                'title_zh'       => '现代简约三居室全屋智能改造',
                'title_en'       => 'Modern Minimalist 3-Bedroom Full Smart Renovation',
                'slug'           => 'modern-3bed-smart',
                'style'          => 'modern',
                'house_type'     => 'three_bedroom',
                'area'           => '120㎡',
                'budget_range'   => '25-35万',
                'smart_features' => '全屋智能灯光控制、智能窗帘、中央空调联动、智能门锁',
                'description_zh' => '武汉某小区三居室，业主追求简洁现代风格，同时集成全套智能家居系统。从方案设计到竣工验收，晴空妙享全程专业跟进。',
                'description_en' => 'A three-bedroom apartment in Wuhan, integrating full smart home system with modern minimalist style. QingKong provided end-to-end professional service.',
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
                'seo_title'       => '现代简约三居室全屋智能改造案例 - 晴空妙享',
                'seo_description' => '晴空妙享为武汉业主打造现代简约智能家居，全屋智能灯光、窗帘、门锁一体控制，免费上门量房设计。',
                'seo_keywords'    => '智能家居,现代简约,全屋改造,武汉,晴空妙享',
                'category_id'     => $caseCategories->get(0)?->id,
                'is_featured'     => true,
                'sort'            => 1,
                'published_at'    => now()->subDays(10),
            ],
            [
                'title_zh'       => '别墅豪宅全屋定制智能系统',
                'title_en'       => 'Luxury Villa Full Custom Smart System',
                'slug'           => 'villa-full-smart',
                'style'          => 'modern',
                'house_type'     => 'villa',
                'area'           => '450㎡',
                'budget_range'   => '80-120万',
                'smart_features' => '全屋影音系统、智能安防监控、地暖控制、电动窗帘、场景联动',
                'description_zh' => '豪华别墅智能化全案设计，从基础建设到末端控制，打造一步到位的智能家居解决方案。公司名湖北晴空妙享科技有限公司承接此类高端项目。',
                'description_en' => 'Comprehensive smart home design for luxury villa from Hubei QingKong Technology, from infrastructure to end control.',
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
                'seo_title'       => '别墅豪宅全屋定制智能系统案例 - 晴空妙享',
                'seo_description' => '晴空妙享为豪华别墅提供全屋定制智能系统，影音安防地暖一体控制，专业团队上门服务。',
                'seo_keywords'    => '别墅智能家居,全屋定制,影音系统,地暖,晴空妙享',
                'category_id'     => $caseCategories->get(1)?->id,
                'is_featured'     => true,
                'sort'            => 2,
                'published_at'    => now()->subDays(20),
            ],
            [
                'title_zh'       => '老房改造—智能灯光场景升级',
                'title_en'       => 'Old Apartment Smart Lighting Scene Upgrade',
                'slug'           => 'old-apt-lighting',
                'style'          => 'nordic',
                'house_type'     => 'two_bedroom',
                'area'           => '88㎡',
                'budget_range'   => '8-12万',
                'smart_features' => '智能灯光场景控制、语音助手接入、手机远程控制',
                'description_zh' => '老房局部改造项目，重点升级灯光控制系统，实现多场景一键切换。晴空妙享提供专业方案设计和施工安装。',
                'description_en' => 'Old apartment partial renovation by QingKong focusing on smart lighting scene control with multi-mode switching.',
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
                'seo_title'       => '老房局部改造智能灯光升级案例 - 晴空妙享',
                'seo_description' => '老房智能改造，灯光场景控制，语音助手，手机远程操作，性价比之选，联系晴空妙享免费咨询。',
                'seo_keywords'    => '老房改造,智能灯光,语音控制,场景模式,武汉',
                'category_id'     => $caseCategories->get(2)?->id,
                'is_featured'     => false,
                'sort'            => 3,
                'published_at'    => now()->subDays(30),
            ],
            [
                'title_zh'       => '新房精装—智能安防一体化方案',
                'title_en'       => 'New Home Smart Security Integration',
                'slug'           => 'new-home-security',
                'style'          => 'modern',
                'house_type'     => 'three_bedroom',
                'area'           => '135㎡',
                'budget_range'   => '15-20万',
                'smart_features' => '智能门锁、视频门铃、室内摄像头、烟雾/燃气报警联动',
                'description_zh' => '新房装修同步集成智能安防系统，让家更安全可靠。晴空妙享智能安防产品自研，品质保障。',
                'description_en' => 'New home decoration with integrated smart security system from QingKong for comprehensive home protection.',
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
                'seo_title'       => '新房精装智能安防一体化方案 - 晴空妙享',
                'seo_description' => '新房安装智能门锁视频门铃摄像头，烟雾报警联动，全方位保护家庭安全，晴空妙享专业安装。',
                'seo_keywords'    => '新房装修,智能安防,智能门锁,视频门铃,武汉',
                'category_id'     => $caseCategories->get(3)?->id,
                'is_featured'     => false,
                'sort'            => 4,
                'published_at'    => now()->subDays(15),
            ],
            [
                'title_zh'       => '复式楼层—中式智能雅居',
                'title_en'       => 'Duplex Chinese Style Smart Residence',
                'slug'           => 'duplex-chinese-smart',
                'style'          => 'chinese',
                'house_type'     => 'duplex',
                'area'           => '280㎡',
                'budget_range'   => '45-60万',
                'smart_features' => '中控屏集中管理、电动升降桌、智能茶室系统、背景音乐',
                'description_zh' => '融合中式美学与现代智能技术，打造独特的智能雅居体验。湖北晴空妙享科技有限公司，电话 027-88888888。',
                'description_en' => 'Blending Chinese aesthetics with modern smart technology by Hubei QingKong Technology for unique smart living.',
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
                'seo_title'       => '复式楼层中式智能雅居案例 - 晴空妙享',
                'seo_description' => '中式风格复式楼层智能系统，中控屏集中管理，背景音乐，智能茶室，晴空妙享精工打造。',
                'seo_keywords'    => '复式智能家居,中式风格,背景音乐,智能茶室,武汉',
                'category_id'     => $caseCategories->get(4)?->id,
                'is_featured'     => true,
                'sort'            => 5,
                'published_at'    => now()->subDays(5),
            ],
            [
                'title_zh'       => '一居室—北欧风格智能小窝',
                'title_en'       => 'Studio Nordic Style Smart Nest',
                'slug'           => 'studio-nordic',
                'style'          => 'nordic',
                'house_type'     => 'one_bedroom',
                'area'           => '55㎡',
                'budget_range'   => '5-8万',
                'smart_features' => '智能插座、空气质量监测、远程家电控制',
                'description_zh' => '小户型也能享受智能生活，经济实惠的入门级智能家居方案。晴空妙享提供免费上门评估服务。',
                'description_en' => 'Smart home solution for small apartments by QingKong, affordable and practical with free home assessment.',
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
                'seo_title'       => '一居室北欧风格智能家居案例 - 晴空妙享',
                'seo_description' => '小户型智能家居入门方案，智能插座空气监测，经济实惠，晴空妙享免费上门咨询。',
                'seo_keywords'    => '小户型,北欧风格,入门智能家居,智能插座,武汉',
                'category_id'     => $caseCategories->get(0)?->id,
                'is_featured'     => false,
                'sort'            => 6,
                'published_at'    => now()->subDays(7),
            ],
        ];

        foreach ($casesData as $data) {
            $case = $this->firstOrCreateBySlug(SiteCase::class, $data);

            // 封面图：仅取本地图片，无图时由前台占位组件兜底（D-11-11）
            $this->addCoverImage($case, 'site/cases/'.$data['slug'].'.jpg');

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
                'title_en'        => 'Full Smart Home Integration Solution',
                'slug'            => 'full-smart-solution',
                'description_zh'  => '从规划到落地的全屋智能家居整体解决方案，覆盖照明、安防、影音、暖通全场景，湖北晴空妙享科技有限公司专业团队全程把控。',
                'description_en'  => 'End-to-end smart home solution by Hubei QingKong Technology covering lighting, security, audio-visual, and HVAC.',
                'content_zh'      => '<p>全屋智能家居方案包含以下核心模块：</p><ul><li>智能照明系统：多场景一键切换</li><li>安防监控系统：24小时守护</li><li>影音娱乐系统：沉浸式体验</li><li>暖通空调联动：舒适恒温</li></ul><p>联系晴空妙享，预约免费上门量房：027-88888888</p>',
                'content_en'      => '<p>Our full smart home solution includes: Smart Lighting (multi-scene), Security System (24/7), Audio-Visual (immersive), HVAC Control (comfort).</p>',
                'price_range'     => '20-100万',
                'seo_title'       => '全屋智能家居一体化方案 - 晴空妙享',
                'seo_description' => '晴空妙享全屋智能家居一体化方案，照明安防影音暖通全覆盖，专业设计施工，武汉地区上门服务。',
                'seo_keywords'    => '全屋智能,智能家居方案,一体化,照明安防,武汉',
                'is_featured'     => true,
                'sort'            => 1,
                'published_at'    => now()->subDays(30),
            ],
            [
                'title_zh'        => '智能灯光场景定制方案',
                'title_en'        => 'Smart Lighting Scene Custom Solution',
                'slug'            => 'smart-lighting-solution',
                'description_zh'  => '根据不同空间和使用场景，定制多模式智能灯光控制方案，一键切换家居氛围，晴空妙享专业定制。',
                'description_en'  => 'Custom smart lighting solutions with multi-scene control for different spaces by QingKong.',
                'content_zh'      => '<p>我们的灯光方案涵盖：起居场景、工作场景、就寝场景、影院模式等多种预设方案。</p><p>支持手机 App 远程控制及语音助手接入。</p>',
                'content_en'      => '<p>Our lighting solutions include: living mode, work mode, sleep mode, cinema mode and more. Supports app control and voice assistant.</p>',
                'price_range'     => '3-15万',
                'seo_title'       => '智能灯光场景定制方案 - 晴空妙享',
                'seo_description' => '晴空妙享智能灯光场景定制，多模式预设，一键切换家居氛围，节能环保，武汉专业上门安装。',
                'seo_keywords'    => '智能灯光,场景控制,调光,节能,武汉',
                'is_featured'     => true,
                'sort'            => 2,
                'published_at'    => now()->subDays(20),
            ],
            [
                'title_zh'        => '家庭安防全覆盖方案',
                'title_en'        => 'Full Home Security Solution',
                'slug'            => 'home-security-solution',
                'description_zh'  => '360度无死角安防监控方案，智能门锁、视频门铃、摄像头、报警系统一体联动。晴空妙享安防产品，品质可靠。',
                'description_en'  => '360-degree home security with smart locks, video doorbell, cameras, and alarm system from QingKong.',
                'content_zh'      => '<p>安防方案核心组件：智能门锁+视频门铃+室内摄像头+户外摄像头+烟感/燃气报警器。</p><p>联动控制：门铃响起自动推送手机通知，烟感报警联动拨打预设电话。</p>',
                'content_en'      => '<p>Core components: Smart lock, Video doorbell, Indoor camera, Outdoor camera, Smoke/gas detector. All interconnected for real-time alerts.</p>',
                'price_range'     => '1-8万',
                'seo_title'       => '家庭安防全覆盖方案 - 晴空妙享',
                'seo_description' => '家庭安防全方位覆盖，智能门锁视频门铃摄像头报警联动，保护家人安全，晴空妙享专业安装。',
                'seo_keywords'    => '家庭安防,智能门锁,摄像头,报警系统,武汉',
                'is_featured'     => false,
                'sort'            => 3,
                'published_at'    => now()->subDays(15),
            ],
            [
                'title_zh'        => '影音娱乐沉浸体验方案',
                'title_en'        => 'Immersive Audio-Visual Entertainment Solution',
                'slug'            => 'av-entertainment-solution',
                'description_zh'  => '打造家庭影院级别的沉浸式影音体验，4K投影、环绕音响、智能遮光帘联动控制，晴空妙享影音专项服务。',
                'description_en'  => 'Create cinema-level immersive experience at home with 4K projector, surround sound, smart curtains from QingKong.',
                'content_zh'      => '<p>影音方案包含：高清投影或大尺寸电视、环绕立体声音响系统、电动遮光帘、场景化智能联动。</p><p>影院模式：一键拉帘降光，环境音效自动激活。</p>',
                'content_en'      => '<p>AV solution includes: 4K projection/TV, surround sound, motorized curtains, smart scene automation. Cinema mode: one-tap curtains, lights, and audio.</p>',
                'price_range'     => '5-30万',
                'seo_title'       => '家庭影音娱乐沉浸体验方案 - 晴空妙享',
                'seo_description' => '家庭影院沉浸影音体验，4K投影环绕音响电动遮光帘智能联动，晴空妙享专业影音安装。',
                'seo_keywords'    => '家庭影院,智能影音,4K投影,环绕音响,武汉',
                'is_featured'     => false,
                'sort'            => 4,
                'published_at'    => now()->subDays(25),
            ],
        ];

        foreach ($solutionsData as $data) {
            $solution = $this->firstOrCreateBySlug(SiteSolution::class, $data);

            // 封面图：仅取本地图片，无图时由前台占位组件兜底（D-11-11）
            $this->addCoverImage($solution, 'site/solutions/'.$data['slug'].'.jpg');

            // 同案例：随机标签只在新建时挂，否则重跑会越挂越多
            if ($solution->wasRecentlyCreated) {
                $solution->tags()->syncWithoutDetaching(
                    $tags->random(min(2, $tags->count()))->pluck('id')->toArray()
                );
            }
        }

        // 6. 创建智能产品（18 个，覆盖三个分类）
        foreach ($this->productsData($productCategories) as $data) {
            $data['brand']           = '晴空妙享';
            $data['is_published']    = true;
            $data['seo_title']       = $data['title_zh'].' - 晴空妙享智能家居';
            $data['seo_description'] = $data['description_zh'];

            $product = $this->firstOrCreateBySlug(SiteProduct::class, $data);

            // 封面图：仅取本地图片，无图时由前台占位组件兜底（D-11-11）
            $this->addCoverImage($product, 'site/products/'.$data['slug'].'.jpg');

            // 图集：site/products/{slug}/gallery-NN.jpg，UI 出图后原样落盘即可被拾取
            foreach (glob(storage_path('app/public/site/products/'.$data['slug'].'/gallery-*.jpg')) ?: [] as $file) {
                $this->addCoverImage(
                    $product,
                    'site/products/'.$data['slug'].'/'.basename($file),
                    'gallery'
                );
            }
        }

        // 7. 创建静态页面（4 个：about/contact/services/faq）
        $pagesData = [
            [
                'title_zh'        => '关于晴空妙享',
                'title_en'        => 'About QingKong',
                'slug'            => 'about',
                'content_zh'      => '<p>湖北晴空妙享科技有限公司，成立于武汉，专注智能家居方案设计与落地实施，致力于让每一个家庭都能享受科技带来的便利与品质生活。</p><p>我们服务覆盖：全屋智能系统设计、产品选购、专业施工、竣工验收、售后维保。</p><p>公司地址：湖北省武汉市，预约咨询电话：027-88888888。</p>',
                'content_en'      => '<p>Hubei QingKong Technology Co., Ltd., based in Wuhan, focuses on smart home solution design and implementation, making smart living accessible to every family.</p><p>Our services: Full smart home design, product selection, professional installation, and after-sales support.</p><p>Address: Wuhan, Hubei Province. Consultation: 027-88888888</p>',
                'seo_title'       => '关于晴空妙享智能家居 - 湖北晴空妙享科技有限公司',
                'seo_description' => '湖北晴空妙享科技有限公司，武汉智能家居专业服务商，专注方案设计与落地，免费上门量房咨询。',
                'seo_keywords'    => '晴空妙享,湖北智能家居,武汉智能家居,关于我们',
                'sort'            => 1,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
            [
                'title_zh'        => '联系我们',
                'title_en'        => 'Contact Us',
                'slug'            => 'contact',
                'content_zh'      => '<p>欢迎致电或在线留言，我们将在 24 小时内回复您的咨询。</p><p><strong>公司名称：</strong>湖北晴空妙享科技有限公司</p><p><strong>咨询热线：</strong>027-88888888</p><p><strong>服务地区：</strong>武汉及周边城市</p><p><strong>工作时间：</strong>周一至周六 9:00-18:00</p>',
                'content_en'      => '<p>Welcome to call or leave a message. We will reply within 24 hours.</p><p><strong>Company:</strong> Hubei QingKong Technology Co., Ltd.</p><p><strong>Phone:</strong> 027-88888888</p><p><strong>Service Area:</strong> Wuhan and surrounding cities</p>',
                'seo_title'       => '联系晴空妙享智能家居 - 预约免费设计咨询',
                'seo_description' => '联系晴空妙享，预约免费上门量房和智能家居方案设计咨询，027-88888888，武汉专业智能家居服务。',
                'seo_keywords'    => '联系我们,预约咨询,智能家居设计,027-88888888,武汉',
                'sort'            => 2,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
            [
                'title_zh'        => '我们的服务',
                'title_en'        => 'Our Services',
                'slug'            => 'services',
                'content_zh'      => '<p>晴空妙享提供从方案设计、产品选购、专业施工到售后维保的一站式智能家居服务。</p><ul><li>智能家居系统规划与设计（免费上门量房）</li><li>智能灯光控制系统</li><li>智能安防监控系统</li><li>全屋影音娱乐系统</li><li>暖通空调智能联动</li><li>竣工验收与使用培训</li><li>7×12 小时售后服务</li></ul>',
                'content_en'      => '<p>QingKong provides one-stop smart home services including design, product selection, installation, and after-sales support.</p><ul><li>Smart home system planning & design (free home assessment)</li><li>Smart lighting control</li><li>Smart security & surveillance</li><li>Home audio-visual systems</li><li>HVAC smart integration</li><li>Post-installation training</li><li>7×12h after-sales support</li></ul>',
                'seo_title'       => '晴空妙享服务项目 - 智能家居一站式解决方案',
                'seo_description' => '晴空妙享一站式智能家居服务：方案设计、产品选购、专业施工、售后维保，武汉地区免费上门咨询。',
                'seo_keywords'    => '智能家居服务,方案设计,施工安装,售后,武汉',
                'sort'            => 3,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
            [
                // 问题清单取自 docs/cms/jd-assets/reviews-insight.json 聚合出的真实购前疑虑，
                // 不是拍脑袋拟的「常见问题」。「镇上的能不能做」这类问句照原样保留，
                // 因为用户就是这么搜的。
                'title_zh'   => '常见问题',
                'title_en'   => 'FAQ',
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
                'seo_title'       => '常见问题 - 晴空妙享智能家居',
                'seo_description' => '智能家居常见问题：什么时候介入装修、周边县镇能否上门、断网能不能用、摄像头不买存储卡能否回看、质保与报修流程。',
                'seo_keywords'    => '常见问题,智能家居FAQ,上门安装,服务范围,质保',
                'sort'            => 4,
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
    }

    /**
     * 产品数据（18 条，分摊到智能照明 / 智能安防 / 智能家电三个分类）
     *
     * 文案取自 docs/cms/jd-assets/ 的调研产物，但一律改写而非照搬：
     * 卖点段落对着 reviews-insight.json 里的真实关注维度写（「亮度很充足」
     * 「运行超安静」「指纹超灵敏」「暗处也能用」），安装段落回答真实购前疑虑
     * （「安装服务方便吗」「一定要插电用吗」「4 米多买一个能行吗」）。
     *
     * 刻意不写的：备案型号、认证型号、原品牌型号。那些是真实产品的注册标识，
     * 挪到自有品牌页面上等于伪造认证信息；型号统一用自拟的 QK- 前缀。
     *
     * 只填中文正文：站点是中文单语言（见包元数据），content_en 没有渲染路径，
     * 填了就是永远不会被读到的死数据。
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
                'title_zh'       => '客厅智能吸顶灯 Pro',
                'title_en'       => 'Smart Ceiling Light Pro',
                'slug'           => 'qk-ceiling-light-pro',
                'price'          => 1099.00,
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
                    <li>型号 QK-CL130，额定功率 130W</li>
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
                'title_en'       => 'Smart Scene Panel Switch',
                'slug'           => 'smart-panel-switch',
                'price'          => 189.00,
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
                    <li>型号 QK-SW01，1 / 2 / 3 / 4 键可选</li>
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
                'title_en'       => 'Magnetic Track Linear Light',
                'slug'           => 'linear-magnetic-light',
                'price'          => 599.00,
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
                    <li>型号 QK-LT48，嵌入式轨道，可现场裁切</li>
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
                'title_en'       => 'Anti-Glare Smart Downlight',
                'slug'           => 'smart-downlight',
                'price'          => 129.00,
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
                    <li>型号 QK-DL07，功率 7W / 光通量 800lm</li>
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
                'title_en'       => 'RGB+CCT Smart LED Strip',
                'slug'           => 'rgb-led-strip',
                'price'          => 199.00,
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
                    <li>型号 QK-LS96，电压 24V，功率 14W/m</li>
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
                'title_en'       => 'Smart Lock with Peephole Camera',
                'slug'           => 'smart-fingerprint-lock',
                'price'          => 2299.00,
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
                    <li>型号 QK-DL60，适配门厚 40-120mm</li>
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
                'title_en'       => 'Battery Video Doorbell',
                'slug'           => 'video-doorbell',
                'price'          => 349.00,
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
                    <li>型号 QK-DB20，分辨率 2K</li>
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
                'title_zh'       => '室内云台摄像头 2K',
                'title_en'       => '2K Indoor Pan-Tilt Camera',
                'slug'           => 'indoor-hd-camera',
                'price'          => 239.00,
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
                    <li>型号 QK-IC21，分辨率 2K</li>
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
                'title_en'       => 'Outdoor Full-Colour Zoom Camera',
                'slug'           => 'outdoor-ptz-camera',
                'price'          => 579.00,
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
                    <li>型号 QK-OC30，分辨率 4MP</li>
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
                'title_en'       => 'mmWave Presence Sensor',
                'slug'           => 'presence-sensor',
                'price'          => 159.00,
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
                    <li>型号 QK-PS24，工作频段 24GHz</li>
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
                'title_en'       => 'Gas and Smoke Alarm Kit',
                'slug'           => 'gas-smoke-alarm',
                'price'          => 259.00,
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
                    <li>型号 QK-GS02，含燃气探头、烟感探头、阀门机械手</li>
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
                'title_en'       => 'Smart Home Control Panel',
                'slug'           => 'smart-control-panel',
                'price'          => 1999.00,
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
                    <li>型号 QK-CP60，屏幕 6 英寸 IPS 触控</li>
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
                'title_en'       => 'Multi-Protocol Smart Gateway',
                'slug'           => 'multimode-gateway',
                'price'          => 249.00,
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
                    <li>型号 QK-GW02</li>
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
                'title_en'       => 'Smart Curtain Motor',
                'slug'           => 'smart-curtain-motor',
                'price'          => 699.00,
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
                    <li>型号 QK-CM30，额定拉力 30N</li>
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
                'title_en'       => 'Smart Central AC Controller',
                'slug'           => 'smart-ac-controller',
                'price'          => 899.00,
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
                    <li>型号 QK-AC01，面板 86 型</li>
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
                'title_en'       => 'Universal IR Remote Hub',
                'slug'           => 'ir-remote-hub',
                'price'          => 89.00,
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
                    <li>型号 QK-IR02，发射角度 360°，有效半径约 8m</li>
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
                'title_en'       => 'Metering Smart Socket',
                'slug'           => 'metering-socket',
                'price'          => 69.00,
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
                    <li>型号 QK-SP16，额定 250V / 16A，最大 3500W</li>
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
                'title_en'       => 'AC Companion for Split Units',
                'slug'           => 'ac-companion',
                'price'          => 149.00,
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
                    <li>型号 QK-ACC16，额定 250V / 16A</li>
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
}
