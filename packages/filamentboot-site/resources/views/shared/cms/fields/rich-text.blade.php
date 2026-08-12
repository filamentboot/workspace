{{--
 * 富文本字段展示局部（批次 5），两套主题共用
 *
 * 落库前 Model 的 setXAttribute mutator 已经 RichText::purify() 过一遍，
 * 这里渲染侧再过一遍——两侧都过是既定纪律（同 Cms\Blocks\RichContentBlock），
 * 不是本处遗漏，防的是"绕过 Model 直接改库"这类不经过 mutator 的写入路径。
 --}}
@if(filled($value))
    <div class="cms-field cms-field-rich-text">{!! \Filamentboot\FilamentbootSite\Support\RichText::purify($value) !!}</div>
@endif
