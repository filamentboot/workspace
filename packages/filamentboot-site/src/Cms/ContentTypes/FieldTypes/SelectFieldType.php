<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\AbstractFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldDefinition;

/**
 * 下拉选择字段类型（批次 5）
 *
 * 存储为普通字符串列（存 FieldDefinition::$choices 的 key），不使用枚举类型——
 * 生成的内容类型不预生成 PHP Enum，选项变更只改声明、重新生成即可，
 * 不需要跟着改一个专属枚举类的 cases()。
 */
class SelectFieldType extends AbstractFieldType
{
    public function key(): string
    {
        return 'select';
    }

    public function label(): string
    {
        return '下拉选择';
    }

    public function migrationColumn(FieldDefinition $field): string
    {
        $stmt = "\$table->string('{$field->key}', 50)";

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
        $expr = "Select::make('{$field->key}')->label(".$this->quote($field->label).')->options('
            .$this->exportChoices($field->choices).')';

        if ($field->default !== null) {
            $expr .= '->default('.$this->quote((string) $field->default).')';
        }

        if ($field->required) {
            $expr .= '->required()';
        }

        return $expr;
    }

    public function formComponentImports(): array
    {
        return ['Filament\\Forms\\Components\\Select'];
    }

    public function rules(FieldDefinition $field): array
    {
        $rules   = [$field->required ? 'required' : 'nullable', 'string'];
        $choices = array_keys($field->choices);

        if ($choices !== []) {
            $rules[] = 'in:'.implode(',', $choices);
        }

        return $rules;
    }

    /**
     * 把 choices（value => label）导出成生成代码里的 PHP 数组字面量
     *
     * @param  array<string, string>  $choices
     */
    private function exportChoices(array $choices): string
    {
        $pairs = [];

        foreach ($choices as $value => $optionLabel) {
            $pairs[] = $this->quote((string) $value).' => '.$this->quote($optionLabel);
        }

        return '['.implode(', ', $pairs).']';
    }
}
