{{--
 * 可配置内容通用卡片外壳（批次 5），两套主题共用
 *
 * $fieldsHtml 已经是逐字段渲染并拼接好的 HTML（ConfigurableContentRenderer::
 * renderCard()），本视图只负责给它套一层通用卡片容器，不认识具体是哪个
 * 内容类型——新增一类内容不需要新增卡片视图。
 --}}
<article class="cms-configurable-card bg-site-surface rounded-lg p-4">
    {!! $fieldsHtml !!}
</article>
