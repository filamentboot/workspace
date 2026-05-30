<?php

use App\Models\Menu;

it('菜单支持父子级和启用作用域', function () {
    $parent = Menu::factory()->create(['is_active' => true]);
    Menu::factory()->create([
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);
    Menu::factory()->create(['is_active' => false]);

    expect($parent->children)->toHaveCount(1)
        ->and(Menu::active()->count())->toBe(2);
});
