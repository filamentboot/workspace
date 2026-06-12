<?php

namespace FilamentAdmin\Filament\Resources\Departments;

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
use FilamentAdmin\Enums\AdminUserStatus;
use FilamentAdmin\Filament\Resources\Concerns\ReorderableWithLog;
use FilamentAdmin\Filament\Resources\Departments\Pages\CreateDepartment;
use FilamentAdmin\Filament\Resources\Departments\Pages\EditDepartment;
use FilamentAdmin\Filament\Resources\Departments\Pages\ListDepartments;
use FilamentAdmin\Filament\Resources\Departments\Pages\ViewDepartment;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\Department;
use FilamentAdmin\Services\DepartmentTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * 部门组织后台资源
 */
class DepartmentResource extends Resource
{
    use ReorderableWithLog;

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
        $query = parent::getEloquentQuery()
            ->with(['parent', 'leader'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $user = auth('admin')->user();

        // 无登录上下文或非管理员用户，返回空结果
        if (! $user instanceof AdminUser) {
            return $query->whereRaw('1 = 0');
        }

        // 超级管理员看全部部门，不加限制
        if ($user->hasRole(config('filament-admin.super_admin_role'))) {
            return $query;
        }

        // 普通管理员无所属部门，返回空结果（安全收敛）
        if (blank($user->department_id)) {
            return $query->whereRaw('1 = 0');
        }

        // 普通管理员只能看本部门及其所有子孙部门
        $dept = $user->department;
        $ids  = app(DepartmentTree::class)->getSelfAndDescendantIds($dept);

        return $query->whereIn('id', $ids);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListDepartments::route('/'),
            'create' => CreateDepartment::route('/create'),
            'edit'   => EditDepartment::route('/{record}/edit'),
            'view'   => ViewDepartment::route('/{record}'),
        ];
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
