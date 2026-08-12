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
     * 八类内容资源
     *
     * ⚠️ 新增内容资源时**这里和 `SitePermissionSeeder` 要一起改**。只加权限点不加
     * 到这份清单里，后果是那类内容的权限**建出来了但没有任何角色拿得到**，
     * 除超管外谁都进不去——而超管走 `Gate::before` 放行，自测时完全看不出来。
     * `site_package` 就是这么漏了一轮（2.5 期加的资源，三期批次 4 才补上）。
     *
     * @var list<string>
     */
    private const CONTENT_RESOURCES = [
        'site_banner',
        'site_case',
        'site_solution',
        'site_package',
        'site_product',
        'site_city_page',
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

        // 分类与标签必须能建，否则新内容挂不上栏目、打不了标签。
        // 标签尤其要给到这一档：它决定 /tags/{slug} 那批公开聚合页，
        // 而写内容的人才知道该起什么标签——要另一个人代建反而更容易起乱。
        foreach (['news_category', 'site_case_category', 'site_product_category', 'site_tag'] as $resource) {
            foreach (['view_any', 'view', 'create', 'update'] as $action) {
                $permissions[] = $action.'_'.$resource;
            }
        }

        // 站内搜索词是选题依据（尤其是零结果那一档），给最低一档角色
        $permissions[] = 'view_site_search_terms';

        // 友情链接 / 广告位（七期批次 5）：与分类/标签同档——editor 只读写不删
        foreach (['friend_link', 'ad_slot'] as $resource) {
            foreach (['view_any', 'view', 'create', 'update'] as $action) {
                $permissions[] = $action.'_'.$resource;
            }
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

        // 发布权限覆盖全 6 类内容（批次 1.5a）；回滚权限覆盖全 7 类内容
        // （批次 1.5c 起，修订历史泛化到全部内容类型）
        $permissions[] = 'publish_site_page';
        $permissions[] = 'publish_site_case';
        $permissions[] = 'publish_news_article';
        $permissions[] = 'publish_site_solution';
        $permissions[] = 'publish_site_product';
        $permissions[] = 'publish_site_package';
        $permissions[] = 'publish_site_city_page';
        $permissions[] = 'rollback_site_page';
        $permissions[] = 'rollback_site_case';
        $permissions[] = 'rollback_news_article';
        $permissions[] = 'rollback_site_solution';
        $permissions[] = 'rollback_site_product';
        $permissions[] = 'rollback_site_package';
        $permissions[] = 'rollback_site_city_page';

        foreach (self::CONTENT_RESOURCES as $resource) {
            foreach (['delete', 'restore', 'force_delete'] as $action) {
                $permissions[] = $action.'_'.$resource;
            }
        }

        // 删分类 / 删标签留到发布这一档：删标签会让一个已收录的 /tags/{slug} 直接 404
        foreach (['news_category', 'site_case_category', 'site_product_category', 'site_tag'] as $resource) {
            $permissions[] = 'delete_'.$resource;
        }

        // 友情链接 / 广告位的删除留到发布这一档，多一个 delete_any——
        // 这两个 Resource 的 toolbarActions 带 DeleteBulkAction，分类/标签没有
        foreach (['friend_link', 'ad_slot'] as $resource) {
            $permissions[] = 'delete_'.$resource;
            $permissions[] = 'delete_any_'.$resource;
        }

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
