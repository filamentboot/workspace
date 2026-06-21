<?php

use Filamentboot\Models\AdminUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('管理员可以上传头像到 avatar Collection', function () {
    Storage::fake('public');

    $user = AdminUser::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg');

    $user->addMedia($file->getRealPath())
        ->usingFileName('avatar.jpg')
        ->usingName('avatar')
        ->toMediaCollection('avatar');

    expect($user->getFirstMedia('avatar'))->not->toBeNull()
        ->and($user->getFirstMedia('avatar')->file_name)->toBe('avatar.jpg');
});
