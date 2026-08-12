{{--
 * 可配置内容通用列表外壳（批次 5），两套主题共用
 *
 * $cardsHtml 已经是逐条记录渲染并拼接好的卡片 HTML（ConfigurableContentRenderer::
 * renderList()），本视图只负责给它套一层网格容器。
 --}}
<div class="cms-configurable-list cms-configurable-list-{{ $key }} grid gap-4">
    {!! $cardsHtml !!}
</div>
