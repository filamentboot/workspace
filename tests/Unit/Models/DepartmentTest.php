<?php

use App\Models\AdminUser;
use App\Models\Department;

it('部门支持父子级和负责人关系', function () {
    $leader = AdminUser::factory()->create();
    $parent = Department::factory()->create();
    $child  = Department::factory()->create([
        'parent_id'            => $parent->id,
        'leader_admin_user_id' => $leader->id,
    ]);

    expect($child->parent->is($parent))->toBeTrue()
        ->and($parent->children)->toHaveCount(1)
        ->and($child->leader->is($leader))->toBeTrue();
});
