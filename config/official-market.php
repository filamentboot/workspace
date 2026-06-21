<?php

// TODO: 改名 review — 以下 filament-admin/* 为未注册占位包名，
// 待用户确认改为 filamentboot/* 或删除后，再更新 package_name / command 字段。
// 原因：这些包名（filament-admin/aliyun-sms、filament-admin/huawei-cloud-sms 等）
// 尚未在 Packagist 注册，且改名目标（filamentboot/*）也未注册，
// 不可盲目自动修改，避免误导下游用户安装不存在的包。
// 参考：.planning/phases/13-filamentboot/13-RESEARCH.md Open Question 2 / Assumption A7

return [
    'entries' => [
        [
            'slug'              => 'tiptap-editor',
            'display_name'      => '富文本编辑器',
            'package_name'      => 'awcodes/filament-tiptap-editor',
            'kind'              => 'plugin',
            'source'            => 'official_trusted',
            'version'           => '^4.0',
            'author_name'       => 'Awcodes',
            'summary'           => '面向内容发布与后台录入场景的富文本编辑器插件。',
            'description'       => '为 FilamentAdmin 提供更完整的正文编辑体验，适合文章、公告、产品详情等内容型表单。',
            'documentation_url' => 'https://filamentphp.com/plugins/awcodes-tiptap-editor',
            'installation'      => [
                'instructions' => '安装后在业务表单中接入富文本字段，并按需补充上传与图片处理配置。',
            ],
        ],
        [
            'slug'              => 'media-library-center',
            'display_name'      => '媒体库中心',
            'package_name'      => 'filament/spatie-laravel-media-library-plugin',
            'kind'              => 'plugin',
            'source'            => 'official_trusted',
            'version'           => '^4.0',
            'author_name'       => 'Filament',
            'summary'           => '为图片、附件和多媒体资源提供统一上传与管理能力。',
            'description'       => '适合头像、Logo、文章封面和附件库等资源管理场景，可作为媒体库能力的基础插件。',
            'documentation_url' => 'https://filamentphp.com/plugins/filament-spatie-media-library',
            'installation'      => [
                'instructions' => '安装后请继续配置磁盘、缩略图和上传策略，再接入业务资源表单。',
            ],
        ],
        [
            'slug'         => 'aliyun-sms',
            'display_name' => '阿里云短信',
            'package_name' => 'filament-admin/aliyun-sms',
            'kind'         => 'plugin',
            'source'       => 'official_listed',
            'version'      => '0.1.0',
            'author_name'  => 'FilamentAdmin',
            'summary'      => '面向中国大陆短信通知场景的云短信接入插件。',
            'description'  => '提供短信签名、模板和发送配置能力，适合登录提醒、验证码和业务通知场景。',
            'installation' => [
                'instructions' => '当前先按安装指引手动接入，后续发布为官方可信来源后再开放后台安装。',
                'command'      => 'composer require filament-admin/aliyun-sms',
            ],
        ],
        [
            'slug'         => 'huawei-cloud-sms',
            'display_name' => '华为云短信',
            'package_name' => 'filament-admin/huawei-cloud-sms',
            'kind'         => 'plugin',
            'source'       => 'official_listed',
            'version'      => '0.1.0',
            'author_name'  => 'FilamentAdmin',
            'summary'      => '面向华为云生态的短信能力插件。',
            'description'  => '适合已经使用华为云账号体系和短信服务的项目，支持模板发送与基础签名配置。',
            'installation' => [
                'instructions' => '当前先按安装指引手动接入，后续发布为官方可信来源后再开放后台安装。',
                'command'      => 'composer require filament-admin/huawei-cloud-sms',
            ],
        ],
        [
            'slug'         => 'crm-suite',
            'display_name' => 'CRM 套件',
            'package_name' => 'filament-admin/crm-suite',
            'kind'         => 'solution_plugin',
            'source'       => 'official_listed',
            'version'      => '0.1.0',
            'author_name'  => 'FilamentAdmin',
            'summary'      => '客户跟进、线索管理和销售过程管理的方案型插件。',
            'description'  => '安装后会带来客户、联系人、销售机会、跟进记录和业务配置等完整后台能力。',
            'installation' => [
                'instructions' => '当前作为官方收录方案型插件展示，先提供兼容信息与安装指引，不开放后台一键安装。',
                'command'      => 'composer require filament-admin/crm-suite',
            ],
        ],
        [
            'slug'         => 'corporate-site-suite',
            'display_name' => '企业官网',
            'package_name' => 'filament-admin/corporate-site-suite',
            'kind'         => 'solution_plugin',
            'source'       => 'official_listed',
            'version'      => '0.1.0',
            'author_name'  => 'FilamentAdmin',
            'summary'      => '适合官网、内容页和基础表单管理的方案型插件。',
            'description'  => '覆盖站点栏目、Banner、内容页、SEO 基础配置和留言表单等官网常见能力。',
            'installation' => [
                'instructions' => '当前作为官方收录方案型插件展示，先提供兼容信息与安装指引，不开放后台一键安装。',
                'command'      => 'composer require filament-admin/corporate-site-suite',
            ],
        ],
    ],
];
