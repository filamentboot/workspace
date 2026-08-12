{{--
 * 下拉选择字段展示局部（批次 5），两套主题共用
 *
 * 展示的是 choices 里配的中文 label，不是存库的 value——$field->choices
 * 未命中时兜底原样显示 value，避免历史脏数据（choices 声明改过之后）直接不显示。
 --}}
@if(filled($value))
    <span class="cms-field cms-field-select">{{ $field->choices[$value] ?? $value }}</span>
@endif
