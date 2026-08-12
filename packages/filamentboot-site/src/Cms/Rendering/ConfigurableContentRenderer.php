<?php

namespace Filamentboot\FilamentbootSite\Cms\Rendering;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\ContentTypeRegistry;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldDefinition;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypeRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

/**
 * 可配置内容前台通用渲染器（批次 5，YZNCMS 式物理列）
 *
 * 与 Cms\Rendering\BlockRenderer 同一降级哲学：未注册的内容类型/字段类型、
 * 缺失的展示视图，一律跳过并记日志，不抛异常——一条内容渲染不出来是可接受
 * 的损失，把整页打成 500 不是。
 *
 * 刻意不提供路由/控制器/独立详情页：友链、广告位这类验收内容类型天然是
 * "嵌入既有页面（页脚/侧栏/首页投放位）的挂件"，不是"有自己网址的文章"，
 * 前台集成方式是任意 Blade 视图里调 `app(ConfigurableContentRenderer::class)
 * ->renderList('friend_link')`，与 BannerProvider::forPosition() 被
 * nav/footer 直接调用是同一条路子。需要独立详情页的内容类型仍应像现有
 * 八类内容一样手写控制器/路由——本渲染器不为这种情况预留扩展点。
 */
class ConfigurableContentRenderer
{
    public function __construct(
        protected ContentTypeRegistry $contentTypes,
        protected FieldTypeRegistry $fieldTypes,
    ) {}

    /**
     * 取某个内容类型的全部记录
     *
     * 内容类型未注册、对应 Model 类不存在（尚未执行 content-type:sync）时
     * 返回空集合并记日志，不抛异常——前台一段挂件取不到数据不该导致整页 500。
     *
     * @return Collection<int, Model>
     */
    public function items(string $key): Collection
    {
        $definition = $this->contentTypes->get($key);

        if ($definition === null) {
            Log::warning('前台请求了未注册的内容类型，已返回空集合。', ['content_type' => $key]);

            return new Collection;
        }

        $modelClass = $definition->modelClass();

        if (! class_exists($modelClass)) {
            Log::warning('内容类型对应的 Model 类不存在，可能尚未执行 content-type:sync。', [
                'content_type' => $key,
                'model'        => $modelClass,
            ]);

            return new Collection;
        }

        /** @var Model $query */
        $query = $modelClass::query();

        if ($definition->sortable) {
            $query->orderBy('sort');
        }

        return $query->get();
    }

    /**
     * 渲染单个字段的展示局部
     *
     * 未注册的字段类型、缺失的展示视图，均跳过并记日志（同 BlockRenderer::renderOne()）。
     */
    public function renderField(FieldDefinition $field, Model $record): HtmlString
    {
        $type = $this->fieldTypes->get($field->type);

        if ($type === null) {
            Log::warning('内容记录引用了未注册的字段类型，已跳过渲染。', [
                'field_type' => $field->type,
                'field'      => $field->key,
            ]);

            return new HtmlString('');
        }

        $view = 'filamentboot-site::cms.fields.'.$type->renderView();

        if (! View::exists($view)) {
            Log::warning('字段展示视图缺失，已跳过渲染。', [
                'field_type' => $field->type,
                'view'       => $view,
            ]);

            return new HtmlString('');
        }

        return new HtmlString(View::make($view, [
            'field'  => $field,
            'value'  => $record->getAttribute($field->key),
            'record' => $record,
        ])->render());
    }

    /**
     * 渲染一条记录的卡片：按内容类型字段清单逐字段渲染后拼接
     *
     * 卡片外壳视图缺失时直接返回拼好的字段 HTML，不因为壳不存在就整条记录不显示。
     */
    public function renderCard(string $key, Model $record): HtmlString
    {
        $definition = $this->contentTypes->get($key);

        if ($definition === null) {
            return new HtmlString('');
        }

        $fieldsHtml = '';

        foreach ($definition->fields as $field) {
            $fieldsHtml .= (string) $this->renderField($field, $record);
        }

        $shell = 'filamentboot-site::cms.configurable.card';

        if (! View::exists($shell)) {
            return new HtmlString($fieldsHtml);
        }

        return new HtmlString(View::make($shell, [
            'definition' => $definition,
            'record'     => $record,
            'fieldsHtml' => new HtmlString($fieldsHtml),
        ])->render());
    }

    /**
     * 渲染某个内容类型的全部记录列表
     */
    public function renderList(string $key): HtmlString
    {
        $items = $this->items($key);

        if ($items->isEmpty()) {
            return new HtmlString('');
        }

        $cardsHtml = $items->map(fn (Model $record): string => (string) $this->renderCard($key, $record))->implode('');

        $shell = 'filamentboot-site::cms.configurable.list';

        if (! View::exists($shell)) {
            return new HtmlString($cardsHtml);
        }

        return new HtmlString(View::make($shell, [
            'key'       => $key,
            'items'     => $items,
            'cardsHtml' => new HtmlString($cardsHtml),
        ])->render());
    }
}
