<?php

/**
 * 冒烟测试：验证应用基础功能正常
 */
test('application returns successful response', function () {
    // / 由 filament-admin-site 插件接管；后台冒烟测试用 /marketing 路由
    $response = $this->get('/marketing');
    $response->assertStatus(200);
});

test('database connection works', function () {
    expect(DB::connection()->getDatabaseName())->not->toBeNull();
});
