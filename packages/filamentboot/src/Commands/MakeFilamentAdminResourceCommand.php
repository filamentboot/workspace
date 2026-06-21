<?php

namespace FilamentAdmin\Commands;

use FilamentAdmin\Services\StubGenerator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandExitCode;

/**
 * 生成 FilamentAdmin Resource stub 命令（FEAT-03 / D-28 薄包装）
 *
 * 薄包装命令，构造注入 StubGenerator，将 Resource stub 及三个 Page 文件渲染后写入用户项目。
 * 接受 Product 或 ProductResource 两种形式的 name 参数，自动剥离 Resource 后缀。
 * 页面构建方法统一委托 StubGenerator::buildListPageContent/buildCreatePageContent/buildEditPageContent，
 * 不在命令层保留副本（D-28 单一来源）。
 *
 * 使用示例：
 *   php artisan make:filament-admin-resource Product
 *   php artisan make:filament-admin-resource ProductResource
 *   php artisan make:filament-admin-resource Product --force
 *   php artisan make:filament-admin-resource Product --path=app/Filament/Reseller
 */
class MakeFilamentAdminResourceCommand extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'make:filament-admin-resource
        {name : 模型类名或 Resource 类名（PascalCase，如 Product 或 ProductResource）}
        {--path=  : Resource 输出根路径（默认 app/Filament/Resources/）}
        {--force  : 强制覆盖已存在文件}';

    /**
     * 命令说明
     *
     * @var string
     */
    protected $description = '生成 FilamentAdmin Resource stub（含三个 Page 文件）到用户项目（FEAT-03）';

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
     * 校验 name 参数格式（T-03-03 PascalCase 防护），渲染 Resource stub 及三个 Page 文件，写入目标路径。
     * 接受 Product 或 ProductResource 两种输入，自动剥离 Resource 后缀得到 baseName。
     *
     * @return int 命令退出码（SUCCESS / FAILURE）
     */
    public function handle(): int
    {
        $input = (string) $this->argument('name');

        // 参数校验：name 必须是合法 PascalCase PHP 类名（T-03-03）
        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $input)) {
            $this->error("name 必须是合法 PascalCase PHP 类名（如 Product）：{$input}");

            return CommandExitCode::FAILURE;
        }

        // 支持 Product 或 ProductResource 两种输入，自动剥离 Resource 后缀（与 PublishCommand --resource 语义一致）
        $baseName = str_ends_with($input, 'Resource')
            ? substr($input, 0, -strlen('Resource'))
            : $input;

        // baseName 剥离后不应为空
        if ($baseName === '') {
            $this->error('name 剥离 Resource 后缀后为空，请传入有效的模型类名（如 Product）');

            return CommandExitCode::FAILURE;
        }

        // 路径校验：拒绝 .. 路径上溯（T-03-04）
        $customPath = (string) ($this->option('path') ?? '');
        if ($customPath !== '' && ! $this->generator->validatePath($customPath)) {
            $this->error('--path 不允许包含 .. 路径上溯');

            return CommandExitCode::FAILURE;
        }

        $path              = $customPath !== '' ? $customPath : 'app/Filament/Resources';
        $resourceNamespace = $this->generator->deriveResourceNamespace($baseName, $path);
        $modelNamespace    = $this->generator->deriveModelNamespace();
        $pluralName        = $this->generator->pluralize($baseName);
        $resourceClass     = $baseName.'Resource';
        $force             = (bool) $this->option('force');

        // 渲染 Resource stub
        $content = $this->generator->renderStub('Resource', [
            'namespace'      => $resourceNamespace,
            'class'          => $resourceClass,
            'model'          => $baseName,
            'modelNamespace' => $modelNamespace,
            'modelLabel'     => $baseName,
            'pluralClass'    => $pluralName,
        ]);

        if ($content === '') {
            $this->error('Stub 渲染失败：Resource.stub 不存在');

            return CommandExitCode::FAILURE;
        }

        // 写 Resource 主文件
        $resourceDir     = base_path($path.'/'.$pluralName);
        $resourceFile    = $resourceDir.'/'.$resourceClass.'.php';
        $resourceWritten = $this->generator->writeFile($resourceFile, $content, $force);

        if ($resourceWritten) {
            $this->info("已生成: {$resourceFile}");
        } else {
            $this->warn("Skipped: {$resourceFile} (use --force to overwrite)");
        }

        // 生成 3 个 Page 文件（委托 StubGenerator，D-28 单一来源，不在命令层重写页面构建逻辑）
        $pagesDir = $resourceDir.'/Pages';

        $listPath    = $pagesDir.'/List'.$pluralName.'.php';
        $listWritten = $this->generator->writeFile(
            $listPath,
            $this->generator->buildListPageContent($resourceNamespace, $resourceClass, $baseName, $pluralName),
            $force
        );

        if ($listWritten) {
            $this->info("已生成: {$listPath}");
        } else {
            $this->warn("Skipped: {$listPath} (use --force to overwrite)");
        }

        $createPath    = $pagesDir.'/Create'.$baseName.'.php';
        $createWritten = $this->generator->writeFile(
            $createPath,
            $this->generator->buildCreatePageContent($resourceNamespace, $resourceClass, $baseName),
            $force
        );

        if ($createWritten) {
            $this->info("已生成: {$createPath}");
        } else {
            $this->warn("Skipped: {$createPath} (use --force to overwrite)");
        }

        $editPath    = $pagesDir.'/Edit'.$baseName.'.php';
        $editWritten = $this->generator->writeFile(
            $editPath,
            $this->generator->buildEditPageContent($resourceNamespace, $resourceClass, $baseName),
            $force
        );

        if ($editWritten) {
            $this->info("已生成: {$editPath}");
        } else {
            $this->warn("Skipped: {$editPath} (use --force to overwrite)");
        }

        return CommandExitCode::SUCCESS;
    }
}
