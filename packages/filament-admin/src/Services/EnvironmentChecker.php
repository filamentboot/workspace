<?php

namespace FilamentAdmin\Services;

use Symfony\Component\Process\Process;

/**
 * 运行环境自检服务
 *
 * 三项检测：proc_open 可用 / composer 可执行路径 / vendor 目录可写。
 * 返回结果数组而非抛出异常（exception-first 的 non-throwing 变体）。
 * 支持构造器注入覆盖，方便单元测试隔离系统调用（T-12-02 威胁缓解）。
 */
class EnvironmentChecker
{
    /**
     * @param  bool|null  $procOpenAvailable  proc_open 可用状态（null 表示真实检测）
     * @param  string|null  $composerPathOverride  composer 路径覆盖（null 表示自动解析；空串表示无法找到）
     * @param  string|null  $vendorPathOverride  vendor 目录覆盖（null 表示 base_path('vendor')）
     */
    public function __construct(
        private readonly ?bool $procOpenAvailable = null,
        private readonly ?string $composerPathOverride = null,
        private readonly ?string $vendorPathOverride = null,
    ) {}

    /**
     * 执行三项环境自检
     *
     * @return array{ok: bool, composer_path: string|null, issues: list<string>}
     */
    public function check(): array
    {
        $issues       = [];
        $composerPath = null;

        // 1. proc_open 可用性检测
        if (! $this->isProcOpenAvailable()) {
            $issues[] = 'proc_open() 函数被禁用（请联系主机商开启或使用手动安装模式）';
        }

        // 2. composer 可执行路径解析（D-12-02 优先级链）
        $composerPath = $this->resolveComposerPath();
        if ($composerPath === null) {
            $issues[] = 'Composer 未找到（请设置 COMPOSER_PATH 环境变量或手动安装 composer）';
        }

        // 3. vendor 目录可写检测
        $vendorPath = $this->vendorPathOverride ?? base_path('vendor');
        if (! is_writable($vendorPath)) {
            $issues[] = 'vendor/ 目录无写权限（Web 服务用户需对 vendor/ 有写权限）';
        }

        return [
            'ok'            => empty($issues),
            'composer_path' => $composerPath,
            'issues'        => $issues,
        ];
    }

    /**
     * 执行三项环境自检（check() 的语义别名）
     *
     * @return array{ok: bool, composer_path: string|null, issues: list<string>}
     */
    public function selfCheck(): array
    {
        return $this->check();
    }

    /**
     * 检测 proc_open 是否可用（支持注入覆盖，便于测试）
     */
    protected function isProcOpenAvailable(): bool
    {
        if ($this->procOpenAvailable !== null) {
            return $this->procOpenAvailable;
        }

        return function_exists('proc_open');
    }

    /**
     * 解析 composer 可执行路径（D-12-02 优先级：COMPOSER_PATH → which → /usr/local/bin/composer）
     *
     * 若 composerPathOverride 已注入，则以其值为准（空串表示无法找到，返回 null）。
     */
    protected function resolveComposerPath(): ?string
    {
        // 测试注入覆盖：空串视为"未找到 composer"
        if ($this->composerPathOverride !== null) {
            $override = trim($this->composerPathOverride);

            return $override !== '' ? $override : null;
        }

        // 优先级 1：COMPOSER_PATH 环境变量（必须可执行）
        if ($path = env('COMPOSER_PATH')) {
            if (is_executable($path)) {
                return $path;
            }
        }

        // 优先级 2：系统 which composer
        try {
            $process = new Process(['which', 'composer']);
            $process->run();
            $which = trim($process->getOutput());
            if ($which !== '') {
                return $which;
            }
        } catch (\Throwable) {
            // which 不可用时继续下一步
        }

        // 优先级 3：/usr/local/bin/composer
        if (is_executable('/usr/local/bin/composer')) {
            return '/usr/local/bin/composer';
        }

        return null;
    }
}
