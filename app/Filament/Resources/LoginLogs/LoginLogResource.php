<?php

namespace App\Filament\Resources\LoginLogs;

use App\Filament\Resources\LoginLogs\Pages\ListLoginLogs;
use App\Filament\Resources\LoginLogs\Pages\ViewLoginLog;
use App\Models\AdminUser;
use App\Models\LoginLog;
use App\Services\DataScopeResolver;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * 管理员登录日志资源
 */
class LoginLogResource extends Resource
{
    protected static ?string $model = LoginLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'username';

    protected static ?string $modelLabel = '管理员日志';

    protected static ?string $pluralModelLabel = '管理员日志';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('adminUser.username')
                    ->label('管理员'),
                TextInput::make('username')
                    ->label('登录账号'),
                Select::make('status')
                    ->label('结果')
                    ->options(static::getStatusOptions()),
                TextInput::make('ip_address')
                    ->label('IP'),
                TextInput::make('failure_reason')
                    ->label('失败原因'),
                DateTimePicker::make('created_at')
                    ->label('时间'),
                Textarea::make('user_agent')
                    ->label('User-Agent')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('adminUser.username')
                    ->label('管理员')
                    ->searchable(),
                TextColumn::make('username')
                    ->label('登录账号')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('结果')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::formatStatusLabel($state))
                    ->color(fn (?string $state): string => static::formatStatusColor($state)),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable(),
                TextColumn::make('failure_reason')
                    ->label('失败原因')
                    ->default('-'),
                TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('admin_user_id')
                    ->label('管理员')
                    ->relationship(name: 'adminUser', titleAttribute: 'username')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('结果')
                    ->options(static::getStatusOptions()),
                Filter::make('ip_address')
                    ->label('IP')
                    ->form([
                        TextInput::make('ip')
                            ->label('IP'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $ip = trim((string) ($data['ip'] ?? ''));

                        if ($ip === '') {
                            return $query;
                        }

                        return $query->where('ip_address', 'like', "%{$ip}%");
                    }),
                Filter::make('created_at')
                    ->label('时间范围')
                    ->form([
                        DateTimePicker::make('created_from')
                            ->label('开始时间'),
                        DateTimePicker::make('created_until')
                            ->label('结束时间'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['created_from'] ?? null),
                                fn (Builder $query): Builder => $query->where('created_at', '>=', $data['created_from']),
                            )
                            ->when(
                                filled($data['created_until'] ?? null),
                                fn (Builder $query): Builder => $query->where('created_at', '<=', $data['created_until']),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('adminUser');
        $user  = auth('admin')->user();

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
                $scopedQuery->whereHas('adminUser', function (Builder $adminQuery) use ($scope): void {
                    $adminQuery->whereIn('department_id', $scope['department_ids']);
                });
                $hasCondition = true;
            }

            if ($scope['admin_user_ids'] !== []) {
                $method = $hasCondition ? 'orWhereIn' : 'whereIn';

                $scopedQuery->{$method}('admin_user_id', $scope['admin_user_ids']);
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
            'index' => ListLoginLogs::route('/'),
            'view'  => ViewLoginLog::route('/{record}'),
        ];
    }

    /**
     * 获取状态选项
     *
     * @return array<string, string>
     */
    protected static function getStatusOptions(): array
    {
        return [
            'success' => '成功',
            'failed'  => '失败',
        ];
    }

    /**
     * 格式化状态文案
     */
    protected static function formatStatusLabel(?string $state): string
    {
        return static::getStatusOptions()[$state] ?? '-';
    }

    /**
     * 格式化状态颜色
     */
    protected static function formatStatusColor(?string $state): string
    {
        return match ($state) {
            'success' => 'success',
            'failed'  => 'danger',
            default   => 'gray',
        };
    }
}
