<?php

namespace FilamentAdmin\Services;

use Illuminate\Support\Facades\File;

/**
 * Stub 渲染与文件写入共享服务类（D-28）
 *
 * 从 PublishCommand 抽取（D-28），供 PublishCommand 与四个 make:* 命令共用。
 * 提供 stub 渲染、文件写入、路径校验、命名空间推导、名称转换等通用方法，
 * 所有方法均为纯函数风格：不依赖 $this->option()、$this->warn() 等命令 IO，
 * 选项值由调用方（命令层）传入，IO 输出也由命令层负责。
 */
class StubGenerator
{
    /**
     * 渲染 stub 文件，替换所有 {{ key }} 占位符（D-11 fallback 逻辑）
     *
     * 优先读取用户发布的自定义 stub（base_path('stubs/vendor/filament-admin/')），
     * 找不到时 fallback 到包内默认 stubs/ 目录。
     * stub 不存在时返回空字符串（与 PublishCommand 原行为等价）。
     *
     * @param  string  $stubName  stub 文件名（不含 .stub 后缀）
     * @param  array<string,string>  $vars  占位符键值对
     * @return string 渲染后的内容，stub 不存在时返回空字符串
     */
    public function renderStub(string $stubName, array $vars): string
    {
        // D-11: 优先读取用户发布的自定义 stub
        $userStub    = base_path('stubs/vendor/filament-admin/'.$stubName.'.stub');
        $packageStub = __DIR__.'/../../stubs/'.$stubName.'.stub';
        $stubPath    = file_exists($userStub) ? $userStub : $packageStub;

        if (! file_exists($stubPath)) {
            return '';
        }

        $content = (string) file_get_contents($stubPath);

        foreach ($vars as $key => $value) {
            $content = str_replace('{{ '.$key.' }}', $value, $content);
        }

        return $content;
    }

    /**
     * 写入文件，处理文件冲突（D-02 skip if exists）
     *
     * 文件已存在且 force=false 时直接返回 false，不写入，也不输出任何文案；
     * force=true 或文件不存在时写入并返回 true。
     * IO 输出（info/warn）由调用方（命令层）负责。
     *
     * @param  string  $targetPath  目标文件绝对路径
     * @param  string  $content  文件内容
     * @param  bool  $force  是否强制覆盖（对应 --force 选项，由调用方传入）
     * @return bool 写入成功返回 true，跳过返回 false
     */
    public function writeFile(string $targetPath, string $content, bool $force = false): bool
    {
        if (File::exists($targetPath) && ! $force) {
            return false;
        }

        File::makeDirectory(dirname($targetPath), 0755, true, true);
        File::put($targetPath, $content);

        return true;
    }

    /**
     * 校验 --path 参数安全性，拒绝路径遍历（T-01-11 / T-03-01 安全防护）
     *
     * 拒绝条件：含 .. 路径上溯、绝对路径（/开头）、Windows 盘符（C:\）、非 app/ 前缀。
     * 不输出任何错误文案，纯返回 bool，由调用方负责 $this->error() 输出。
     *
     * @param  string  $path  用户传入的路径字符串
     * @return bool 安全时返回 true，存在安全风险时返回 false
     */
    public function validatePath(string $path): bool
    {
        if (str_contains($path, '..') || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:\\\\#', $path)) {
            return false;
        }

        // 限制 --path 必须位于 app/ 之内，避免写出到 storage/routes/config 等非 PSR-4 目录
        if ($path !== 'app' && ! str_starts_with($path, 'app/')) {
            return false;
        }

        return true;
    }

    /**
     * 简单复数化（追加 s，如 Product → Products）
     *
     * 注意：复数特例（y→ies、category→categories 等）延迟到 Phase 3 FEAT-03
     * 通过 doctrine/inflector 处理，当前仅做最简实现以避免引入新依赖。
     *
     * @param  string  $name  单数 PascalCase 名称
     * @return string 复数形式
     */
    public function pluralize(string $name): string
    {
        return $name.'s';
    }

