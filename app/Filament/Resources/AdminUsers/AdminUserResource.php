<?php

namespace App\Filament\Resources\AdminUsers;

use App\Enums\AdminUserStatus;
use App\Filament\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Filament\Resources\AdminUsers\Pages\EditAdminUser;
use App\Filament\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Filament\Resources\AdminUsers\Pages\ViewAdminUser;
use App\Models\AdminUser;
use App\Services\DataScopeResolver;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * 管理员后台资源
 */
class AdminUserResource extends Resource
{
    protected static ?string $model = AdminUser::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'username';

    protected static ?string $modelLabel = '管理员';

    protected static ?string $pluralModelLabel = '管理员管理';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->label('用户名')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('邮箱')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('姓名')
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('密码')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->visible(fn (string $operation): bool => static::canEditPassword($operation))
                    ->dehydrated(fn (?string $state, string $operation): bool => filled($state) && static::canEditPassword($operation)),
                Select::make('status')
                    ->label('状态')
                    ->options(static::getStatusOptions())
                    ->default(AdminUserStatus::Active->value)
                    ->required(),
                Select::make('department_id')
                    ->label('所属部门')
                    ->relationship(name: 'department', titleAttribute: 'name')
                    ->searchable()
                    ->preload(),
                Select::make('roles')
                    ->label('角色')
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('guard_name', 'admin'),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth('admin')->user()?->can('assign_role_admin_user') ?? false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label('用户名')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('邮箱')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('姓名')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (AdminUserStatus|string|null $state): string => static::formatStatusLabel($state))
                    ->color(fn (AdminUserStatus|string|null $state): string => static::formatStatusColor($state)),
                TextColumn::make('department.name')
                    ->label('部门')
                    ->default('-'),
                TextColumn::make('roles.name')
                    ->label('角色')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(static::getStatusOptions()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['department', 'roles'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $user = auth('admin')->user();

        if (! $user instanceof AdminUser) {
            return $query->whereRaw('1 = 0');
        }

        $scope = app(DataScopeResolver::class)->resolve($user);

        if ($scope['is_all']) {
            return $query;
        }

        return $query->where(function (Builder $scopedQuery) use ($scope): void {
            $hasCondition = false;

            if ($scope['department_ids'] !== []) {
                $scopedQuery->whereIn('department_id', $scope['department_ids']);
                $hasCondition = true;
            }

            if ($scope['admin_user_ids'] !== []) {
                $method = $hasCondition ? 'orWhereIn' : 'whereIn';

                $scopedQuery->{$method}('id', $scope['admin_user_ids']);
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $scopedQuery->whereRaw('1 = 0');
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'view'   => ViewAdminUser::route('/{record}'),
            'edit'   => EditAdminUser::route('/{record}/edit'),
        ];
    }

    /**
     * 获取状态选项
     *
     * @return array<string, string>
     */
    protected static function getStatusOptions(): array
    {
        return collect(AdminUserStatus::cases())
            ->mapWithKeys(fn (AdminUserStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /**
     * 格式化状态文案
     */
    protected static function formatStatusLabel(AdminUserStatus|string|null $state): string
    {
        if ($state instanceof AdminUserStatus) {
            return $state->label();
        }

        if (blank($state)) {
            return '-';
        }

        return AdminUserStatus::from($state)->label();
    }

    /**
     * 格式化状态颜色
     */
    protected static function formatStatusColor(AdminUserStatus|string|null $state): string
    {
        $status = $state instanceof AdminUserStatus
            ? $state
            : (filled($state) ? AdminUserStatus::from($state) : null);

        return match ($status) {
            AdminUserStatus::Active   => 'success',
            AdminUserStatus::Disabled => 'gray',
            default                   => 'gray',
        };
    }

    /**
     * 当前操作是否允许编辑密码
     */
    public static function canEditPassword(string $operation): bool
    {
        if ($operation === 'create') {
            return true;
        }

        return auth('admin')->user()?->can('reset_password_admin_user') ?? false;
    }
}
