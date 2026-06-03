<?php

namespace FilamentAdmin\Services;

use BackedEnum;
use Carbon\CarbonInterface;
use FilamentAdmin\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * 后台操作日志写入服务
 */
class ActivityLogger
{
    /**
     * 敏感字段和噪音字段
     *
     * @var list<string>
     */
    protected array $ignoredFields = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'created_at',
        'updated_at',
    ];

    /**
     * 记录操作日志
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function log(
        Model $causer,
        Model $subject,
        string $action,
        array $before = [],
        array $after = [],
    ): void {
        activity('admin')
            ->causedBy($causer)
            ->performedOn($subject)
            ->withProperties([
                'action'     => $action,
                'before'     => $before,
                'after'      => $after,
                'ip'         => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->event($action)
            ->log($action);
    }

    /**
     * 获取当前操作人
     */
    public function currentCauser(): ?AdminUser
    {
        $user = auth('admin')->user();

        return $user instanceof AdminUser ? $user : null;
    }

    /**
     * 生成模型快照
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function snapshot(Model $subject, array $extra = []): array
    {
        return $this->sanitize([
            ...$subject->attributesToArray(),
            ...$extra,
        ]);
    }

    /**
     * 生成指定属性快照
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function snapshotFromAttributes(Model $subject, array $attributes, array $extra = []): array
    {
        $snapshot = $subject->newInstance([], $subject->exists);
        $snapshot->setRawAttributes($attributes, true);

        return $this->snapshot($snapshot, $extra);
    }

    /**
     * 比对前后变化
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function diff(array $before, array $after): array
    {
        $changedBefore = [];
        $changedAfter  = [];

        $keys = array_unique([
            ...array_keys($before),
            ...array_keys($after),
        ]);
        sort($keys);

        foreach ($keys as $key) {
            $hasBefore = array_key_exists($key, $before);
            $hasAfter  = array_key_exists($key, $after);

            if (! $hasBefore && ! $hasAfter) {
                continue;
            }

            $beforeValue = $before[$key] ?? null;
            $afterValue  = $after[$key] ?? null;

            if ($beforeValue === $afterValue) {
                continue;
            }

            if ($hasBefore) {
                $changedBefore[$key] = $beforeValue;
            }

            if ($hasAfter) {
                $changedAfter[$key] = $afterValue;
            }
        }

        return [$changedBefore, $changedAfter];
    }

    /**
     * 记录变更日志
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function logChanges(
        Model $causer,
        Model $subject,
        string $action,
        array $before = [],
        array $after = [],
    ): void {
        [$changedBefore, $changedAfter] = $this->diff($before, $after);

        if ($changedBefore === [] && $changedAfter === []) {
            return;
        }

        $this->log($causer, $subject, $action, $changedBefore, $changedAfter);
    }

    /**
     * 清洗快照数据
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function sanitize(array $attributes): array
    {
        $attributes = Arr::except($attributes, $this->ignoredFields);

        ksort($attributes);

        return $this->normalizeArray($attributes);
    }

    /**
     * 规范化数组结构
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function normalizeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = $this->normalizeValue($value);
        }

        return $values;
    }

    /**
     * 规范化标量与嵌套值
     */
    protected function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeValue($item);
        }

        return $value;
    }
}
