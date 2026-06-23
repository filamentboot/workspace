<?php

namespace Filamentboot\FilamentbootWangEditor\Http\Controllers;

use Filamentboot\Support\UploadValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * wangEditor 图片上传控制器
 *
 * 接收 wangEditor customUpload 的图片上传请求，经 UploadValidator 三重安全校验落盘，
 * 返回 wangEditor errno 协议格式（D-09-09）：
 * - 成功：{errno: 0, data: {url, alt, href}}
 * - 失败：{errno: 1, message}（不抛 500，友好告知前端）
 *
 * 路由中间件 ['web', 'auth:admin']：CSRF 防护（T-09-07）+ 登录鉴权。
 */
class WangEditorUploadController extends Controller
{
    /**
     * 处理 wangEditor 图片上传请求
     *
     * @param  Request  $request  HTTP 请求（包含 file 和 disk 字段）
     * @param  UploadValidator  $validator  三重安全校验服务
     * @return JsonResponse wangEditor errno 协议格式响应
     */
    public function __invoke(Request $request, UploadValidator $validator): JsonResponse
    {
        $file = $request->file('file');

        if ($file === null) {
            return response()->json([
                'errno'   => 1,
                'message' => '未接收到上传文件',
            ]);
        }

        // 解析目标磁盘：取 request 中的 disk 参数，local 回退 public（D-09-08）
        $disk = $request->input('disk') ?: config('filesystems.default', 'public');

        if ($disk === 'local') {
            $disk = 'public';
        }

        // 执行三重安全校验（D-09-09）：扩展名黑名单 + 大小限制 + finfo MIME
        try {
            $validator->validate($file);
        } catch (\RuntimeException $e) {
            return response()->json([
                'errno'   => 1,
                'message' => $e->getMessage(),
            ]);
        }

        // 存储文件并返回可访问 URL
        $path = $file->store('wang-editor', ['disk' => $disk]);
        $url  = Storage::disk($disk)->url($path);

        return response()->json([
            'errno' => 0,
            'data'  => [
                'url'  => $url,
                'alt'  => '',
                'href' => '',
            ],
        ]);
    }
}
