<?php

use Illuminate\Support\Facades\Route;
use LaravelStack\FilamentAdminWangEditor\Http\Controllers\WangEditorUploadController;

/*
 * wangEditor 图片上传路由
 *
 * middleware(['web', 'auth:admin'])：
 * - web：提供 CSRF 防护（T-09-07）
 * - auth:admin：限定已登录的后台管理员，防止未授权上传
 */
Route::post('/filament-admin-wang-editor/upload', WangEditorUploadController::class)
    ->name('filament-admin-wang-editor.upload')
    ->middleware(['web', 'auth:admin']);
