<?php

namespace FilamentAdmin\Observers;

use FilamentAdmin\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * 核心后台模型操作日志观察器
 */
class ActivityLogObserver
{
    /**
     * 更新前快照
     *
     * @var array<int, array<string, mixed>>
     */
    protected static array $beforeSnapshots = [];

    /**
     * 删除动作缓存
     *
     * @var array<int, string>
     */
    protected static array $deleteActions = [];

    /**
     * 恢复流程标记
     *
     * @var array<int, bool>
     */
    protected static array $restoringStates = [];

    public function created(Model $model): void
    {
        $causer = $this->logger()->currentCauser();

        if (! $causer) {
            return;
        }

        $this->logger()->logChanges(
            causer: $causer,
            subject: $model,
            action: 'created',
            before: [],
            after: $this->logger()->snapshot($model),
        );
    }

    public function updating(Model $model): void
    {
        self::$beforeSnapshots[$this->key($model)] = $this->logger()->snapshotFromAttributes($model, $model->getOriginal());
    }

    public function updated(Model $model): void
    {
        if (self::$restoringStates[$this->key($model)] ?? false) {
            return;
        }

        $causer = $this->logger()->currentCauser();

        if (! $causer) {
            $this->forget($model);

            return;
        }

        $this->logger()->logChanges(
            causer: $causer,
            subject: $model,
            action: 'updated',
            before: self::$beforeSnapshots[$this->key($model)] ?? [],
            after: $this->logger()->snapshot($model),
        );

        $this->forget($model);
    }

    public function deleting(Model $model): void
    {
        self::$beforeSnapshots[$this->key($model)] = $this->logger()->snapshot($model);
        self::$deleteActions[$this->key($model)]   = $this->isForceDeleting($model) ? 'force_deleted' : 'deleted';
    }

    public function deleted(Model $model): void
    {
        $causer = $this->logger()->currentCauser();

        if (! $causer) {
            $this->forget($model);

            return;
        }

        $action = self::$deleteActions[$this->key($model)] ?? 'deleted';

        $this->logger()->logChanges(
            causer: $causer,
            subject: $model,
            action: $action,
            before: self::$beforeSnapshots[$this->key($model)] ?? [],
            after: $action === 'force_deleted' ? [] : $this->logger()->snapshot($model),
        );

        $this->forget($model);
    }

    public function restoring(Model $model): void
    {
        self::$beforeSnapshots[$this->key($model)] = $this->logger()->snapshot($model);
        self::$restoringStates[$this->key($model)] = true;
    }

    public function restored(Model $model): void
    {
        $causer = $this->logger()->currentCauser();

        if (! $causer) {
            $this->forget($model);

            return;
        }

        $this->logger()->logChanges(
            causer: $causer,
            subject: $model,
            action: 'restored',
            before: self::$beforeSnapshots[$this->key($model)] ?? [],
            after: $this->logger()->snapshot($model),
        );

        $this->forget($model);
    }

    /**
     * 获取观察键
     */
    protected function key(Model $model): int
    {
        return spl_object_id($model);
    }

    /**
     * 判断是否为强制删除
     */
    protected function isForceDeleting(Model $model): bool
    {
        if (! method_exists($model, 'isForceDeleting')) {
            return false;
        }

        return $model->isForceDeleting();
    }

    /**
     * 清理缓存
     */
    protected function forget(Model $model): void
    {
        unset(
            self::$beforeSnapshots[$this->key($model)],
            self::$deleteActions[$this->key($model)],
            self::$restoringStates[$this->key($model)],
        );
    }

    /**
     * 获取日志服务
     */
    protected function logger(): ActivityLogger
    {
        return app(ActivityLogger::class);
    }
}
