<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes;

/**
 * 字段类型基类（批次 5）
 *
 * 吃掉多数字段类型共享的默认实现：不需要 cast、无额外校验规则、
 * 展示视图按 key 推导。具体字段类型只需声明 key / label /
 * migrationColumn / formComponentExpression / formComponentImports。
 */
abstract class AbstractFieldType implements FieldTypeContract
{
    /**
     * 默认不需要 cast
     */
    public function modelCast(FieldDefinition $field): ?string
    {
        return null;
    }

    /**
     * 默认校验规则：仅按 required/nullable 派生，不含类型专属规则
     *
     * @return list<string>
     */
    public function rules(FieldDefinition $field): array
    {
        return [$field->required ? 'required' : 'nullable'];
    }

    /**
     * 前台展示视图名（默认按 key 推导）
     */
    public function renderView(): string
    {
        return $this->key();
    }

    /**
     * 把 FieldDefinition::$label 转成表单/迁移代码里安全的单引号字符串字面量
     */
    protected function quote(string $value): string
    {
        return "'".addslashes($value)."'";
    }
}
