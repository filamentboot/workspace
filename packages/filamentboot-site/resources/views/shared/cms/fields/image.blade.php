{{--
 * 图片字段展示局部（批次 5），两套主题共用
 *
 * ImageFieldType 固定用 public 磁盘存相对路径（见该类文档），这里必须用
 * 同一个磁盘名读，否则「上传到 A、前台从 B 读」的错位会重演。
 --}}
@if(filled($value))
    <img class="cms-field cms-field-image" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($value) }}" alt="{{ $field->label }}" loading="lazy">
@endif
