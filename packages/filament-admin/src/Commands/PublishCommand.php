<?php

namespace FilamentAdmin\Commands;

use FilamentAdmin\Services\StubGenerator;
use Illuminate\Console\Command;

/**
 * 发布 FilamentAdmin 扩展 stub 命令（COMPLY-02）
 *
 * 用户通过此命令将包内置的 Model/Resource/Migration/FeatureTest
 * stub 渲染后写入用户项目，支持自定义路径、强制覆盖、批量发布等操作。
 * 支持 D-01~D-06 + D-11 全部决策契约。
 * 重构后委托 StubGenerator 处理渲染/校验/写文件，IO 输出仍保留在命令层（D-28）。
 *
 * 使用示例：
 *   php artisan filament-admin:publish --model=Product
 *   php artisan filament-admin:publish --resource=Product
 *   php artisan filament-admin:publish --all
 *   php artisan filament-admin:publish --model=Product --all
 *   php artisan filament-admin:publish --resource=Product --path=app/Filament/Reseller
 */
class PublishCommand extends Command
{
    /**
     * 命令签名（含 D-01~D-06 全部 8 个参数）
     *
     * @var string
     */
    protected $signature = 'filament-admin:publish
        {--model=         : 发布指定 Model 的扩展 stub（如 --model=Product）}
        {--resource=      : 发布指定 Resource 的扩展 stub（如 --resource=Product）}
        {--all            : 发布全套（无其他参数时）或为指定 name 补齐四件套（配合 --model/--resource）}
        {--path=          : Resource 输出根路径（默认 app/Filament/Resources/）}
        {--with-models    : 在 --path 对应的 panel 子目录下生成独立 Model 副本}
        {--force          : 覆盖已存在的文件（默认 skip if exists）}
        {--only=          : 与 --all 配合，只生成指定名称子集，逗号分隔（如 --only=AdminUser,Department）}
        {--except=        : 与 --all 配合，排除指定名称，逗号分隔（如 --except=Menu,LoginLog）}';

    /**
     * 命令说明
     *
     * @var string
     */
    protected $description = '发布 FilamentAdmin 扩展 stub 到用户项目（支持 --model/--resource/--all/--path 等选项）';

    /**
     * 内置资源名称列表（D-03 语义 A）
     *
     * @var list<string>
     */
    protected const BUILTIN_NAMES = ['AdminUser', 'Department', 'Menu', 'LoginLog'];

    /**
     * 构造函数，注入 StubGenerator 服务（D-28）
     */
    public function __construct(protected StubGenerator $generator)
    {
        parent::__construct();
    }

    /**
     * 命令主入口（D-03 双重语义分发）
     */
    public function handle(): int
    {
        $modelName    = (string) ($this->option('model') ?? '');
        $resourceName = (string) ($this->option('resource') ?? '');
        $all          = (bool) $this->option('all');
        $path         = (string) ($this->option('path') ?? '');

        // 校验 --path 不含路径上溯（T-01-11 安全防护）
        if ($path !== '' && ! $this->generator->validatePath($path)) {
            $this->error('--path 不允许包含 .. 路径上溯');

            return self::FAILURE;
        }

        // 校验 name 格式（T-01-12 安全防护）
        if ($modelName !== '' && ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $modelName)) {
            $this->error("--model 的值必须是合法的 PHP 类名（PascalCase，仅含字母数字）：{$modelName}");

            return self::FAILURE;
        }

