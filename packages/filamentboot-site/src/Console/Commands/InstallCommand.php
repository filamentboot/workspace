<?php

namespace Filamentboot\FilamentbootSite\Console\Commands;

use Filamentboot\FilamentbootSite\Database\Seeders\SiteDemoSeeder;
use Filamentboot\FilamentbootSite\Database\Seeders\SiteMenuSeeder;
use Filamentboot\FilamentbootSite\Database\Seeders\SiteNewsSeeder;
use Filamentboot\FilamentbootSite\Database\Seeders\SitePermissionSeeder;
use Filamentboot\FilamentbootSite\Database\Seeders\SiteRoleSeeder;
use Filamentboot\Models\Plugin;
use Filamentboot\Services\PluginManager;
use Illuminate\Console\Command;

/**
 * filamentboot-site 一键安装命令
 *
 * 七步顺序执行：
 * Step 1-2：发布配置与前端资源
 * Step 3：migrate（25 张表由 SiteServiceProvider::loadMigrationsFrom 自动加载，
 *          不 publish migrations，避免与 loadMigrationsFrom 同名类冲突）
 * Step 4：三个结构性种子（权限点 / 三层角色 / 后台导航菜单），必需，
 *          否则除超管外无人能进官网管理
 * Step 5：扫描并启用插件（plugin:scan 等价逻辑 + PluginManager::enable()）
 * Step 6：--with-demo 时追加播种演示内容（默认不跑，演示数据是开关不是无条件行为）
 * Step 7：输出安装报告
 */
class InstallCommand extends Command
{
    /** @var string */
    protected $signature = 'filamentboot-site:install
                            {--force : 覆盖已发布的配置与前端资源}
                            {--with-demo : 附带播种演示内容（案例/方案/产品/资讯等示例内容）}';

    /** @var string */
    protected $description = '安装 filamentboot-site：发布资源，执行迁移，写入结构性数据，启用插件';

    public function handle(PluginManager $manager): int
    {
        $this->components->info('开始安装 filamentboot-site...');

        $publishForce = (bool) $this->option('force');

        // Step 1：发布配置文件
        $this->callSilently('vendor:publish', [
            '--tag'   => 'filamentboot-site-config',
            '--ansi'  => false,
            '--force' => $publishForce,
        ]);
        $this->components->info('✓ 配置文件已发布到 config/filamentboot-site.php');

        // Step 2：发布前端资源（主题 CSS 与 site.js）
        $this->callSilently('vendor:publish', [
            '--tag'   => 'filamentboot-site-assets',
            '--ansi'  => false,
            '--force' => $publishForce,
        ]);
        $this->components->info('✓ 前端资源已发布到 resources/{css,js}/vendor/filamentboot-site/');

        // Step 3：迁移
        $this->components->info('正在执行数据库迁移...');

        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            $this->components->error('迁移失败，请检查数据库配置后重试');

            return self::FAILURE;
        }

        $this->components->info('✓ 数据库迁移完成');

        // Step 4：结构性种子
        $this->callSilently('db:seed', ['--class' => SitePermissionSeeder::class, '--force' => true]);
        $this->callSilently('db:seed', ['--class' => SiteRoleSeeder::class, '--force' => true]);
        $this->callSilently('db:seed', ['--class' => SiteMenuSeeder::class, '--force' => true]);
        $this->components->info('✓ 权限点、三层角色、后台导航菜单已写入');

        // Step 5：扫描并启用插件
        if (! $this->enablePlugin($manager)) {
            return self::FAILURE;
        }

        // Step 6：可选演示内容
        if ($this->option('with-demo')) {
            $this->callSilently('db:seed', ['--class' => SiteDemoSeeder::class, '--force' => true]);
            $this->callSilently('db:seed', ['--class' => SiteNewsSeeder::class, '--force' => true]);
            $this->components->info('✓ 演示内容已播种（案例/方案/产品/静态页/资讯等）');
        }

        $this->callSilently('cache:clear');

        // Step 7：安装报告
        $this->newLine();
        $this->components->success('filamentboot-site 安装完成！');
        $this->newLine();
        $this->line('  前台地址：<fg=cyan>'.$this->frontendUrl().'</>');
        $this->line('  后台入口：登录 /admin 后台，左侧导航「官网管理」分组');
        $this->newLine();

        if (! $this->option('with-demo')) {
            $this->line('如需查看演示效果，重新运行并加 <fg=cyan>--with-demo</>：');
            $this->line('  <fg=cyan>php artisan filamentboot-site:install --with-demo</>');
        }

        return self::SUCCESS;
    }

    /**
     * 扫描已安装插件并启用 filamentboot-site
     *
     * 幂等：已启用时跳过 enable() 调用，直接报告已启用。
     */
    protected function enablePlugin(PluginManager $manager): bool
    {
        $manager->syncFromInstalled();

        $plugin = Plugin::where('package_name', 'filamentboot/filamentboot-site')->first();

        if ($plugin === null) {
            $this->components->error('未在 vendor/composer/installed.json 中找到 filamentboot/filamentboot-site，请确认已 composer require。');

            return false;
        }

        if ($plugin->is_enabled) {
            $this->components->info('✓ 插件已启用，跳过');

            return true;
        }

        try {
            $manager->enable($plugin);
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return false;
        }

        $this->components->info('✓ 插件已启用，前台路由已注册');

        return true;
    }

    /**
     * 按当前路由挂载模式算出前台首页地址，仅用于安装报告展示
     *
     * 不能假设固定的 "/"——挂载模式（prefix/root/domain）由
     * config('filamentboot-site.route.mode') 决定，见 config/filamentboot-site.php 顶部说明。
     */
    protected function frontendUrl(): string
    {
        $mode   = config('filamentboot-site.route.mode', 'prefix');
        $domain = config('filamentboot-site.route.domain');

        if ($mode === 'domain' && $domain) {
            return 'https://'.$domain.'/';
        }

        if ($mode === 'root') {
            return url('/');
        }

        // prefix 模式，或 domain 模式未配置域名时降级为 prefix（config 里注释过这条降级规则）
        $prefix = trim((string) config('filamentboot-site.route.prefix', 'site'), '/');

        return url('/'.$prefix.'/');
    }
}
