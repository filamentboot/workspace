<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes;

/**
 * 内容类型的单个字段声明（批次 5）
 *
 * 纯数据对象：一个字段 = 列名 + 字段类型 key（对应 FieldTypeRegistry）+ 展示标签 +
 * 若干由具体 FieldTypeContract 实现自行解读的约束项。约束项没有做成每种字段类型
 * 一个子类，是因为 required/nullable/maxLength 这些是绝大多数字段类型共享的概念，
 * 拆子类只会让 ContentTypeDefinition 里声明字段列表时要 match 字段类型再选构造器。
 *
 * $choices 只被 SelectFieldType 消费，$default 只在生成迁移/表单时用作默认值——
 * 具体某个字段类型用不用某个约束项，由它自己的 FieldTypeContract 实现决定，
 * 不使用的约束项直接忽略，不强制每种类型都要理解全部字段。
 */
final class FieldDefinition
{
    /**
     * @param  string  $key  数据库列名 / 表单字段名，snake_case
     * @param  string  $type  字段类型 key，对应 FieldTypeRegistry 里注册的 key
     * @param  string  $label  后台表单与列表的显示标签
     * @param  bool  $required  是否必填（驱动表单 required() 与校验规则）
     * @param  bool  $nullable  数据库列是否允许 NULL
     * @param  int|null  $maxLength  字符串类字段的最大长度，null 表示不限制
     * @param  array<string, string>  $choices  SelectFieldType 用：value => label
     * @param  mixed  $default  迁移列默认值 / 表单默认值
     * @param  bool  $showInList  是否作为后台列表页的表格列
     * @param  bool  $unique  数据库列是否唯一索引
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $label,
        public readonly bool $required = false,
        public readonly bool $nullable = true,
        public readonly ?int $maxLength = null,
        public readonly array $choices = [],
        public readonly mixed $default = null,
        public readonly bool $showInList = false,
        public readonly bool $unique = false,
    ) {}
}
