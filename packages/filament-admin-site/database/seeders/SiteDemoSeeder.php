<?php

namespace LaravelStack\FilamentAdminSite\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use LaravelStack\FilamentAdminSite\Models\ContactMessage;
use LaravelStack\FilamentAdminSite\Models\SiteCase;
use LaravelStack\FilamentAdminSite\Models\SiteCaseCategory;
use LaravelStack\FilamentAdminSite\Models\SitePage;
use LaravelStack\FilamentAdminSite\Models\SiteProduct;
use LaravelStack\FilamentAdminSite\Models\SiteProductCategory;
use LaravelStack\FilamentAdminSite\Models\SiteSolution;
use LaravelStack\FilamentAdminSite\Models\SiteTag;

/**
 * 官网演示内容种子
 *
 * 植入湖北晴空妙享科技有限公司官网演示数据：
 * 装修案例/智能方案/产品/静态页面（D-10-18）。
 *
 * 图片：优先使用本地 storage/app/public/site/ 目录图片（D-11-11）；
 * 本地图片不存在时降级到 picsum.photos（开发/演示/离线环境不阻断播种）。
 * diskRelPath 形如 'site/cases/modern-3bed-smart.jpg'，相对 public disk（Pitfall 5）。
 * 禁止使用已关闭的 Unsplash source 接口（per RESEARCH Pitfall 7）。
 *
 * 幂等：首部检查 site_cases 是否已存在数据，避免重复播种。
 */
