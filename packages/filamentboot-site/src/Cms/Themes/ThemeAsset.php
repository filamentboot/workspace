<?php

namespace Filamentboot\FilamentbootSite\Cms\Themes;

/**
 * 主题前端资源入口解析
 *
 * 主题 CSS 的 Vite 入口路径在不同安装形态下并不一致：
 *
 * - 真实 Composer 安装：vendor/filamentboot/filamentboot-site/resources/css/themes/{theme}.css
 * - 宿主发布资源后：    resources/css/vendor/filamentboot-site/themes/{theme}.css
 * - monorepo path 仓库：vendor/ 是指向 packages/ 的符号链接，Vite 会把 manifest 键
 *                       写成真实路径 packages/filamentboot-site/resources/css/themes/{theme}.css
 *
 * 布局里写死任何一种，都会在另外两种形态下抛
 * "Unable to locate file in Vite manifest"。本类改为按 config 声明的候选顺序
 * 查 manifest，命中哪个用哪个。
 */
class ThemeAsset
{
    /**
     * manifest 内容缓存（按 manifest 路径）
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $manifestCache = [];

    /**
     * 解析主题 CSS 的 Vite 入口路径
     *
     * 全部候选都未出现在 manifest 中时返回第一个候选，
     * 让 Vite 抛出的错误信息指向标准安装路径而不是内部路径。
     *
     * @param  string  $theme  主题目录名
     */
    public static function viteEntry(string $theme): string
    {
        $candidates = static::candidates($theme);
        $manifest   = static::manifest();

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $manifest)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    /**
     * 布局要注入的全部 Vite 入口（主题 CSS + 前台脚本）
     *
     * @return list<string>
     */
    public static function viteEntries(string $theme): array
    {
        return [
            static::viteEntry($theme),
            static::scriptEntry(),
        ];
    }

    /**
     * 解析前台脚本的 Vite 入口路径（#29：Alpine 的交付路径）
     *
     * 与主题 CSS 同一套候选机制，但脚本只有一份、不随主题变，所以模板里没有 {theme}。
     */
    public static function scriptEntry(): string
    {
        $candidates = static::scriptCandidates();
        $manifest   = static::manifest();

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $manifest)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    /**
     * 前台脚本的候选入口路径
     *
     * @return list<string>
     */
    protected static function scriptCandidates(): array
    {
        /** @var list<string> $templates */
        $templates = config('filamentboot-site.assets.script_entries', []);

        return array_values(array_filter($templates)) ?: [
            'vendor/filamentboot/filamentboot-site/resources/js/site.js',
        ];
    }

    /**
     * 按 config 模板生成候选入口路径
     *
     * @return list<string>
     */
    protected static function candidates(string $theme): array
    {
        /** @var list<string> $templates */
        $templates = config('filamentboot-site.assets.vite_entries', [
            'vendor/filamentboot/filamentboot-site/resources/css/themes/{theme}.css',
        ]);

        $paths = array_map(
            static fn (string $template): string => str_replace('{theme}', $theme, $template),
            $templates
        );

        return array_values(array_filter($paths)) ?: [
            'vendor/filamentboot/filamentboot-site/resources/css/themes/'.$theme.'.css',
        ];
    }

    /**
     * 读取 Vite manifest
     *
     * manifest 不存在（尚未 npm run build）时返回空数组，
     * 由 @vite 自己抛出标准错误提示，不在这里额外报错。
     *
     * @return array<string, mixed>
     */
    protected static function manifest(): array
    {
        $path = public_path(implode('/', array_filter([
            trim((string) config('vite.build_path', 'build'), '/'),
            'manifest.json',
        ])));

        if (array_key_exists($path, static::$manifestCache)) {
            return static::$manifestCache[$path];
        }

        if (! is_file($path)) {
            return static::$manifestCache[$path] = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return static::$manifestCache[$path] = is_array($decoded) ? $decoded : [];
    }

    /**
     * 清空 manifest 缓存（测试用）
     */
    public static function flush(): void
    {
        static::$manifestCache = [];
    }
}
