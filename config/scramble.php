<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

/**
 * Scramble API 文档配置（FEAT-02 / D-33）
 *
 * 演示项目专属配置，仅在根 composer.json require-dev 中引入，不进主包。
 * 访问路径：/docs/api（OpenAPI 3.0 文档界面）、/docs/api.json（OpenAPI JSON）
 *
 * 关键决策：
 *   D-33：dedoc/scramble 仅装演示项目根，不进主包 require
 *   D-34：与 knuckleswtf/scribe 共存，不替代 Scribe（.scribe/ 静态文档保留）
 *   D-35：默认使用 RestrictedDocsAccess 中间件，仅 local/非生产环境可访问
 *
 * Pitfall 4 防护：api_path => 'api/v1' 过滤路由范围，
 * 配合 AppServiceProvider::boot() 中的 Scramble::routes() 回调双保险，
 * 只文档化 api/v1/* 路由，避免 Filament 内部路由和 Sanctum token 管理路由被文档化。
 */
return [

    /*
    |--------------------------------------------------------------------------
    | API 路由前缀过滤
    |--------------------------------------------------------------------------
    |
    | 指定被文档化的 API 路由前缀。设置为 'api/v1' 可精确匹配
    | routes/api.php 中定义的 v1 管理接口（admin/login、admin/me、admin/logout）。
    |
    | 同时在 AppServiceProvider::boot() 中通过 Scramble::routes() 回调进行双重过滤。
    |
    */
    'api_path' => 'api/v1',

    /*
    |--------------------------------------------------------------------------
    | API 域名
    |--------------------------------------------------------------------------
    |
    | 若 API 与前台运行在不同子域名下，在此配置 API 域名。
    | null 表示与当前应用同域（演示环境默认值）。
    |
    */
    'api_domain' => null,

    /*
    |--------------------------------------------------------------------------
    | OpenAPI 导出路径
    |--------------------------------------------------------------------------
    |
    | /docs/api.json 响应导出的文件名（相对于 public/ 目录）。
    |
    */
    'export_path' => 'api.json',

    /*
    |--------------------------------------------------------------------------
    | 文档访问中间件
    |--------------------------------------------------------------------------
    |
    | D-35：RestrictedDocsAccess 中间件限制仅 local/非生产环境可访问，
    | 防止 API 结构在生产环境泄露（T-03-08 威胁缓解）。
    |
    */
    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | 扩展
    |--------------------------------------------------------------------------
    |
    | 自定义 Scramble 扩展（如 SecurityScheme、类型处理器等）。
    | 演示项目暂无需自定义扩展。
    |
    */
    'extensions' => [],

];
