<?php

namespace Filamentboot\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 本地首字头像 Provider
 *
 * 替代 Filament 默认的 UiAvatarsProvider（每次渲染都请求 ui-avatars.com，
 * 国内网络下表现为长时间转圈直至超时）。本实现返回自绘 SVG 的 data URI，
 * 不产生任何网络请求，也不需要发布静态资源。
 *
 * FilamentManager::getUserAvatarUrl() 对 `data:image/` 开头的返回值直通不加工，
 * 因此 data URI 是唯一零文件、零请求的兜底形态。
 */
class InitialsAvatarProvider implements AvatarProvider
{
    /**
     * 生成用户头像 URL（data URI 形式的 SVG）
     *
     * 参数类型与官方 UiAvatarsProvider 一致放宽为 Model|Authenticatable，
     * 以兼容非 Eloquent 的 guard 用户。
     */
    public function get(Model|Authenticatable $record): string
    {
        $background = Color::convertToHex(
            FilamentColor::getColor('primary')[600] ?? Color::Blue[600]
        );

        $svg = $this->buildSvg(
            $this->resolveInitial(Filament::getNameForDefaultAvatar($record)),
            $background
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * 从显示名中取首字
     *
     * 中文取第一个汉字，英文取首字母并转大写；名称为空时回退为问号。
     */
    protected function resolveInitial(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }

    /**
     * 拼装圆形底色 + 白色首字的 SVG
     *
     * 字体走系统栈，不引用任何外部字体资源。
     */
    protected function buildSvg(string $initial, string $background): string
    {
        $fontFamily = 'system-ui,-apple-system,&quot;Segoe UI&quot;,&quot;PingFang SC&quot;,'
            .'&quot;Microsoft YaHei&quot;,Arial,sans-serif';

        return implode('', [
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">',
            '<circle cx="50" cy="50" r="50" fill="'.$background.'"/>',
            '<text x="50" y="50" fill="#ffffff" font-family="'.$fontFamily.'"',
            ' font-size="48" font-weight="500" text-anchor="middle" dominant-baseline="central">',
            htmlspecialchars($initial, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            '</text></svg>',
        ]);
    }
}
