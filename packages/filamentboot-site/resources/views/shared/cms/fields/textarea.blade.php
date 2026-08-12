{{-- 多行文本字段展示局部（批次 5），两套主题共用。非富文本，逐字转义后按换行分段 --}}
@if(filled($value))
    <div class="cms-field cms-field-textarea">{!! nl2br(e($value)) !!}</div>
@endif
