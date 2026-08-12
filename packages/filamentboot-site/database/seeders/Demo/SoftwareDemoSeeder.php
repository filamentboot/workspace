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
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\PackageTier;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Models\SitePackage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProductCategory;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * software 主题演示内容种子（批次 3）
 *
 * 植入虚构的「示例软件有限公司」演示数据：客户案例/应用场景/产品与模块/静态页面。
 * 由 SiteDemoSeeder 按 SiteServiceProvider::resolveActiveTheme() 分发到这里；
 * 不要在测试或代码里直接依赖这个类名，应该 seed SiteDemoSeeder。
 *
 * 与 DecorationDemoSeeder 的关系见 CLAUDE.md「双主题各存完整副本」——两套主题的
 * 演示数据也各存一份，不抽公共层：内容主体完全不同（企业软件 vs 智能家居装修），
 * 硬抽象只会换来一堆按主题分支的 if。
 *
 * SiteCase 的 style / house_type / area / budget_range / smart_features 五个字段
 * 是装修行业专属（见 SiteCase 类注释与 CaseStyle/HouseType 枚举），软件案例
 * 全部留空，不塞不适用的值——前台已经验证过「信息不全不渲染空壳」这条路径。
 *
 * 图片：仅使用本地 storage/app/public/site/ 目录图片（D-11-11），本地不存在时
 * 不写入任何媒体，由前台 image-placeholder 组件渲染空态，禁止外部图片服务。
 *
 * 幂等：可反复执行，按 slug 增量补种，见 Concerns\SeedsBySlug。
 */
class SoftwareDemoSeeder extends Seeder
{
    use SeedsBySlug;
    use SeedsMediaImages;

