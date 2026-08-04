<?php

namespace Filamentboot\FilamentbootSite\Cms\Services;

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Illuminate\Support\Facades\Cache;

/**
 * 可索取资料登记表（gated content）
 *
 * 「手册换联系方式」要成立，前提是**文件本身拿不到**。所以资料文件存在一个非公开
 * 磁盘上（config 的 gated.disk，默认 local = storage/app，Web 根之外），
 * 只能经 Http\Controllers\GatedDownloadController 下发。
 *
 * ## 为什么需要这张登记表
 *
 * 提交询盘后要给一条下载链接，服务端就得知道「该放出哪个文件」。
 * 客户端只能送一个**不透明 key**，绝不能送路径——送路径等于把任意文件读取
 * 挂到公开端点上（`../../.env` 那类）。
 *
 * 于是本类扫一遍**已发布**页面的 gated-download 区块，建立 key => {path, label}
 * 的映射：能被下载的文件只有「某个已发布页面明确声明过的那些」。
 * 草稿页面里的资料因此下不到——它还没对外，链接也不该生效。
 *
 * key 取路径的 sha1 前缀：**确定性**的，同一个文件每次渲染出同一个 key，
 * 页面 HTML 才能整页缓存（#29）。随机 token 会让每次响应都不同，缓存直接失效。
 * key 本身不泄露信息，且没有登记过的 key 一律 404。
 *
 * 缓存与 MenuResolver 同一套路：rememberForever + 页面变更时失效
 * （SitePageObserver 里调 forget()）。改一次页面清一次，不靠 TTL 熬——
 * 靠 TTL 会让「刚发布的资料下不了」变成常态投诉。
 */
class GatedAssetRegistry
{
    public const CACHE_KEY = 'site:gated-assets';

    /**
     * key 长度（sha1 前缀）
     *
     * 16 个十六进制字符 = 64 bit，足够避免碰撞，又不会让下载地址长得离谱。
     */
    protected const KEY_LENGTH = 16;

    /**
     * 全部已登记资料，键为不透明 key
     *
     * @return array<string, array{path: string, label: string}>
     */
    public function all(): array
    {
        /** @var array<string, array{path: string, label: string}> $assets */
        $assets = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->build());

        return $assets;
    }

    /**
     * 按 key 查一条，未登记时返回 null
     *
     * 返回 null 而不抛异常：调用方要的是 404，而「这个 key 没登记」是常态
     * （页面下线、资料换文件、链接被人改了几个字符）。
     *
     * @return array{path: string, label: string}|null
     */
    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * 由文件路径算出不透明 key
     *
     * 确定性：同一个文件永远同一个 key，页面 HTML 因此可整页缓存。
     */
    public static function key(string $path): string
    {
        return substr(sha1('filamentboot-site:gated:'.$path), 0, self::KEY_LENGTH);
    }

    /**
     * 清空登记表缓存
     */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * 扫描已发布页面，建立登记表
     *
     * 只读 id 与 blocks 两列：这张表可能在任何一次前台提交时被重建，
     * 没必要把每个页面的正文全捞进内存。
     *
     * @return array<string, array{path: string, label: string}>
     */
    protected function build(): array
    {
        $assets = [];

        foreach (SitePage::published()->get(['id', 'blocks']) as $page) {
            foreach ($page->blocks ?? [] as $block) {
                if (! is_array($block) || ($block['type'] ?? null) !== 'gated-download') {
                    continue;
                }

                $data = is_array($block['data'] ?? null) ? $block['data'] : [];
                $path = trim((string) ($data['file'] ?? ''));

                if ($path === '') {
                    continue;
                }

                $label = trim((string) ($data['title'] ?? '')) ?: basename($path);

                $assets[self::key($path)] = [
                    'path'  => $path,
                    'label' => mb_substr($label, 0, 120),
                ];
            }
        }

        return $assets;
    }
}
