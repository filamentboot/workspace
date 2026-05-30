<?php

namespace App\Filament\Resources\RoleDataScopes;

use App\Enums\DataScope;
use App\Filament\Resources\RoleDataScopes\Pages\EditRoleDataScope;
use App\Filament\Resources\RoleDataScopes\Pages\ListRoleDataScopes;
use App\Models\Department;
use App\Models\RoleDataScope;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * 角色数据范围资源
 */
class RoleDataScopeResource extends Resource
{
    protected static ?string $model = RoleDataScope::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 60;

    protected static ?string $recordTitleAttribute = 'role.name';

    protected static ?string $modelLabel = '数据权限';

    protected static ?string $pluralModelLabel = '数据权限';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('role_id')
                    ->label('角色')
                    ->relationship(
                        name: 'role',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('guard_name', 'admin'),
                    )
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->searchable()
                    ->preload(),
                Select::make('scope')
                    ->label('数据范围')
                    ->options(static::getScopeOptions())
                    ->required()
                    ->live(),
                Select::make('department_ids')
                    ->label('指定部门')
                    ->multiple()
                    ->options(fn (): array => Department::query()
                        ->active()
                        ->orderBy('sort')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get): bool => $get('scope') === DataScope::CustomDepartments->value),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('role.name')
                    ->label('角色')
                    ->searchable(),
                TextColumn::make('scope')
                    ->label('数据范围')
                    ->badge()
                    ->formatStateUsing(fn (DataScope|string $state): string => static::formatScopeLabel($state)),
                TextColumn::make('department_ids')
                    ->label('指定部门')
                    ->formatStateUsing(fn (?array $state): string => $state === [] || $state === null ? '-' : implode(', ', $state)),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('role');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoleDataScopes::route('/'),
            'edit'  => EditRoleDataScope::route('/{record}/edit'),
        ];
    }

    /**
     * 规范化表单数据
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data): array
    {
        if (($data['scope'] ?? null) !== DataScope::CustomDepartments->value) {
            $data['department_ids'] = null;
        }

        return $data;
    }

    /**
     * 获取数据范围选项
     *
     * @return array<string, string>
     */
    protected static function getScopeOptions(): array
    {
        return collect(DataScope::cases())
            ->mapWithKeys(fn (DataScope $scope): array => [$scope->value => $scope->label()])
            ->all();
    }

    /**
     * 格式化数据范围文案
     */
    protected static function formatScopeLabel(DataScope|string $state): string
    {
        return ($state instanceof DataScope ? $state : DataScope::from($state))->label();
    }
}
