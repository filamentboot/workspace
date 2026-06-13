{{--
 * 语言切换组件（UI-SPEC §Component 10）
 *
 * 中文/EN 全页跳转，aria-current 标注当前语言，44px 最小触达区。
 * 用法：@include('filament-admin-site::components.lang-switcher')
 --}}
@php
    $currentLocale  = app()->getLocale();
    $currentPath    = request()->path();

    // 构建英文 URL：在路径前加 /en/ 前缀（去除已有的 en/ 前缀后重建）
    $pathWithoutEn  = ltrim(preg_replace('#^en/?#', '', $currentPath), '/');
    $enUrl          = url('en' . ($pathWithoutEn ? '/' . $pathWithoutEn : ''));

    // 构建中文 URL：移除 /en/ 前缀
    $zhUrl          = url($pathWithoutEn ?: '/');
@endphp

<div class="inline-flex items-center gap-1" aria-label="{{ $currentLocale === 'zh' ? '切换语言' : 'Switch Language' }}">
    {{-- 中文按钮 --}}
    <a href="{{ $zhUrl }}"
       class="inline-flex items-center min-w-[44px] min-h-[44px] px-2 text-sm
              {{ $currentLocale === 'zh' ? 'text-site-primary font-bold' : 'text-site-muted hover:text-site-secondary' }}
              transition-colors duration-200"
       {{ $currentLocale === 'zh' ? 'aria-current="true"' : '' }}
       lang="zh">
        中文
    </a>

    {{-- 分隔符（装饰性，aria-hidden） --}}
    <span class="text-site-muted text-sm" aria-hidden="true">/</span>

    {{-- EN 按钮 --}}
    <a href="{{ $enUrl }}"
       class="inline-flex items-center min-w-[44px] min-h-[44px] px-2 text-sm
              {{ $currentLocale === 'en' ? 'text-site-primary font-bold' : 'text-site-muted hover:text-site-secondary' }}
              transition-colors duration-200"
       {{ $currentLocale === 'en' ? 'aria-current="true"' : '' }}
       lang="en">
        EN
    </a>
</div>
