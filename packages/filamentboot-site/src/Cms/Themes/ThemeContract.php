<?php

namespace Filamentboot\FilamentbootSite\Cms\Themes;

/**
 * 主题能力契约（#28）
 *
 * 主题是一组 Blade 视图，不是类，所以契约由每个主题目录下的 theme.php 清单来满足，
 * 由 ThemeManifest 读取后以本接口对外暴露。
 *
 * 存在的意义是让「后台配得出来的，前台一定显示得出来」可以被**提前**检查：
 * BlockRenderer 已有运行时兜底（缺视图跳过并记 warning），但那是事后降级——
 * 已发布页面上的区块悄悄消失，没人会收到通知。有了清单，切主题之前就能算出
 * 哪些已发布页面会掉东西。
 */
interface ThemeContract
{
    /**
     * 主题目录名（即 config('filamentboot-site.themes') 的键）
     */
    public function key(): string;

    /**
     * 后台显示名
     */
    public function label(): string;

    /**
     * 支持的页面版式标识（site_pages.template 的取值）
     *
     * @return list<string>
     */
    public function templates(): array;

    /**
     * 支持的区块 key（BlockContract::key() 的取值）
     *
     * @return list<string>
     */
    public function blocks(): array;

    /**
     * 是否支持某项能力
     *
     * 目前只有 nested_menu（二级下拉导航）。未声明的能力一律按不支持处理——
     * 新增能力时旧主题不会因为"没说不支持"而被当成支持。
     */
    public function supports(string $feature): bool;
}
