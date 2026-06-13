<?php

namespace LaravelStack\FilamentAdminSite\Http\Livewire;

use Illuminate\View\View;
use LaravelStack\FilamentAdminSite\Enums\CaseStyle;
use LaravelStack\FilamentAdminSite\Enums\HouseType;
use LaravelStack\FilamentAdminSite\Models\SiteCase;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 装修案例筛选器 Livewire 组件
 *
 * 支持按风格（style）和户型（house_type）筛选已发布案例，
 * 带分页（Livewire WithPagination）。
 * 筛选状态通过 #[Url] 属性同步到 URL 参数（SEO 友好，T-10-04-06）。
 *
 * 组件别名：filament-admin-site::case-filter
 * 在 Blade 中使用：<livewire:filament-admin-site::case-filter />
 */
class CaseFilter extends Component
{
    use WithPagination;

    /**
     * 风格筛选（CaseStyle 枚举值，#[Url] 同步到 URL，T-10-04-06）
     *
     * @var string|null
     */
    #[Url]
    public ?string $style = null;

    /**
     * 户型筛选（HouseType 枚举值，#[Url] 同步到 URL，T-10-04-06）
     *
     * @var string|null
     */
    #[Url]
    public ?string $houseType = null;

    /**
     * 风格更新时校验枚举白名单并重置分页
     *
     * 非法枚举值直接重置为 null，防止任意字符串进入 URL 参数与查询（WR-04）。
     *
     * @param mixed $value 新风格值
     * @return void
     */
    public function updatingStyle(mixed $value): void
    {
        $validValues = array_column(CaseStyle::cases(), 'value');
        if ($value !== null && ! in_array($value, $validValues, true)) {
            $this->style = null;

            return;
        }
        $this->resetPage();
    }

    /**
     * 户型更新时校验枚举白名单并重置分页
     *
     * 非法枚举值直接重置为 null，防止任意字符串进入 URL 参数与查询（WR-04）。
     *
     * @param mixed $value 新户型值
     * @return void
     */
    public function updatingHouseType(mixed $value): void
    {
        $validValues = array_column(HouseType::cases(), 'value');
        if ($value !== null && ! in_array($value, $validValues, true)) {
            $this->houseType = null;

            return;
        }
        $this->resetPage();
    }

    /**
     * 获取风格筛选选项（枚举 label 映射）
     *
     * @return array<string, string>
     */
    public function styleOptions(): array
    {
        $options = [];
        foreach (CaseStyle::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * 获取户型筛选选项（枚举 label 映射）
     *
     * @return array<string, string>
     */
    public function houseTypeOptions(): array
    {
        $options = [];
        foreach (HouseType::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * 渲染筛选结果视图
     *
     * 查询已发布案例，按风格和户型条件筛选（Eloquent 参数绑定防注入，T-10-04-06），
     * 非法枚举值查询返回空集，不报错（安全降级）。
     *
     * @return View
     */
    public function render(): View
    {
        $cases = SiteCase::published()
            ->when(
                $this->style,
                fn ($q) => $q->where('style', $this->style)
            )
            ->when(
                $this->houseType,
                fn ($q) => $q->where('house_type', $this->houseType)
            )
            ->latest('published_at')
            ->paginate(12);

        return view('filament-admin-site::livewire.case-filter', [
            'cases' => $cases,
        ]);
    }
}
