<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\AbstractFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldDefinition;

/**
 * 布尔开关字段类型（批次 5）
 *
 * 覆盖默认 rules()：Toggle 未勾选时提交的是 false 而不是缺失字段，
 * required/nullable 对布尔值没有意义，永远只校验类型本身。
 */
class BooleanFieldType extends AbstractFieldType
{
    public function key(): string
    {
        return 'boolean';
    }

    public function label(): string
    {
        return '开关';
    }

    public function migrationColumn(FieldDefinition $field): string
    {
        $default = $field->default === null ? 'false' : ($field->default ? 'true' : 'false');

        return "\$table->boolean('{$field->key}')->default({$default})->comment(".$this->quote($field->label).');';
    }

    public function modelCast(FieldDefinition $field): ?string
    {
        return $this->quote($field->key).' => '.$this->quote('boolean');
    }

    public function formComponentExpression(FieldDefinition $field): string
    {
        $default = $field->default === null ? 'false' : ($field->default ? 'true' : 'false');

        return "Toggle::make('{$field->key}')->label(".$this->quote($field->label).")->default({$default})";
    }

    public function formComponentImports(): array
    {
        return ['Filament\\Forms\\Components\\Toggle'];
    }

    public function rules(FieldDefinition $field): array
    {
        return ['boolean'];
    }
}
