<?php

namespace Filamentboot\FilamentbootRichEditor;

use Illuminate\Support\ServiceProvider;

/**
 * 富文本编辑器插件服务提供者
 *
 * 职责：
 * 1. 提供路由注册扩展点（09-02 填充 wangEditor 图片上传路由）
 * 2. FilamentAsset 资产注册由 09-02 在 boot() 中实现（wangEditor CDN + Alpine component）
 * 3. 本计划（09-01）仅建立骨架，boot() 调用 registerRoutes() 占位空方法
 *
 * 扩展点说明：
 * - registerRoutes()：09-02 在此调用 loadRoutesFrom() 注册 wangEditor 上传路由
 * - boot() FilamentAsset::register()：09-02 注册 wangEditor CDN 脚本与 Alpine component
 */
class RichEditorServiceProvider extends ServiceProvider
{
    /**
     * 启动服务提供者
     *
     * 09-01 阶段仅调用路由注册占位方法。
     * 09-02 阶段将在此添加 FilamentAsset::register() 注册 wangEditor 资产。
     */
    public function boot(): void
    {
        // 注册 wangEditor 上传路由（09-02 在 registerRoutes 中实现）
        $this->registerRoutes();

        // === 09-02 扩展点 ===
        // FilamentAsset::register([
        //     Js::make('wangeditor', 'https://...'),
        //     AlpineComponent::make('wang-editor-field', __DIR__ . '/../resources/dist/wang-editor.js'),
        // ], package: 'filamentboot/filamentboot-rich-editor');
    }

    /**
     * 注册路由（wangEditor 图片上传接口）
     *
     * 09-01 阶段为空占位，09-02 阶段填充：
     * $this->loadRoutesFrom(__DIR__ . '/../routes/rich-editor.php');
     */
    protected function registerRoutes(): void
    {
        // 09-02 实现：$this->loadRoutesFrom(__DIR__ . '/../routes/rich-editor.php');
    }
}
