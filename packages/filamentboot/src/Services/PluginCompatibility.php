<?php

namespace Filamentboot\Services;

use Composer\InstalledVersions;
use Composer\Semver\Semver;

/**
 * 插件 Filament 版本兼容性判定服务（三态）
 *
 * 根据插件声明的 require.filament/filament 约束与当前已安装版本比对，
 * 返回 'compatible'（绿标）/ 'incompatible'（红标）/ 'unknown'（黄标）。
 *
 * D-12-15：unknown 不是硬拦截——UI 允许安装但显示警告；
 * incompatible 时 Plan 04 安装门隐藏按钮。
 * RESEARCH Pattern 4：Semver::satisfies 三态逻辑。
 */
class PluginCompatibility
{
    /**
     * 判定插件是否与当前已安装 Filament 版本兼容
     *
     * @param  string|null  $constraint  插件 composer.json 中 require.filament/filament 的约束字符串
     * @return string 'compatible' | 'incompatible' | 'unknown'
     */
    public function checkFilamentCompatibility(?string $constraint): string
    {
        if ($constraint === null || $constraint === '') {
            return 'unknown';
        }

        // getPrettyVersion 返回 'v5.6.6'，Semver::satisfies 接受带 v 前缀
        $currentVersion = InstalledVersions::getPrettyVersion('filament/filament') ?? '0.0.0';

        try {
            return Semver::satisfies($currentVersion, $constraint) ? 'compatible' : 'incompatible';
        } catch (\Throwable) {
            // 约束格式无效 → 黄标（D-12-15 边界处理）
            return 'unknown';
        }
    }
}
