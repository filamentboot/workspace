<?php

namespace FilamentAdmin\Filament\Resources\Departments;

use App\Enums\AdminUserStatus;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use FilamentAdmin\Filament\Resources\Departments\Pages\CreateDepartment;
use FilamentAdmin\Filament\Resources\Departments\Pages\EditDepartment;
use FilamentAdmin\Filament\Resources\Departments\Pages\ListDepartments;
use FilamentAdmin\Models\Department;
use FilamentAdmin\Services\ActivityLogger;
use FilamentAdmin\Services\DepartmentTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * 部门组织后台资源
 */
class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = '部门';

    protected static ?string $pluralModelLabel = '部门管理';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label('上级部门')
                    ->relationship(name: 'parent', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->rule(static::parentDepartmentRule()),
                TextInput::make('name')
                    ->label('部门名称')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('部门编码')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('leader_admin_user_id')
                    ->label('负责人')
                    ->relationship(
                        name: 'leader',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('status', AdminUserStatus::Active->value)
                            ->whereNull('deleted_at'),
                    )
                    ->searchable()
                    ->preload(),
                TextInput::make('sort')
                    ->label('排序')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->label('启用')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('部门名称')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('部门编码')
                    ->searchable(),
                TextColumn::make('parent.name')
                    ->label('上级部门')
                    ->default('-'),
                TextColumn::make('leader.name')
                    ->label('负责人')
                    ->default('-'),
                TextColumn::make('sort')
                    ->label('排序')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->beforeReordering(function (array $order): void {
                static::rememberReorderSnapshot($order);
            })
            ->afterReordering(function (array $order): void {
                static::logReorderActivity($order);
            })
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function canReorder(): bool
    {
        return auth('admin')->user()?->can('reorder_department') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['parent', 'leader'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListDepartments::route('/'),
            'create' => CreateDepartment::route('/create'),
            'edit'   => EditDepartment::route('/{record}/edit'),
        ];
    }

    /**
     * 缓存排序前快照
     *
     * @param  array<int, string|int>  $order
     */
    protected static function rememberReorderSnapshot(array $order): void
    {
        request()->attributes->set('department_reorder_before', static::buildReorderSnapshot($order));
    }

    /**
     * 记录部门排序日志
     *
     * @param  array<int, string|int>  $order
     */
    protected static function logReorderActivity(array $order): void
    {
        $logger = app(ActivityLogger::class);
        $causer = $logger->currentCauser();
        $record = Department::query()->find($order[0] ?? null);

        if (! $causer || ! $record) {
            return;
        }

        $before = request()->attributes->get('department_reorder_before', []);
        $after  = static::buildReorderSnapshot($order);

        $logger->logChanges(
            causer: $causer,
            subject: $record,
            action: 'reordered',
            before: ['order' => $before],
            after: ['order' => $after],
        );
    }

    /**
     * 构建排序快照
     *
     * @param  array<int, string|int>  $order
     * @return array<int, array<string, mixed>>
     */
    protected static function buildReorderSnapshot(array $order): array
    {
        return Department::query()
            ->whereKey($order)
            ->orderBy('sort')
            ->get(['id', 'parent_id', 'name', 'sort', 'is_active'])
            ->map(fn (Department $department): array => [
                'id'        => $department->id,
                'parent_id' => $department->parent_id,
                'name'      => $department->name,
                'sort'      => $department->sort,
                'is_active' => $department->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * 上级部门校验规则
     */
    protected static function parentDepartmentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $record = request()->route('record');

            if (blank($value) || blank($record)) {
                return;
            }

            $department = Department::query()->find($record);
            $parent     = Department::query()->find($value);

            if (! $department || ! $parent) {
                return;
            }

            if (app(DepartmentTree::class)->wouldCreateCycle($department, $parent)) {
                $fail('上级部门不能选择自己或下级部门。');
            }
        };
    }
}
