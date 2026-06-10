<?php

namespace FilamentAdmin\Commands;

use FilamentAdmin\FilamentAdminPlugin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * 发布 FilamentAdmin 扩展 stub 命令（COMPLY-02）
 *
 * 用户通过此命令将包内置的 Model/Resource/Migration/FeatureTest
 * stub 渲染后写入用户项目，支持自定义路径、强制覆盖、批量发布等操作。
 * 支持 D-01~D-06 + D-11 全部决策契约。
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
     * 命令主入口（D-03 双重语义分发）
     */
    public function handle(): int
    {
        $modelName    = (string) ($this->option('model') ?? '');
        $resourceName = (string) ($this->option('resource') ?? '');
        $all          = (bool) $this->option('all');
        $path         = (string) ($this->option('path') ?? '');

        // 校验 --path 不含路径上溯（T-01-11 安全防护）
        if ($path !== '' && ! $this->validatePath($path)) {
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

            return $this->publishSet($name) ? self::SUCCESS : self::FAILURE;
        }

        // D-03 语义 A：单独使用 --all，发布全套内置资源
        if ($all) {
            return $this->publishAllBuiltin();
        }

        // 单件发布模式
        if ($modelName !== '') {
            return $this->publishModel($modelName) ? self::SUCCESS : self::FAILURE;
        }

        if ($resourceName !== '') {
            $success = $this->publishResource($resourceName);

            if ($success) {
                $modelClass    = $this->deriveModelNamespace().'\\'.$resourceName;
                $resourceClass = $this->deriveResourceNamespace($resourceName).'\\'.$resourceName.'Resource';
                $this->printBindingExample($modelClass, $resourceClass);
            }

            return $success ? self::SUCCESS : self::FAILURE;
        }

        $this->error('请指定 --model / --resource / --all 至少一个；--help 查看用法。');

        return self::FAILURE;
    }

    /**
     * 发布所有内置资源的四件套（D-03 语义 A，受 --only/--except 筛选）
     */
    protected function publishAllBuiltin(): int
    {
        $names   = $this->resolveBuiltinNames();
        $success = true;

        foreach ($names as $name) {
            if (! $this->publishSet($name)) {
                $success = false;
            }
        }

        return $success ? self::SUCCESS : self::FAILURE;
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
            $modelClass    = $this->deriveModelNamespace().'\\'.$name;
            $resourceClass = $this->deriveResourceNamespace($name).'\\'.$name.'Resource';
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
        $namespace  = $this->deriveModelNamespace();
        $table      = $this->toSnakeCase($this->pluralize($name));
        $content    = $this->renderStub('Model', [
            'namespace' => $namespace,
            'class'     => $name,
            'table'     => $table,
        ]);

        $panelPrefix = $this->derivePanelPrefix();
        $subDir      = ($this->option('with-models') && $panelPrefix !== '') ? $panelPrefix.'/' : '';
        $targetPath  = base_path('app/Models/'.$subDir.$name.'.php');

        return $this->writeFile($targetPath, $content);
    }

    /**
     * 发布 Resource stub 及 3 个 Page 文件（D-01 + D-04）
     *
     * @param  string  $name  PascalCase 类名
     */
    protected function publishResource(string $name): bool
    {
        $resourceNamespace = $this->deriveResourceNamespace($name);
        $modelNamespace    = $this->deriveModelNamespace();
        $pluralName        = $this->pluralize($name);
        $path              = $this->option('path') ?: 'app/Filament/Resources';

        $content    = $this->renderStub('Resource', [
            'namespace'         => $resourceNamespace,
            'class'             => $name.'Resource',
            'model'             => $name,
            'modelNamespace'    => $modelNamespace,
            'modelLabel'        => $name,
            'pluralClass'       => $pluralName,
            'resourceNamespace' => $pluralName,
        ]);

        $resourceDir  = base_path($path.'/'.$pluralName);
        $resourceFile = $resourceDir.'/'.$name.'Resource.php';
        $resourceOk   = $this->writeFile($resourceFile, $content);

        // 生成 3 个 Page 文件（内嵌模板，不依赖外部 Page.stub）
        $pagesDir = $resourceDir.'/Pages';
        $listOk   = $this->writeFile(
            $pagesDir.'/List'.$pluralName.'.php',
            $this->buildListPageContent($resourceNamespace, $name, $pluralName)
        );
        $createOk = $this->writeFile(
            $pagesDir.'/Create'.$name.'.php',
            $this->buildCreatePageContent($resourceNamespace, $name)
        );
        $editOk = $this->writeFile(
            $pagesDir.'/Edit'.$name.'.php',
            $this->buildEditPageContent($resourceNamespace, $name)
        );

        return $resourceOk || $listOk || $createOk || $editOk;
    }

    /**
     * 发布 Migration stub（D-01）
     *
     * @param  string  $name  PascalCase 类名
     */
    protected function publishMigration(string $name): bool
    {
        $table   = $this->toSnakeCase($this->pluralize($name));
        $content = $this->renderStub('Migration', [
            'table' => $table,
        ]);

        $targetPath = base_path(
            'database/migrations/'.date('Y_m_d_His').'_create_'.$table.'_table.php'
        );

        return $this->writeFile($targetPath, $content);
    }

    /**
     * 发布 FeatureTest stub（D-01）
     *
     * @param  string  $name  PascalCase 类名
     */
    protected function publishFeatureTest(string $name): bool
    {
        $pluralName = $this->pluralize($name);
        $content    = $this->renderStub('FeatureTest', [
            'namespace'         => 'Tests\\Feature',
            'resourceNamespace' => $pluralName,
            'resource'          => $name.'Resource',
            'model'             => $name,
            'modelLabel'        => $name,
        ]);

        $targetPath = base_path('tests/Feature/'.$name.'ResourceTest.php');

        return $this->writeFile($targetPath, $content);
    }

    /**
     * 渲染 stub 文件，替换所有占位符（D-11 fallback 逻辑）
     *
     * 优先读取用户发布的自定义 stub（base_path('stubs/vendor/filament-admin/')），
     * 找不到时 fallback 到包内默认 stubs/ 目录。
     *
     * @param  string  $stubName  stub 文件名（不含 .stub 后缀）
     * @param  array<string,string>  $vars  占位符键值对
     */
    protected function renderStub(string $stubName, array $vars): string
    {
        // D-11: 优先读取用户发布的自定义 stub
        $userStub    = base_path('stubs/vendor/filament-admin/'.$stubName.'.stub');
        $packageStub = __DIR__.'/../../stubs/'.$stubName.'.stub';
        $stubPath    = file_exists($userStub) ? $userStub : $packageStub;

        $content = (string) file_get_contents($stubPath);

        foreach ($vars as $key => $value) {
            $content = str_replace('{{ '.$key.' }}', $value, $content);
        }

        return $content;
    }

    /**
     * 写入文件，处理文件冲突（D-02 skip if exists）
     *
     * @param  string  $targetPath  目标文件绝对路径
     * @param  string  $content  文件内容
     */
    protected function writeFile(string $targetPath, string $content): bool
    {
        if (File::exists($targetPath) && ! $this->option('force')) {
            $this->warn("Skipped: {$targetPath} (use --force to overwrite)");

            return false;
        }

        File::makeDirectory(dirname($targetPath), 0755, true, true);
        File::put($targetPath, $content);
        $this->info("已生成: {$targetPath}");

        return true;
    }

    /**
     * 校验 --path 参数安全性，拒绝路径遍历（T-01-11 安全防护）
     *
     * @param  string  $path  用户传入的路径字符串
     */
    protected function validatePath(string $path): bool
    {
        if (str_contains($path, '..') || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:\\\\#', $path)) {
            $this->error('--path 不允许包含 .. 路径上溯');

            return false;
        }

        return true;
    }

    /**
     * 推导 Model 所在命名空间（D-05）
     *
     * 默认为 App\Models；若传入 --with-models 且 --path 非默认路径，
     * 则在 App\Models\{PanelPrefix} 下生成独立 Model 副本。
     */
    protected function deriveModelNamespace(): string
    {
        if (! $this->option('with-models')) {
            return 'App\\Models';
        }

        $panelPrefix = $this->derivePanelPrefix();

        return $panelPrefix !== '' ? 'App\\Models\\'.$panelPrefix : 'App\\Models';
    }

    /**
     * 推导 Resource 所在命名空间（D-04）
     *
     * 根据 --path 推导命名空间，如：
     * - 默认（app/Filament/Resources） → App\Filament\Resources\{Plural}
     * - --path=app/Filament/Reseller → App\Filament\Reseller\Resources\{Plural}
     *
     * @param  string  $name  PascalCase 类名
     */
    protected function deriveResourceNamespace(string $name): string
    {
        $path        = $this->option('path') ?: 'app/Filament/Resources';
        $pluralName  = $this->pluralize($name);

        // 将路径转换为命名空间：app/Filament/Reseller → App\Filament\Reseller
        $nsBase = implode('\\', array_map(
            fn ($seg) => ucfirst($seg),
            explode('/', $path)
        ));

        return $nsBase.'\\'.$pluralName;
    }

    /**
     * 从 --path 末段推导 PanelPrefix（用于 --with-models 时的 Model 子目录）
     *
     * 如 --path=app/Filament/Reseller → PanelPrefix = Reseller
     * 如 --path=app/Filament/Resources（默认）→ PanelPrefix = ''
     */
    protected function derivePanelPrefix(): string
    {
        $path = $this->option('path') ?: '';

        if ($path === '' || $path === 'app/Filament/Resources') {
            return '';
        }

        $segments = explode('/', rtrim($path, '/'));

        return ucfirst(end($segments));
    }

    /**
     * 简单复数化（追加 s，如 Product → Products）
     *
     * 注意：复数特例（y→ies、category→categories 等）延迟到 Phase 3 FEAT-03
     * 通过 doctrine/inflector 处理，当前仅做最简实现以避免引入新依赖。
     *
     * @param  string  $name  单数 PascalCase 名称
     */
    protected function pluralize(string $name): string
    {
        return $name.'s';
    }

    /**
     * 将 PascalCase 转为 snake_case（如 AdminUser → admin_user）
     *
     * @param  string  $name  PascalCase 名称
     */
    protected function toSnakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name));
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