class SiteDemoSeeder extends Seeder
{
    /**
     * 执行演示数据播种
     */
    public function run(): void
    {
        // 幂等守卫：已有数据则跳过，防止重复播种
        if (SiteCase::query()->exists()) {
            return;
        }

        // 1. 创建装修案例分类
        $caseCategoryData = [
            ['name_zh' => '现代简约', 'name_en' => 'Modern Minimalist', 'slug' => 'modern-minimalist', 'sort' => 1],
            ['name_zh' => '智能家居全屋', 'name_en' => 'Full Smart Home', 'slug' => 'full-smart-home', 'sort' => 2],
            ['name_zh' => '局部改造', 'name_en' => 'Partial Renovation', 'slug' => 'partial-renovation', 'sort' => 3],
            ['name_zh' => '新房装修', 'name_en' => 'New Home Decoration', 'slug' => 'new-home', 'sort' => 4],
            ['name_zh' => '别墅豪装', 'name_en' => 'Luxury Villa', 'slug' => 'luxury-villa', 'sort' => 5],
        ];

        $caseCategories = collect($caseCategoryData)->map(
            fn (array $data) => SiteCaseCategory::firstOrCreate(['slug' => $data['slug']], $data)
        );

        // 2. 创建产品分类
        $productCategoryData = [
            ['name_zh' => '智能照明', 'name_en' => 'Smart Lighting', 'slug' => 'smart-lighting', 'sort' => 1],
            ['name_zh' => '智能安防', 'name_en' => 'Smart Security', 'slug' => 'smart-security', 'sort' => 2],
            ['name_zh' => '智能家电', 'name_en' => 'Smart Appliances', 'slug' => 'smart-appliances', 'sort' => 3],
        ];

        $productCategories = collect($productCategoryData)->map(
            fn (array $data) => SiteProductCategory::firstOrCreate(['slug' => $data['slug']], $data)
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
            fn (array $data) => SiteTag::firstOrCreate(['slug' => $data['slug']], $data)
        );

        // 4. 创建装修案例（6 个）
        // 图片：本地优先（site/cases/{slug}.jpg），降级 picsum.photos（D-11-11）
        $casesData = [
            [
                'title_zh'        => '现代简约三居室全屋智能改造',
                'title_en'        => 'Modern Minimalist 3-Bedroom Full Smart Renovation',
                'slug'            => 'modern-3bed-smart',
                'style'           => 'modern',
                'house_type'      => 'three_bedroom',
                'area'            => '120㎡',
                'budget_range'    => '25-35万',
                'smart_features'  => '全屋智能灯光控制、智能窗帘、中央空调联动、智能门锁',
                'description_zh'  => '武汉某小区三居室，业主追求简洁现代风格，同时集成全套智能家居系统。从方案设计到竣工验收，晴空妙享全程专业跟进。',
                'description_en'  => 'A three-bedroom apartment in Wuhan, integrating full smart home system with modern minimalist style. QingKong provided end-to-end professional service.',
                'seo_title'       => '现代简约三居室全屋智能改造案例 - 晴空妙享',
                'seo_description' => '晴空妙享为武汉业主打造现代简约智能家居，全屋智能灯光、窗帘、门锁一体控制，免费上门量房设计。',
                'seo_keywords'    => '智能家居,现代简约,全屋改造,武汉,晴空妙享',
                'category_id'     => $caseCategories->get(0)?->id,
                'is_featured'     => true,
                'sort'            => 1,
                'published_at'    => now()->subDays(10),
            ],
            [
                'title_zh'        => '别墅豪宅全屋定制智能系统',
                'title_en'        => 'Luxury Villa Full Custom Smart System',
                'slug'            => 'villa-full-smart',
                'style'           => 'modern',
                'house_type'      => 'villa',
                'area'            => '450㎡',
                'budget_range'    => '80-120万',
                'smart_features'  => '全屋影音系统、智能安防监控、地暖控制、电动窗帘、场景联动',
                'description_zh'  => '豪华别墅智能化全案设计，从基础建设到末端控制，打造一步到位的智能家居解决方案。公司名湖北晴空妙享科技有限公司承接此类高端项目。',
                'description_en'  => 'Comprehensive smart home design for luxury villa from Hubei QingKong Technology, from infrastructure to end control.',
                'seo_title'       => '别墅豪宅全屋定制智能系统案例 - 晴空妙享',
                'seo_description' => '晴空妙享为豪华别墅提供全屋定制智能系统，影音安防地暖一体控制，专业团队上门服务。',
                'seo_keywords'    => '别墅智能家居,全屋定制,影音系统,地暖,晴空妙享',
                'category_id'     => $caseCategories->get(1)?->id,
                'is_featured'     => true,
                'sort'            => 2,
                'published_at'    => now()->subDays(20),
            ],
            [
                'title_zh'        => '老房改造—智能灯光场景升级',
                'title_en'        => 'Old Apartment Smart Lighting Scene Upgrade',
                'slug'            => 'old-apt-lighting',
                'style'           => 'nordic',
                'house_type'      => 'two_bedroom',
                'area'            => '88㎡',
                'budget_range'    => '8-12万',
                'smart_features'  => '智能灯光场景控制、语音助手接入、手机远程控制',
                'description_zh'  => '老房局部改造项目，重点升级灯光控制系统，实现多场景一键切换。晴空妙享提供专业方案设计和施工安装。',
                'description_en'  => 'Old apartment partial renovation by QingKong focusing on smart lighting scene control with multi-mode switching.',
                'seo_title'       => '老房局部改造智能灯光升级案例 - 晴空妙享',
                'seo_description' => '老房智能改造，灯光场景控制，语音助手，手机远程操作，性价比之选，联系晴空妙享免费咨询。',
                'seo_keywords'    => '老房改造,智能灯光,语音控制,场景模式,武汉',
                'category_id'     => $caseCategories->get(2)?->id,
                'is_featured'     => false,
                'sort'            => 3,
                'published_at'    => now()->subDays(30),
            ],
            [
                'title_zh'        => '新房精装—智能安防一体化方案',
                'title_en'        => 'New Home Smart Security Integration',
                'slug'            => 'new-home-security',
                'style'           => 'modern',
                'house_type'      => 'three_bedroom',
                'area'            => '135㎡',
                'budget_range'    => '15-20万',
                'smart_features'  => '智能门锁、视频门铃、室内摄像头、烟雾/燃气报警联动',
                'description_zh'  => '新房装修同步集成智能安防系统，让家更安全可靠。晴空妙享智能安防产品自研，品质保障。',
                'description_en'  => 'New home decoration with integrated smart security system from QingKong for comprehensive home protection.',
                'seo_title'       => '新房精装智能安防一体化方案 - 晴空妙享',
                'seo_description' => '新房安装智能门锁视频门铃摄像头，烟雾报警联动，全方位保护家庭安全，晴空妙享专业安装。',
                'seo_keywords'    => '新房装修,智能安防,智能门锁,视频门铃,武汉',
                'category_id'     => $caseCategories->get(3)?->id,
                'is_featured'     => false,
                'sort'            => 4,
                'published_at'    => now()->subDays(15),
            ],
            [
                'title_zh'        => '复式楼层—中式智能雅居',
                'title_en'        => 'Duplex Chinese Style Smart Residence',
                'slug'            => 'duplex-chinese-smart',
                'style'           => 'chinese',
                'house_type'      => 'duplex',
                'area'            => '280㎡',
                'budget_range'    => '45-60万',
                'smart_features'  => '中控屏集中管理、电动升降桌、智能茶室系统、背景音乐',
                'description_zh'  => '融合中式美学与现代智能技术，打造独特的智能雅居体验。湖北晴空妙享科技有限公司，电话 027-88888888。',
                'description_en'  => 'Blending Chinese aesthetics with modern smart technology by Hubei QingKong Technology for unique smart living.',
                'seo_title'       => '复式楼层中式智能雅居案例 - 晴空妙享',
                'seo_description' => '中式风格复式楼层智能系统，中控屏集中管理，背景音乐，智能茶室，晴空妙享精工打造。',
                'seo_keywords'    => '复式智能家居,中式风格,背景音乐,智能茶室,武汉',
                'category_id'     => $caseCategories->get(4)?->id,
                'is_featured'     => true,
                'sort'            => 5,
                'published_at'    => now()->subDays(5),
            ],
            [
                'title_zh'        => '一居室—北欧风格智能小窝',
                'title_en'        => 'Studio Nordic Style Smart Nest',
                'slug'            => 'studio-nordic',
                'style'           => 'nordic',
                'house_type'      => 'one_bedroom',
                'area'            => '55㎡',
                'budget_range'    => '5-8万',
                'smart_features'  => '智能插座、空气质量监测、远程家电控制',
                'description_zh'  => '小户型也能享受智能生活，经济实惠的入门级智能家居方案。晴空妙享提供免费上门评估服务。',
                'description_en'  => 'Smart home solution for small apartments by QingKong, affordable and practical with free home assessment.',
                'seo_title'       => '一居室北欧风格智能家居案例 - 晴空妙享',
                'seo_description' => '小户型智能家居入门方案，智能插座空气监测，经济实惠，晴空妙享免费上门咨询。',
                'seo_keywords'    => '小户型,北欧风格,入门智能家居,智能插座,武汉',
                'category_id'     => $caseCategories->get(0)?->id,
                'is_featured'     => false,
                'sort'            => 6,
                'published_at'    => now()->subDays(7),
            ],
        ];

        foreach ($casesData as $index => $data) {
            $case = SiteCase::firstOrCreate(['slug' => $data['slug']], $data);

            // 封面图：本地图片优先，降级 picsum.photos（D-11-11）
            $this->addCoverImage($case, 'site/cases/' . $data['slug'] . '.jpg', $index + 1);

            // 关联标签
            $case->tags()->syncWithoutDetaching(
                $tags->random(min(2, $tags->count()))->pluck('id')->toArray()
            );
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

        foreach ($solutionsData as $index => $data) {
            $solution = SiteSolution::firstOrCreate(['slug' => $data['slug']], $data);

            // 封面图：本地图片优先，降级 picsum.photos（D-11-11）
            $this->addCoverImage($solution, 'site/solutions/' . $data['slug'] . '.jpg', $index + 1);

            $solution->tags()->syncWithoutDetaching(
                $tags->random(min(2, $tags->count()))->pluck('id')->toArray()
            );
        }

        // 6. 创建智能产品（8 个）
        $productsData = [
            ['title_zh' => '全彩智能灯带', 'title_en' => 'RGB Smart LED Strip', 'slug' => 'rgb-led-strip', 'price' => 299.00, 'brand' => '晴空妙享', 'category_id' => $productCategories->get(0)?->id, 'is_featured' => true, 'sort' => 1],
            ['title_zh' => '智能面板开关', 'title_en' => 'Smart Panel Switch', 'slug' => 'smart-panel-switch', 'price' => 399.00, 'brand' => '晴空妙享', 'category_id' => $productCategories->get(0)?->id, 'is_featured' => true, 'sort' => 2],
            ['title_zh' => '智能指纹门锁', 'title_en' => 'Smart Fingerprint Lock', 'slug' => 'smart-fingerprint-lock', 'price' => 1899.00, 'brand' => '晴空妙享', 'category_id' => $productCategories->get(1)?->id, 'is_featured' => true, 'sort' => 3],
            ['title_zh' => '视频可视门铃', 'title_en' => 'Video Doorbell', 'slug' => 'video-doorbell', 'price' => 699.00, 'brand' => '晴空妙享', 'category_id' => $productCategories->get(1)?->id, 'is_featured' => false, 'sort' => 4],
            ['title_zh' => '室内高清摄像头', 'title_en' => 'Indoor HD Camera', 'slug' => 'indoor-hd-camera', 'price' => 499.00, 'brand' => '晴空妙享', 'category_id' => $productCategories->get(1)?->id, 'is_featured' => false, 'sort' => 5],
            ['title_zh' => '智能中央空调控制器', 'title_en' => 'Smart AC Controller', 'slug' => 'smart-ac-controller', 'price' => 899.00, 'brand' => '晴空妙享', 'category_id' => $productCategories->get(2)?->id, 'is_featured' => false, 'sort' => 6],
            ['title_zh' => '全屋智能中控屏', 'title_en' => 'Smart Home Control Panel', 'slug' => 'smart-control-panel', 'price' => 3999.00, 'brand' => '晴空妙享', 'category_id' => $productCategories->get(2)?->id, 'is_featured' => true, 'sort' => 7],
            ['title_zh' => '智能电动窗帘电机', 'title_en' => 'Smart Curtain Motor', 'slug' => 'smart-curtain-motor', 'price' => 799.00, 'brand' => '晴空妙享', 'category_id' => $productCategories->get(2)?->id, 'is_featured' => false, 'sort' => 8],
        ];

        foreach ($productsData as $index => $data) {
            $data['description_zh']  = '晴空妙享旗舰产品，' . $data['title_zh'] . '，专为智能家居场景设计，品质保障，专业安装。';
            $data['description_en']  = 'QingKong flagship product ' . $data['title_en'] . ', designed for smart home scenarios with professional installation.';
            $data['seo_title']       = $data['title_zh'] . ' - 晴空妙享智能家居';
            $data['seo_description'] = $data['title_zh'] . '，晴空妙享官方出品，品质保障，武汉地区专业上门安装。';
            $data['seo_keywords']    = '智能家居,' . $data['title_zh'] . ',晴空妙享,武汉';
            $data['is_published']    = true;

            $product = SiteProduct::firstOrCreate(['slug' => $data['slug']], $data);

            // 封面图：本地图片优先，降级 picsum.photos（D-11-11）
            $this->addCoverImage($product, 'site/products/' . $data['slug'] . '.jpg', $index + 1);
        }

        // 7. 创建静态页面（3 个：about/contact/services）
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
                'is_published'    => true,
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
                'is_published'    => true,
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
                'is_published'    => true,
            ],
        ];

