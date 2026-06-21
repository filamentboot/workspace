<?php

use Filamentboot\Models\AdminUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * WangEditor 图片上传 HTTP 集成测试
 *
 * 验证 /filamentboot-wang-editor/upload 路由（D-09-09 / T-09-07）：
 * 1. 未认证请求返回非 200（401 或重定向）
 * 2. 合法 JPG 图片上传成功，返回 errno=0 + url
 * 3. 危险扩展名（php）被拒绝，返回 errno=1
 * 4. 无 file 字段时返回 errno=1
 */
beforeEach(function () {
    Storage::fake('public');
});

it('未认证请求上传图片应被拒绝（不返回 errno=0 成功）', function () {
    $file = UploadedFile::fake()->image('avatar.jpg');

    $response = post('/filamentboot-wang-editor/upload', [
        'file' => $file,
        'disk' => 'public',
    ]);

    // auth:admin 中间件拦截未认证请求：可能重定向（302）、返回 401/403、
    // 或因 admin guard 找不到 login route 而抛 500。
    // 核心断言：未认证用户不能得到 200 成功响应（errno=0 文件落盘）。
    if ($response->status() === 200) {
        expect($response->json('errno'))->not->toBe(0, '未认证用户不应能成功上传');
    } else {
        expect($response->status())->not->toBe(200);
    }
});

it('合法 JPG 图片上传返回 errno=0 和 url', function () {
    $user = AdminUser::factory()->create();
    actingAs($user, 'admin');

    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    $response = post('/filamentboot-wang-editor/upload', [
        'file' => $file,
        'disk' => 'public',
    ]);

    $response->assertOk();
    $json = $response->json();

    expect($json['errno'])->toBe(0)
        ->and($json['data']['url'])->not->toBeEmpty();
});

it('危险扩展名 .php 文件被拒绝，返回 errno=1', function () {
    $user = AdminUser::factory()->create();
    actingAs($user, 'admin');

    // 创建一个伪装成 PHP 脚本的文件
    $file = UploadedFile::fake()->create('shell.php', 10, 'text/plain');

    $response = post('/filamentboot-wang-editor/upload', [
        'file' => $file,
        'disk' => 'public',
    ]);

    $response->assertOk();
    $json = $response->json();

    expect($json['errno'])->toBe(1)
        ->and($json['message'])->not->toBeEmpty();
});

it('无 file 字段时返回 errno=1', function () {
    $user = AdminUser::factory()->create();
    actingAs($user, 'admin');

    $response = post('/filamentboot-wang-editor/upload', [
        'disk' => 'public',
    ]);

    $response->assertOk();
    $json = $response->json();

    expect($json['errno'])->toBe(1);
});