        if ($resourceName !== '' && ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $resourceName)) {
            $this->error("--resource 的值必须是合法的 PHP 类名（PascalCase，仅含字母数字）：{$resourceName}");

            return self::FAILURE;
        }

        // D-03 双重语义：--all 配合 --model 或 --resource 时，为指定 name 补齐四件套（语义 B）
        if ($all && ($modelName !== '' || $resourceName !== '')) {
            $name = $modelName !== '' ? $modelName : $resourceName;
            $this->publishSet($name);

            return self::SUCCESS;
        }

        // D-03 语义 A：单独使用 --all，发布全套内置资源
        if ($all) {
            return $this->publishAllBuiltin();
        }

        // 单件发布模式（跳过已存在文件不算失败，仍返回 SUCCESS；D-02）
        if ($modelName !== '') {
            $this->publishModel($modelName);

            return self::SUCCESS;
        }

        if ($resourceName !== '') {
            $published = $this->publishResource($resourceName);

            if ($published) {
                $modelClass    = $this->generator->deriveModelNamespace().'\\'.$resourceName;
                $resourceClass = $this->generator->deriveResourceNamespace($resourceName, (string) ($this->option('path') ?: 'app/Filament/Resources')).'\\'.$resourceName.'Resource';
                $this->printBindingExample($modelClass, $resourceClass);
            }

            return self::SUCCESS;
        }

        $this->error('请指定 --model / --resource / --all 至少一个；--help 查看用法。');

        return self::FAILURE;
    }

    /**
     * 发布所有内置资源的四件套（D-03 语义 A，受 --only/--except 筛选）
     *
     * 跳过已存在的文件不算失败，始终返回 SUCCESS（D-02）。
     */
    protected function publishAllBuiltin(): int
    {
        $names = $this->resolveBuiltinNames();

        foreach ($names as $name) {
            $this->publishSet($name);
        }

        return self::SUCCESS;
    }

    /**
     * 根据 --only / --except 筛选内置资源名称列表（D-06）
     *
     * @return list<string>
     */
    protected function resolveBuiltinNames(): array
    {
        $names = self::BUILTIN_NAMES;

        $only   = (string) ($this->option('only') ?? '');
        $except = (string) ($this->option('except') ?? '');

        if ($only !== '') {
            $allowed = array_map('trim', explode(',', $only));
            $names   = array_values(array_filter($names, fn ($n) => in_array($n, $allowed, true)));
        }

        if ($except !== '') {
            $excluded = array_map('trim', explode(',', $except));
            $names    = array_values(array_filter($names, fn ($n) => ! in_array($n, $excluded, true)));
        }

        return $names;
    }

    /**
     * 为指定 name 发布四件套（Model + Resource + Pages + Migration + FeatureTest）
     *
     * @param  string  $name  PascalCase 类名，如 Product
     */
    protected function publishSet(string $name): bool
    {
        $modelOk     = $this->publishModel($name);
        $resourceOk  = $this->publishResource($name);
        $migrationOk = $this->publishMigration($name);
        $testOk      = $this->publishFeatureTest($name);

        if ($resourceOk) {
            $modelClass    = $this->generator->deriveModelNamespace().'\\'.$name;
            $resourceClass = $this->generator->deriveResourceNamespace($name, (string) ($this->option('path') ?: 'app/Filament/Resources')).'\\'.$name.'Resource';
            $this->printBindingExample($modelClass, $resourceClass);
        }

        return $modelOk || $resourceOk || $migrationOk || $testOk;
    }

    /**
     * 发布 Model stub（D-01 + D-05）
     *
     * @param  string  $name  PascalCase 类名
     */
    protected function publishModel(string $name): bool
    {
        $namespace = $this->generator->deriveModelNamespace(
            $this->option('with-models') ? $this->generator->derivePanelPrefix((string) ($this->option('path') ?? '')) : null
        );
        $table   = $this->generator->toSnakeCase($this->generator->pluralize($name));
        $content = $this->generator->renderStub('Model', [
            'namespace' => $namespace,
            'class'     => $name,
            'table'     => $table,
        ]);

        if ($content === '') {
            return false;
        }

        $panelPrefix = $this->generator->derivePanelPrefix((string) ($this->option('path') ?? ''));
        $subDir      = ($this->option('with-models') && $panelPrefix !== '') ? $panelPrefix.'/' : '';
        $targetPath  = base_path('app/Models/'.$subDir.$name.'.php');

        $written = $this->generator->writeFile($targetPath, $content, (bool) $this->option('force'));

        if ($written) {
            $this->info("已生成: {$targetPath}");
        } else {
            $this->warn("Skipped: {$targetPath} (use --force to overwrite)");
        }

        return $written;
    }

    /**
     * 发布 Resource stub 及 3 个 Page 文件（D-01 + D-04）
     *
     * @param  string  $name  PascalCase 类名
     */
    protected function publishResource(string $name): bool
    {
        $path              = $this->option('path') ?: 'app/Filament/Resources';
        $resourceNamespace = $this->generator->deriveResourceNamespace($name, (string) $path);
        $modelNamespace    = $this->generator->deriveModelNamespace();
        $pluralName        = $this->generator->pluralize($name);

        $content = $this->generator->renderStub('Resource', [
            'namespace'      => $resourceNamespace,
            'class'          => $name.'Resource',
            'model'          => $name,
            'modelNamespace' => $modelNamespace,
            'modelLabel'     => $name,
            'pluralClass'    => $pluralName,
        ]);

        if ($content === '') {
            return false;
        }

        $resourceDir  = base_path($path.'/'.$pluralName);
        $resourceFile = $resourceDir.'/'.$name.'Resource.php';

        $resourceWritten = $this->generator->writeFile($resourceFile, $content, (bool) $this->option('force'));

        if ($resourceWritten) {
            $this->info("已生成: {$resourceFile}");
        } else {
            $this->warn("Skipped: {$resourceFile} (use --force to overwrite)");
        }

        // 生成 3 个 Page 文件（内嵌模板，不依赖外部 Page.stub）
        $pagesDir = $resourceDir.'/Pages';

        $listPath    = $pagesDir.'/List'.$pluralName.'.php';
        $listWritten = $this->generator->writeFile($listPath, $this->buildListPageContent($resourceNamespace, $name, $pluralName), (bool) $this->option('force'));

        if ($listWritten) {
            $this->info("已生成: {$listPath}");
        } else {
            $this->warn("Skipped: {$listPath} (use --force to overwrite)");
        }

        $createPath    = $pagesDir.'/Create'.$name.'.php';
        $createWritten = $this->generator->writeFile($createPath, $this->buildCreatePageContent($resourceNamespace, $name), (bool) $this->option('force'));

        if ($createWritten) {
            $this->info("已生成: {$createPath}");
        } else {
            $this->warn("Skipped: {$createPath} (use --force to overwrite)");
        }

        $editPath    = $pagesDir.'/Edit'.$name.'.php';
        $editWritten = $this->generator->writeFile($editPath, $this->buildEditPageContent($resourceNamespace, $name), (bool) $this->option('force'));

        if ($editWritten) {
            $this->info("已生成: {$editPath}");
        } else {
            $this->warn("Skipped: {$editPath} (use --force to overwrite)");
        }

        return $resourceWritten || $listWritten || $createWritten || $editWritten;
    }

    /**
     * 发布 Migration stub（D-01）
     *
     * @param  string  $name  PascalCase 类名
     */
    protected function publishMigration(string $name): bool
    {
        $table   = $this->generator->toSnakeCase($this->generator->pluralize($name));
        $content = $this->generator->renderStub('Migration', [
            'table' => $table,
        ]);

        if ($content === '') {
            return false;
        }

        $targetPath = base_path(
            'database/migrations/'.date('Y_m_d_His').'_create_'.$table.'_table.php'
        );

        $written = $this->generator->writeFile($targetPath, $content, (bool) $this->option('force'));

        if ($written) {
            $this->info("已生成: {$targetPath}");
        } else {
            $this->warn("Skipped: {$targetPath} (use --force to overwrite)");
        }

        return $written;
    }

    /**
     * 发布 FeatureTest stub（D-01）
     *
     * @param  string  $name  PascalCase 类名
     */
    protected function publishFeatureTest(string $name): bool
    {
        $path              = $this->option('path') ?: 'app/Filament/Resources';
        $resourceNamespace = $this->generator->deriveResourceNamespace($name, (string) $path);
        $appModelNamespace = $this->generator->deriveModelNamespace();
        $content           = $this->generator->renderStub('FeatureTest', [
            'namespace'         => 'Tests\\Feature',
            'resourceNamespace' => $resourceNamespace,
            'resource'          => $name.'Resource',
            'model'             => $name,
            'modelLabel'        => $name,
            'appModelNamespace' => $appModelNamespace,
        ]);

        if ($content === '') {
            return false;
        }

        $targetPath = base_path('tests/Feature/'.$name.'ResourceTest.php');

        $written = $this->generator->writeFile($targetPath, $content, (bool) $this->option('force'));

        if ($written) {
            $this->info("已生成: {$targetPath}");
        } else {
            $this->warn("Skipped: {$targetPath} (use --force to overwrite)");
        }

        return $written;
    }

    /**
     * 构建 ListXxx Page 类的文件内容
     *
     * @param  string  $resourceNamespace  Resource 命名空间（含 Plural 子目录）
     * @param  string  $name  PascalCase 类名
     * @param  string  $pluralName  复数类名
     */
    protected function buildListPageContent(string $resourceNamespace, string $name, string $pluralName): string
    {
        return <<<PHP
<?php

namespace {$resourceNamespace}\\Pages;

use {$resourceNamespace}\\{$name}Resource;
use Filament\Resources\Pages\ListRecords;

/**
 * {$name} 列表页
 */
class List{$pluralName} extends ListRecords
{
    protected static string \$resource = {$name}Resource::class;
}
PHP;
    }

    /**
     * 构建 CreateXxx Page 类的文件内容
     *
     * @param  string  $resourceNamespace  Resource 命名空间（含 Plural 子目录）
     * @param  string  $name  PascalCase 类名
     */
    protected function buildCreatePageContent(string $resourceNamespace, string $name): string
    {
        return <<<PHP
<?php

namespace {$resourceNamespace}\\Pages;

use {$resourceNamespace}\\{$name}Resource;
use Filament\Resources\Pages\CreateRecord;

/**
 * {$name} 新建页
 */
class Create{$name} extends CreateRecord
{
    protected static string \$resource = {$name}Resource::class;
}
PHP;
    }

    /**
     * 构建 EditXxx Page 类的文件内容
     *
     * @param  string  $resourceNamespace  Resource 命名空间（含 Plural 子目录）
     * @param  string  $name  PascalCase 类名
     */
    protected function buildEditPageContent(string $resourceNamespace, string $name): string
    {
        return <<<PHP
<?php

namespace {$resourceNamespace}\\Pages;

use {$resourceNamespace}\\{$name}Resource;
use Filament\Resources\Pages\EditRecord;

/**
 * {$name} 编辑页
 */
class Edit{$name} extends EditRecord
{
    protected static string \$resource = {$name}Resource::class;
}
PHP;
    }

    /**
     * 在发布完成后输出 FilamentAdminPlugin 绑定示例代码
     *
     * @param  string  $modelClass  已发布的 Model 完整类名（不含前导反斜线）
     * @param  string  $resourceClass  已发布的 Resource 完整类名（不含前导反斜线）
     */
    protected function printBindingExample(string $modelClass, string $resourceClass): void
    {
        $this->newLine();
        $this->info('========================================');
        $this->info('请将以下代码添加到 AdminPanelProvider::panel() 中：');
        $this->newLine();
        $this->line('->plugins([');
        $this->line('    FilamentAdminPlugin::make()');
        $this->line("        ->adminUserModel(\\{$modelClass}::class)");
        $this->line("        ->adminUserResource(\\{$resourceClass}::class),");
        $this->line('])');
        $this->info('========================================');
    }
}
