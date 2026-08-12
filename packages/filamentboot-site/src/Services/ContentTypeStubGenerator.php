<?php

namespace Filamentboot\FilamentbootSite\Services;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\ContentTypeDefinition;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypeRegistry;
use Filamentboot\Services\StubGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * 内容类型代码生成服务（批次 5，YZNCMS 式物理列）
 *
 * 按 ContentTypeDefinition 声明生成迁移/Model/Resource（含 3 个 Page）/Policy
 * 五个真实文件，落在 src/Modules/{module}/ 下——与现有八类硬编码内容同一
 * 目录规范。生成的文件是一次性快照：写文件用的是 heredoc 拼字符串，不是
 * 运行时反射 ContentTypeDefinition/FieldTypeRegistry，因此字段类型实现变了
 * 不会让已生成的文件跟着改变行为，必须重新执行 sync 才会反映新逻辑。
 *
 * Page 三件套未复用 filamentboot 主包 Filamentboot\Services\StubGenerator 的
 * 同名方法（那三个方法假设的目录形状与本包既有八类内容不一致，见
 * buildListPageContent() 的说明），只直接复用其 writeFile()（文件冲突处理，
 * D-02 skip if exists）。
 */
class ContentTypeStubGenerator
{
    public function __construct(protected StubGenerator $stubs) {}

    /**
     * 包根目录（packages/filamentboot-site）
     */
    public function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public function modelPath(ContentTypeDefinition $definition): string
    {
        return $this->packageRoot().'/src/Modules/'.$definition->module.'/Models/'.$definition->modelName().'.php';
    }

    public function policyPath(ContentTypeDefinition $definition): string
    {
        return $this->packageRoot().'/src/Modules/'.$definition->module.'/Policies/'.$definition->modelName().'Policy.php';
    }

    public function resourcePath(ContentTypeDefinition $definition): string
    {
        return $this->resourceDirectory($definition).'/'.$definition->modelName().'Resource.php';
    }

    public function resourcePagesDirectory(ContentTypeDefinition $definition): string
    {
        return $this->resourceDirectory($definition).'/'.$definition->modelName().'Resource/Pages';
    }

    protected function resourceDirectory(ContentTypeDefinition $definition): string
    {
        return $this->packageRoot().'/src/Modules/'.$definition->module.'/Filament';
    }

    /**
     * 迁移文件路径
     *
     * 已存在「_create_{table}_table.php」形状的迁移文件时复用同一路径（重新
     * 生成覆盖同一份历史文件，而不是每次 sync 都新建一份、留下一堆残次品）；
     * 否则用当前时间生成新文件名。
     */
    public function migrationPath(ContentTypeDefinition $definition): string
    {
        $directory = $this->packageRoot().'/database/migrations';
        $existing  = File::glob($directory.'/*_create_'.$definition->table.'_table.php');

        if ($existing !== []) {
            return $existing[0];
        }

        return $directory.'/'.now()->format('Y_m_d_His').'_create_'.$definition->table.'_table.php';
    }

    public function writeFile(string $path, string $content, bool $force): bool
    {
        return $this->stubs->writeFile($path, $content, $force);
    }

    /**
     * 生成迁移文件内容
     */
    public function buildMigrationContent(ContentTypeDefinition $definition, FieldTypeRegistry $fieldTypes): string
    {
        $columns = [];

        foreach ($definition->fields as $field) {
            $type = $fieldTypes->get($field->type);

            if ($type === null) {
                throw new InvalidArgumentException("内容类型「{$definition->key}」的字段「{$field->key}」引用了未注册的字段类型「{$field->type}」。");
            }

            $columns[] = '            '.$type->migrationColumn($field);
        }

        if ($definition->sortable) {
            $columns[] = "            \$table->unsignedInteger('sort')->default(0)->comment('排序权重，数字越小越靠前');";
        }

        $columnsBlock = implode("\n", $columns);
        $softDeletes  = $definition->softDeletes ? "\n            \$table->softDeletes();" : '';

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建 {$definition->table} 表
 *
 * 由 filamentboot-site:content-type:sync 按「{$definition->key}」内容类型声明生成。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('{$definition->table}')) {
            return;
        }

        Schema::create('{$definition->table}', function (Blueprint \$table) {
            \$table->id();

{$columnsBlock}

            \$table->timestamps();{$softDeletes}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$definition->table}');
    }
};

