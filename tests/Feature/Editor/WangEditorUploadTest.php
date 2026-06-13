<?php

use FilamentAdmin\Models\AdminUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * WangEditor 图片上传 HTTP 集成测试
 *
 * 验证 /filament-admin-wang-editor/upload 路由（D-09-09 / T-09-07）：
 * 1. 未认证请求返回非 200（401 或重定向）
 * 2. 合法 JPG 图片上传成功，返回 errno=0 + url
 * 3. 危险扩展名（php）被拒绝，返回 errno=1
 * 4. 无 file 字段时返回 errno=1
 */
beforeEach(function () {
    Storage::fake('public');
});

it('未认证请求上传图片应被拒绝', function () {
    $file = UploadedFile::fake()->image('avatar.jpg');

    $response = post('/filament-admin-wang-editor/upload', [
        'file' => $file,
        'disk' => 'public',
    ]);

    // web 中间件 + auth:admin 导致未认证请求重定向或返回 401/403
    expect($response->status())->toBeIn([401, 403, 302]);
});

it('合法 JPG 图片上传返回 errno=0 和 url', function () {
    $user = AdminUser::factory()->create();
    actingAs($user, 'admin');

    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    $response = post('/filament-admin-wang-editor/upload', [
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

    $response = post('/filament-admin-wang-editor/upload', [
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

    $response = post('/filament-admin-wang-editor/upload', [
        'disk' => 'public',
    ]);

    $response->assertOk();
    $json = $response->json();

    expect($json['errno'])->toBe(1);
});
