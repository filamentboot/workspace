<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Concerns;

use Filament\Actions\Action;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * 草稿预览 Header Action（批次 1.5b 从 EditSitePage 抽出，覆盖全 7 类内容）
 *
 * 签名链接的 type 参数按 BasePolicy::resourceName() 同一套规则从当前记录类名
 * 推导（Str::snake(class_basename($record))：SiteCase → site_case、
 * NewsArticle → news_article），与 SiteFrontController::PREVIEW_TYPES 的键、
 * 各 Policy 的权限点前缀三处天然对齐，不需要每个 Edit 页各自再声明一遍类型标识。
 *
 * 与 HasPublishWorkflowActions 分成两个 trait 而不是合并：两者演进节奏不同
 * （状态机是四件套之一，预览是另一件），合并后任何一边改动都会牵动另一边的
 * diff。调用方式沿用 CreatesRedirectOnSlugChange.php 记录的「显式方法调用而
 * 非覆写钩子」模式，同一个 Edit 页可以同时 use 这两个 trait。
 */
trait HasPreviewAction
{
    /**
     * 草稿预览（#16）
     *
     * 生成 15 分钟有效的签名 URL 并新标签打开。签名而不是直接给 /preview/{type}/{id}：
     * 编辑经常要把未发布内容发给不登录后台的人过目，签名链接能过期是前提。
     *
     * 插件禁用时前台路由未注册，route() 会抛 —— 此时按钮直接不显示，
     * 而不是点了之后 500。
     */
    protected function previewAction(): Action
    {
        return Action::make('preview')
            ->label('预览')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->visible(fn (): bool => $this->previewUrl() !== null)
            ->url(fn (): ?string => $this->previewUrl())
            ->openUrlInNewTab();
    }

    /**
     * 生成签名预览 URL，前台路由不可用时返回 null
     */
    protected function previewUrl(): ?string
    {
        return rescue(
            fn (): string => URL::temporarySignedRoute(
                'site.preview',
                now()->addMinutes(15),
                [
                    'type' => Str::snake(class_basename($this->getRecord())),
                    'id'   => $this->getRecord()->getKey(),
                ],
            ),
            null,
            report: false,
        );
    }
}
