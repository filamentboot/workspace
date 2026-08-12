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
     * preservingOriginal()：源文件必须留在原处
     * ---------------------------------------
     * Media Library 的默认行为是**搬走**源文件，不是复制。所以此前每播种一次，
     * `storage/app/public/site/` 里的图就被吃掉一批——2026-08-05 查到本地那个目录
     * 已经 0 个文件，20 张封面只剩 Media Library 里的副本。
     *
     * 这是个真问题：`storage/` 既不进 git，也是 rsync 的排除项，**数据库一旦重建，
     * 那些图就再也挂不回来了**，只能重跑整条素材采集流水线（Commons 的候选池会变，
     * 未必还能选到同一张）。加 `preservingOriginal()` 之后 `site/` 成为可重复播种的
     * 素材区，代价是同一张图在磁盘上存两份——官网内容量小，这个代价换掉一个单点故障
     * 很划算。
     *
     * ⚠️ 这里**不负责换图**。上面的幂等守卫是「已有就跳过」，所以往 `site/` 放一张
     * 新图重跑种子不会顶掉旧封面（`cover` 虽是 `singleFile()`，但记录已经存在、
     * 根本走不到添加那一步）。换封面要先删掉旧的 media 记录，或在后台改。
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
                ->preservingOriginal()
                ->toMediaCollection($collection);
        } catch (\Throwable) {
            // 磁盘不可用时静默跳过，不阻断播种
        }
    }
}
