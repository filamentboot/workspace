{{--
 * 标签列表组件（两套主题共用）
 *
 * 接受 $tags（SiteTag 集合，通常是 $record->tags）。空集合时整块不渲染，
 * 调用方不必自己判空。
 *
 * **标签是链接，不是装饰。** 这一族标记从上线起就是灰色 `<span>`——看着像标签、
 * 点不动、也没有落点，58 条关联一条内链都没产生。指向 /tags/{slug} 之后，
 * 它成了站上唯一一条跨内容类型的横向通路。
 *
 * 用色守纪律：标签不是强调项，走中性灰底 + 次级文字色，hover 才升到主色。
 * 全站只有一个强调色，标签堆在正文末尾，给它们上色会把版面打散。
 --}}
@if(($tags ?? null) !== null && $tags->isNotEmpty())
    <nav class="mt-10 pt-8 border-t border-site" aria-label="内容标签">
        <ul class="flex flex-wrap gap-2">
            @foreach($tags as $tag)
                <li>
                    <a href="{{ route('site.tags.show', ['slug' => $tag->slug]) }}"
                       class="inline-flex items-center bg-site-elevated text-site-secondary hover:text-site-primary
                              text-xs px-3 py-1 rounded-full transition-colors
                              focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none">
                        {{ $tag->name_zh }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
