<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filamentboot\FilamentbootSite\Cms\Models\SiteRedirect;
use Filamentboot\FilamentbootSite\Cms\Routing\SiteRedirectMiddleware;

/**
 * slug 变更后自动建 301 重定向（3.5 期 B 段）
 *
 * 从 `EditSitePage` 抽出来复用给其余五类有前台详情页的内容（案例 / 方案 /
 * 产品 / 套餐 / 资讯）——此前只有静态页面享有这份保护，其余五类改 slug
 * 就是直接死链，与包 README「内容契约」不变量 5 的措辞（"页面 slug 修改后
 * 可以选择建一条 301 重定向"）实际覆盖范围不符。
 *
 * 新旧地址走**命名路由**取，而不是像原实现那样直接拼裸 slug，顺带修掉一个
 * 被 `SITE_ROUTE_MODE=root` 掩盖的 bug：prefix 模式下前台地址是
 * `/{prefix}/{slug}`，直接用裸 slug 建重定向会漏掉前缀，中间件按完整请求
 * 路径查表，永远查不中。
 *
 * 不做成直接覆盖 `mutateFormDataBeforeSave()` / `afterSave()` 两个钩子的
 * trait，是因为部分调用方（如 `EditSitePage`）在同一个钩子里还要做别的事
 * （区块净化）——同名方法会被 trait 的实现整个吃掉。改成显式调用
 * `captureSlugBeforeSave()` / `createRedirectIfSlugChanged()`，调用方自己
 * 决定何时调、要不要在前后再做别的事。
 */
trait CreatesRedirectOnSlugChange
{
    /**
     * 保存前的旧 slug
     */
    protected ?string $slugBeforeSave = null;

    /**
     * 该类型详情页的命名路由（如 `site.cases.show`），路由参数只有 `slug` 一个
     */
    abstract protected function redirectRouteName(): string;

    /**
     * 保存前记下旧 slug
     *
     * 必须在 mutateFormDataBeforeSave 阶段调用：afterSave 时模型已经是新值。
     */
    protected function captureSlugBeforeSave(): void
    {
        $this->slugBeforeSave = (string) $this->getRecord()->getAttribute('slug');
    }

    /**
     * slug 变了就建一条 301，且清理可能出现的反向死循环
     *
     * **自动创建 + 通知里给撤销按钮**，而不是保存前弹确认框：默认永不丢旧
     * URL，比默认弹窗少一次点击、也少一次误关。已被搜索引擎收录的地址一旦
     * 404，权重要几周才能重新积累回来。
     */
    protected function createRedirectIfSlugChanged(): void
    {
        $record  = $this->getRecord();
        $newSlug = (string) $record->getAttribute('slug');

        if ($this->slugBeforeSave === null || $this->slugBeforeSave === $newSlug) {
            return;
        }

        $routeName = $this->redirectRouteName();
        $from      = SiteRedirectMiddleware::normalizePath(
            (string) route($routeName, ['slug' => $this->slugBeforeSave], false)
        );
        $to = SiteRedirectMiddleware::normalizePath(
            (string) route($routeName, ['slug' => $newSlug], false)
        );

        // to == from 时不建不跳（自指重定向会让浏览器判为循环）
        if ($from === '' || $to === '' || $from === $to) {
            return;
        }

        // 已有同源记录就改指向：slug 从 a 改到 b 再改到 c 时，a 应当直指 c，
        // 而不是留下 a→b、b→c 两跳（多一跳就多一次权重损耗）
        $redirect = SiteRedirect::query()->updateOrCreate(
            ['from_path' => $from],
            ['to_path' => '/'.$to, 'status_code' => 301],
        );

        // 反向链（b→a）若存在必须删掉，否则新旧地址互相指形成死循环
        SiteRedirect::query()->where('from_path', $to)->where('to_path', '/'.$from)->delete();

        Notification::make()
            ->success()
            ->title('已创建 301 跳转')
            ->body("/{$from} → /{$to}，旧链接不会 404。")
            ->actions([
                Action::make('undoRedirect')
                    ->label('撤销')
                    ->color('danger')
                    ->action(function () use ($redirect): void {
                        $redirect->delete();

                        Notification::make()
                            ->warning()
                            ->title('已撤销跳转')
                            ->body('旧链接将返回 404。')
                            ->send();
                    }),
            ])
            ->persistent()
            ->send();
    }
}
