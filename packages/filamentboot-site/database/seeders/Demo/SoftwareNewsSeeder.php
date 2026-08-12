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
 * software 主题资讯演示内容种子（批次 3）
 *
 * 由 SiteNewsSeeder 按 SiteServiceProvider::resolveActiveTheme() 分发到这里；
 * 不要在测试或代码里直接依赖这个类名，应该 seed SiteNewsSeeder。
 *
 * 时间分布刻意跨 5 个月：归档页按年月分组，全堆在同一个月侧栏就只有一行，
 * 看不出功能是否正常。内容里含一篇草稿（published_at = null），用于验证
 * 前台不泄露未发布内容以及后台的状态筛选，与 DecorationNewsSeeder 同一套目的。
 *
 * 标签与 SoftwareDemoSeeder 共用两个 slug（api-integration / automation），
 * 按 slug 取用已存在的行——两边都用 firstOrCreateBySlug，谁先跑都一样。
 *
 * 幂等：可反复执行，按 slug 增量补种，见 Concerns\SeedsBySlug。
 */
class SoftwareNewsSeeder extends Seeder
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

            $data['seo_title']       = $data['title_zh'].' - 示例软件有限公司';
            $data['seo_description'] = $data['excerpt_zh'];
            $data['status']          = $data['published_at'] !== null ? PageStatus::PUBLISHED : PageStatus::DRAFT;

            $article = $this->firstOrCreateBySlug(NewsArticle::class, $data);

            $this->addCoverImage($article, 'site/demo/software/news/'.$data['slug'].'.jpg');

            $article->tags()->syncWithoutDetaching(
                $tags->whereIn('slug', $tagSlugs)->pluck('id')->all()
            );
        }
    }

    /**
     * 资讯分类（按内容意图分）
     *
     * @return Collection<int, NewsCategory>
     */
    protected function categories(): Collection
    {
        return collect([
            ['name_zh' => '选型指南', 'slug' => 'buying-guide', 'sort' => 1],
            ['name_zh' => '使用技巧', 'slug' => 'usage-tips', 'sort' => 2],
            ['name_zh' => '安全与合规', 'slug' => 'security-compliance', 'sort' => 3],
            ['name_zh' => '公司动态', 'slug' => 'company-news', 'sort' => 4],
        ])->map(
            fn (array $data): NewsCategory => $this->firstOrCreateBySlug(NewsCategory::class, $data)
        );
    }

    /**
     * 标签（前两个与 SoftwareDemoSeeder 共用 slug，按 slug 取用已存在的行）
     *
     * @return Collection<int, SiteTag>
     */
    protected function tags(): Collection
    {
        return collect([
            ['name_zh' => 'API 集成', 'slug' => 'api-integration'],
            ['name_zh' => '自动化', 'slug' => 'automation'],
            ['name_zh' => '权限管理', 'slug' => 'permission-management'],
            ['name_zh' => '私有化部署', 'slug' => 'deployment'],
            ['name_zh' => '客户故事', 'slug' => 'customer-story'],
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
        $guide      = $categories->firstWhere('slug', 'buying-guide')?->id;
        $tips       = $categories->firstWhere('slug', 'usage-tips')?->id;
        $compliance = $categories->firstWhere('slug', 'security-compliance')?->id;
        $company    = $categories->firstWhere('slug', 'company-news')?->id;

        return [
            [
                'title_zh'     => '什么时候该考虑引入工作流自动化',
                'slug'         => 'when-to-adopt-workflow-automation',
                'category_id'  => $guide,
                'is_featured'  => true,
                'sort'         => 1,
                'published_at' => now()->subDays(3),
                'seo_keywords' => '工作流自动化,流程管理,团队效率',
                'tag_slugs'    => ['automation'],
                'excerpt_zh'   => '不是团队越大越该上自动化，而是重复操作越多越该上。三个信号帮你判断现在是不是时候。',
                'content_zh'   => <<<'HTML'
                    <p>很多团队犹豫要不要上自动化，纠结的其实不是「值不值」，是「现在是不是时候」。这里给三个比较明确的信号。</p>
                    <p><strong>三个信号</strong></p>
                    <ul>
                    <li><strong>同一个操作一周重复超过 20 次</strong>：比如「订单来了手动通知仓库」，这类操作规则清晰、重复率高，是自动化的第一候选</li>
                    <li><strong>核对工作靠人工导表</strong>：多系统数据对不上，只能靠人每天导出来比对，这正是数据集成加自动化要解决的问题</li>
                    <li><strong>新人上手要背一长串操作步骤</strong>：说明这套流程该固化成系统规则，而不是靠人记</li>
                    </ul>
                    <p><strong>不建议一次性全上</strong></p>
                    <p>先挑一条高频、规则清晰的流程试点，跑通再扩展，比一开始就把所有流程都搬进系统更容易成功。</p>
                    HTML,
            ],
            [
                'title_zh'     => 'API 接口没有文档怎么办',
                'slug'         => 'integrating-without-api-docs',
                'category_id'  => $tips,
                'is_featured'  => false,
                'sort'         => 2,
                'published_at' => now()->subDays(10),
                'seo_keywords' => 'API集成,接口对接,系统集成',
                'tag_slugs'    => ['api-integration'],
                'excerpt_zh'   => '不是每个系统的接口都有齐全的文档。这几种情况下我们通常怎么处理。',
                'content_zh'   => <<<'HTML'
                    <p>理想情况下每个系统都有清晰的 API 文档，但现实中经常遇到文档缺失或过期的情况。</p>
                    <p><strong>常见的三种处理方式</strong></p>
                    <ul>
                    <li><strong>抓包分析</strong>：通过浏览器开发者工具观察前端请求，反推接口格式，适合内部管理系统</li>
                    <li><strong>联系对方技术人员</strong>：多数情况下对方系统的开发者能提供内部接口说明，即使不是正式文档</li>
                    <li><strong>数据库直连</strong>：极少数场景下 API 确实不存在，退而求其次通过数据库同步，但需要评估数据一致性风险</li>
                    </ul>
                    <p>不管走哪条路，接入前都建议先在测试环境验证数据准确性，再切到生产环境。</p>
                    HTML,
            ],
            [
                'title_zh'     => '权限设计：从「谁都能改」到「按角色分级」',
                'slug'         => 'permission-design-role-based',
                'category_id'  => $guide,
                'is_featured'  => true,
                'sort'         => 3,
                'published_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(11),
                'seo_keywords' => '权限管理,角色分级,数据安全',
                'tag_slugs'    => ['permission-management'],
                'excerpt_zh'   => '权限混乱往往不是设计出来的，是团队变大之后没人回头整理出来的。三步理清一套能长期用的权限体系。',
                'content_zh'   => <<<'HTML'
                    <p>很多团队早期图省事，所有人共用一套账号权限，等团队变大才发现谁都能改任何数据的问题——这时候再收权限，阻力比一开始就设计好大得多。</p>
                    <p><strong>三步理清权限</strong></p>
                    <ul>
                    <li><strong>先按部门分数据范围</strong>：销售看不到财务数据，客服看不到人事数据，这是最基本的边界</li>
                    <li><strong>再按角色分操作权限</strong>：同一份数据里，普通员工只读，主管可以编辑，管理员才能删除</li>
                    <li><strong>敏感操作单独审计</strong>：导出、删除这类高风险操作即使权限允许，也要留下操作记录</li>
                    </ul>
                    <p>建议先从「只读」权限上线试运行一段时间，确认数据范围划分没有问题，再逐步放开编辑权限。</p>
                    HTML,
            ],
            [
                'title_zh'     => '私有化部署与 SaaS 该怎么选',
                'slug'         => 'on-premise-vs-saas',
                'category_id'  => $guide,
                'is_featured'  => false,
                'sort'         => 4,
                'published_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(4),
                'seo_keywords' => '私有化部署,SaaS,数据合规',
                'tag_slugs'    => ['deployment'],
                'excerpt_zh'   => '不是私有化一定更安全、SaaS 一定更省事，关键要看你的数据出域限制和团队运维能力。',
                'content_zh'   => <<<'HTML'
                    <p>这个问题没有统一答案，取决于两个因素：数据能不能出内网，以及团队有没有能力运维一套系统。</p>
                    <p><strong>适合 SaaS 的情况</strong></p>
                    <p>没有明确的数据出域限制，团队没有专职运维人员，希望开通即用、按需扩展坐席，SaaS 通常是更省事的选择。</p>
                    <p><strong>适合私有化部署的情况</strong></p>
                    <p>金融、法律、政务等行业往往有明确的数据不出域要求，或者内部已有成熟的运维团队，私有化部署能满足合规要求，代价是需要投入运维资源。</p>
                    <p><strong>一个折中方案</strong></p>
                    <p>先用 SaaS 版验证流程是否好用，确认长期使用后再评估是否迁移到私有化部署，不必一开始就做最重的选择。</p>
                    HTML,
            ],
            [
                'title_zh'     => '数据打通后如何避免「重复通知轰炸」',
                'slug'         => 'avoiding-notification-overload',
                'category_id'  => $tips,
                'is_featured'  => false,
                'sort'         => 5,
                'published_at' => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(17),
                'seo_keywords' => '消息通知,自动化流程,通知设计',
                'tag_slugs'    => ['automation'],
                'excerpt_zh'   => '刚上自动化最容易犯的错，是把所有事件都设成通知，结果群里消息比人工时代还多。',
                'content_zh'   => <<<'HTML'
                    <p>自动化上线初期很容易矫枉过正：担心漏掉重要信息，把几乎所有事件都设成通知，结果是消息比人工核对时代还多，反而没人认真看了。</p>
                    <p><strong>三个建议</strong></p>
                    <ul>
                    <li><strong>只通知需要人决策的事件</strong>：系统能自动处理完的，不需要额外通知</li>
                    <li><strong>按严重程度分级</strong>：一般提醒进群消息，紧急异常单独推送给负责人</li>
                    <li><strong>定期回顾通知规则</strong>：上线一个月后检查哪些通知从没被真正处理过，该关的关掉</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'     => '审计日志到底该记多细',
                'slug'         => 'how-detailed-should-audit-logs-be',
                'category_id'  => $compliance,
                'is_featured'  => false,
                'sort'         => 6,
                'published_at' => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(7),
                'seo_keywords' => '审计日志,合规,数据安全',
                'tag_slugs'    => ['permission-management'],
                'excerpt_zh'   => '记得太粗查不出问题，记得太细存储和性能都吃不消。按操作风险分级是比较务实的做法。',
                'content_zh'   => <<<'HTML'
                    <p>审计日志不是记得越细越好，也不是能省则省——这两个极端我们都遇到过客户踩坑。</p>
                    <p><strong>按风险分级记录</strong></p>
                    <ul>
                    <li><strong>高风险操作</strong>（删除、批量导出、修改权限）：记录操作人、时间、具体内容，长期保存</li>
                    <li><strong>常规操作</strong>（查看、普通编辑）：记录操作人和时间即可，按合理周期滚动清理</li>
                    <li><strong>登录行为</strong>：记录 IP 与设备信息，用于异常登录检测</li>
                    </ul>
                    <p>具体保留多久、哪些字段必须记录，建议结合所在行业的合规要求确定，不同行业的标准差异不小。</p>
                    HTML,
            ],
            [
                'title_zh'     => '从人工核对到自动化：一个真实团队的迁移过程',
                'slug'         => 'manual-to-automation-migration-story',
                'category_id'  => $tips,
                'is_featured'  => false,
                'sort'         => 7,
                'published_at' => now()->subMonthsNoOverflow(3)->startOfMonth()->addDays(13),
                'seo_keywords' => '自动化迁移,团队实践,案例分享',
                'tag_slugs'    => ['customer-story', 'automation'],
                'excerpt_zh'   => '不是一次性切换，而是分三步走。记录一个团队从人工核对到自动化的真实迁移节奏。',
                'content_zh'   => <<<'HTML'
                    <p>把人工流程搬到自动化，不建议一次性全部切换——风险太高，出问题也不好排查。这里分享一个团队的实际节奏。</p>
                    <p><strong>第一步：并行运行</strong></p>
                    <p>先让自动化流程和人工核对同时跑两周，比对结果是否一致，确认规则没有遗漏边界情况。</p>
                    <p><strong>第二步：切换主流程，保留人工抽查</strong></p>
                    <p>确认无误后把自动化设为主流程，人工改为每周抽查而不是逐条核对。</p>
                    <p><strong>第三步：扩展到下一条流程</strong></p>
                    <p>第一条流程稳定运行一个月后，再启动下一条流程的迁移，而不是一开始就铺开多条。</p>
                    HTML,
            ],
            [
                'title_zh'     => '企业微信 / 钉钉集成：消息推送的三个坑',
                'slug'         => 'wecom-dingtalk-integration-pitfalls',
                'category_id'  => $tips,
                'is_featured'  => false,
                'sort'         => 8,
                'published_at' => now()->subMonthsNoOverflow(3)->startOfMonth()->addDays(5),
                'seo_keywords' => '企业微信集成,钉钉集成,消息推送',
                'tag_slugs'    => ['automation'],
                'excerpt_zh'   => '接进去只是第一步，真正让人愿意看这些通知，还要避开这三个常见坑。',
                'content_zh'   => <<<'HTML'
                    <p>企业微信和钉钉集成技术上不难，但用得好不好，往往取决于几个容易忽略的细节。</p>
                    <p><strong>三个常见坑</strong></p>
                    <ul>
                    <li><strong>通知内容太简略</strong>：只写「有一个新订单」，收到消息的人还得再打开系统查详情，不如直接把关键信息写进消息</li>
                    <li><strong>群消息和个人消息不分</strong>：所有通知都发进一个大群，重要信息很快被淹没</li>
                    <li><strong>没有免打扰时段</strong>：非工作时间的常规提醒也照发不误，容易引起团队反感</li>
                    </ul>
                    HTML,
            ],
            [
                'title_zh'     => '服务范围与响应时间说明',
                'slug'         => 'service-scope-response-time',
                'category_id'  => $company,
                'is_featured'  => false,
                'sort'         => 9,
                'published_at' => now()->subMonthsNoOverflow(4)->startOfMonth()->addDays(19),
                'seo_keywords' => '服务范围,响应时间,技术支持',
                'tag_slugs'    => ['deployment'],
                'excerpt_zh'   => '不同版本的支持响应时间不一样，这里一次说清楚，省得来回问。',
                'content_zh'   => <<<'HTML'
                    <p>不同版本配的支持服务不同，这里统一说明，方便对照「版本与定价」页面选择合适的版本。</p>
                    <p><strong>支持方式</strong></p>
                    <ul>
                    <li><strong>个人版</strong>：工作日邮件支持，1 个工作日内响应</li>
                    <li><strong>团队版</strong>：工作日在线支持，4 小时内响应</li>
                    <li><strong>企业版</strong>：7×5 在线支持，配专属客户成功经理</li>
                    </ul>
                    <p>接口对接、私有化部署这类实施类工作不计入日常响应时间，会单独约定交付周期。</p>
                    HTML,
            ],
            [
                // 草稿：验证前台不泄露未发布内容，也给后台状态筛选留一条样本
                'title_zh'     => '上半年功能迭代复盘（未定稿）',
                'slug'         => 'h1-feature-iteration-review-draft',
                'category_id'  => $company,
                'is_featured'  => false,
                'sort'         => 10,
                'published_at' => null,
                'seo_keywords' => '功能迭代,产品复盘',
                'tag_slugs'    => ['automation'],
                'excerpt_zh'   => '上半年在工作流引擎和权限中心上做了几处调整，记录一下原因。',
                'content_zh'   => <<<'HTML'
                    <p>本文尚未定稿，正在补充具体的功能变更清单。</p>
                    <p>上半年主要调整集中在工作流引擎的重试机制和权限中心的数据范围粒度，具体细节整理完再发。</p>
                    HTML,
            ],
        ];
    }
}
