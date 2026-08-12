{{-- 日期时间字段展示局部（批次 5），两套主题共用。cast 为 datetime，$value 是 Carbon 实例 --}}
@if($value)
    <time class="cms-field cms-field-date" datetime="{{ $value->toAtomString() }}">{{ $value->format('Y-m-d H:i') }}</time>
@endif