    /**
     * 将 PascalCase 转为 snake_case（如 AdminUser → admin_user）
     *
     * @param  string  $name  PascalCase 名称
     * @return string snake_case 形式
     */
    public function toSnakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name));
    }

    /**
     * 推导 Model 所在命名空间（D-05）
     *
     * 默认返回 App\Models；若传入非空的 $panelPrefix，则返回 App\Models\{PanelPrefix}。
     * 调用方应在 --with-models 选项为 true 时传入 derivePanelPrefix() 的结果，
     * 否则传入 null 表示使用默认命名空间。
     *
     * @param  string|null  $panelPrefix  面板前缀（如 Reseller），为 null 或空字符串时使用默认命名空间
     * @return string Model 命名空间
     */
    public function deriveModelNamespace(?string $panelPrefix = null): string
    {
        if ($panelPrefix === null || $panelPrefix === '') {
            return 'App\\Models';
        }

        return 'App\\Models\\'.$panelPrefix;
    }

    /**
     * 推导 Resource 所在命名空间（D-04）
     *
     * 根据 $path 推导命名空间，如：
     * - 默认（app/Filament/Resources） → App\Filament\Resources\{Plural}
     * - app/Filament/Reseller → App\Filament\Reseller\{Plural}
     *
     * @param  string  $name  PascalCase 类名
     * @param  string  $path  Resource 输出根路径（对应 --path 选项，由调用方传入）
     * @return string Resource 命名空间
     */
    public function deriveResourceNamespace(string $name, string $path = 'app/Filament/Resources'): string
    {
        $pluralName = $this->pluralize($name);

        // 将路径转换为命名空间：app/Filament/Reseller → App\Filament\Reseller
        $nsBase = implode('\\', array_map(
            fn ($seg) => ucfirst($seg),
            explode('/', $path)
        ));

        return $nsBase.'\\'.$pluralName;
    }

    /**
     * 从 $path 末段推导 PanelPrefix（用于 --with-models 时的 Model 子目录）
     *
     * 如 app/Filament/Reseller → PanelPrefix = Reseller
     * 如 app/Filament/Resources（默认）→ PanelPrefix = ''
     *
     * @param  string  $path  Resource 输出根路径（对应 --path 选项，由调用方传入）
     * @return string 面板前缀，默认路径或空路径时返回空字符串
     */
    public function derivePanelPrefix(string $path = ''): string
    {
        if ($path === '' || $path === 'app/Filament/Resources') {
            return '';
        }

        $segments = explode('/', rtrim($path, '/'));

        return ucfirst(end($segments));
    }

    /**
     * 构建 List{Plural} Page 类的文件内容（D-28 单一来源，迁自 PublishCommand）
     *
     * 由 PublishCommand::publishResource() 和 MakeFilamentAdminResourceCommand 共同调用，
     * 不允许在命令层保留副本，统一通过此方法生成列表页文件内容。
     *
     * @param  string  $resourceNamespace  Resource 命名空间（含 Plural 子目录，如 App\Filament\Resources\Products）
     * @param  string  $resourceClass  Resource 类名（如 ProductResource）
     * @param  string  $modelClass  Model 类名（如 Product）
     * @param  string  $pluralClass  Model 复数类名（如 Products）
     * @return string 生成的 PHP 文件内容
     */
    public function buildListPageContent(string $resourceNamespace, string $resourceClass, string $modelClass, string $pluralClass): string
    {
        return <<<PHP
<?php

namespace {$resourceNamespace}\\Pages;

use {$resourceNamespace}\\{$resourceClass};
use Filament\Resources\Pages\ListRecords;

/**
 * {$modelClass} 列表页
 */
class List{$pluralClass} extends ListRecords
{
    protected static string \$resource = {$resourceClass}::class;
}
PHP;
    }

    /**
     * 构建 Create{Name} Page 类的文件内容（D-28 单一来源，迁自 PublishCommand）
     *
     * 由 PublishCommand::publishResource() 和 MakeFilamentAdminResourceCommand 共同调用，
     * 不允许在命令层保留副本，统一通过此方法生成新建页文件内容。
     *
     * @param  string  $resourceNamespace  Resource 命名空间（含 Plural 子目录）
     * @param  string  $resourceClass  Resource 类名（如 ProductResource）
     * @param  string  $modelClass  Model 类名（如 Product）
     * @return string 生成的 PHP 文件内容
     */
    public function buildCreatePageContent(string $resourceNamespace, string $resourceClass, string $modelClass): string
    {
        return <<<PHP
<?php

namespace {$resourceNamespace}\\Pages;

use {$resourceNamespace}\\{$resourceClass};
use Filament\Resources\Pages\CreateRecord;

/**
 * {$modelClass} 新建页
 */
class Create{$modelClass} extends CreateRecord
{
    protected static string \$resource = {$resourceClass}::class;
}
PHP;
    }

    /**
     * 构建 Edit{Name} Page 类的文件内容（D-28 单一来源，迁自 PublishCommand）
     *
     * 由 PublishCommand::publishResource() 和 MakeFilamentAdminResourceCommand 共同调用，
     * 不允许在命令层保留副本，统一通过此方法生成编辑页文件内容。
     *
     * @param  string  $resourceNamespace  Resource 命名空间（含 Plural 子目录）
     * @param  string  $resourceClass  Resource 类名（如 ProductResource）
     * @param  string  $modelClass  Model 类名（如 Product）
     * @return string 生成的 PHP 文件内容
     */
    public function buildEditPageContent(string $resourceNamespace, string $resourceClass, string $modelClass): string
    {
        return <<<PHP
<?php

namespace {$resourceNamespace}\\Pages;

use {$resourceNamespace}\\{$resourceClass};
use Filament\Resources\Pages\EditRecord;

/**
 * {$modelClass} 编辑页
 */
class Edit{$modelClass} extends EditRecord
{
    protected static string \$resource = {$resourceClass}::class;
}
PHP;
    }
}
