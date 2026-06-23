# CRUD 开发规范

本文档为 Filamentboot 模块开发者提供标准开发流程和检查清单。

## 开发流程

### 1. 创建 Migration

参考模板 `stubs/Migration.stub`：

```bash
php artisan make:migration create_xxx_table
```

**字段命名规范：**

| 类型 | 命名示例 | 说明 |
|------|---------|------|
| 主键 | `id` | 自增整型 |
| 字符串 | `name`, `code`, `title` | 避免拼音 |
| 状态 | `status` | 建议配合 Enum |
| 外键 | `department_id` | 必须建索引 |
| 布尔 | `is_active`, `is_deleted` | is_ 前缀 |
| 时间 | `published_at`, `expired_at` | _at 后缀 |
| 软删除 | `deleted_at` | 用 `$table->softDeletes()` |

**必须包含的字段：**
- `$table->id()`
- `$table->timestamps()`
- 软删除表加 `$table->softDeletes()`

### 2. 创建 Model

参考模板 `stubs/Model.stub`：

```bash
php artisan make:model Xxx
```

**规范检查清单：**
- [ ] 声明 `$fillable`（明确可批量赋值字段，禁止 `$guarded = []`）
- [ ] 声明 `$casts`（枚举字段、JSON 字段、日期字段）
- [ ] 软删除表使用 `SoftDeletes` Trait
- [ ] 需要操作日志的模型使用 `LogsActivity` Trait
- [ ] 公共方法包含 PHPDoc 注释（中文）

### 3. 创建 Filament Resource

参考模板 `stubs/Resource.stub`：

```bash
php artisan make:filament-resource Xxx
```

**规范检查清单：**
- [ ] 表单使用 Filament 5 API：`Schema $schema` + `->components([])`（禁止旧版 `->schema([])`）
- [ ] 设置 `$modelLabel`（中文名，用于页面标题）
- [ ] 设置 `$navigationGroup`（归入对应菜单分组）
- [ ] 实现 `getPermissionPrefixes()`（告知 Shield 生成哪些权限点）
- [ ] 列表页包含 `created_at` 列（toggleable）

### 4. 创建测试

参考模板 `stubs/FeatureTest.stub`：

**测试必须覆盖：**
- [ ] 超级管理员可以访问列表页
- [ ] 创建操作：填写表单、提交、验证数据库
- [ ] 更新操作：修改字段、提交、验证变化
- [ ] 删除操作：确认删除、验证记录消失
- [ ] 权限控制：无权限用户被拒绝

**测试规范：**
- `it()` 描述使用中文
- 涉及 admin 认证必须 `actingAs($admin, 'admin')`
- `beforeEach` 清理权限缓存：`app(PermissionRegistrar::class)->forgetCachedPermissions()`

### 5. 运行质量检查

```bash
composer pint          # 格式化代码
composer phpstan       # 静态分析
composer test          # 运行所有测试
```

## Git 提交规范

| 类型 | 场景 | 示例 |
|------|------|------|
| `feat` | 新功能 | `feat: 添加商品管理模块` |
| `fix` | 修复 Bug | `fix: 修复商品状态切换失败` |
| `test` | 测试代码 | `test: 添加商品管理权限测试` |
| `docs` | 文档 | `docs: 更新商品模块开发文档` |
| `refactor` | 重构 | `refactor: 提取商品价格计算逻辑` |

## 插件开发规范

Filamentboot 插件通过 Filament 5 Plugin 机制集成（`->plugins([YourPlugin::make()])`）。

**插件应提供：**
- `ServiceProvider`：注册路由、迁移、监听器
- `Plugin`（实现 `FilamentPlugin` 接口）：注册 Resource、Widget、Page
- `config/your-plugin.php`：可发布的配置文件
- 权限初始化 Seeder（使用 `admin` guard 创建权限和角色）

**示例：**

```php
use Filament\Contracts\Plugin;
use Filament\Panel;

class YourPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'your-plugin';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            YourResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
```
