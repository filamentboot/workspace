<?php

namespace Filamentboot\FilamentbootSite\Http\Controllers;

use Filamentboot\FilamentbootSite\Cms\Services\GatedAssetRegistry;
use Filamentboot\FilamentbootSite\Enums\ContactSubmissionResult;
use Filamentboot\FilamentbootSite\Services\ContactSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * 询盘提交端点（#29）
 *
 * 挂在一条**不带 `web` 中间件组**的路由上：没有 StartSession 也没有 CSRF，
 * 因为公开页要能整页缓存就不能起 session，而不起 session 就发不出 CSRF token。
 * 防刷靠蜜罐 + 客户端耗时 + IP 限流（都在 ContactSubmission 里），
 * 路由上另有一层 throttle 做粗粒度兜底。
 *
 * **自己拼 JSON 而不依赖异常渲染器**：宿主的 bootstrap/app.php 可能（本仓库就）把
 * ValidationException 改成了自己的 API 信封格式，包内前端脚本不能假设那个形状。
 * 这里固定回 {ok, errors?}，下游换不换异常渲染器都不影响。
 *
 * 资料索取（gated content）时额外回 download / filename：提交成功后现签的限时下载链接。
 */
class ContactSubmissionController extends Controller
{
    /**
     * 接收一次询盘
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();

        // 资料索取：先把 key 解析成真实资料，解析不出来就当普通询盘处理。
        // 在调服务之前做，是为了把资料名一起记进线索——销售看到的第一眼就该知道
        // 这条线索是「下载了选型手册」来的，而不是一条没有上下文的电话
        $asset = $this->resolveAsset($input['asset'] ?? null);

        if ($asset !== null) {
            $input = $this->withAssetAnswer($input, $asset['label']);
        }

        try {
            $result = app(ContactSubmission::class)->submit($input, $request->ip());
        } catch (ValidationException $exception) {
            return response()->json([
                'ok'     => false,
                'errors' => $exception->errors(),
            ], 422);
        }

        if ($result === ContactSubmissionResult::THROTTLED) {
            return response()->json([
                'ok'     => false,
                'errors' => ['phone' => ['提交过于频繁，请稍后再试。']],
            ], 429);
        }

        $payload = ['ok' => true];

        // 只有真入库了才给下载链接：判为机器人（DISCARDED）时对外回成功但不放资料，
        // 否则蜜罐就成了「不用留真联系方式也能拿到手册」的后门
        if ($result === ContactSubmissionResult::CREATED && $asset !== null) {
            $payload['download'] = $this->downloadUrl($asset['key']);
            $payload['filename'] = $asset['label'];
        }

        // CREATED 与 DISCARDED 都回成功：判为机器人时回错误等于在教脚本怎么绕过
        return response()->json($payload);
    }

    /**
     * 把请求里的资料 key 解析成登记表里的条目
     *
     * 客户端送的是不透明 key，真实路径只在服务端的登记表里
     * （见 Cms\Services\GatedAssetRegistry 的类注释）。
     *
     * @return array{key: string, label: string}|null
     */
    protected function resolveAsset(mixed $key): ?array
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        $entry = app(GatedAssetRegistry::class)->find($key);

        return $entry !== null ? ['key' => $key, 'label' => $entry['label']] : null;
    }

    /**
     * 把「索取了哪份资料」并进自定义答案
     *
     * 复用 extra 而不是新加一列：它已经会出现在后台详情、导出与通知邮件里，
     * 加一列就要在那三处各改一遍。放在最前面是为了不被答案条数上限挤掉。
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function withAssetAnswer(array $input, string $label): array
    {
        $extra = is_array($input['extra'] ?? null) ? $input['extra'] : [];

        $input['extra'] = ['索取资料' => $label] + $extra;

        return $input;
    }

    /**
     * 现签一条限时下载链接
     *
     * 有效期短是因为链接可能被转发：够一次正常下载，又不足以当长期分发地址。
     */
    protected function downloadUrl(string $key): string
    {
        $minutes = (int) config('filamentboot-site.gated.link_ttl', 30);

        return URL::temporarySignedRoute(
            'site.download',
            now()->addMinutes($minutes > 0 ? $minutes : 30),
            ['asset' => $key]
        );
    }
}
