<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\AbstractFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldDefinition;

/**
 * 单行文本字段类型（批次 5）
 *
 * 对应数据库 VARCHAR 列，表单用 TextInput。字段类型实现的标准范式——
 * 其余标量字段类型（Url/Number 等）照此结构增减约束项。
 */
class TextFieldType extends AbstractFieldType
{
    public function key(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return '单行文本';
    }

    public function migrationColumn(FieldDefinition $field): string
    {
        $length = $field->maxLength ?? 255;
        $stmt   = "\$table->string('{$field->key}', {$length})";

        if ($field->unique) {
            $stmt .= '->unique()';
        }

        if (! $field->required) {
            $stmt .= '->nullable()';
        }

        if ($field->default !== null) {
            $stmt .= '->default('.$this->quote((string) $field->default).')';
        }

        return $stmt.'->comment('.$this->quote($field->label).');';
    }

    public function formComponentExpression(FieldDefinition $field): string
    {
        $expr = "TextInput::make('{$field->key}')->label(".$this->quote($field->label).')';

        if ($field->required) {
            $expr .= '->required()';
        }

        if ($field->maxLength !== null) {
            $expr .= "->maxLength({$field->maxLength})";
        }

        if ($field->unique) {
            $expr .= '->unique(ignoreRecord: true)';
        }

        return $expr;
    }

    public function formComponentImports(): array
    {
        return ['Filament\\Forms\\Components\\TextInput'];
    }

    public function rules(FieldDefinition $field): array
    {
        $rules = [$field->required ? 'required' : 'nullable', 'string'];

        if ($field->maxLength !== null) {
            $rules[] = 'max:'.$field->maxLength;
        }

        return $rules;
    }
}
