<?php

namespace Filamentboot\FilamentbootWangEditor;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

/**
 * wangEditor 插件服务提供者
 *
 * 职责：
 * 1. loadViewsFrom()：注册 Blade 视图命名空间 filamentboot-wang-editor
 * 2. loadRoutesFrom()：注册 wangEditor 图片上传路由（/filamentboot-wang-editor/upload）
 * 3. FilamentAsset::register()：注册 wangEditor CDN JS + Alpine component
 *
 * D-09-03/D-09-04：本包独立于 filamentboot-rich-editor，可被 plugin:scan 扫描并启停。
 */
class WangEditorServiceProvider extends ServiceProvider
{
    /**
     * 启动服务提供者
     *
     * 注册视图命名空间、上传路由和前端资产。
     */
    public function boot(): void
    {
        // 注册 Blade 视图命名空间（filamentboot-wang-editor::components.wang-editor）
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filamentboot-wang-editor');

        // 注册 wangEditor 图片上传路由（/filamentboot-wang-editor/upload）
        // 路由文件由 Task 3 创建，此处安全降级防止包级单元测试（Task 1）报错
        if (file_exists(__DIR__.'/../routes/wang-editor-upload.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/wang-editor-upload.php');
        }

        // 注册前端资产：wangEditor CDN JS + Alpine component（D-09-03/D-09-04）
        // CDN 地址：@wangeditor/editor@5.1.23（per Open Questions RESOLVED #2，用 CDN 桥接）
        // 注意：生产环境可替换为本地打包版本（仅改此处 CDN URL 即可）
        // dist 文件由 Task 2 创建，此处安全降级防止包级单元测试（Task 1）报错
        $assets = [
            Js::make('wangeditor', 'https://unpkg.com/@wangeditor/editor@5.1.23/dist/index.js'),
        ];

        if (file_exists(__DIR__.'/../resources/dist/wang-editor.js')) {
            $assets[] = AlpineComponent::make('wang-editor-field', __DIR__.'/../resources/dist/wang-editor.js');
        }

        FilamentAsset::register($assets, package: 'filamentboot/filamentboot-wang-editor');
    }

    /**
     * 注册服务容器绑定
     *
     * wangEditor 插件目前无需额外的容器绑定，
     * 上传控制器通过路由文件直接绑定到路由。
     */
    public function register(): void
    {
        // 暂无需要注册的容器绑定
    }
}
