<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders\Concerns;

use Spatie\MediaLibrary\HasMedia;

/**
 * 演示内容的本地图片灌入
 *
 * 从 SiteDemoSeeder 提取：资讯种子也要按同一规则挂封面，
 * 两份实现早晚会在「是否允许外部图源」这条红线上走偏。
 */
trait SeedsMediaImages
{
    /**
     * 添加封面图：仅使用本地 storage 图片（D-11-11）
     *
     * 本地图片不存在时不添加任何媒体，前台由 image-placeholder 组件兜底。
     * 此前会降级到 picsum.photos，导致演示数据向站点写入外部占位图 URL，
     * 上线后表现为封面持续模糊、且依赖第三方图片服务可用性。
     * 播种数据一律不得引入外部图片来源。
     *
     * 任何异常静默处理，不阻断 Seeder 执行（适用离线环境）。
     *
     * @param  HasMedia  $model  目标模型
     * @param  string  $diskRelPath  相对 public disk 的路径，如 'site/cases/modern-3bed-smart.jpg'
     * @param  string  $collection  媒体集合名称（默认 'cover'）
     */
    protected function addCoverImage(
        HasMedia $model,
        string $diskRelPath,
        string $collection = 'cover'
    ): void {
        // 幂等守卫：已有图片则跳过
        if ($model->getMedia($collection)->isNotEmpty()) {
            return;
        }

        // 本地图片不存在时直接跳过，由前台占位组件渲染空态
        if (! file_exists(storage_path('app/public/'.$diskRelPath))) {
            return;
        }

        try {
            // diskRelPath 必须相对 public disk，禁绝对路径（Pitfall 5）
            $model->addMediaFromDisk($diskRelPath, 'public')
                ->toMediaCollection($collection);
        } catch (\Throwable) {
            // 磁盘不可用时静默跳过，不阻断播种
        }
    }
}
