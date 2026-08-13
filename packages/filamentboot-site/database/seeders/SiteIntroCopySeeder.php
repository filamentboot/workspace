<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders;

use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Database\Seeder;

/**
 * 页脚简介与列表页导语的演示文案（3.5 期 A 段修复六，批次 3 起按主题分岔）
 *
 * 这 6 段文案原本写死在各主题的 blade 里，A 段把它们改成了站点设置
 * （`site.footer_intro_zh` 与五个 `site.list_intro_*_zh`）。
 *
 * **设置留空时前台整段不渲染，没有视图侧兜底**——有兜底就等于后台空着、前台
 * 仍有字，正是这一批改动要修的毛病（参见 SiteFrontMenuSeeder 的同款处置）。
 * 代价是演示数据得有人填，就是本 Seeder。
 *
 * 设置跨主题共用，此前统一取 decoration 一套原文，切到 software 会看到装修口径
 * 的话术——批次 3 起按 `SiteServiceProvider::resolveActiveTheme()` 分岔成两套。
 *
 * 独立成一个类而不是并进 SiteDemoSeeder：它可以脱离演示内容单独执行，
 * **已上线站点补这 6 个设置项时跑的就是它**（`db:seed --class=...`），
 * 不必为了填几行文案把整套演示内容再走一遍。
 *
 * 幂等：只填空值，运营改过的文案一概不覆盖。
 */
class SiteIntroCopySeeder extends Seeder
{
    /**
     * 6 段演示文案对应的设置字段名——两套主题字段名相同，只是文案内容不同（见 copy()）
     *
     * @var list<string>
     */
    private const FIELDS = [
        'footer_intro_zh', 'list_intro_cases_zh', 'list_intro_solutions_zh',
        'list_intro_products_zh', 'list_intro_packages_zh', 'list_intro_news_zh',
    ];

    /**
     * 把空着的文案设置填上
     */
    public function run(): void
    {
        $settings = app(SiteSettings::class);
        $changed  = false;

        foreach ($this->copy() as $name => $value) {
            if (trim((string) $settings->{$name}) === '') {
                $settings->{$name} = $value;
                $changed           = true;
            }
        }

        if ($changed) {
            $settings->save();
        }
    }

    /**
     * 清空演示数据用：把 6 个字段无条件复位为空字符串
     *
     * 与 run() 的「只填空值」相反——run() 不覆盖运营改过的文案，这里反过来直接清空，
     * 不判断当前值是不是 demo 原文。调用方（后台「清空演示数据」按钮）必须在确认
     * 文案里说清楚这一条，不能让操作者以为只清的是没改过的默认文案。
     */
    public static function resetToEmpty(): void
    {
        $settings = app(SiteSettings::class);

        foreach (self::FIELDS as $name) {
            $settings->{$name} = '';
        }

        $settings->save();
    }

    /**
     * 6 段演示文案，按当前主题分岔
     *
     * @return array<string, string>
     */
    protected function copy(): array
    {
        return match (SiteServiceProvider::resolveActiveTheme()) {
            'software' => $this->softwareCopy(),
            default    => $this->decorationCopy(),
        };
    }

    /**
     * decoration 主题的 6 段演示文案
     *
     * @return array<string, string>
     */
    protected function decorationCopy(): array
    {
        return [
            'footer_intro_zh'         => '我们将智能科技与精致设计融为一体，为您打造真正属于未来的家居空间。',
            'list_intro_cases_zh'     => '探索我们的精选智能家居装修案例，感受设计与科技的完美融合。',
            'list_intro_solutions_zh' => '针对不同场景提供专业智能家居解决方案，满足您的个性化需求。',
            'list_intro_products_zh'  => '精选顶级智能家居产品，为您的智慧生活赋能。',
            'list_intro_packages_zh'  => '按户型和档位分好的整套配置，包含什么、放在哪儿、大概多少钱，都摊开写在里面。',
            'list_intro_news_zh'      => '选型经验、施工细节、踩坑复盘——我们把项目里学到的东西写下来。',
        ];
    }

    /**
     * software 主题的 6 段演示文案
     *
     * @return array<string, string>
     */
    protected function softwareCopy(): array
    {
        return [
            'footer_intro_zh'         => '我们把分散在多个系统里的数据和操作连起来，让重复工作交给自动化流程。',
            'list_intro_cases_zh'     => '看看不同行业的客户是怎么用我们的产品解决实际问题的。',
            'list_intro_solutions_zh' => '按数据打通、权限治理、私有化合规这类常见场景整理的现成方案，可以直接改成适合你的版本。',
            'list_intro_products_zh'  => '核心模块、系统集成与企业增值服务，按需组合，不必从零搭建。',
            'list_intro_packages_zh'  => '个人版到企业版三档授权，包含什么模块、坐席数多少、大概多少钱，都摊开写在里面。',
            'list_intro_news_zh'      => '选型建议、接入经验、踩坑复盘——我们把产品迭代里学到的东西写下来。',
        ];
    }
}
