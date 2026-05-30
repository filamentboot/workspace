<?php

use App\Models\Department;
use App\Services\DepartmentTree;

it('可以获取部门及下级部门 ID', function () {
    $root      = Department::factory()->create();
    $child     = Department::factory()->create(['parent_id' => $root->id]);
    $grandson  = Department::factory()->create(['parent_id' => $child->id]);
    $other     = Department::factory()->create();
    $service   = app(DepartmentTree::class);

    expect($service->getDescendantIds($root))->toBe([$child->id, $grandson->id])
        ->and($service->getSelfAndDescendantIds($root))->toBe([$root->id, $child->id, $grandson->id])
        ->and($service->getDescendantIds($other))->toBe([]);
});

it('可以识别部门循环父级', function () {
    $root     = Department::factory()->create();
    $child    = Department::factory()->create(['parent_id' => $root->id]);
    $grandson = Department::factory()->create(['parent_id' => $child->id]);
    $service  = app(DepartmentTree::class);

    expect($service->wouldCreateCycle($root, $grandson))->toBeTrue()
        ->and($service->wouldCreateCycle($child, $root))->toBeFalse()
        ->and($service->wouldCreateCycle($child, $child))->toBeTrue();
});
