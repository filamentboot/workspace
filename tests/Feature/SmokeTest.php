<?php

/**
 * 冒烟测试：验证应用基础功能正常
 */
test('application returns successful response', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});

test('database connection works', function () {
    expect(DB::connection()->getDatabaseName())->not->toBeNull();
});
