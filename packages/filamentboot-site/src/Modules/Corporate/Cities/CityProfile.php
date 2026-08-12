<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cities;

use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteCityPage;

/**
 * 城市概况字段表
 *
 * `site_city_pages.profile` 存的是一组键值对，**键的含义由宿主 config 声明**：
 * `config('filamentboot-site.city_pages.profile_fields')`。后台表单与前台模板
 * 都从这里读，所以包本身不认识「气候类型」「供暖方式」这些词——
 * 那是装修行业的口径，换个行业装这个包，字段表整个不一样。
 *
 * 包内默认是**空数组**。没配字段表，城市页照样能用（标题 + 简介 + 下辖区县 +
 * 同省城市），只是没有概况表。
 *
 * ## 配错的条目会被静默丢掉
 *
 * 与 `Cms\Blocks\ContactFormBlock::normalizedFields()` 同一套取舍：宁可少显示
 * 一个字段，也不要在页面上渲染出一个坏掉的格子。改完字段表请到前台看一眼。
 *
 * ## 空值不渲染，不是渲染成「—」
 *
 * 采不到就留空，模板整行跳过。这是三期采集口径里写死的一条：
 * **没有一个编的数**——渲染成「—」看起来像数据坏了，而实际上是「这个城市
 * 拿不到权威来源」，两件事不一样。
 */
class CityProfile
{
    /**
     * 支持的字段类型 => 是否需要 options
     *
     * 刻意只有四种。城市概况是一张事实表，不是问卷——日期、开关、上传
     * 这些进来只会让「这一格该填什么」变得没边。
     *
     * @var array<string, bool>
     */
    public const TYPES = [
        'text'     => false,
        'number'   => false,
        'textarea' => false,
        'select'   => true,
    ];

    /**
     * 归一后的字段表
     *
     * @return list<array{key: string, label: string, type: string, unit: string, help: string, options: list<string>}>
     */
    public function fields(): array
    {
        /** @var mixed $raw */
        $raw = config('filamentboot-site.city_pages.profile_fields', []);

        if (! is_array($raw)) {
            return [];
        }

        $fields = [];
        $seen   = [];

        foreach ($raw as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key   = trim((string) ($field['key'] ?? ''));
            $label = trim((string) ($field['label'] ?? ''));
            $type  = (string) ($field['type'] ?? 'text');

            // key 会拼进表单字段名与 JSON 键，字符集卡死（同 BlockRegistry 对 key 的约束）
            if (preg_match('/^[a-z0-9]+(_[a-z0-9]+)*$/', $key) !== 1) {
                continue;
            }

            if ($label === '' || isset($seen[$key]) || ! isset(self::TYPES[$type])) {
                continue;
            }

            $options = self::TYPES[$type] ? $this->parseOptions($field['options'] ?? null) : [];

            // 下拉却没有可选项 = 后台选不了，整条丢掉
            if (self::TYPES[$type] && $options === []) {
                continue;
            }

            $seen[$key] = true;

            $fields[] = [
                'key'     => $key,
                'label'   => $label,
                'type'    => $type,
                'unit'    => trim((string) ($field['unit'] ?? '')),
                'help'    => trim((string) ($field['help'] ?? '')),
                'options' => $options,
            ];
        }

        return $fields;
    }

    /**
     * 某个城市页上**确实有值**的概况行，按字段表顺序
     *
     * 值统一压成单行字符串：概况表是一行一格的紧凑版式，换行会把它撑散。
     * 想写多段的东西属于 `content_zh`。
     *
     * @return list<array{key: string, label: string, value: string, unit: string}>
     */
    public function rows(SiteCityPage $page): array
    {
        $profile = is_array($page->profile) ? $page->profile : [];

        $rows = [];

        foreach ($this->fields() as $field) {
            $value = $profile[$field['key']] ?? null;

            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

            if ($value === '') {
                continue;
            }

            $rows[] = [
                'key'   => $field['key'],
                'label' => $field['label'],
                'value' => $value,
                'unit'  => $field['unit'],
            ];
        }

        return $rows;
    }

    /**
     * 解析「一行一个」的选项文本，也接受数组
     *
     * 两种写法都收：config 里直接写 `['集中供暖', '自采暖']` 最自然，
     * 而与询盘额外问题保持一致的「一行一个」字符串写法也不该报错。
     *
     * @return list<string>
     */
    protected function parseOptions(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/\R/u', $raw) ?: [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $options = [];

        foreach ($raw as $option) {
            if (! is_scalar($option)) {
                continue;
            }

            $option = trim((string) $option);

            if ($option !== '' && ! in_array($option, $options, true)) {
                $options[] = $option;
            }
        }

        return $options;
    }
}
