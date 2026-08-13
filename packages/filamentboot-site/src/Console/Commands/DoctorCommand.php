<?php

namespace Filamentboot\FilamentbootSite\Console\Commands;

use Filamentboot\FilamentbootSite\Database\Seeders\SitePermissionSeeder;
use Filamentboot\FilamentbootSite\Services\SiteHealthCheck;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Throwable;

/**
 * 官网插件健康检查命令（批次 4）
 *
 * 客户装完包，"装完为什么不对"的第一道排查工具——覆盖插件启用状态、迁移完整性、
 * 结构性种子、关键路由、首页 HTTP 响应头、媒体磁盘可写六项运维类检查，
 * 外加复用既有的 SiteHealthCheck 做一次内容配置完整性检查。
 *
 * 每一项单独判定、单独报告，任意一项不通过就以非零状态退出（报告/退出码形态
 * 照 `filamentboot:audit-plugins`）。零额外依赖，装完包立刻能跑。
 */
class DoctorCommand extends Command
{
    /** @var string */
    protected $signature = 'filamentboot-site:doctor';

    /** @var string */
    protected $description = '检查官网插件的安装/运行状态（插件启用、迁移、结构性种子、路由、首页响应头、媒体磁盘）';

    /**
     * site 包建的核心表——按 `Schema::create()` 实测枚举，不含已废弃改嫁的
     * `site_page_revisions`（数据已搬进多态的 `site_revisions`，见对应 drop 迁移）。
     *
     * @var list<string>
     */
    private const CORE_TABLES = [
        'site_ad_slots', 'site_banners', 'site_case_categories', 'site_cases',
        'site_city_pages', 'site_contact_message_notes', 'site_contact_messages',
        'site_friend_links', 'site_menu_items', 'site_menus', 'site_news_articles',
        'site_news_categories', 'site_packages', 'site_pages', 'site_product_categories',
        'site_products', 'site_redirects', 'site_regions', 'site_revisions',
        'site_search_terms', 'site_solutions', 'site_taggables', 'site_tags',
    ];

    /**
     * `routes/site.php` 声明的全部 route name
     *
     * @var list<string>
     */
    private const CRITICAL_ROUTES = [
        'site.cases.index', 'site.cases.show', 'site.city.index', 'site.city.province',
        'site.city.show', 'site.contact.store', 'site.download', 'site.home', 'site.llms',
        'site.news.archive', 'site.news.index', 'site.news.show', 'site.packages.index',
        'site.packages.show', 'site.page', 'site.preview', 'site.products.index',
        'site.products.show', 'site.robots', 'site.search', 'site.sitemap',
        'site.sitemap.city', 'site.sitemap.content', 'site.solutions.index',
        'site.solutions.show', 'site.tags.show',
    ];

