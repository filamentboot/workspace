<?php

namespace Filamentboot\Tests\Support;

use Illuminate\Support\ServiceProvider;
use Livewire\Mechanisms\DataStore;

/**
 * 修复 Testbench 环境下 Livewire::test() 渲染完整 Filament Page 时
 * DataStore 单例丢失导致 getErrorBag() 返回 null 的问题
 *
 * 根因：Livewire\Mechanisms\Mechanism::register() 用 app()->instance()
 * （非 singleton()）绑定各 Mechanism（含 DataStore）。Livewire::test() 的
 * InitialRender 内部走一次真实的“伪 HTTP 请求”（RequestBroker::call），
 * 该请求生命周期结束时容器里这份 instance() 绑定会被清空，而 instance()
 * 绑定没有可重建的工厂——之后每次 app(DataStore::class) 都会拿到一个
 * 全新的、互不相通的实例，导致 HandlesValidation::getErrorBag() 内部
 * has()/set()/get() 三次调用各自落在不同实例的 WeakMap 上，最终返回 null，
 * 触发 ViewErrorBag::put(): Argument #2 must be of type MessageBag, null given。
 *
 * 修复：本 Provider 必须排在 Livewire\LivewireServiceProvider 之后注册
 * （getPackageProviders() 数组里靠后即可，Laravel 的 register() 阶段
 * 按数组顺序执行），用 singleton() 重新绑定 DataStore——singleton()
 * 绑定的是可重建工厂，即使 instance() 缓存被清空，下一次解析会自动
 * 重建并重新缓存，同一次渲染内的连续调用不再互相看不到彼此写入的值。
 *
 * 只需要处理 DataStore（本包测试唯一实际触发该问题的 Mechanism），
 * 未观察到其余 Mechanism 出现同类问题，不做无依据的预防性处理。
 */
class StabilizeLivewireDataStoreProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DataStore::class);
    }
}
