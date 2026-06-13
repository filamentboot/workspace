{{-- 询盘表单占位视图（10-04 最小占位，10-05 替换为完整主题视图）--}}
<div>
    @if ($submitted)
        <p>感谢您的留言，我们将尽快与您联系。</p>
    @else
        <form wire:submit.prevent="submit">
            <input type="text" wire:model="name" placeholder="姓名">
            <input type="text" wire:model="phone" placeholder="电话">
            <textarea wire:model="message" placeholder="留言"></textarea>
            @error('phone') <span>{{ $message }}</span> @enderror
            <button type="submit">提交</button>
        </form>
    @endif
</div>