    public function handle(): int
    {
        $checks = [
            '插件启用状态'   => $this->checkPluginEnabled(),
            '迁移完整性'     => $this->checkMigrations(),
            '结构性种子'     => $this->checkStructuralSeeds(),
            '关键路由'       => $this->checkRoutes(),
            '内容配置完整性' => $this->checkContentHealth(),
            '首页响应'       => $this->checkHomepageResponse(),
            '媒体磁盘可写'   => $this->checkMediaDiskWritable(),
        ];

        $lines      = ['# 官网插件健康检查报告', ''];
        $hasFailure = false;

        foreach ($checks as $name => $result) {
            $lines[] = ($result['ok'] ? '[x] ' : '[ ] ')."**{$name}**：{$result['detail']}";

            $hasFailure = $hasFailure || ! $result['ok'];
        }

        $this->line(implode("\n", $lines));

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 插件是否启用：直接查 `plugins.is_enabled`，不依赖 SiteServiceProvider
     * 的 protected 方法，也不吃 24 小时缓存——诊断工具要看当下的真实状态。
     *
     * @return array{ok: bool, detail: string}
     */
    private function checkPluginEnabled(): array
    {
        try {
            $enabled = DB::table('plugins')
                ->where('slug', SiteServiceProvider::PLUGIN_SLUG)
                ->where('is_enabled', true)
                ->exists();
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => 'plugins 表查询失败（可能未迁移）：'.$e->getMessage()];
        }

        return $enabled
            ? ['ok' => true, 'detail' => '已启用']
            : ['ok' => false, 'detail' => '未启用，前台不会注册路由——在后台"插件管理"启用，或重跑 filamentboot-site:install'];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function checkMigrations(): array
    {
        $missing = array_values(array_filter(
            self::CORE_TABLES,
            fn (string $table): bool => ! Schema::hasTable($table)
        ));

        if ($missing !== []) {
            return ['ok' => false, 'detail' => count($missing).' 张表未迁移：'.implode('、', $missing)];
        }

        return ['ok' => true, 'detail' => count(self::CORE_TABLES).' 张核心表齐全'];
    }

    /**
     * 结构性种子（权限点）是否跑完——按 SitePermissionSeeder::permissions() 的
     * 完整清单核对，不再靠猜前缀。
     *
     * @return array{ok: bool, detail: string}
     */
    private function checkStructuralSeeds(): array
    {
        $expected = SitePermissionSeeder::permissions();

        try {
            // guard 固定 'admin'，与 SitePermissionSeeder::GUARD 一致
            $existing = Permission::where('guard_name', 'admin')->pluck('name')->all();
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => 'permissions 表查询失败（可能未迁移）：'.$e->getMessage()];
        }

        $missing = array_values(array_diff($expected, $existing));

        if ($missing !== []) {
            $shown = implode('、', array_slice($missing, 0, 5)).(count($missing) > 5 ? ' 等' : '');

            return ['ok' => false, 'detail' => count($missing)." 个官网权限点缺失（SitePermissionSeeder 未执行）：{$shown}"];
        }

        return ['ok' => true, 'detail' => count($expected).' 个官网权限点齐全'];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function checkRoutes(): array
    {
        $missing = array_values(array_filter(
            self::CRITICAL_ROUTES,
            fn (string $name): bool => ! Route::has($name)
        ));

        if ($missing !== []) {
            return ['ok' => false, 'detail' => count($missing).' 个关键路由未注册：'.implode('、', $missing)];
        }

        return ['ok' => true, 'detail' => count(self::CRITICAL_ROUTES).' 个关键路由均可解析'];
    }

    /**
     * 内容配置完整性：复用 SiteHealthCheck，但先把"settings 表未迁移"与
     * "配置已齐全"分开报——两者都会让 SiteHealthCheck::missing() 返回空数组，
     * 直接转发 passes() 会把前者误判成后者。
     *
     * @return array{ok: bool, detail: string}
     */
    private function checkContentHealth(): array
    {
        if (! Schema::hasTable('settings')) {
            return ['ok' => false, 'detail' => 'settings 表未迁移，暂无法判断内容配置是否完整——先跑 migrate'];
        }

        $health = app(SiteHealthCheck::class);

        return $health->passes()
            ? ['ok' => true, 'detail' => '发布前必填项已配置完整']
            : ['ok' => false, 'detail' => $health->summary()];
    }

    /**
     * 首页 HTTP 状态与响应头：真实发一次请求（不是进程内 dispatch），
     * 与 CLAUDE.md「公开页零 session、整页缓存」那节手工用 curl 验的是同一件事。
     *
     * @return array{ok: bool, detail: string}
     */
    private function checkHomepageResponse(): array
    {
        try {
            $url = route('site.home');
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => '无法解析 site.home 路由（插件未启用或路由未注册）：'.$e->getMessage()];
        }

        try {
            $response = Http::timeout(5)->get($url);
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => "请求 {$url} 失败：".$e->getMessage()];
        }

        $issues = [];

        if ($response->status() !== 200) {
            $issues[] = "状态码 {$response->status()}（期望 200）";
        }

        $cacheControl = (string) $response->header('Cache-Control');

        if (! str_contains($cacheControl, 'public')) {
            $issues[] = 'Cache-Control 未含 public（实际：'.($cacheControl !== '' ? $cacheControl : '空').'）';
        }

        if ($response->header('Set-Cookie')) {
            $issues[] = '响应带 Set-Cookie，公开页整页缓存会失效';
        }

        return $issues === []
            ? ['ok' => true, 'detail' => "{$url} 返回 200，Cache-Control 含 public，无 Set-Cookie"]
            : ['ok' => false, 'detail' => implode('；', $issues)];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function checkMediaDiskWritable(): array
    {
        $disk = app(UploadSettings::class)->default_disk;
        $path = 'filamentboot-site-doctor-probe-'.uniqid().'.tmp';

        try {
            Storage::disk($disk)->put($path, 'doctor-probe');
            $ok = Storage::disk($disk)->exists($path);
            Storage::disk($disk)->delete($path);
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => "磁盘 [{$disk}] 探测写入失败：".$e->getMessage()];
        }

        return $ok
            ? ['ok' => true, 'detail' => "磁盘 [{$disk}] 可写"]
            : ['ok' => false, 'detail' => "磁盘 [{$disk}] 写入后读不到文件"];
    }
}
