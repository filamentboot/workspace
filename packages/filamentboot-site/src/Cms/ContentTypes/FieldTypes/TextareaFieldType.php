<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\AbstractFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldDefinition;

/**
 * 多行文本字段类型（批次 5）
 *
 * 存 TEXT 列，表单用 Textarea——不做富文本，需要 HTML 的场景用 RichTextFieldType。
 */
class TextareaFieldType extends AbstractFieldType
{
    public function key(): string
    {
        return 'textarea';
    }

    public function label(): string
    {
        return '多行文本';
    }

    public function migrationColumn(FieldDefinition $field): string
    {
        $stmt = "\$table->text('{$field->key}')";

        if (! $field->required) {
            $stmt .= '->nullable()';
        }

        return $stmt.'->comment('.$this->quote($field->label).');';
    }

    public function formComponentExpression(FieldDefinition $field): string
    {
        $expr = "Textarea::make('{$field->key}')->label(".$this->quote($field->label).')';

        if ($field->required) {
            $expr .= '->required()';
        }

        if ($field->maxLength !== null) {
            $expr .= "->maxLength({$field->maxLength})";
        }

        return $expr;
    }

    public function formComponentImports(): array
    {
        return ['Filament\\Forms\\Components\\Textarea'];
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
