<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\AbstractFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldDefinition;

/**
 * 数字字段类型（批次 5）
 *
 * 统一用 INTEGER 列——内容类型系统的验收题（友链排序、广告位排序）都是
 * 整数场景，不需要小数；要小数字段属于超出当前验收范围的新增类型，
 * 留给真有需求时再加 DecimalFieldType，不预先做。
 */
class NumberFieldType extends AbstractFieldType
{
    public function key(): string
    {
        return 'number';
    }

    public function label(): string
    {
        return '数字';
    }

    public function migrationColumn(FieldDefinition $field): string
    {
        $default = $field->default !== null ? (string) (int) $field->default : '0';

        return "\$table->integer('{$field->key}')->default({$default})->comment(".$this->quote($field->label).');';
    }

    public function formComponentExpression(FieldDefinition $field): string
    {
        $default = $field->default !== null ? (string) (int) $field->default : '0';
        $expr    = "TextInput::make('{$field->key}')->label(".$this->quote($field->label).")->numeric()->default({$default})";

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
        return [$field->required ? 'required' : 'nullable', 'integer'];
    }
}
