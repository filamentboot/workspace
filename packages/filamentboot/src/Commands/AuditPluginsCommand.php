<?php

namespace Filamentboot\Commands;

use Illuminate\Console\Command;

/**
 * 一方插件合规审查命令（MKTPLACE-09, D-12-12）
 *
 * 遍历 packages/filamentboot-* 目录，检查每个包的：
 * - Plugin 类是否实现 Filament\Contracts\Plugin 接口（通过源码 grep）
 * - composer.json type:library, keywords 含 filament, extra.laravel.providers 已声明
 * - extra.filamentboot.post_install 块已声明
 *
 * 产出 Markdown 合规报告（标准输出，可通过 --output 重定向到文件）。
 * 任意包有未修复缺口时以非零状态退出。
 */
class AuditPluginsCommand extends Command
{
    /** @var string */
    protected $signature = 'filamentboot:audit-plugins {--output= : 报告输出文件路径}';

    /** @var string */
    protected $description = '审查一方插件合规状态，产出 Markdown 报告（MKTPLACE-09）';

    public function handle(): int
    {
        $packagesRoot = base_path('packages');
        $dirs         = glob($packagesRoot.'/filamentboot-*', GLOB_ONLYDIR) ?: [];
        $lines        = ["# 一方插件合规审查报告\n"];
        $hasFailure   = false;

        foreach ($dirs as $dir) {
            $result  = $this->auditPackage($dir);
            $slug    = basename($dir);
            $passed  = empty($result['failures']);
            $status  = $passed ? '[PASS]' : '[FAIL]';
            $lines[] = "\n## {$slug}";

            foreach ($result['checks'] as $check => $ok) {
                $mark    = $ok ? '[x]' : '[ ]';
                $lines[] = "- {$mark} {$check}";
            }

            if ($passed) {
                $lines[] = "{$status} {$slug} — 完整实现 Filament\\Contracts\\Plugin 接口，composer.json 规范字段齐备";
            } else {
                $missing    = implode('、', $result['failures']);
                $lines[]    = "{$status} {$slug} — 缺失：{$missing}";
                $hasFailure = true;
            }
        }

        $report = implode("\n", $lines)."\n";
        $this->line($report);

        $output = $this->option('output');
        if ($output) {
            file_put_contents($output, $report);
            $this->info("报告已写入: {$output}");
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 审查单个插件包目录
     *
     * @param  string  $dir  包目录绝对路径
     * @return array{checks: array<string, bool>, failures: list<string>}
     */
    private function auditPackage(string $dir): array
    {
        $composerFile = $dir.'/composer.json';

        if (! file_exists($composerFile)) {
            return [
                'checks'   => ['composer.json 存在' => false],
                'failures' => ['composer.json 不存在'],
            ];
        }

        $manifest = json_decode(file_get_contents($composerFile), true) ?? [];
        $extra    = $manifest['extra']['filamentboot'] ?? [];
        $psr4     = $manifest['autoload']['psr-4'] ?? [];

        $implementsPlugin = $this->detectPluginInterface($dir, $extra, $psr4);
        $typeLibrary      = ($manifest['type'] ?? '') === 'library';
        $hasFilamentKw    = in_array('filament', $manifest['keywords'] ?? [], true);
        $hasProviders     = ! empty($manifest['extra']['laravel']['providers'] ?? []);
        $hasPostInstall   = isset($extra['post_install']);

        $checks = [
            'implements Filament\\Contracts\\Plugin' => $implementsPlugin,
            'composer.json type: library'            => $typeLibrary,
            'keywords 含 filament'                   => $hasFilamentKw,
            'extra.laravel.providers 已声明'         => $hasProviders,
            'extra.filamentboot.post_install 已声明' => $hasPostInstall,
        ];

        $failures = array_keys(array_filter($checks, fn (bool $v) => ! $v));

        return ['checks' => $checks, 'failures' => $failures];
    }

    /**
     * 通过源码 grep 判断包内是否有实现 Filament\Contracts\Plugin 的类
     *
     * 使用文件内容检查（不触发 PHP autoload，T-12-01-01 安全约定）。
     *
     * @param  string  $dir  包目录绝对路径
     * @param  array<string, mixed>  $extra  extra.filamentboot 配置块
     * @param  array<string, string>  $psr4  manifest autoload.psr-4 映射（前缀 → 目录）
     */
    private function detectPluginInterface(string $dir, array $extra, array $psr4 = []): bool
    {
        // 快速路径：extra.filamentboot.plugin_class 已声明
        // 使用 PSR-4 映射将完全限定类名解析为源文件路径（与 PluginManager::detectPluginClass 策略一一致）
        if (isset($extra['plugin_class'])) {
            $srcFile = $this->resolveClassToFile($dir, $extra['plugin_class'], $psr4);

            if ($srcFile !== null && file_exists($srcFile)) {
                $content = file_get_contents($srcFile);

                return str_contains($content, 'implements Plugin')
                    || str_contains($content, 'Filament\Contracts\Plugin');
            }
        }

        // 回退：递归扫描 src/ 目录
        $srcDir = $dir.'/src';
        if (! is_dir($srcDir)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getRealPath());
            if (str_contains($content, 'implements Plugin')
                || str_contains($content, 'Filament\Contracts\Plugin')) {
                return true;
            }
        }

        return false;
    }

    /**
     * 将完全限定类名解析为源文件绝对路径（PSR-4 感知，与 PluginManager 策略一一致）
     *
     * 遍历 manifest autoload.psr-4 映射，找到匹配的前缀后按 PSR-4 规则拼接文件路径。
     * 若无匹配前缀则回退到 src/<ClassBasename>.php（宽松回退，覆盖前缀与类名段相同的单层结构）。
     *
     * @param  string  $dir  包目录绝对路径
     * @param  string  $className  完全限定类名（如 Filamentboot\FilamentbootCos\CosPlugin）
     * @param  array<string, string>  $psr4  manifest autoload.psr-4 映射
     * @return string|null 解析到的文件路径，无法解析时返回 null
     */
    private function resolveClassToFile(string $dir, string $className, array $psr4): ?string
    {
        // 规范化类名（去除前导反斜杠）
        $className = ltrim($className, '\\');

        // 按 PSR-4 前缀长度降序排列，保证最长前缀优先匹配
        $prefixes = array_keys($psr4);
        usort($prefixes, fn (string $a, string $b) => strlen($b) - strlen($a));

        foreach ($prefixes as $prefix) {
            // PSR-4 前缀必须以反斜杠结尾（Composer 规范）
            $normalizedPrefix = rtrim($prefix, '\\').'\\';
            if (! str_starts_with($className, $normalizedPrefix)) {
                continue;
            }

            $relativeClass = substr($className, strlen($normalizedPrefix));
            $srcSubDir     = rtrim((string) $psr4[$prefix], '/');
            $relPath       = str_replace('\\', '/', $relativeClass).'.php';

            return $dir.'/'.$srcSubDir.'/'.$relPath;
        }

        // 回退：无 PSR-4 前缀匹配，使用类基名（不含命名空间）直接在 src/ 下查找
        $basename = substr($className, (int) strrpos($className, '\\') + 1);

        return $dir.'/src/'.$basename.'.php';
    }
}
