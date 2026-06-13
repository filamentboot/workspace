<?php

namespace LaravelStack\FilamentAdminWangEditor;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * wangEditor 富文本编辑器 Filament 插件
 *
 * 通过 ->plugins([WangEditorPlugin::make()]) 注册到 Filament Panel。
 *
 * 本插件实现 D-09-03（独立包语义）与 D-09-04（一键替换默认 Tiptap）：
 * 客户在插件市场启用本插件后，使用 WangEditorField::make('content') 即可
 * 替代默认 Tiptap RichEditor，无需改动其余业务代码。
 *
 * 图片上传由 WangEditorUploadController 接收，经 UploadValidator 三重安全校验
 * 后落到当前生效磁盘（D-09-07/D-09-08/D-09-09）。
 */
class WangEditorPlugin implements Plugin
{
    /**
     * 创建插件实例（通过 IoC 容器）
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * 插件唯一标识符，与 extra.filament-admin.slug 保持一致
     */
    public function getId(): string
    {
        return 'filament-admin-wang-editor';
    }

    /**
     * 向 Panel 注册资源
     *
     * wangEditor 插件无独立设置页面，图片上传路由由 WangEditorServiceProvider
     * 通过 loadRoutesFrom() 注册，资产由 FilamentAsset::register() 注入前端。
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function register(Panel $panel): void
    {
        // wangEditor 插件无需注册额外 Pages/Widgets，
        // 路由与资产注册均由 WangEditorServiceProvider::boot() 完成。
    }

    /**
     * 插件启动钩子
     *
     * FilamentAsset 资产注册（wangEditor CDN + Alpine component）由
     * WangEditorServiceProvider::boot() 完成，此处无需重复操作。
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function boot(Panel $panel): void
    {
        // 资产注册由 WangEditorServiceProvider::boot() 执行。
    }
}
