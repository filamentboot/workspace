{{-- 案例筛选器占位视图（10-04 最小占位，10-05 替换为完整主题视图）--}}
<div>
    <div>
        @foreach ($styleOptions() as $value => $label)
            <button wire:click="$set('style', '{{ $value }}')">{{ $label }}</button>
        @endforeach
    </div>
    <div>
        @foreach ($cases as $case)
            <div>{{ $case->title_zh }}</div>
        @endforeach
        {{ $cases->links() }}
    </div>
</div>
