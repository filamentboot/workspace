<?php

use Filament\Facades\Filament;
use Filament\Models\Contracts\HasAvatar;
use Filamentboot\Models\AdminUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('AdminUser 实现 HasAvatar 契约', function () {
    expect(AdminUser::factory()->make())->toBeInstanceOf(HasAvatar::class);
});

it('无头像时回落到本地首字头像，不请求外网', function () {
    $user = AdminUser::factory()->create(['nickname' => '张三', 'avatar' => null]);

    $url = Filament::getUserAvatarUrl($user);

    expect($url)->toStartWith('data:image/svg+xml;base64,')
        ->and($url)->not->toContain('ui-avatars');

    // 解码后应包含昵称首字与圆形底
    $svg = base64_decode(substr($url, strlen('data:image/svg+xml;base64,')), true);

    expect($svg)->toContain('张')
        ->and($svg)->toContain('<circle');
});

it('已上传头像时返回 media 库 URL', function () {
    Storage::fake('public');

    $user = AdminUser::factory()->create(['avatar' => null]);
    $file = UploadedFile::fake()->image('avatar.jpg');

    $user->addMedia($file->getRealPath())
        ->usingFileName('avatar.jpg')
        ->toMediaCollection('avatar');

    expect(Filament::getUserAvatarUrl($user->refresh()))
        ->toContain('avatar.jpg')
        ->and(Filament::getUserAvatarUrl($user))->not->toStartWith('data:image/');
});
