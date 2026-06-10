<?php

namespace FilamentAdmin\Commands;

use FilamentAdmin\Services\StubGenerator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandExitCode;

/**
 * 生成 FilamentAdmin Migration stub 命令（FEAT-03 / D-28 薄包装）
 *
 * 薄包装命令，构造注入 StubGenerator，将 Migration stub 渲染后写入用户项目数据库迁移目录。
 * 文件名含时间戳前缀（对齐 Laravel 迁移命名约定，PublishCommand 第 269-271 行）。
 * 不重写渲染/写文件逻辑，全部委托 StubGenerator 处理（D-28 零重复原则）。
 *
 * 使用示例：
 *   php artisan make:filament-admin-migration Product
 *   php artisan make:filament-admin-migration Product --force
 */
class MakeFilamentAdminMigrationCommand extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'make:filament-admin-migration
        {name : 模型类名（PascalCase，如 Product），将转为表名 products}
        {--path=  : 输出根路径（默认 database/migrations/）}
        {--force  : 强制覆盖已存在文件}';

    /**
     * 命令说明
     *
     * @var string
     */
    protected $description = '生成 FilamentAdmin Migration stub 到用户项目（FEAT-03）';

    /**
     * 构造函数，注入 StubGenerator 服务（D-28）
     */
    public function __construct(protected StubGenerator $generator)
    {
        parent::__construct();
    }

    /**
     * 命令主入口
     *
     * 校验 name 参数格式（T-03-03 PascalCase 防护），渲染 Migration stub，写入含时间戳的目标路径。
     *
     * @return int 命令退出码（SUCCESS / FAILURE）
     */
    public function handle(): int
    {
        $name = (string) $this->argument('name');

        // 参数校验：name 必须是合法 PascalCase PHP 类名（T-03-03）
        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->error("name 必须是合法 PascalCase PHP 类名（如 Product）：{$name}");

            return CommandExitCode::FAILURE;
        }

        // 路径校验：拒绝 .. 路径上溯（T-03-04，Migration 路径不经过 validatePath，但检查 --path 选项）
        $customPath = (string) ($this->option('path') ?? '');
        if ($customPath !== '' && str_contains($customPath, '..')) {
            $this->error('--path 不允许包含 .. 路径上溯');

            return CommandExitCode::FAILURE;
        }

        // 计算表名（snake_case + 复数）
        $table = $this->generator->toSnakeCase($this->generator->pluralize($name));

        // 渲染 Migration stub
        $content = $this->generator->renderStub('Migration', [
            'table' => $table,
        ]);

        if ($content === '') {
            $this->error('Stub 渲染失败：Migration.stub 不存在');

            return CommandExitCode::FAILURE;
        }

        // 写文件，文件名含时间戳（对齐 PublishCommand 第 269-271 行）
        $targetPath = base_path(
            'database/migrations/'.date('Y_m_d_His').'_create_'.$table.'_table.php'
        );
        $written = $this->generator->writeFile($targetPath, $content, (bool) $this->option('force'));

        if ($written) {
            $this->info("已生成: {$targetPath}");
        } else {
            $this->warn("Skipped: {$targetPath} (use --force to overwrite)");
        }

        return CommandExitCode::SUCCESS;
    }
}
