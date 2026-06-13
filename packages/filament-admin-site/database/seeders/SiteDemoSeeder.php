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
 * 图片：使用 picsum.photos seed 固定 URL（开发/演示用，生产替换真实图）。
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

        // 4. 创建装修案例（约 6 个）
        // 图片使用 picsum.photos seed 固定 URL（开发/演示用，生产替换真实图，per D-10-18）
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
                'description_zh'  => '武汉某小区三居室，业主追求简洁现代风格，同时集成全套智能家居系统。',
                'description_en'  => 'A three-bedroom apartment in Wuhan, integrating full smart home system with modern minimalist style.',
                'seo_title'       => '现代简约三居室全屋智能改造案例 - 晴空妙享',
                'seo_description' => '晴空妙享为武汉业主打造现代简约智能家居，全屋智能灯光、窗帘、门锁一体控制。',
                'seo_keywords'    => '智能家居,现代简约,全屋改造,武汉',
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
                'description_zh'  => '豪华别墅智能化全案设计，从基础建设到末端控制，一步到位的智能家居解决方案。',
                'description_en'  => 'Comprehensive smart home design for luxury villa, from infrastructure to end control.',
                'seo_title'       => '别墅豪宅全屋定制智能系统案例 - 晴空妙享',
                'seo_description' => '晴空妙享为豪华别墅提供全屋定制智能系统，影音安防地暖一体控制。',
                'seo_keywords'    => '别墅智能家居,全屋定制,影音系统,地暖',
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
                'description_zh'  => '老房局部改造项目，重点升级灯光控制系统，实现多场景一键切换。',
                'description_en'  => 'Old apartment partial renovation focusing on smart lighting scene control.',
                'seo_title'       => '老房局部改造智能灯光升级案例 - 晴空妙享',
                'seo_description' => '老房智能改造，灯光场景控制，语音助手，手机远程操作，性价比之选。',
                'seo_keywords'    => '老房改造,智能灯光,语音控制,场景模式',
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
                'description_zh'  => '新房装修同步集成智能安防系统，让家更安全可靠。',
                'description_en'  => 'New home decoration with integrated smart security system for peace of mind.',
                'seo_title'       => '新房精装智能安防一体化方案 - 晴空妙享',
                'seo_description' => '新房安装智能门锁视频门铃摄像头，烟雾报警联动，全方位保护家庭安全。',
                'seo_keywords'    => '新房装修,智能安防,智能门锁,视频门铃',
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
                'description_zh'  => '融合中式美学与现代智能技术，打造独特的智能雅居体验。',
                'description_en'  => 'Blending Chinese aesthetics with modern smart technology for unique smart living.',
                'seo_title'       => '复式楼层中式智能雅居案例 - 晴空妙享',
                'seo_description' => '中式风格复式楼层智能系统，中控屏集中管理，背景音乐，智能茶室。',
                'seo_keywords'    => '复式智能家居,中式风格,背景音乐,智能茶室',
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
                'description_zh'  => '小户型也能享受智能生活，经济实惠的入门级智能家居方案。',
                'description_en'  => 'Smart home solution for small apartments, affordable and practical.',
                'seo_title'       => '一居室北欧风格智能家居案例 - 晴空妙享',
                'seo_description' => '小户型智能家居入门方案，智能插座空气监测，经济实惠。',
                'seo_keywords'    => '小户型,北欧风格,入门智能家居,智能插座',
                'category_id'     => $caseCategories->get(0)?->id,
                'is_featured'     => false,
                'sort'            => 6,
                'published_at'    => now()->subDays(7),
            ],
        ];

        foreach ($casesData as $index => $data) {
            $case = SiteCase::firstOrCreate(['slug' => $data['slug']], $data);

            // cover_image：使用 picsum.photos seed 固定 URL（开发/演示用）
            // 生产替换真实图片（D-10-18），此处仅演示占位
            // addMediaFromUrl 在离线环境可能失败，用 try/catch 包裹不阻断 seeding
            try {
                if ($case->getMedia('cover')->isEmpty()) {
                    $case->addMediaFromUrl('https://picsum.photos/seed/smarthome' . ($index + 1) . '/800/600')
                        ->toMediaCollection('cover');
                }
            } catch (\Throwable) {
                // 离线环境或 picsum.photos 不可达时静默跳过，不阻断播种
            }

            // 关联标签
            $case->tags()->syncWithoutDetaching(
                $tags->random(min(2, $tags->count()))->pluck('id')->toArray()
            );
        }

        // 5. 创建智能方案（约 4 个）
        $solutionsData = [
            [
                'title_zh'        => '全屋智能家居一体化方案',
                'title_en'        => 'Full Smart Home Integration Solution',
                'slug'            => 'full-smart-solution',
                'description_zh'  => '从规划到落地的全屋智能家居整体解决方案，覆盖照明、安防、影音、暖通全场景。',
                'description_en'  => 'End-to-end smart home solution covering lighting, security, audio-visual, and HVAC.',
                'content_zh'      => '<p>全屋智能家居方案包含以下核心模块：</p><ul><li>智能照明系统</li><li>安防监控系统</li><li>影音娱乐系统</li><li>暖通空调联动</li></ul>',
                'content_en'      => '<p>Our full smart home solution includes: Smart Lighting, Security System, Audio-Visual, HVAC Control.</p>',
                'price_range'     => '20-100万',
                'seo_title'       => '全屋智能家居一体化方案 - 晴空妙享',
                'seo_description' => '晴空妙享全屋智能家居一体化方案，照明安防影音暖通全覆盖，专业设计施工。',
                'seo_keywords'    => '全屋智能,智能家居方案,一体化,照明安防',
                'is_featured'     => true,
                'sort'            => 1,
                'published_at'    => now()->subDays(30),
            ],
            [
                'title_zh'        => '智能灯光场景定制方案',
                'title_en'        => 'Smart Lighting Scene Custom Solution',
                'slug'            => 'smart-lighting-solution',
                'description_zh'  => '根据不同空间和使用场景，定制多模式智能灯光控制方案，一键切换家居氛围。',
                'description_en'  => 'Custom smart lighting solutions with multi-scene control for different spaces.',
                'content_zh'      => '<p>我们的灯光方案涵盖：起居场景、工作场景、就寝场景、影院模式等多种预设方案。</p>',
                'content_en'      => '<p>Our lighting solutions include: living mode, work mode, sleep mode, cinema mode and more.</p>',
                'price_range'     => '3-15万',
                'seo_title'       => '智能灯光场景定制方案 - 晴空妙享',
                'seo_description' => '晴空妙享智能灯光场景定制，多模式预设，一键切换家居氛围，节能环保。',
                'seo_keywords'    => '智能灯光,场景控制,调光,节能',
                'is_featured'     => true,
                'sort'            => 2,
                'published_at'    => now()->subDays(20),
            ],
            [
                'title_zh'        => '家庭安防全覆盖方案',
                'title_en'        => 'Full Home Security Solution',
                'slug'            => 'home-security-solution',
                'description_zh'  => '360度无死角安防监控方案，智能门锁、视频门铃、摄像头、报警系统一体联动。',
                'description_en'  => '360-degree home security with smart locks, video doorbell, cameras, and alarm system.',
                'content_zh'      => '<p>安防方案核心组件：智能门锁+视频门铃+室内摄像头+户外摄像头+烟感/燃气报警器。</p>',
                'content_en'      => '<p>Core components: Smart lock, Video doorbell, Indoor camera, Outdoor camera, Smoke/gas detector.</p>',
                'price_range'     => '1-8万',
                'seo_title'       => '家庭安防全覆盖方案 - 晴空妙享',
                'seo_description' => '家庭安防全方位覆盖，智能门锁视频门铃摄像头报警联动，保护家人安全。',
                'seo_keywords'    => '家庭安防,智能门锁,摄像头,报警系统',
                'is_featured'     => false,
                'sort'            => 3,
                'published_at'    => now()->subDays(15),
            ],
            [
                'title_zh'        => '影音娱乐沉浸体验方案',
                'title_en'        => 'Immersive Audio-Visual Entertainment Solution',
                'slug'            => 'av-entertainment-solution',
                'description_zh'  => '打造家庭影院级别的沉浸式影音体验，4K投影、环绕音响、智能遮光帘联动控制。',
                'description_en'  => 'Create cinema-level immersive experience at home with 4K projector, surround sound, smart curtains.',
                'content_zh'      => '<p>影音方案包含：高清投影或大尺寸电视、环绕立体声音响系统、电动遮光帘、场景化智能联动。</p>',
                'content_en'      => '<p>AV solution includes: 4K projection/TV, surround sound, motorized curtains, smart scene automation.</p>',
                'price_range'     => '5-30万',
                'seo_title'       => '家庭影音娱乐沉浸体验方案 - 晴空妙享',
                'seo_description' => '家庭影院沉浸影音体验，4K投影环绕音响电动遮光帘智能联动。',
                'seo_keywords'    => '家庭影院,智能影音,4K投影,环绕音响',
                'is_featured'     => false,
                'sort'            => 4,
                'published_at'    => now()->subDays(25),
            ],
        ];

        foreach ($solutionsData as $index => $data) {
            $solution = SiteSolution::firstOrCreate(['slug' => $data['slug']], $data);

            try {
                if ($solution->getMedia('cover')->isEmpty()) {
                    $solution->addMediaFromUrl('https://picsum.photos/seed/solution' . ($index + 1) . '/800/600')
                        ->toMediaCollection('cover');
                }
            } catch (\Throwable) {
                // 离线环境静默跳过
            }

            $solution->tags()->syncWithoutDetaching(
                $tags->random(min(2, $tags->count()))->pluck('id')->toArray()
            );
        }

        // 6. 创建智能产品（约 8 个）
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
            $data['description_zh']  = '晴空妙享旗舰产品，' . $data['title_zh'] . '，专为智能家居场景设计。';
            $data['description_en']  = 'QingKong flagship product ' . $data['title_en'] . ', designed for smart home scenarios.';
            $data['seo_title']       = $data['title_zh'] . ' - 晴空妙享智能家居';
            $data['seo_description'] = $data['title_zh'] . '，晴空妙享官方出品，品质保障。';
            $data['seo_keywords']    = '智能家居,' . $data['title_zh'] . ',晴空妙享';
            $data['is_published']    = true;

            $product = SiteProduct::firstOrCreate(['slug' => $data['slug']], $data);

            try {
                if ($product->getMedia('cover')->isEmpty()) {
                    $product->addMediaFromUrl('https://picsum.photos/seed/product' . ($index + 1) . '/400/400')
                        ->toMediaCollection('cover');
                }
            } catch (\Throwable) {
                // 离线环境静默跳过
            }
        }

        // 7. 创建静态页面（3 个：about/contact/services）
        $pagesData = [
            [
                'title_zh'        => '关于晴空妙享',
                'title_en'        => 'About QingKong',
                'slug'            => 'about',
                'content_zh'      => '<p>湖北晴空妙享科技有限公司，专注智能家居方案设计与落地，致力于让每一个家庭都能享受科技带来的便利与品质生活。</p>',
                'content_en'      => '<p>Hubei QingKong Technology Co., Ltd. focuses on smart home solution design and implementation.</p>',
                'seo_title'       => '关于晴空妙享智能家居 - 湖北晴空妙享科技有限公司',
                'seo_description' => '湖北晴空妙享科技有限公司，专注智能家居方案设计与落地，品质服务，专业团队。',
                'seo_keywords'    => '晴空妙享,湖北智能家居,关于我们',
                'sort'            => 1,
                'is_published'    => true,
            ],
            [
                'title_zh'        => '联系我们',
                'title_en'        => 'Contact Us',
                'slug'            => 'contact',
                'content_zh'      => '<p>欢迎致电或在线留言，我们将在 24 小时内回复您的咨询。</p>',
                'content_en'      => '<p>Welcome to call or leave a message online. We will reply within 24 hours.</p>',
                'seo_title'       => '联系晴空妙享智能家居 - 预约免费设计咨询',
                'seo_description' => '联系晴空妙享，预约免费上门量房和智能家居方案设计咨询，专业团队全程服务。',
                'seo_keywords'    => '联系我们,预约咨询,智能家居设计',
                'sort'            => 2,
                'is_published'    => true,
            ],
            [
                'title_zh'        => '我们的服务',
                'title_en'        => 'Our Services',
                'slug'            => 'services',
                'content_zh'      => '<p>晴空妙享提供从方案设计、产品选购、专业施工到售后维保的一站式智能家居服务。</p>',
                'content_en'      => '<p>QingKong provides one-stop smart home services including design, product selection, installation, and after-sales support.</p>',
                'seo_title'       => '晴空妙享服务项目 - 智能家居一站式解决方案',
                'seo_description' => '晴空妙享一站式智能家居服务：方案设计、产品选购、专业施工、售后维保。',
                'seo_keywords'    => '智能家居服务,方案设计,施工安装,售后',
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
                'message' => '您好，我想了解全屋智能家居方案，我家是三居室，面积约120平，请问大概需要多少费用？',
                'status'  => 'unread',
                'ip'      => '127.0.0.1',
            ],
            [
                'name'    => '李女士',
                'phone'   => '18987654321',
                'message' => '我家别墅想做全套智能系统，包括安防、影音和灯光控制，能上门量房吗？',
                'status'  => 'unread',
                'ip'      => '127.0.0.1',
            ],
        ];

        foreach ($messagesData as $data) {
            ContactMessage::create($data);
        }
    }
}