    /**
     * 执行演示数据播种
     */
    public function run(): void
    {
        // 1. 客户案例分类
        $caseCategoryData = [
            ['name_zh' => '电商与零售', 'slug' => 'retail-ecommerce', 'sort' => 1],
            ['name_zh' => '制造与供应链', 'slug' => 'manufacturing-supply-chain', 'sort' => 2],
            ['name_zh' => '金融与专业服务', 'slug' => 'finance-professional-services', 'sort' => 3],
            ['name_zh' => '教育与非营利', 'slug' => 'education-nonprofit', 'sort' => 4],
        ];

        $caseCategories = collect($caseCategoryData)->map(
            fn (array $data) => $this->firstOrCreateBySlug(SiteCaseCategory::class, $data)
        );

        // 2. 产品分类
        $productCategoryData = [
            ['name_zh' => '核心模块', 'slug' => 'core-modules', 'sort' => 1],
            ['name_zh' => '集成与插件', 'slug' => 'integrations', 'sort' => 2],
            ['name_zh' => '企业增值服务', 'slug' => 'enterprise-addons', 'sort' => 3],
        ];

        $productCategories = collect($productCategoryData)->map(
            fn (array $data) => $this->firstOrCreateBySlug(SiteProductCategory::class, $data)
        );

        // 3. 标签
        $tagData = [
            ['name_zh' => 'API 集成', 'slug' => 'api-integration'],
            ['name_zh' => '数据安全', 'slug' => 'data-security'],
            ['name_zh' => '自动化', 'slug' => 'automation'],
            ['name_zh' => '团队协作', 'slug' => 'team-collaboration'],
            ['name_zh' => '私有化部署', 'slug' => 'on-premise-deployment'],
        ];

        $tags = collect($tagData)->map(
            fn (array $data) => $this->firstOrCreateBySlug(SiteTag::class, $data)
        );

        // 4. 客户案例（4 个）
        $casesData = [
            [
                'title_zh'       => '跨境电商订单与库存数据打通',
                'slug'           => 'cross-border-ecommerce-data-sync',
                'description_zh' => '三个平台的订单、库存、物流数据此前各自为政，靠人工每天导表核对。接入工作流引擎后，跨平台库存超卖问题基本消失。',
                'content_zh'     => <<<'HTML'
                    <p>客户在三个电商平台同时开店，仓库、订单、物流分别由不同系统管理，此前每天需要人工导出三份表格核对库存，超卖和漏发时有发生。</p>
                    <p><strong>做了什么</strong></p>
                    <ul>
                    <li>用 API 网关连接器把三个平台的订单接口统一接入，落到同一张订单表</li>
                    <li>工作流引擎按「下单 - 扣库存 - 通知仓库」编排自动化流程，替代人工核对</li>
                    <li>库存低于阈值时通过企业微信集成包推送采购提醒</li>
                    </ul>
                    <p><strong>效果</strong></p>
                    <p>上线后三个月，跨平台超卖投诉降到接近零，原本两人每天两小时的核对工作现在只需要抽查异常记录。</p>
                    HTML,
                'customer_name'   => '陈女士',
                'customer_meta'   => '某跨境电商运营负责人 · 使用 8 个月',
                'customer_quote'  => '以前最怕大促，三个后台来回切换核库存。现在系统自己核对，我们只处理系统标出来的异常单。',
                'seo_title'       => '跨境电商订单与库存数据打通案例 - 示例软件有限公司',
                'seo_description' => '示例软件帮助跨境电商客户打通多平台订单与库存数据，工作流自动化消除超卖问题。',
                'seo_keywords'    => '跨境电商,库存打通,订单自动化,API集成',
                'category_id'     => $caseCategories->get(0)?->id,
                'is_featured'     => true,
                'sort'            => 1,
                'published_at'    => now()->subDays(12),
            ],
            [
                'title_zh'       => '制造企业工单与设备台账自动化',
                'slug'           => 'manufacturing-workorder-automation',
                'description_zh' => '设备报修靠电话和纸质单，追溯维修历史全凭记忆。上线工单模块后，报修到派工的响应时间明显缩短。',
                'content_zh'     => <<<'HTML'
                    <p>客户是一家中型制造企业，车间设备报修长期靠电话通知和纸质单流转，维修历史散落在各班组的笔记本里，出故障时很难判断这台设备是不是老毛病。</p>
                    <p><strong>做了什么</strong></p>
                    <ul>
                    <li>用工作流引擎搭建报修 - 派工 - 验收的标准流程，报修即建单</li>
                    <li>报表与看板模块按设备维度汇总维修历史，故障复发一目了然</li>
                    <li>权限与审计中心按车间划分数据可见范围，班组只看自己的单</li>
                    </ul>
                    <p><strong>效果</strong></p>
                    <p>报修到派工的平均响应时间从原来的半天缩短到不到一小时，同一台设备的历史维修记录第一次能连续查到。</p>
                    HTML,
                'customer_name'   => '刘工',
                'customer_meta'   => '某制造企业设备科负责人 · 使用 6 个月',
                'customer_quote'  => '最有用的是看板能按设备查历史，以前一台设备修了几次全靠老师傅记性，现在系统里都有。',
                'seo_title'       => '制造企业工单与设备台账自动化案例 - 示例软件有限公司',
                'seo_description' => '示例软件为制造企业搭建设备报修工单流程，缩短响应时间，沉淀设备维修历史数据。',
                'seo_keywords'    => '制造企业,工单系统,设备台账,工作流自动化',
                'category_id'     => $caseCategories->get(1)?->id,
                'is_featured'     => true,
                'sort'            => 2,
                'published_at'    => now()->subDays(25),
            ],
            [
                'title_zh'       => '律所知识库与权限体系重构',
                'slug'           => 'law-firm-knowledge-permission-rebuild',
                'description_zh' => '案卷资料散落在各律师的本地硬盘和共享盘里，检索靠文件名猜。重建知识库和分级权限后，跨团队协作不再靠口头交接。',
                'content_zh'     => <<<'HTML'
                    <p>客户是一家中型律所，案卷资料分散在律师个人电脑和一个权限混乱的共享盘里，新人接手案子经常找不全材料，敏感案卷也没有分级管控。</p>
                    <p><strong>做了什么</strong></p>
                    <ul>
                    <li>用权限与审计中心按团队和案件类型划分访问范围，操作记录可追溯</li>
                    <li>报表与看板模块汇总每个案件的材料完整度，缺件一眼看出</li>
                    <li>私有化部署包把系统部署在律所自有机房，满足数据不出内网的要求</li>
                    </ul>
                    <p><strong>效果</strong></p>
                    <p>新人接手案子的材料查找时间明显缩短，敏感案卷的访问记录第一次做到可查可审。</p>
                    HTML,
                'customer_name'   => '王律师',
                'customer_meta'   => '某律所合伙人 · 使用 10 个月',
                'customer_quote'  => '数据不出内网是我们的硬要求，私有化部署这一点谈之前就问清楚了，用下来没出过问题。',
                'seo_title'       => '律所知识库与权限体系重构案例 - 示例软件有限公司',
                'seo_description' => '示例软件帮助律所重建案卷知识库与分级权限体系，支持私有化部署满足数据合规要求。',
                'seo_keywords'    => '律所,知识库,权限管理,私有化部署',
                'category_id'     => $caseCategories->get(2)?->id,
                'is_featured'     => false,
                'sort'            => 3,
                'published_at'    => now()->subDays(40),
            ],
            [
                'title_zh'       => '教育机构多校区数据一体化',
                'slug'           => 'education-multi-campus-integration',
                'description_zh' => '五个校区的教务系统各自独立，跨校区调课和统计要靠人工汇总。接入报表与看板模块后，总部第一次能实时看到全部校区数据。',
                // 这一单刻意不填客户见证：与装修案例同一套验证目的——
                // 信息不全时前台不渲染残缺的见证卡片
                'content_zh' => <<<'HTML'
                    <p>客户是一家有五个校区的教育机构，各校区教务系统相互独立，跨校区排课和月度统计全靠人工导表汇总，总部经常要等三四天才能拿到上月数据。</p>
                    <p><strong>做了什么</strong></p>
                    <ul>
                    <li>用 API 网关连接器把五个校区的教务系统数据统一接入</li>
                    <li>报表与看板模块按校区、班型两个维度实时汇总</li>
                    <li>Webhook 与事件总线把跨校区调课请求自动通知相关校区</li>
                    </ul>
                    <p><strong>效果</strong></p>
                    <p>总部现在可以实时查看全部校区的招生与排课数据，月度统计从三四天缩短到当天出结果。</p>
                    HTML,
                'seo_title'       => '教育机构多校区数据一体化案例 - 示例软件有限公司',
                'seo_description' => '示例软件帮助多校区教育机构打通教务数据，实现跨校区实时汇总与自动化通知。',
                'seo_keywords'    => '教育机构,多校区,数据一体化,教务系统集成',
                'category_id'     => $caseCategories->get(3)?->id,
                'is_featured'     => false,
                'sort'            => 4,
                'published_at'    => now()->subDays(30),
            ],
        ];

        foreach ($casesData as $data) {
            $data['status'] = PageStatus::PUBLISHED;

            $case = $this->firstOrCreateBySlug(SiteCase::class, $data);

            $this->addCoverImage($case, 'site/demo/software/cases/'.$data['slug'].'.jpg');

            if ($case->wasRecentlyCreated) {
                $case->tags()->syncWithoutDetaching(
                    $tags->random(min(2, $tags->count()))->pluck('id')->toArray()
                );
            }
        }

        // 5. 应用场景（3 个）
        $solutionsData = [
            [
                'title_zh'        => '多系统数据打通与自动化方案',
                'slug'            => 'data-integration-automation-solution',
                'description_zh'  => '把订单、库存、CRM、财务这类分散在不同系统里的数据统一接入，用工作流引擎替代人工核对与转发。',
                'content_zh'      => '<p>方案覆盖三个环节：API 网关统一接入各系统数据、工作流引擎编排自动化流程、报表与看板实时汇总结果。适合数据分散在 3 个以上系统、依赖人工核对的团队。</p>',
                'price_range'     => '按坐席数与集成系统数量报价',
                'seo_title'       => '多系统数据打通与自动化方案 - 示例软件有限公司',
                'seo_description' => '示例软件多系统数据打通方案，API 集成加工作流自动化，替代人工核对与转发。',
                'seo_keywords'    => '数据集成,自动化方案,API网关,工作流引擎',
                'is_featured'     => true,
                'sort'            => 1,
                'published_at'    => now()->subDays(28),
            ],
            [
                'title_zh'        => '客户数据与权限统一方案',
                'slug'            => 'unified-customer-data-permission-solution',
                'description_zh'  => '客户资料分散在销售、客服、财务各自的表格里，权限也没有统一口径。方案把客户数据收拢到一处，按角色分级授权。',
                'content_zh'      => '<p>方案核心是权限与审计中心：按部门和角色划定谁能看什么、谁能改什么，所有操作留痕可查。适合客户数据分散、缺乏统一权限管理的中大型团队。</p>',
                'price_range'     => '按部署规模报价',
                'seo_title'       => '客户数据与权限统一方案 - 示例软件有限公司',
                'seo_description' => '示例软件客户数据与权限统一方案，收拢分散客户资料，按角色分级授权与审计。',
                'seo_keywords'    => '客户数据管理,权限管理,数据安全,审计日志',
                'is_featured'     => true,
                'sort'            => 2,
                'published_at'    => now()->subDays(18),
            ],
            [
                'title_zh'        => '私有化部署与安全合规方案',
                'slug'            => 'on-premise-security-compliance-solution',
                'description_zh'  => '数据不能出内网是不少行业客户的硬性要求。方案把系统整体部署在客户自有机房或私有云，配合高级安全审计满足合规检查。',
                'content_zh'      => '<p>方案包含私有化部署包与高级安全审计模块：系统运行在客户自有环境，全部操作日志留存备查。适合金融、法律、政务等对数据出域有明确限制的行业。</p>',
                'price_range'     => '定制报价',
                'seo_title'       => '私有化部署与安全合规方案 - 示例软件有限公司',
                'seo_description' => '示例软件私有化部署方案，系统运行在客户自有环境，配合安全审计满足行业合规要求。',
                'seo_keywords'    => '私有化部署,数据合规,安全审计,行业合规',
                'is_featured'     => false,
                'sort'            => 3,
                'published_at'    => now()->subDays(35),
            ],
        ];

        foreach ($solutionsData as $data) {
            $data['status'] = PageStatus::PUBLISHED;

            $solution = $this->firstOrCreateBySlug(SiteSolution::class, $data);

            $this->addCoverImage($solution, 'site/demo/software/solutions/'.$data['slug'].'.jpg');

            if ($solution->wasRecentlyCreated) {
                $solution->tags()->syncWithoutDetaching(
                    $tags->random(min(2, $tags->count()))->pluck('id')->toArray()
                );
            }
        }

        // 6. 产品与模块（9 个，覆盖三个分类）
        foreach ($this->productsData($productCategories) as $data) {
            $data['status']       = PageStatus::PUBLISHED;
            $data['published_at'] = now();

            $product = $this->firstOrCreateBySlug(SiteProduct::class, $data);

            $this->addCoverImage($product, 'site/demo/software/products/'.$data['slug'].'.jpg');
        }

        // 7. 静态页面（5 个：about/contact/docs/faq/privacy）
        //
        // privacy 与 decoration 主题使用完全相同的正文：这份隐私政策本来就设计成
        // 主体无关——不写公司名、不写域名、不写联系方式，一律说「本站」，具体
        // 主体信息由页脚的 SiteSettings 渲染（见 DecorationDemoSeeder 同一处注释）。
        $pagesData = [
            [
                'title_zh'   => '关于示例软件',
                'slug'       => 'about',
                'content_zh' => <<<'HTML'
                    <p>示例软件有限公司专注于企业内部系统的<strong>数据打通、流程自动化与权限治理</strong>三件事。</p>
                    <p><strong>我们解决的问题</strong></p>
                    <p>大多数企业不是缺系统，是系统太多、互不说话：订单在一个系统，库存在另一个，客服记录又在第三个。人工核对填补这些缝隙，既慢又容易出错。我们做的是把这些系统接起来，用工作流引擎替代重复的人工操作。</p>
                    <p><strong>核心能力</strong></p>
                    <ul>
                    <li><strong>数据集成</strong>：API 网关连接器统一接入各类业务系统</li>
                    <li><strong>流程自动化</strong>：工作流引擎编排跨系统的自动化流程</li>
                    <li><strong>权限与审计</strong>：按角色分级授权，操作全程留痕</li>
                    <li><strong>私有化部署</strong>：满足对数据出域有明确限制的行业需求</li>
                    </ul>
                    <p>欢迎联系我们了解更多，预约产品演示。</p>
                    HTML,
                'seo_title'       => '关于示例软件 - 示例软件有限公司',
                'seo_description' => '示例软件有限公司专注企业数据打通、流程自动化与权限治理，提供 SaaS 与私有化部署两种交付方式。',
                'seo_keywords'    => '示例软件,企业软件,关于我们',
                'sort'            => 1,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
            [
                'title_zh'        => '联系我们',
                'slug'            => 'contact',
                'content_zh'      => '<p>欢迎致电或在线留言，我们将在 1 个工作日内回复您的咨询。</p><p><strong>公司名称：</strong>示例软件有限公司</p><p><strong>咨询热线：</strong>400-900-6688</p><p><strong>工作时间：</strong>周一至周五 9:00-18:00</p>',
                'seo_title'       => '联系示例软件 - 预约产品演示',
                'seo_description' => '联系示例软件有限公司，预约产品演示与方案咨询，400-900-6688。',
                'seo_keywords'    => '联系我们,预约演示,产品咨询,400-900-6688',
                'sort'            => 2,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
            [
                // slug 沿用 'services' 而不是更贴切的 'docs'：software 主题目前是
                // decoration 的字面复制（批次 2），nav/footer 组件里的兜底导航数组
                // 硬编码了 route('site.page', 'services')，真分岔前保持这个 slug
                // 存在，兜底链路（DB 菜单为空时）才不会 404。等六期真分岔时一并改。
                'title_zh'   => '快速开始',
                'slug'       => 'services',
                'content_zh' => <<<'HTML'
                    <p>从开通账号到跑通第一条自动化流程，一般不超过一个工作日。</p>
                    <p><strong>第一步：开通与接入</strong></p>
                    <ul>
                    <li>联系我们开通试用账号，选择 SaaS 版或私有化部署</li>
                    <li>用 API 网关连接器接入第一个业务系统（订单、库存或 CRM 均可）</li>
                    <li>确认数据同步无误后再接入第二个系统</li>
                    </ul>
                    <p><strong>第二步：搭建第一条流程</strong></p>
                    <ul>
                    <li>在工作流引擎里选一个高频重复操作作为试点（如「下单后通知仓库」）</li>
                    <li>用可视化编排搭好流程节点，先在测试环境跑通</li>
                    <li>确认无误后切到生产环境，观察一周再扩展下一条流程</li>
                    </ul>
                    <p><strong>第三步：配置权限</strong></p>
                    <p>在权限与审计中心按部门划定数据可见范围，建议先从「只读」权限开始试运行，确认无误后再逐步放开编辑权限。</p>
                    HTML,
                'seo_title'       => '快速开始 - 示例软件使用文档',
                'seo_description' => '示例软件快速开始指南：开通接入、搭建第一条自动化流程、配置权限，一般一个工作日内完成。',
                'seo_keywords'    => '快速开始,使用文档,接入指南,新手教程',
                'sort'            => 3,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
            [
                'title_zh'   => '常见问题',
                'slug'       => 'faq',
                'content_zh' => <<<'HTML'
                    <p>下面这些问题来自我们收到的真实咨询，按被问到的频次排列。</p>
                    <p><strong>部署与安全</strong></p>
                    <p><strong>支持私有化部署吗？</strong></p>
                    <p>支持。可以部署在客户自有机房或私有云，数据不出内网，具体方案见「私有化部署与安全合规方案」。</p>
                    <p><strong>数据安全怎么保障？</strong></p>
                    <p>权限与审计中心按角色分级授权，所有操作留痕可查；私有化部署下数据全程在客户自有环境内，我们不会接触生产数据。</p>
                    <p><strong>接入与使用</strong></p>
                    <p><strong>接入一个新系统需要多久？</strong></p>
                    <p>用标准 API 网关连接器接入通常 1-2 天可以完成；如果对方系统没有标准接口，需要额外评估工期。</p>
                    <p><strong>不会写代码能配置工作流吗？</strong></p>
                    <p>可以。工作流引擎提供可视化编排，常见的「触发 - 判断 - 执行」类流程不需要写代码；涉及复杂逻辑的场景可以联系我们协助搭建。</p>
                    <p><strong>费用与试用</strong></p>
                    <p><strong>能不能先试用再决定？</strong></p>
                    <p>可以，联系我们开通试用账号，试用期内可以接入真实系统验证效果。</p>
                    <p><strong>企业版和团队版差别大吗？</strong></p>
                    <p>差别主要在私有化部署、专属客户成功服务和安全审计深度，核心模块两档都包含，具体见「版本与定价」页面。</p>
                    HTML,
                'seo_title'       => '常见问题 - 示例软件有限公司',
                'seo_description' => '示例软件常见问题：私有化部署、数据安全、接入周期、工作流配置、试用与版本差异。',
                'seo_keywords'    => '常见问题,私有化部署,数据安全,试用,版本差异',
                'sort'            => 4,
                'status'          => PageStatus::PUBLISHED,
                'published_at'    => now(),
            ],
            [
                // 与 DecorationDemoSeeder::pagesData() 的 privacy 页完全同源：
                // 正文一个字不改，理由见那一处的完整注释。
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
                    <li><strong>咨询内容</strong>，以及表单中可选问题的回答</li>
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
                    <li>回复咨询、安排产品演示与后续业务沟通</li>
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
                    <li>为完成您所要求的服务而必需</li>
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

        // 8. 示例询盘（2 条）
        $messagesData = [
            [
                'name'    => '张先生',
                'phone'   => '13812345678',
                'message' => '您好，我们是一家有 5 个门店的零售企业，想了解一下多系统数据打通方案，能否安排一次演示？',
                'status'  => 'unread',
                'ip'      => '127.0.0.1',
            ],
            [
                'name'    => '李女士',
                'phone'   => '18987654321',
                'message' => '我们对私有化部署比较关心，数据不能出内网，请问能否先安排技术对接了解一下可行性？',
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

        // 9. 首页幻灯片（3 张）
        foreach ($this->bannersData() as $data) {
            $banner = $this->firstOrCreateBySlug(SiteBanner::class, $data);

            $this->addCoverImage($banner, 'site/demo/software/banners/'.$data['slug'].'.jpg');
        }

        // 10. 版本与定价（3 档，批次 3 新增）
        //
        // 与 DecorationDemoSeeder 一样复用 SitePackage：house_layout 是装修行业专属
        // 字段，这里留空；tier 借用同一套三档语义表达「定制 / 团队 / 企业」三档授权。
        foreach ($this->packagesData() as $data) {
            $data['status'] = PageStatus::PUBLISHED;

            $package = $this->firstOrCreateBySlug(SitePackage::class, $data);

            $this->addCoverImage($package, 'site/demo/software/packages/'.$data['slug'].'.jpg');
        }

        // 城市页（SiteCityPage）不做：软件产品没有「服务城市」这个概念，
        // 见五期规划批次 3 的决定。

        // 导航/页脚菜单（SiteFrontMenuSeeder）与列表页导语（SiteIntroCopySeeder）
        // 两套主题共用，且都依赖本方法建好的静态页，改由外层 SiteDemoSeeder
        // 在分发到具体主题之后统一调用一次，不在这里重复调用。
    }

    /**
     * 首页幻灯片数据（3 张，全部投放 HOME_TOP）
     *
     * @return list<array<string, mixed>>
     */
    protected function bannersData(): array
    {
        return [
            [
                'slug'       => 'home-hero',
                'title'      => '把分散的系统连起来，让重复工作交给流程',
                'subtitle'   => '从数据接入到自动化流程编排，帮企业把分散在多个系统里的数据和操作统一起来。',
                'cta_label'  => '预约产品演示',
                'cta_url'    => null,
                'cta_action' => BannerCtaAction::INQUIRY,
                'position'   => BannerPosition::HOME_TOP,
                'sort'       => 1,
                'is_enabled' => true,
            ],
            [
                'slug'       => 'home-cases',
                'title'      => '看看客户是怎么用的',
                'subtitle'   => '每个案例都写清了要解决的问题、具体做法和上线后的效果。',
                'cta_label'  => '查看客户案例',
                'cta_url'    => '/cases',
                'cta_action' => BannerCtaAction::LINK,
                'position'   => BannerPosition::HOME_TOP,
                'sort'       => 2,
                'is_enabled' => true,
            ],
            [
                'slug'       => 'home-solutions',
                'title'      => '按场景挑方案，不用从零搭建',
                'subtitle'   => '数据打通、权限治理、私有化合规各有一套现成方案，可以直接改成适合你的版本。',
                'cta_label'  => '浏览应用场景',
                'cta_url'    => '/solutions',
                'cta_action' => BannerCtaAction::LINK,
                'position'   => BannerPosition::HOME_TOP,
                'sort'       => 3,
                'is_enabled' => true,
            ],
        ];
    }

    /**
     * 产品与模块数据（9 条，分摊到核心模块 / 集成与插件 / 企业增值服务三个分类）
     *
     * brand 一律 null：这是软件公司卖自己的模块，不是渠道商代理第三方品牌，
     * 与 DecorationDemoSeeder::productsData() 的渠道商业态不同，brand 字段
     * 在这里本来就不适用。
     *
     * price：核心模块与集成类给了参考价，企业增值服务（涉及定制范围）留 null，
     * 前台渲染「咨询报价」——同一套空态早已被装修主题的产品页验证过。
     *
     * @param  Collection<int, SiteProductCategory>  $categories
     * @return list<array<string, mixed>>
     */
    protected function productsData(Collection $categories): array
    {
        $core         = $categories->firstWhere('slug', 'core-modules')?->id;
        $integrations = $categories->firstWhere('slug', 'integrations')?->id;
        $enterprise   = $categories->firstWhere('slug', 'enterprise-addons')?->id;

        return [
            // ---------- 核心模块 ----------
            [
                'title_zh'       => '工作流引擎',
                'slug'           => 'workflow-engine',
                'brand'          => null,
                'price'          => '999',
                'category_id'    => $core,
                'is_featured'    => true,
                'sort'           => 1,
                'seo_keywords'   => '工作流引擎,流程自动化,可视化编排',
                'description_zh' => '可视化编排「触发 - 判断 - 执行」流程，替代人工核对与转发。常见场景不用写代码，复杂逻辑支持自定义脚本节点。',
                'content_zh'     => <<<'HTML'
                    <p>大多数重复性工作的本质是「A 发生后按规则做 B」，工作流引擎就是把这类规则可视化地搭起来，交给系统自动执行。</p>
                    <p><strong>能做什么</strong></p>
                    <ul>
                    <li>触发节点支持定时、Webhook、数据变更三种方式</li>
                    <li>判断节点可以按字段值分支，覆盖大多数业务规则</li>
                    <li>执行节点内置常见操作（发通知、写数据、调用接口），也支持自定义脚本</li>
                    </ul>
                    <p><strong>典型用法</strong></p>
                    <p>「订单创建后自动扣减库存并通知仓库」「库存低于阈值时推送采购提醒」这类流程，通常半天内可以搭建并上线测试。</p>
                    HTML,
            ],
            [
                'title_zh'       => '报表与看板',
                'slug'           => 'reporting-dashboard',
                'brand'          => null,
                'price'          => '699',
                'category_id'    => $core,
                'is_featured'    => true,
                'sort'           => 2,
                'seo_keywords'   => '报表看板,数据可视化,实时汇总',
                'description_zh' => '把接入的多系统数据实时汇总成看板，按部门、时间、业务线自由拆分，不用再手工导表拼数字。',
                'content_zh'     => <<<'HTML'
                    <p>数据分散在多个系统里，最先暴露出来的问题往往不是「用不了」，而是「看不全」——月底统计要等好几天。</p>
                    <p><strong>能做什么</strong></p>
                    <ul>
                    <li>接入工作流引擎产生的数据，实时刷新，不用等夜间批处理</li>
                    <li>支持按部门、时间、业务线等维度自由切换视图</li>
                    <li>看板可以设置阈值告警，异常数据自动高亮</li>
                    </ul>
                    <p><strong>典型用法</strong></p>
                    <p>制造企业按设备维度看维修历史，教育机构按校区维度看招生数据，都是同一个看板模块，换的只是维度配置。</p>
                    HTML,
            ],
            [
                'title_zh'       => '权限与审计中心',
                'slug'           => 'permission-audit-center',
                'brand'          => null,
                'price'          => '599',
                'category_id'    => $core,
                'is_featured'    => false,
                'sort'           => 3,
                'seo_keywords'   => '权限管理,审计日志,角色分级',
                'description_zh' => '按部门和角色划定谁能看什么、谁能改什么，所有操作留痕可查，满足内部合规与外部审计的双重要求。',
                'content_zh'     => <<<'HTML'
                    <p>权限混乱通常不是一开始就乱的，是团队变大之后没人再回头整理。这个模块把权限和操作记录收拢到一处管理。</p>
                    <p><strong>能做什么</strong></p>
                    <ul>
                    <li>按部门、角色、数据范围三个维度组合授权</li>
                    <li>敏感操作（删除、导出、修改权限）强制记录操作人与时间</li>
                    <li>审计日志支持按时间段导出，配合外部审计检查</li>
                    </ul>
                    <p><strong>典型用法</strong></p>
                    <p>法律、金融类客户常用它满足「谁动过这份数据」的合规追溯要求。</p>
                    HTML,
            ],

            // ---------- 集成与插件 ----------
            [
                'title_zh'       => 'API 网关连接器',
                'slug'           => 'api-gateway-connector',
                'brand'          => null,
                'price'          => '499',
                'category_id'    => $integrations,
                'is_featured'    => true,
                'sort'           => 4,
                'seo_keywords'   => 'API集成,网关连接器,数据接入',
                'description_zh' => '统一接入各类业务系统的 API，标准接口 1-2 天可以完成接入，非标接口支持自定义适配。',
                'content_zh'     => <<<'HTML'
                    <p>企业常用系统大多提供 API，但接口风格、认证方式、数据格式各不相同，逐个对接费时费力。连接器把这层差异统一封装。</p>
                    <p><strong>能做什么</strong></p>
                    <ul>
                    <li>内置主流电商、CRM、ERP 系统的标准适配器</li>
                    <li>非标接口支持自定义适配配置，不需要另写一套集成代码</li>
                    <li>接入后的数据直接可供工作流引擎和报表模块使用</li>
                    </ul>
                    <p><strong>安装与服务</strong></p>
                    <p>标准接口通常 1-2 天完成接入并验证数据准确性，非标接口需要额外评估工期。</p>
                    HTML,
            ],
            [
                'title_zh'       => '企业微信 / 钉钉集成包',
                'slug'           => 'wecom-dingtalk-integration',
                'brand'          => null,
                'price'          => '299',
                'category_id'    => $integrations,
                'is_featured'    => false,
                'sort'           => 5,
                'seo_keywords'   => '企业微信集成,钉钉集成,消息推送',
                'description_zh' => '把工作流触发的通知直接推送到企业微信或钉钉，不用额外打开系统查看。',
                'content_zh'     => <<<'HTML'
                    <p>系统里的告警和待办，如果不推送到日常在用的沟通工具，很容易被忽略。集成包把两者接起来。</p>
                    <p><strong>能做什么</strong></p>
                    <ul>
                    <li>工作流触发的通知直接推送到指定群或个人</li>
                    <li>支持在企业微信 / 钉钉内直接审批工作流中的待办节点</li>
                    <li>消息模板可自定义，避免通知信息不完整</li>
                    </ul>
                    <p><strong>典型用法</strong></p>
                    <p>库存告警、审批提醒、异常订单通知，是接入频率最高的三类场景。</p>
                    HTML,
            ],
            [
                'title_zh'       => 'Webhook 与事件总线',
                'slug'           => 'webhook-event-bus',
                'brand'          => null,
                'price'          => '399',
                'category_id'    => $integrations,
                'is_featured'    => false,
                'sort'           => 6,
                'seo_keywords'   => 'Webhook,事件总线,系统间通知',
                'description_zh' => '系统内部事件（如数据变更）可以对外推送 Webhook，也可以接收外部系统推来的事件，触发对应的工作流。',
                'content_zh'     => <<<'HTML'
                    <p>有些系统间协作不是「定时查一次」，而是「一发生就要立刻知道」。事件总线负责这类实时通知。</p>
                    <p><strong>能做什么</strong></p>
                    <ul>
                    <li>数据变更时向外部系统推送 Webhook，无需外部系统轮询</li>
                    <li>接收外部系统推来的事件，直接触发工作流引擎里的流程</li>
                    <li>失败重试与投递记录可查，避免事件静默丢失</li>
                    </ul>
                    <p><strong>典型用法</strong></p>
                    <p>跨校区调课请求、跨平台订单状态变更，都是「一发生就要通知」的典型场景。</p>
                    HTML,
            ],

            // ---------- 企业增值服务 ----------
            [
                'title_zh'       => '私有化部署包',
                'slug'           => 'on-premise-deployment-package',
                'brand'          => null,
                'price'          => null,
                'category_id'    => $enterprise,
                'is_featured'    => true,
                'sort'           => 7,
                'seo_keywords'   => '私有化部署,数据不出内网,行业合规',
                'description_zh' => '整套系统部署在客户自有机房或私有云，数据不出内网，满足金融、法律、政务等行业的合规要求。',
                'content_zh'     => <<<'HTML'
                    <p>部分行业对数据出域有明确限制，SaaS 模式无法满足。私有化部署把系统整体交付到客户自有环境运行。</p>
                    <p><strong>包含什么</strong></p>
                    <ul>
                    <li>系统安装部署与环境适配</li>
                    <li>与客户现有身份认证系统对接（如企业内部 SSO）</li>
                    <li>version 升级与技术支持，不需要公网访问</li>
                    </ul>
                    <p><strong>适用场景</strong></p>
                    <p>律所、金融机构、政务单位是最常见的三类客户，具体方案见「私有化部署与安全合规方案」。</p>
                    HTML,
            ],
            [
                'title_zh'       => '高级安全审计模块',
                'slug'           => 'advanced-security-audit-module',
                'brand'          => null,
                'price'          => null,
                'category_id'    => $enterprise,
                'is_featured'    => false,
                'sort'           => 8,
                'seo_keywords'   => '安全审计,登录日志,异常检测',
                'description_zh' => '在权限与审计中心基础上加强审计粒度，覆盖登录行为分析、异常访问检测与定制化审计报告。',
                'content_zh'     => <<<'HTML'
                    <p>基础审计记录「谁做了什么」，高级安全审计进一步分析「这个行为是否异常」。</p>
                    <p><strong>能做什么</strong></p>
                    <ul>
                    <li>登录行为分析：异地登录、非工作时段登录自动标记</li>
                    <li>批量导出、批量删除等高风险操作二次确认并留痕</li>
                    <li>支持按合规要求定制审计报告模板</li>
                    </ul>
                    <p><strong>适用场景</strong></p>
                    <p>需要定期向监管方或母公司提交合规审计材料的企业。</p>
                    HTML,
            ],
            [
                'title_zh'       => '专属客户成功服务',
                'slug'           => 'dedicated-customer-success-service',
                'brand'          => null,
                'price'          => null,
                'category_id'    => $enterprise,
                'is_featured'    => false,
                'sort'           => 9,
                'seo_keywords'   => '客户成功,专属支持,培训服务',
                'description_zh' => '配备专属客户成功经理，协助流程设计、上线培训与后续优化，不是通用工单排队式支持。',
                'content_zh'     => <<<'HTML'
                    <p>系统能力再强，团队不会用也发挥不出效果。专属客户成功服务负责把能力真正落到日常使用里。</p>
                    <p><strong>包含什么</strong></p>
                    <ul>
                    <li>上线前的流程梳理与工作流设计协助</li>
                    <li>团队使用培训，覆盖管理员与普通用户两个层面</li>
                    <li>季度回访，根据实际使用情况给出优化建议</li>
                    </ul>
                    <p><strong>适用场景</strong></p>
                    <p>团队规模较大、内部缺乏专职系统管理员的企业，通常配合企业版一起购买。</p>
                    HTML,
            ],
        ];
    }

    /**
     * 版本与定价数据（3 档，批次 3 新增）
     *
     * 复用 SitePackage：house_layout（装修户型）留空不适用；tier 借用「定制 /
     * 舒适 / 豪华」三档语义表达「个人版 / 团队版 / 企业版」授权——真分岔（按软件
     * 业态改标签措辞）推后到六期之后，与 software 主题其它页面同一节奏（批次 2）。
     * items 的 quantity 借用表达坐席数，location 列本就不适用，全部留空，
     * 前台 itemColumns() 会按列自动隐藏没有内容的那一列。
     *
     * @return list<array<string, mixed>>
     */
    protected function packagesData(): array
    {
        return [
            [
                'title_zh'       => '个人版年度授权',
                'slug'           => 'personal-annual-license',
                'description_zh' => '适合个人或两三人小团队起步试用，包含核心模块，坐席数有限。',
                'content_zh'     => '<p>个人版覆盖工作流引擎与报表看板两个核心模块，适合先跑通一条自动化流程再决定要不要升级。</p>',
                'tier'           => PackageTier::CUSTOM,
                'price'          => '2999',
                'price_note'     => '按年计费，含 3 个坐席，超出部分按坐席数加购',
                'items'          => [
                    ['name' => '工作流引擎', 'quantity' => '3 坐席', 'purpose' => '流程自动化编排', 'location' => ''],
                    ['name' => '报表与看板', 'quantity' => '3 坐席', 'purpose' => '数据实时汇总', 'location' => ''],
                    ['name' => 'API 网关连接器', 'quantity' => '2 个系统', 'purpose' => '接入外部业务系统', 'location' => ''],
                ],
                'excludes'        => '不含私有化部署、不含专属客户成功服务',
                'duration'        => '开通后当天可用',
                'warranty'        => '工作日邮件支持，1 个工作日内响应',
                'seo_title'       => '个人版年度授权 - 示例软件有限公司',
                'seo_description' => '示例软件个人版年度授权，包含工作流引擎与报表看板核心模块，适合小团队起步试用。',
                'seo_keywords'    => '个人版,年度授权,定价,工作流引擎',
                'is_featured'     => false,
                'sort'            => 1,
                'published_at'    => now()->subDays(20),
            ],
            [
                'title_zh'       => '团队版年度授权',
                'slug'           => 'team-annual-license',
                'description_zh' => '常用场景一次做齐，适合 10-50 人团队，包含全部核心模块与主流集成。',
                'content_zh'     => '<p>团队版在个人版基础上加入权限与审计中心和企业微信 / 钉钉集成包，坐席数上调到 20 个起，满足团队协作的日常需求。</p>',
                'tier'           => PackageTier::COMFORT,
                'price'          => '9999',
                'price_note'     => '按年计费，含 20 个坐席，超出部分按坐席数加购',
                'items'          => [
                    ['name' => '工作流引擎', 'quantity' => '20 坐席', 'purpose' => '流程自动化编排', 'location' => ''],
                    ['name' => '报表与看板', 'quantity' => '20 坐席', 'purpose' => '数据实时汇总', 'location' => ''],
                    ['name' => '权限与审计中心', 'quantity' => '全部坐席', 'purpose' => '角色分级与操作留痕', 'location' => ''],
                    ['name' => 'API 网关连接器', 'quantity' => '5 个系统', 'purpose' => '接入外部业务系统', 'location' => ''],
                    ['name' => '企业微信 / 钉钉集成包', 'quantity' => '1 套', 'purpose' => '通知推送与审批', 'location' => ''],
                ],
                'excludes'        => '不含私有化部署、不含高级安全审计模块',
                'duration'        => '开通后当天可用',
                'warranty'        => '工作日在线支持，4 小时内响应',
                'seo_title'       => '团队版年度授权 - 示例软件有限公司',
                'seo_description' => '示例软件团队版年度授权，覆盖全部核心模块与主流集成，适合 10-50 人团队。',
                'seo_keywords'    => '团队版,年度授权,定价,权限管理',
                'is_featured'     => true,
                'sort'            => 2,
                'published_at'    => now()->subDays(15),
            ],
            [
                'title_zh'       => '企业版年度授权',
                'slug'           => 'enterprise-annual-license',
                'description_zh' => '全部能力做满，包含私有化部署与专属客户成功服务，适合对数据合规有明确要求的企业。',
                'content_zh'     => '<p>企业版在团队版基础上补齐私有化部署包、高级安全审计模块与专属客户成功服务，坐席数不设上限，按实际规模报价。</p>',
                'tier'           => PackageTier::DELUXE,
                'price'          => null,
                'price_note'     => '按坐席数与部署方式定制报价，联系我们获取报价单',
                'items'          => [
                    ['name' => '工作流引擎 / 报表看板 / 权限审计', 'quantity' => '不限坐席', 'purpose' => '全部核心模块', 'location' => ''],
                    ['name' => 'API 网关连接器', 'quantity' => '不限系统数', 'purpose' => '接入外部业务系统', 'location' => ''],
                    ['name' => '私有化部署包', 'quantity' => '1 套', 'purpose' => '部署在客户自有环境', 'location' => ''],
                    ['name' => '高级安全审计模块', 'quantity' => '1 套', 'purpose' => '登录分析与合规审计报告', 'location' => ''],
                    ['name' => '专属客户成功服务', 'quantity' => '1 名专属经理', 'purpose' => '流程设计与季度回访', 'location' => ''],
                ],
                'excludes'        => '无',
                'duration'        => '按部署方式评估，私有化部署通常 1-2 周完成交付',
                'warranty'        => '7×5 在线支持，配专属客户成功经理',
                'seo_title'       => '企业版年度授权 - 示例软件有限公司',
                'seo_description' => '示例软件企业版年度授权，含私有化部署与专属客户成功服务，满足数据合规与规模化需求。',
                'seo_keywords'    => '企业版,私有化部署,定制报价,客户成功',
                'is_featured'     => false,
                'sort'            => 3,
                'published_at'    => now()->subDays(10),
            ],
        ];
    }
}
