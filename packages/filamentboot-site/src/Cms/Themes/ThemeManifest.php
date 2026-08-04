<?php

namespace Filamentboot\FilamentbootSite\Cms\Themes;

use Filamentboot\FilamentbootSite\SiteServiceProvider;

/**
 * 主题清单（#28）
 *
 * 读 resources/views/themes/{theme}/theme.php，形如：
 *
 *   return [
 *       'label'     => '科技装修（深色）',
 *       'templates' => ['default', 'landing'],
 *       'blocks'    => ['hero', 'rich-content', …],
 *       'features'  => ['nested_menu' => true],
 *   ];
 *
 * **清单缺失时不报错，改为扫目录推断**：宿主发布主题视图后可能只覆盖了几个 blade
 * 而没带清单，或者第三方主题作者没写。这时按 `blocks/*.blade.php` 与
 * `pages/templates/*.blade.php` 的实际文件推断，比拒绝加载更有用。
 * 只有 features 推断不出来——文件系统看不出一个 nav 有没有下拉版式——一律按不支持。
 *
 * 解析路径与 SiteServiceProvider::registerThemeViews() 同源，宿主发布的覆盖优先。
 */
class ThemeManifest implements ThemeContract
{
    /**
     * 每请求缓存，避免同一请求内反复读盘
     *
     * @var array<string, self>
     */
    protected static array $resolved = [];

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function __construct(
        protected string $key,
        protected array $manifest,
    ) {}

    /**
     * 取某个主题的清单
     */
    public static function for(string $key): ThemeContract
    {
        return self::$resolved[$key] ??= new self($key, self::load($key));
    }

    /**
     * 当前生效主题的清单
     */
    public static function active(): ThemeContract
    {
        return self::for(SiteServiceProvider::resolveActiveTheme());
    }

    /**
     * 丢掉解析缓存（测试与主题切换后用）
     */
    public static function flush(): void
    {
        self::$resolved = [];
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        $label = $this->manifest['label'] ?? null;

        if (is_string($label) && $label !== '') {
            return $label;
        }

        /** @var array<string, string> $configured */
        $configured = config('filamentboot-site.themes', []);

        return $configured[$this->key] ?? $this->key;
    }

    /**
     * @return list<string>
     */
    public function templates(): array
    {
        return $this->stringList('templates');
    }

    /**
     * @return list<string>
     */
    public function blocks(): array
    {
        return $this->stringList('blocks');
    }

    public function supports(string $feature): bool
    {
        $features = $this->manifest['features'] ?? [];

        return is_array($features) && ($features[$feature] ?? false) === true;
    }

    /**
     * 取一个字符串列表字段
     *
     * @return list<string>
     */
    protected function stringList(string $field): array
    {
        $values = $this->manifest[$field] ?? [];

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $v): string => is_string($v) ? $v : '', $values),
            static fn (string $v): bool => $v !== '',
        )));
    }

    /**
     * 加载清单，缺失则扫目录推断
     *
     * @return array<string, mixed>
     */
    protected static function load(string $key): array
    {
        foreach (self::candidateDirs($key) as $dir) {
            $file = $dir.'/theme.php';

            if (is_file($file)) {
                /** @var mixed $manifest */
                $manifest = require $file;

                if (is_array($manifest)) {
                    return $manifest;
                }
            }
        }

        return self::inferFromFiles($key);
    }

    /**
     * 主题目录候选路径（宿主发布覆盖优先，与视图解析顺序一致）
     *
     * @return list<string>
     */
    protected static function candidateDirs(string $key): array
    {
        return array_values(array_filter([
            resource_path('views/vendor/'.SiteServiceProvider::VIEW_NAMESPACE.'/themes/'.$key),
            __DIR__.'/../../../resources/views/themes/'.$key,
        ], 'is_dir'));
    }

    /**
     * 没有清单时按实际存在的视图文件推断
     *
     * @return array<string, mixed>
     */
    protected static function inferFromFiles(string $key): array
    {
        $templates = ['default'];
        $blocks    = [];

        foreach (self::candidateDirs($key) as $dir) {
            foreach (glob($dir.'/blocks/*.blade.php') ?: [] as $path) {
                $blocks[] = basename($path, '.blade.php');
            }

            foreach (glob($dir.'/pages/templates/*.blade.php') ?: [] as $path) {
                $templates[] = basename($path, '.blade.php');
            }
        }

        return [
            'templates' => array_values(array_unique($templates)),
            'blocks'    => array_values(array_unique($blocks)),
            'features'  => [],
        ];
    }
}