PHP;
    }

    /**
     * 生成 Model 文件内容
     */
    public function buildModelContent(ContentTypeDefinition $definition, FieldTypeRegistry $fieldTypes): string
    {
        $namespace = $definition->baseNamespace().'\\Models';
        $model     = $definition->modelName();

        $fillable  = [];
        $casts     = [];
        $mutators  = [];
        $richTexts = [];

        foreach ($definition->fields as $field) {
            $type = $fieldTypes->get($field->type);

            if ($type === null) {
                throw new InvalidArgumentException("内容类型「{$definition->key}」的字段「{$field->key}」引用了未注册的字段类型「{$field->type}」。");
            }

            $fillable[] = "        '{$field->key}',";

            $cast = $type->modelCast($field);

            if ($cast !== null) {
                $casts[] = '        '.$cast.',';
            }

            if ($field->type === 'rich-text') {
                $richTexts[] = $field;
            }
        }

        if ($definition->sortable) {
            $fillable[] = "        'sort',";
        }

        // 不带 HasFactory：本系统不生成 Factory 类（Seeder/工厂不在验收范围内，
        // 见 ContentTypeDefinition 类文档），挂一个没有对应 Factory 的 trait
        // 只会触发 phpstan 的泛型缺失告警，不带来任何实际能力。
        $traits = [];
        $uses   = [
            'Illuminate\\Database\\Eloquent\\Model',
        ];

        if ($definition->softDeletes) {
            $traits[] = 'use SoftDeletes;';
            $uses[]   = 'Illuminate\\Database\\Eloquent\\SoftDeletes';
        }

        // 富文本字段落库前统一走 RichText::purify()，与 Cms\Blocks\RichContentBlock
        // 保存侧净化同一处理方式（见 RichTextFieldType 类文档）——渲染侧净化
        // 由前台通用渲染器负责，两侧都过是既定纪律，不是本处遗漏。
        if ($richTexts !== []) {
            $uses[] = 'Filamentboot\\FilamentbootSite\\Support\\RichText';

            foreach ($richTexts as $field) {
                $mutatorName  = 'set'.ucfirst(Str::camel($field->key)).'Attribute';
                $mutators[]   = <<<PHP

    public function {$mutatorName}(?string \$value): void
    {
        \$this->attributes['{$field->key}'] = RichText::purify(\$value);
    }
PHP;
            }
        }

        sort($uses);
        $useBlock      = implode("\n", array_map(fn (string $import): string => "use {$import};", $uses));
        $traitsBlock   = implode("\n    ", $traits);
        $fillableBlock = implode("\n", $fillable);
        $castsBlock    = implode("\n", $casts);
        $mutatorsBlock = implode("\n", $mutators);

        return <<<PHP
<?php

namespace {$namespace};

{$useBlock}

/**
 * {$definition->label}模型
 *
 * 由 filamentboot-site:content-type:sync 按「{$definition->key}」内容类型声明生成。
 */
class {$model} extends Model
{
    {$traitsBlock}

    /** @var string */
    protected \$table = '{$definition->table}';

    /**
     * @var list<string>
     */
    protected \$fillable = [
{$fillableBlock}
    ];

    /**
     * @var array<string, string>
     */
    protected \$casts = [
{$castsBlock}
    ];
{$mutatorsBlock}
}

PHP;
    }

    /**
     * 生成 Resource 文件内容
     */
    public function buildResourceContent(ContentTypeDefinition $definition, FieldTypeRegistry $fieldTypes): string
    {
        $namespace      = $definition->baseNamespace().'\\Filament';
        $modelNamespace = $definition->baseNamespace().'\\Models';
        $model          = $definition->modelName();
        $plural         = $definition->pluralModelName();

        $formComponents = [];
        $tableColumns   = [];
        $imports        = [
            // BackedEnum/UnitEnum：$navigationIcon/$navigationGroup 的属性类型必须
            // 与 Filament\Resources\Resource 基类完全一致，否则 phpstan 报
            // property.nativeType（覆写属性类型收窄不被允许）
            'BackedEnum',
            'UnitEnum',
            'Filament\\Actions\\BulkActionGroup',
            'Filament\\Actions\\DeleteAction',
            'Filament\\Actions\\DeleteBulkAction',
            'Filament\\Actions\\EditAction',
            'Filament\\Resources\\Resource',
            'Filament\\Schemas\\Schema',
            'Filament\\Tables\\Columns\\TextColumn',
            'Filament\\Tables\\Table',
            "{$modelNamespace}\\{$model}",
            "{$namespace}\\{$model}Resource\\Pages\\Create{$model}",
            "{$namespace}\\{$model}Resource\\Pages\\Edit{$model}",
            "{$namespace}\\{$model}Resource\\Pages\\List{$plural}",
        ];

        foreach ($definition->fields as $field) {
            $type = $fieldTypes->get($field->type);

            if ($type === null) {
                throw new InvalidArgumentException("内容类型「{$definition->key}」的字段「{$field->key}」引用了未注册的字段类型「{$field->type}」。");
            }

            $formComponents[] = '                '.$type->formComponentExpression($field).',';

            foreach ($type->formComponentImports() as $import) {
                $imports[] = $import;
            }

            if ($field->showInList) {
                $tableColumns[] = "                TextColumn::make('{$field->key}')->label(".$this->quote($field->label).'),';
            }
        }

        if ($tableColumns === [] && $definition->fields !== []) {
            $first          = $definition->fields[0];
            $tableColumns[] = "                TextColumn::make('{$first->key}')->label(".$this->quote($first->label).'),';
        }

        $reorderable = '';

        if ($definition->sortable) {
            $imports[]        = 'Filament\\Forms\\Components\\TextInput';
            $formComponents[] = "                TextInput::make('sort')->label('排序')->numeric()->default(0),";
            $tableColumns[]   = "                TextColumn::make('sort')->label('排序')->sortable(),";
            $reorderable      = "\n            ->defaultSort('sort', 'asc')\n            ->reorderable('sort')";
        }

        $imports = array_values(array_unique($imports));
        sort($imports);

        $useBlock     = implode("\n", array_map(fn (string $import): string => "use {$import};", $imports));
        $formBlock    = implode("\n", $formComponents);
        $columnsBlock = implode("\n", $tableColumns);

        return <<<PHP
<?php

namespace {$namespace};

{$useBlock}

/**
 * {$definition->label}后台资源
 *
 * 由 filamentboot-site:content-type:sync 按「{$definition->key}」内容类型声明生成。
 *
 * @extends Resource<{$model}>
 */
class {$model}Resource extends Resource
{
    /** @var class-string<{$model}> */
    protected static ?string \$model = {$model}::class;

    protected static string|BackedEnum|null \$navigationIcon = '{$definition->navigationIcon}';

    protected static string|UnitEnum|null \$navigationGroup = '{$definition->navigationGroup}';

    protected static ?string \$modelLabel = '{$definition->label}';

    protected static ?string \$pluralModelLabel = '{$definition->pluralLabel}';

    public static function form(Schema \$schema): Schema
    {
        return \$schema->components([
{$formBlock}
        ]);
    }

    public static function table(Table \$table): Table
    {
        return \$table
            ->columns([
{$columnsBlock}
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]){$reorderable};
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index'  => List{$plural}::route('/'),
            'create' => Create{$model}::route('/create'),
            'edit'   => Edit{$model}::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete', 'delete_any'];
    }
}

PHP;
    }

    /**
     * 生成 Page 三件套（未复用主包 StubGenerator 的同名方法）
     *
     * 主包那三个方法假设 Resource 与 Pages 是同级子命名空间（App\Filament\
     * Resources\{Plural}\{ProductResource,Pages\ListProducts}），而本包既有
     * 八类内容资源（如 SiteBannerResource）一律走 Filament 官方
     * make:filament-resource 的默认形状——Pages 嵌套在 Resource 类自己的
     * 命名空间之下（Filament\SiteBannerResource\Pages\ListSiteBanners）。
     * 两种形状不兼容，为保持与既有八类内容一致，这里各自实现。
     */
    public function buildListPageContent(ContentTypeDefinition $definition): string
    {
        $namespace = $definition->baseNamespace().'\\Filament\\'.$definition->modelName().'Resource\\Pages';
        $resource  = $definition->baseNamespace().'\\Filament\\'.$definition->modelName().'Resource';
        $model     = $definition->modelName();
        $plural    = $definition->pluralModelName();

        return <<<PHP
<?php

namespace {$namespace};

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use {$resource};

/**
 * {$definition->label}列表页
 *
 * 由 filamentboot-site:content-type:sync 按「{$definition->key}」内容类型声明生成。
 */
class List{$plural} extends ListRecords
{
    protected static string \$resource = {$model}Resource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

PHP;
    }

    public function buildCreatePageContent(ContentTypeDefinition $definition): string
    {
        $namespace = $definition->baseNamespace().'\\Filament\\'.$definition->modelName().'Resource\\Pages';
        $resource  = $definition->baseNamespace().'\\Filament\\'.$definition->modelName().'Resource';
        $model     = $definition->modelName();

        return <<<PHP
<?php

namespace {$namespace};

use Filament\Resources\Pages\CreateRecord;
use {$resource};

/**
 * {$definition->label}新建页
 *
 * 由 filamentboot-site:content-type:sync 按「{$definition->key}」内容类型声明生成。
 */
class Create{$model} extends CreateRecord
{
    protected static string \$resource = {$model}Resource::class;
}

PHP;
    }

    public function buildEditPageContent(ContentTypeDefinition $definition): string
    {
        $namespace = $definition->baseNamespace().'\\Filament\\'.$definition->modelName().'Resource\\Pages';
        $resource  = $definition->baseNamespace().'\\Filament\\'.$definition->modelName().'Resource';
        $model     = $definition->modelName();

        return <<<PHP
<?php

namespace {$namespace};

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use {$resource};

/**
 * {$definition->label}编辑页
 *
 * 由 filamentboot-site:content-type:sync 按「{$definition->key}」内容类型声明生成。
 */
class Edit{$model} extends EditRecord
{
    protected static string \$resource = {$model}Resource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

PHP;
    }

    /**
     * 生成 Policy 文件内容
     */
    public function buildPolicyContent(ContentTypeDefinition $definition): string
    {
        $namespace = $definition->baseNamespace().'\\Policies';
        $model     = $definition->modelName();

        return <<<PHP
<?php

namespace {$namespace};

use Filamentboot\Policies\BasePolicy;

/**
 * {$definition->label}权限策略
 *
 * 由 filamentboot-site:content-type:sync 按「{$definition->key}」内容类型声明生成。
 * 继承 BasePolicy，自动推导权限点。
 */
class {$model}Policy extends BasePolicy {}

PHP;
    }

    private function quote(string $value): string
    {
        return "'".addslashes($value)."'";
    }
}
