{{--
    wangEditor Alpine 桥接视图

    wire:ignore 防止 Livewire 在重渲染时销毁 wangEditor DOM（必须保留）。
    x-data 绑定 Alpine component wangEditorField，传入状态路径、上传 URL 和磁盘。
    Alpine 组件实现 init/destroy 和死循环防护（Pitfall 3）。

    CSRF 防护：customUpload 中通过 X-CSRF-TOKEN header 携带 token（T-09-07）。
--}}
<div
    wire:ignore
    x-data="wangEditorField({
        statePath: '{{ $getStatePath() }}',
        uploadUrl: '{{ route('filamentboot-wang-editor.upload') }}',
        disk: '{{ $getDisk() }}'
    })"
    x-init="init()"
    x-on:remove.window="destroy()"
>
    {{-- wangEditor 工具栏容器 --}}
    <div
        id="wang-editor-toolbar-{{ $getStatePath() }}"
        style="border: 1px solid #e5e7eb; border-bottom: none;"
    ></div>

    {{-- wangEditor 编辑区容器 --}}
    <div
        id="wang-editor-content-{{ $getStatePath() }}"
        style="border: 1px solid #e5e7eb; min-height: 300px;"
    ></div>
</div>
