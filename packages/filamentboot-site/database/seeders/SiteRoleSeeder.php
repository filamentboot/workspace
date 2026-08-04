<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 官网插件三层角色种子（#19）
 *
 * 把 SitePermissionSeeder 建好的权限点组装成开箱可用的三档角色：
 *
 * | 角色     | 能做什么                                             |
 * |----------|------------------------------------------------------|
 * | 内容编辑 | 写内容、提交审核，**不能发布**                        |
 * | 内容发布 | 内容编辑全部 + 发布 / 定时发布 / 删除 / 版本回滚      |
 * | 站点管理 | 内容发布全部 + 站点设置 / 导航 / 重定向 / 询盘与导出  |
 *
 * 分层的实际意义就在「内容编辑不能发布」这一条：小团队里写文案的人和
 * 对外发声负责的人往往不是同一个，publish_site_page 是那道闸门。
 *
 * 媒体上传没有独立权限点：图片是通过各内容资源的 FileUpload 字段上传的，
 * 有 create/update 内容的权限就能传图，另立一个权限点只会带来
 * 「能编辑但传不了图」这种没人想要的组合。
 *
 * 幂等：角色用 firstOrCreate，权限用 syncPermissions（重跑会把手工加的权限刷掉，
 * 这是有意的——角色定义应当以代码为准，否则升级后各站权限各不相同没法支持）。
 * 权限点缺失时跳过而不报错：下游可能只装了部分功能。
 *
 * 超管沿用主包 Gate::before()，不需要也不应该在这里授予任何权限。
 */
class SiteRoleSeeder extends Seeder
{
    /**
     * 权限守卫名（后台 guard）
     */
    private const GUARD = 'admin';

    /**
     * 五类内容资源
     *
     * @var list<string>
     */
    private const CONTENT_RESOURCES = [
        'site_case',
        'site_solution',
        'site_product',
        'site_page',
        'news_article',
    ];

    /**
     * 写入三层角色
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $editor    = $this->editorPermissions();
        $publisher = $this->publisherPermissions($editor);
        $manager   = $this->managerPermissions($publisher);

        foreach ([
            '内容编辑' => $editor,
            '内容发布' => $publisher,
            '站点管理' => $manager,
        ] as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => self::GUARD]);

            $role->syncPermissions($this->existing($permissions));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * 内容编辑：五类内容的读写 + 资讯分类读写，无发布无删除
     *
     * @return list<string>
     */
    private function editorPermissions(): array
    {
        $permissions = [];

        foreach (self::CONTENT_RESOURCES as $resource) {
            foreach (['view_any', 'view', 'create', 'update'] as $action) {
                $permissions[] = $action.'_'.$resource;
            }
        }

        // 资讯必须能分类，否则新文章挂不上栏目
        foreach (['view_any', 'view', 'create', 'update'] as $action) {
            $permissions[] = $action.'_news_category';
        }

        return $permissions;
    }

    /**
     * 内容发布：内容编辑全部 + 发布 + 回滚 + 删除类动作
     *
     * @param  list<string>  $editor
     * @return list<string>
     */
    private function publisherPermissions(array $editor): array
    {
        $permissions = $editor;

        $permissions[] = 'publish_site_page';
        $permissions[] = 'rollback_site_page';

        foreach (self::CONTENT_RESOURCES as $resource) {
            foreach (['delete', 'restore', 'force_delete'] as $action) {
                $permissions[] = $action.'_'.$resource;
            }
        }

        $permissions[] = 'delete_news_category';

        return array_values(array_unique($permissions));
    }

    /**
     * 站点管理：内容发布全部 + 站点级配置 + 询盘
     *
     * @param  list<string>  $publisher
     * @return list<string>
     */
    private function managerPermissions(array $publisher): array
    {
        return array_values(array_unique([
            ...$publisher,
            'manage_site_settings',
            'manage_site_menu',
            'manage_site_redirect',
            'view_site_settings_page',
            'view_any_contact_message',
            'view_contact_message',
            'update_contact_message',
            'delete_contact_message',
            // 询盘导出是 PII 批量外流，只给到站点管理这一档
            'export_contact_message',
        ]));
    }

    /**
     * 过滤出数据库里真实存在的权限点
     *
     * 下游可能只装了部分功能（比如没跑资讯模块的种子），给 syncPermissions
     * 传一个不存在的权限名会抛 PermissionDoesNotExist，让整个安装流程中断。
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function existing(array $names): array
    {
        return Permission::query()
            ->whereIn('name', $names)
            ->where('guard_name', self::GUARD)
            ->pluck('name')
            ->all();
    }
}
