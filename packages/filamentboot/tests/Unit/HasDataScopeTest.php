<?php

namespace Filamentboot\Tests\Unit;

use Filamentboot\Filament\Resources\Concerns\HasDataScope;
use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Models\AdminUser;
use Filamentboot\Models\Department;
use Filamentboot\Services\DepartmentTree;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Orchestra\Testbench\TestCase;

/**
 * 数据权限分档 trait（HasDataScope）行为测试（四期功能清单第 1 档 #5）
 *
 * 只验证 trait 自身的过滤逻辑，不绑定任何真实 Resource——批次 1.4 拍板
 * 暂不接入具体内容类型，先把可复用的机制打好。用 Mockery 伪造 AdminUser /
 * DepartmentTree，不落真实 DB（做法参照 MenuBulkActionAuthTest）。
 *
 * ⚠️ 必须用独立临时目录隔离 base_path()（做法照抄 MakeMigrationCommandTest）：
 * Testbench 默认复用 vendor/orchestra/testbench-core/laravel/ 这一份共享
 * skeleton，其 bootstrap/cache/services.php 是跨测试文件共享的磁盘缓存——
 * 不隔离的话，本文件的 Testbench 应用一启动就会重写这份缓存，导致排在
 * 后面、经由同一份共享 skeleton 启动的 MakeMigrationCommandTest 等命令测试
 * 集体炸掉一个和本文件毫无关系的 Spatie\Permission\Guard 类型错误（批次
 * 1.4 提交历史里实测过，且与本文件注册什么 provider、设置什么 config 完全
 * 无关——纯粹是"任何新插入的非隔离 Testbench 启动"都会触发）。
 */
class HasDataScopeTest extends TestCase
{
    protected string $tempBase = '';

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [FilamentbootServiceProvider::class];
    }

    protected function getApplicationBasePath(): string
    {
        return $this->tempBase;
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('auth.guards.admin', [
            'driver'   => 'session',
            'provider' => 'admin_users',
        ]);
        $app['config']->set('auth.providers.admin_users', [
            'driver' => 'eloquent',
            'model'  => AdminUser::class,
        ]);
    }

    protected function setUp(): void
    {
        $this->tempBase = sys_get_temp_dir().'/filamentboot-has-data-scope-'.uniqid();

        foreach (['bootstrap/cache', 'storage/app/public', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/testing', 'storage/framework/views', 'storage/logs', 'app', 'config', 'database/migrations', 'resources/views', 'tests'] as $dir) {
            mkdir($this->tempBase.'/'.$dir, 0755, true);
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();

        if ($this->tempBase !== '' && is_dir($this->tempBase)) {
            $this->deleteDirectoryNative($this->tempBase);
        }
    }

    private function deleteDirectoryNative(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;

            if (is_dir($path)) {
                $this->deleteDirectoryNative($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * 造一个自带、从不执行的 Builder——不经 AdminUser::query()：Testbench 的
     * Model 连接解析器是进程级静态状态，配一个真实数据库连接同样会跨测试
     * 文件互相污染。
     */
    private function newAdminUserQuery(): Builder
    {
        $connection = new Connection(fn () => null);
        $builder    = new Builder($connection->query());
        $builder->setModel(new AdminUser);

        return $builder;
    }

    public function test_no_scope_applies_no_restriction(): void
    {
        $user = Mockery::mock(AdminUser::class)->makePartial();
        Auth::guard('admin')->setUser($user);

        DataScopeTestResource::$scopeOverride = null;
        $query                                = DataScopeTestResource::scopeQuery($this->newAdminUserQuery());

        $this->assertEmpty($query->getQuery()->wheres);
    }

    public function test_super_admin_bypasses_personal_scope(): void
    {
        $user = Mockery::mock(AdminUser::class)->makePartial();
        $user->shouldReceive('hasRole')->andReturn(true);
        Auth::guard('admin')->setUser($user);

        DataScopeTestResource::$scopeOverride = 'personal';
        $query                                = DataScopeTestResource::scopeQuery($this->newAdminUserQuery());

        $this->assertEmpty($query->getQuery()->wheres);
    }

    public function test_super_admin_bypasses_department_scope(): void
    {
        $user = Mockery::mock(AdminUser::class)->makePartial();
        $user->shouldReceive('hasRole')->andReturn(true);
        Auth::guard('admin')->setUser($user);

        DataScopeTestResource::$scopeOverride = 'department';
        $query                                = DataScopeTestResource::scopeQuery($this->newAdminUserQuery());

        $this->assertEmpty($query->getQuery()->wheres);
    }

    public function test_personal_scope_filters_by_created_by(): void
    {
        $user = Mockery::mock(AdminUser::class)->makePartial();
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->id = 42;
        Auth::guard('admin')->setUser($user);

        DataScopeTestResource::$scopeOverride = 'personal';
        $query                                = DataScopeTestResource::scopeQuery($this->newAdminUserQuery());

        $wheres = $query->getQuery()->wheres;
        $this->assertCount(1, $wheres);
        $this->assertSame('created_by', $wheres[0]['column']);
        $this->assertSame('=', $wheres[0]['operator']);
        $this->assertSame(42, $wheres[0]['value']);
    }

    public function test_department_scope_filters_by_self_and_descendant_ids(): void
    {
        $user = Mockery::mock(AdminUser::class)->makePartial();
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->department_id  = 7;
        $user->department     = new Department; // DepartmentTree 被替身，具体传值无关紧要

        $tree = Mockery::mock(DepartmentTree::class);
        $tree->shouldReceive('getSelfAndDescendantIds')->once()->andReturn([7, 8, 9]);
        $this->app->instance(DepartmentTree::class, $tree);

        Auth::guard('admin')->setUser($user);

        DataScopeTestResource::$scopeOverride = 'department';
        $query                                = DataScopeTestResource::scopeQuery($this->newAdminUserQuery());

        $wheres = $query->getQuery()->wheres;
        $this->assertCount(1, $wheres);
        $this->assertSame('department_id', $wheres[0]['column']);
        $this->assertSame([7, 8, 9], $wheres[0]['values']);
    }

    public function test_department_scope_denies_user_without_department(): void
    {
        $user = Mockery::mock(AdminUser::class)->makePartial();
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->department_id = null;
        Auth::guard('admin')->setUser($user);

        DataScopeTestResource::$scopeOverride = 'department';
        $query                                = DataScopeTestResource::scopeQuery($this->newAdminUserQuery());

        $wheres = $query->getQuery()->wheres;
        $this->assertCount(1, $wheres);
        $this->assertSame('raw', $wheres[0]['type']);
        $this->assertSame('1 = 0', $wheres[0]['sql']);
    }

    public function test_guest_is_denied_when_scope_is_set(): void
    {
        DataScopeTestResource::$scopeOverride = 'personal';
        $query                                = DataScopeTestResource::scopeQuery($this->newAdminUserQuery());

        $wheres = $query->getQuery()->wheres;
        $this->assertCount(1, $wheres);
        $this->assertSame('raw', $wheres[0]['type']);
        $this->assertSame('1 = 0', $wheres[0]['sql']);
    }
}

/**
 * 测试专用哑资源：暴露 trait 的受保护方法供断言
 */
class DataScopeTestResource
{
    use HasDataScope;

    public static ?string $scopeOverride = null;

    protected static function dataScope(): ?string
    {
        return static::$scopeOverride;
    }

    public static function scopeQuery(Builder $query): Builder
    {
        return static::applyDataScope($query);
    }
}
