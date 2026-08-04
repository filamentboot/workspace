<?php

namespace Filamentboot\FilamentbootSite\Http\Controllers;

use Filamentboot\FilamentbootSite\Cms\Services\GatedAssetRegistry;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 资料下载端点（gated content）
 *
 * 只接受**限时签名链接**（路由上挂 ValidateSignature），链接由询盘提交成功后
 * 由服务端现签下发，见 ContactSubmissionController。
 *
 * 三道闸，缺一不可：
 *   1. 签名有效且未过期（中间件）
 *   2. key 在登记表里（= 某个**已发布**页面明确声明过这份资料）
 *   3. 文件在磁盘上真实存在
 *
 * 路由参数是不透明 key 而不是路径：接受路径就等于把任意文件读取挂到公开端点上。
 * 就算签名保住了「不是谁都能调」，一个能读任意路径的端点也不该存在。
 */
class GatedDownloadController extends Controller
{
    /**
     * 下发一份资料
     *
     * @param  string  $asset  不透明 key（GatedAssetRegistry::key() 产出）
     */
    public function show(string $asset): StreamedResponse
    {
        $entry = app(GatedAssetRegistry::class)->find($asset);

        // 未登记：页面下线了、资料换了文件，或者链接被人改了几个字符
        abort_if($entry === null, 404);

        $disk = Storage::disk((string) config('filamentboot-site.gated.disk', 'local'));

        // 登记表可能比磁盘新（文件被手工删掉）。这里必须自己判：
        // 让 download() 去撞不存在的文件会抛 500，而这只是一个 404 的场景
        abort_unless($disk->exists($entry['path']), 404);

        return $disk->download($entry['path'], basename($entry['path']), [
            // 签名链接是一人一条、限时的，绝不能进任何共享缓存
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