        foreach ($pagesData as $data) {
            SitePage::firstOrCreate(['slug' => $data['slug']], $data);
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
     * 添加封面图：本地 storage 优先，降级 picsum.photos（D-11-11）
     *
     * 先检查媒体集合幂等守卫，再尝试本地图片；
     * 本地图片不存在时降级为 picsum.photos；
     * 任何异常静默处理，不阻断 Seeder 执行（适用离线环境）。
     *
     * @param  mixed  $model       目标模型（InteractsWithMedia）
     * @param  string $diskRelPath 相对 public disk 的路径，如 'site/cases/modern-3bed-smart.jpg'
     * @param  int    $fallbackSeed picsum.photos seed 序号（降级用）
     * @param  string $collection  媒体集合名称（默认 'cover'）
     */
    protected function addCoverImage(
        mixed $model,
        string $diskRelPath,
        int $fallbackSeed,
        string $collection = 'cover'
    ): void {
        // 幂等守卫：已有图片则跳过
        if ($model->getMedia($collection)->isNotEmpty()) {
            return;
        }

        try {
            // 优先使用本地图片（生产/晴空独立项目环境）
            $fullPath = storage_path('app/public/' . $diskRelPath);

            if (file_exists($fullPath)) {
                // diskRelPath 必须相对 public disk，禁绝对路径（Pitfall 5）
                $model->addMediaFromDisk($diskRelPath, 'public')
                      ->toMediaCollection($collection);

                return;
            }

            // 降级：使用 picsum.photos 占位（开发/演示/离线环境）
            $model->addMediaFromUrl("https://picsum.photos/seed/qkznj{$fallbackSeed}/800/600")
                  ->toMediaCollection($collection);
        } catch (\Throwable) {
            // 离线环境或图片不可达时静默跳过，不阻断播种
        }
    }
}
