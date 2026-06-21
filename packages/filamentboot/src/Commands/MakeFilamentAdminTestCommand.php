<?php

namespace FilamentAdmin\Commands;

use FilamentAdmin\Services\StubGenerator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandExitCode;

/**
 * 生成 FilamentAdmin FeatureTest stub 命令（FEAT-03 / D-28 薄包装）
 *
 * 薄包装命令，构造注入 StubGenerator，将 FeatureTest stub 渲染后写入用户项目 tests/Feature 目录。
 * 变量绑定对齐 PublishCommand::publishFeatureTest 第 285-291 行，确保生成文件可直接运行。
 * 不重写渲染/写文件逻辑，全部委托 StubGenerator 处理（D-28 零重复原则）。
 *
 * 使用示例：
 *   php artisan make:filament-admin-test Product
 *   php artisan make:filament-admin-test Product --force
 */
class MakeFilamentAdminTestCommand extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'make:filament-admin-test
        {name : 模型类名（PascalCase，如 Product），将生成 ProductResourceTest.php}
        {--path=  : 输出根路径（默认 tests/Feature/）}
        {--force  : 强制覆盖已存在文件}';

    /**
     * 命令说明
     *
     * @var string
     */
    protected $description = '生成 FilamentAdmin FeatureTest stub 到用户项目（FEAT-03）';

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
     * 校验 name 参数格式（T-03-03 PascalCase 防护），渲染 FeatureTest stub，写入目标路径。
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

        // 路径校验：拒绝 .. 路径上溯（T-03-04）
        $customPath = (string) ($this->option('path') ?? '');
        if ($customPath !== '' && str_contains($customPath, '..')) {
            $this->error('--path 不允许包含 .. 路径上溯');

            return CommandExitCode::FAILURE;
        }

        // 推导变量（对齐 PublishCommand::publishFeatureTest 第 285-291 行）
        $resourceNamespace = $this->generator->deriveResourceNamespace($name);
        $appModelNamespace = $this->generator->deriveModelNamespace();

        // 渲染 FeatureTest stub
        $content = $this->generator->renderStub('FeatureTest', [
            'namespace'         => 'Tests\\Feature',
            'resourceNamespace' => $resourceNamespace,
            'resource'          => $name.'Resource',
            'model'             => $name,
            'modelLabel'        => $name,
            'appModelNamespace' => $appModelNamespace,
        ]);

        if ($content === '') {
            $this->error('Stub 渲染失败：FeatureTest.stub 不存在');

            return CommandExitCode::FAILURE;
        }

        // 写文件（IO 输出保留在命令层，D-28）
        $targetPath = base_path('tests/Feature/'.$name.'ResourceTest.php');
        $written    = $this->generator->writeFile($targetPath, $content, (bool) $this->option('force'));

        if ($written) {
            $this->info("已生成: {$targetPath}");
        } else {
            $this->warn("Skipped: {$targetPath} (use --force to overwrite)");
        }

        return CommandExitCode::SUCCESS;
    }
}
