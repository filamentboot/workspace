<?php

namespace App\Services;

use Composer\Semver\Semver;
use FilamentAdmin\Models\Plugin;
use Illuminate\Support\Facades\Http;

/**
 * 插件管理服务（演示项目扩展版）
 *
 * 继承包基础版，追加 validatePackageName 能力（需 HTTP，不入包）。
 */
class PluginManager extends \FilamentAdmin\Services\PluginManager
{
    /**
     * 校验包名合法性（白名单直通 + Packagist p2 API 404 阻断 + semver 约束校验）
     */
    public function validatePackageName(string $packageName): bool
    {
        // 白名单来源检查（D-06-08 第一层）
        $entry = collect(config('official-market.entries', []))
            ->firstWhere('package_name', $packageName);

        if ($entry && in_array($entry['source'], ['official_trusted', 'official_listed'], true)) {
            return true;
        }

        // Packagist p2 API 校验（D-06-08 第二层）
        try {
            [$vendor, $name] = explode('/', $packageName, 2);
            $response        = Http::timeout(5)
                ->get("https://repo.packagist.org/p2/{$vendor}/{$name}.json");

            if (! $response->ok()) {
                return false;
            }

            /** @var array<int, array<string, mixed>> $versions */
            $versions = $response->json("packages.{$packageName}", []);

            if (empty($versions)) {
                return false;
            }

            $latest     = $versions[0]['version'] ?? null;
            $constraint = $this->getCompatibilityConstraint($packageName);

            if ($latest && $constraint) {
                return Semver::satisfies($latest, $constraint);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function getCompatibilityConstraint(string $packageName): ?string
    {
        $entry = collect(config('official-market.entries', []))
            ->firstWhere('package_name', $packageName);

        if ($entry && isset($entry['version'])) {
            return (string) $entry['version'];
        }

        $plugin = Plugin::where('package_name', $packageName)->first();
        if ($plugin && $plugin->compatibility) {
            return $plugin->compatibility[$packageName] ?? null;
        }

        return null;
    }
}
