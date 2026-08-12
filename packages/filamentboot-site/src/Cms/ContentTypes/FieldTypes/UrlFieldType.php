<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\AbstractFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldDefinition;

/**
 * 链接字段类型（批次 5）
 *
 * 与 TextFieldType 同一列形状（VARCHAR），区别只在表单侧套 ->url() 校验
 * 与前台展示局部——这正是「字段类型只在自己的类里描述差异」的示范。
 */
class UrlFieldType extends AbstractFieldType
{
    public function key(): string
    {
        return 'url';
    }

    public function label(): string
    {
        return '链接';
    }

    public function migrationColumn(FieldDefinition $field): string
    {
        $length = $field->maxLength ?? 255;
        $stmt   = "\$table->string('{$field->key}', {$length})";

        if (! $field->required) {
            $stmt .= '->nullable()';
        }

        return $stmt.'->comment('.$this->quote($field->label).');';
    }

    public function formComponentExpression(FieldDefinition $field): string
    {
        $maxLength = $field->maxLength ?? 255;
        $expr      = "TextInput::make('{$field->key}')->label(".$this->quote($field->label).")->url()->maxLength({$maxLength})";

        if ($field->required) {
            $expr .= '->required()';
        }

        return $expr;
    }

    public function formComponentImports(): array
    {
        return ['Filament\\Forms\\Components\\TextInput'];
    }

    public function rules(FieldDefinition $field): array
    {
        return [$field->required ? 'required' : 'nullable', 'url', 'max:'.($field->maxLength ?? 255)];
    }
}
