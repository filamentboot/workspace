{{--
 * 链接字段展示局部（批次 5），两套主题共用
 *
 * 过 SafeUrl scheme 白名单（同 Cms\Blocks\HeroBlock 的 cta_url 处理）——
 * 内容编辑账号一旦被盗，一个自由文本链接字段就是现成的 XSS 跳板。
 * 被拦下时不渲染成 # 或裸文本，直接不显示，理由见 SafeUrl 类文档。
 --}}
@php($safeUrl = \Filamentboot\FilamentbootSite\Support\SafeUrl::sanitize($value))
@if($safeUrl)
    <a class="cms-field cms-field-url" href="{{ $safeUrl }}">{{ $safeUrl }}</a>
@endif
