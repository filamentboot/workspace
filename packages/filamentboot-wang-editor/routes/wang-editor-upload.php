<?php

use Filamentboot\FilamentbootWangEditor\Http\Controllers\WangEditorUploadController;
use Illuminate\Support\Facades\Route;

/*
 * wangEditor 图片上传路由
 *
 * middleware(['web', 'auth:admin'])：
 * - web：提供 CSRF 防护（T-09-07）
 * - auth:admin：限定已登录的后台管理员，防止未授权上传
 */
Route::post('/filamentboot-wang-editor/upload', WangEditorUploadController::class)
    ->name('filamentboot-wang-editor.upload')
    ->middleware(['web', 'auth:admin']);
