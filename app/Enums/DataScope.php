<?php

namespace App\Enums;

/**
 * 数据权限范围枚举
 */
enum DataScope: string
{
    case All                   = 'all';
    case Department            = 'department';
    case DepartmentAndChildren = 'department_and_children';
    case Self                  = 'self';
    case CustomDepartments     = 'custom_departments';

    public function label(): string
    {
        return match ($this) {
            self::All                   => '全部数据',
            self::Department            => '本部门数据',
            self::DepartmentAndChildren => '本部门及下级部门数据',
            self::Self                  => '仅本人数据',
            self::CustomDepartments     => '指定部门数据',
        };
    }
}
