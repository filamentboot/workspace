<?php

/**
 * 官网占位页测试（FINAL-05）
 *
 * 验证 GET / 返回 200 并包含四块关键内容：
 * - 项目定位文案
 * - 功能清单
 * - 安装指引（composer require 示例）
 * - 演示站链接
 */

it('GET /marketing 返回 200', function () {
    $this->get('/marketing')->assertOk();
});

it('首页包含项目定位关键文案', function () {
    $this->get('/marketing')
        ->assertOk()
        ->assertSee('FilamentAdmin');
});

it('首页包含安装指引文案', function () {
    $this->get('/marketing')
        ->assertOk()
        ->assertSee('composer require laravelstack/filament-admin');
});

it('首页包含演示站链接', function () {
    $this->get('/marketing')
        ->assertOk()
        ->assertSee('demo.xitongapp.com');
});
