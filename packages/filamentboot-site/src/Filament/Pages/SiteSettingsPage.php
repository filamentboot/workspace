<?php

namespace Filamentboot\FilamentbootSite\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filamentboot\FilamentbootSite\Cms\Themes\ThemeManifest;
use Filamentboot\FilamentbootSite\Cms\Themes\ThemeSwitchCheck;
use Filamentboot\FilamentbootSite\Services\DemoDataToggle;
use Filamentboot\FilamentbootSite\Services\SiteHealthCheck;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Filamentboot\Services\ActivityLogger;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use ReflectionClass;
use ReflectionNamedType;
use UnitEnum;

/**
 * 官网设置页面
 *
 * 管理公司基本信息、前台文案、SEO 默认值、主题切换及媒体上传（D-10-14），
 * 另含线索通知邮箱（A2）与统计代码注入（A3）。
 * CMS v1 为中文单语言，英文字段与对应数据列已在 3.5 期 A 段整批删除。
 *
 * 保存后执行 view:clear 确保主题切换立即生效（RESEARCH Pitfall 3）。
 * 上传字段使用 UploadSettings.default_disk 磁盘（SITE-04 跨切）。
 *
 * 自定义前台代码块（head_scripts / body_end_scripts）是本页唯一的高风险字段：
 * 原样执行、不过滤，因此额外用 manage_site_settings 权限门住并记操作日志。
 */
class SiteSettingsPage extends SettingsPage
{
    protected static string $settings = SiteSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = '网站设置';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'settings/site';

    /**
     * 进入页面时提示尚未配置的发布前必填项
     */
    public function mount(): void
    {
        parent::mount();

        $this->notifyMissingSettings();
    }

    /**
     * 种入 / 清空演示数据（批次 3）
     *
     * 只对超管开放，不走 manage_site_settings 权限点：这是包维护/演示用途的动作，
     * 不该随「站点管理」角色一起授出去。两个动作都退出面板级 databaseTransaction()——
     * 种入会连续跑两个 db:seed，清空会对多张表做批量强删，都不该被外层事务包住。
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('seedDemoData')
                ->label('种入演示数据')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->databaseTransaction(false)
                ->requiresConfirmation()
                ->modalDescription('按当前主题播种案例、方案、产品、静态页、套餐与资讯等演示内容。按 slug 增量补种，已有的同名内容不受影响，可反复点击。')
                ->authorize(fn (): bool => $this->isSuperAdmin())
                ->action(function (): void {
                    abort_unless($this->isSuperAdmin(), 403);

                    app(DemoDataToggle::class)->seed();

                    Notification::make()->success()->title('演示数据已种入')->send();
                }),

            Action::make('clearDemoData')
                ->label('清空演示数据')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->databaseTransaction(false)
                ->requiresConfirmation()
                ->modalDescription('按 slug 强制删除演示案例、方案、产品、静态页、套餐与资讯，并重置前台导航菜单与列表页导语。⚠️ 这些内容或菜单一旦被运营改过，改动会一并清空且不可恢复。')
                ->authorize(fn (): bool => $this->isSuperAdmin())
                ->action(function (): void {
                    abort_unless($this->isSuperAdmin(), 403);

                    app(DemoDataToggle::class)->clear();

                    Notification::make()->success()->title('演示数据已清空')->send();
                }),
        ];
    }

    /**
     * 当前用户是否为超级管理员
     */
    protected function isSuperAdmin(): bool
    {
        return (bool) Auth::user()?->hasRole(config('filamentboot.super_admin_role', 'super_admin'));
    }

    /**
     * 获取上传默认磁盘名称
     *
     * 读取 UploadSettings.default_disk，降级到 'public'（T-10-01-02 防护）。
     */
    protected function getDefaultDisk(): string
    {
        return rescue(
            fn () => app(UploadSettings::class)->default_disk,
            'public',
            report: false,
        );
    }

    /**
     * 可选主题列表（读 config 白名单，与前台视图解析同源）
     *
     * @return array<string, string>
     */
    protected function getThemeOptions(): array
    {
        /** @var array<string, string> $themes */
        $themes = config('filamentboot-site.themes', []);

        return $themes;
    }

    /**
     * 表单字段定义（基本信息 / SEO / 外观 三 Tab）
     */
    public function form(Schema $schema): Schema
    {
        $defaultDisk = $this->getDefaultDisk();

        return $schema->components([
            Tabs::make('网站设置')
                ->tabs([
                    Tab::make('基本信息')
                        ->schema([
                            Section::make('公司信息')
                                ->description('页脚、导航与结构化数据都会读取这里的内容，缺项会导致前台对应栏目整块不渲染。')
                                ->schema([
                                    TextInput::make('company_name_zh')
                                        ->label('公司名称')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('phone')
                                        ->label('联系电话')
                                        ->tel()
                                        ->maxLength(50)
                                        ->helperText('未填写时前台页脚不显示电话入口'),
                                    TextInput::make('address_zh')
                                        ->label('公司地址')
                                        ->maxLength(500),
                                    TextInput::make('icp_number')
                                        ->label('ICP 备案号')
                                        ->maxLength(100)
                                        ->helperText('国内站点对外发布必填'),
                                    TextInput::make('privacy_url')
                                        ->label('隐私政策链接')
                                        ->url()
                                        ->maxLength(500)
                                        ->helperText('填写后在页脚显示「隐私政策」入口'),
                                    FileUpload::make('wechat_qrcode')
                                        ->label('微信二维码')
                                        ->image()
                                        ->disk($defaultDisk)
                                        ->helperText('上传微信公众号或企业微信二维码图片'),
                                ]),
                            Section::make('线索通知')
                                ->description('新询盘到达时立即发邮件提醒。营销站的线索响应速度直接决定转化率，留空则只能靠人主动登后台刷新。')
                                ->schema([
                                    TextInput::make('notify_emails')
                                        ->label('新询盘通知邮箱')
                                        ->maxLength(500)
                                        ->helperText('多个邮箱用英文逗号分隔，留空关闭通知。通知走队列异步发送，发送失败不会影响访客提交。'),
                                ]),
                        ]),
                    Tab::make('前台文案')
                        ->schema([
                            Section::make('页脚')
                                ->description('页脚品牌列下面那段介绍。留空则整段不渲染。')
                                ->schema([
                                    Textarea::make('footer_intro_zh')
                                        ->label('公司简介')
                                        ->maxLength(300)
                                        ->rows(3),
                                ]),
                            Section::make('列表页导语')
                                ->description('五个列表页标题下面那一句话。这里是它们唯一的来源——留空则该页标题下不显示任何导语，前台不会退回主题自带的文案。')
                                ->schema([
                                    Textarea::make('list_intro_cases_zh')
                                        ->label('案例列表导语')
                                        ->maxLength(300)
                                        ->rows(2),
                                    Textarea::make('list_intro_solutions_zh')
                                        ->label('方案列表导语')
                                        ->maxLength(300)
                                        ->rows(2),
                                    Textarea::make('list_intro_products_zh')
                                        ->label('产品列表导语')
                                        ->maxLength(300)
                                        ->rows(2),
                                    Textarea::make('list_intro_packages_zh')
                                        ->label('套餐列表导语')
                                        ->maxLength(300)
                                        ->rows(2),
                                    Textarea::make('list_intro_news_zh')
                                        ->label('资讯列表导语')
                                        ->maxLength(300)
                                        ->rows(2)
                                        ->helperText('按分类筛选时标题会变成分类名，导语不跟着变'),
                                ]),
                        ]),
                    Tab::make('SEO 默认值')
                        ->schema([
                            Section::make('搜索与分享')
                                ->description('内容自身未填写 SEO 字段时的回退值。默认描述留空会退到包内兜底文案。')
                                ->schema([
                                    TextInput::make('seo_default_title_zh')
                                        ->label('默认页面标题')
                                        ->maxLength(70)
                                        ->helperText('建议不超过 70 个字符'),
                                    Textarea::make('seo_default_description_zh')
                                        ->label('默认页面描述')
                                        ->maxLength(160)
                                        ->rows(3)
                                        ->helperText('建议不超过 160 个字符'),
                                    TextInput::make('seo_default_keywords_zh')
                                        ->label('默认关键词')
                                        ->maxLength(255)
                                        ->helperText('首页与各列表页唯一的关键词来源，内容自身未填关键词时也回退到这里。多个词用空格或英文逗号分隔，留空则全站不输出 keywords'),
                                    FileUpload::make('og_default_image')
                                        ->label('默认 Open Graph 图')
                                        ->image()
                                        ->disk($defaultDisk)
                                        ->helperText('社交平台分享时的缩略图，建议 1200×630。未上传时前台不输出 og:image'),
                                ]),
                            Section::make('站长平台验证')
                                ->description('各搜索引擎站长平台下发的验证串，填入后自动输出到全站 <head>，无需改模板。只填串本身，不要粘整段 meta 标签。')
                                ->schema([
                                    TextInput::make('baidu_verify_code')
                                        ->label('百度站长验证串')
                                        ->maxLength(128)
                                        ->helperText('百度搜索资源平台「站点验证 - HTML 标签」给出的 content 值'),
                                    TextInput::make('google_verify_code')
                                        ->label('Google Search Console 验证串')
                                        ->maxLength(128),
                                    TextInput::make('bing_verify_code')
                                        ->label('Bing 站长验证串')
                                        ->maxLength(128),
                                    TextInput::make('sogou_verify_code')
                                        ->label('搜狗站长验证串')
                                        ->maxLength(128)
                                        ->helperText('搜狗站长平台「网站验证 - 添加 meta 标签」给出的 content 值。搜狗同时是腾讯元宝的检索源，做 AI 引用绕不开它'),
                                ]),
                            Section::make('百度主动推送')
                                ->description('新内容发布时主动推给百度，比等抓取快一个数量级。留空即关闭，不影响其它功能。')
                                ->schema([
                                    TextInput::make('baidu_push_token')
                                        ->label('准入密钥 token')
                                        ->maxLength(64)
                                        ->helperText('百度搜索资源平台「普通收录 - API 提交」页获取'),
                                    TextInput::make('baidu_push_site')
                                        ->label('推送站点域名')
                                        ->maxLength(255)
                                        ->helperText('必须与站长平台登记的站点完全一致（含 www 与否），否则接口返回 not_same_site。留空则取 APP_URL 的主机名'),
                                ]),
                        ]),
                    Tab::make('外观')
                        ->schema([
                            Section::make('主题与品牌')
                                ->schema([
                                    Select::make('active_theme')
                                        ->label('前台主题')
                                        ->options($this->getThemeOptions())
                                        ->required()
                                        ->native(false)
                                        // live()：下面两个控件要跟着选择实时显隐，
                                        // 否则得先保存一次才知道目标主题支不支持现有内容
                                        ->live()
                                        ->helperText('切换主题后自动清除视图缓存，下次访问即生效'),

                                    // 切换预检查（#28）。BlockRenderer 的运行时兜底是缺视图就跳过并记
                                    // warning——已发布页面上悄悄少一块，没人会收到通知。所以在切换**之前**
                                    // 按目标主题的 theme.php 清单算一遍，把会掉内容的页面列出来。
                                    Placeholder::make('theme_switch_warning')
                                        ->label('目标主题不支持以下已发布内容')
                                        ->visible(fn (Get $get): bool => $this->themeSwitchIssues((string) $get('active_theme')) !== [])
                                        ->content(fn (Get $get): HtmlString => $this->themeSwitchWarning((string) $get('active_theme'))),

                                    Toggle::make('confirm_theme_switch')
                                        ->label('我知道上面这些内容切换后会不显示，仍要切换')
                                        ->visible(fn (Get $get): bool => $this->themeSwitchIssues((string) $get('active_theme')) !== [])
                                        ->default(false)
                                        ->dehydrated(),
                                    FileUpload::make('logo')
                                        ->label('公司 LOGO')
                                        ->image()
                                        ->disk($defaultDisk)
                                        ->helperText('建议使用 SVG 或 PNG 格式，透明背景'),
                                ]),
                        ]),
                    Tab::make('统计与代码')
                        ->schema([
                            Section::make('网站统计')
                                ->description('填 ID 即可，代码由系统按固定模板生成，无需手写脚本。')
                                ->schema([
                                    TextInput::make('baidu_tongji_id')
                                        ->label('百度统计 ID')
                                        ->maxLength(64)
                                        ->helperText('百度统计代码 hm.js?后面那串十六进制字符，填错格式不会输出统计代码'),
                                    TextInput::make('ga_measurement_id')
                                        ->label('Google Analytics 衡量 ID')
                                        ->maxLength(32)
                                        ->helperText('形如 G-XXXXXXXXXX'),
                                ]),
                            Section::make('在线客服')
                                ->description('第三方客服供应商给的那段代码，输出在页面底部。开关与代码分开，换供应商或临时无人值守时直接关掉即可，不用把代码剪出去。')
                                ->schema([
                                    Toggle::make('live_chat_enabled')
                                        ->label('启用在线客服')
                                        ->disabled(fn (): bool => ! $this->canManageScripts())
                                        ->helperText('关闭后代码仍然保留，只是不再输出到前台'),
                                    Textarea::make('live_chat_script')
                                        ->label('客服代码')
                                        ->rows(6)
                                        ->maxLength(20000)
                                        ->disabled(fn (): bool => ! $this->canManageScripts())
                                        ->helperText($this->scriptFieldHelperText()
                                            .' 移动端底部已有操作条，多数客服气泡会与它重叠，接入后请在真机上确认。'),
                                ]),
                            Section::make('自定义代码')
                                ->description('用于挂接上面没有覆盖的第三方脚本（其它统计平台、A/B 测试等）。')
                                ->schema([
                                    Textarea::make('head_scripts')
                                        ->label('<head> 内代码')
                                        ->rows(6)
                                        ->maxLength(20000)
                                        ->disabled(fn (): bool => ! $this->canManageScripts())
                                        ->helperText($this->scriptFieldHelperText()),
                                    Textarea::make('body_end_scripts')
                                        ->label('</body> 前代码')
                                        ->rows(6)
                                        ->maxLength(20000)
                                        ->disabled(fn (): bool => ! $this->canManageScripts())
                                        ->helperText($this->scriptFieldHelperText()),
                                ]),
                        ]),
                ]),
        ]);
    }

    /**
     * 当前用户是否可修改自定义代码块
     *
     * 自定义代码块原样输出到前台且不过 purifier，等于开放任意 JS 执行能力，
     * 因此单独用 manage_site_settings 权限点门住，不与查看设置页的权限混用。
     * 超管由主包 Gate::before() 放行。
     */
    protected function canManageScripts(): bool
    {
        return (bool) Auth::user()?->can('manage_site_settings');
    }

    /**
     * 自定义代码字段的风险提示
     */
    protected function scriptFieldHelperText(): string
    {
        if (! $this->canManageScripts()) {
            return '此处代码会原样在前台执行，需 manage_site_settings 权限才能修改。当前账号只能查看。';
        }

        return '⚠️ 此处代码会原样输出到前台并执行，不做任何过滤。请只粘贴来源可信的脚本，变更会记入操作日志。';
    }

    /**
     * 保存前把留空字段的 null 归一回空字符串
     *
     * Filament 会把空文本框的状态归一为 null，而 SiteSettings 的多数属性声明为
     * 非空 string（phone、address_zh、icp_number、privacy_url、seo_* 等）。
     * 两者相遇时 Spatie 的 Settings::fill() 直接抛
     * 「Cannot assign null to property ... of type string」——
     * 也就是说只要有任一可选文本字段留空，保存设置页就 500。
     *
     * 用反射按属性声明类型判断，新增字段自动受同一规则保护，
     * 不需要每加一个字段就来这里补一次名单。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->guardThemeSwitch($data);

        $reflection = new ReflectionClass(static::getSettings());

        foreach ($data as $key => $value) {
            if ($value !== null || ! $reflection->hasProperty($key)) {
                continue;
            }

            $type = $reflection->getProperty($key)->getType();

            if ($type instanceof ReflectionNamedType
                && ! $type->allowsNull()
                && $type->getName() === 'string'
            ) {
                $data[$key] = '';
            }
        }

        return $data;
    }

    /**
     * 原样输出到前台、不过 purifier 的高风险字段
     *
     * 快照与审计两处都读这份清单：分开写死迟早漏一个字段，
     * 而漏掉的那个就是「改了前台执行的代码但操作日志里查不到」。
     *
     * @var list<string>
     */
    protected const SCRIPT_FIELDS = ['head_scripts', 'body_end_scripts', 'live_chat_script'];

    /**
     * 保存前记下自定义代码块的原值，供 afterSave 比对审计
     *
     * 必须在 SettingsPage::save() 的 $settings->fill() 之前抓：Spatie 的
     * Settings 是容器单例，fill() 之后再读就已经是新值了。
     *
     * @var array<string, string>
     */
    protected array $scriptSnapshot = [];

    /**
     * 保存前快照高风险字段
     */
    protected function beforeSave(): void
    {
        $this->scriptSnapshot = rescue(function (): array {
            $settings = app(SiteSettings::class);
            $snapshot = [];

            foreach (self::SCRIPT_FIELDS as $field) {
                $snapshot[$field] = (string) $settings->{$field};
            }

            return $snapshot;
        }, [], report: false);
    }

    /**
     * 保存后清除视图缓存、审计自定义代码变更并复检发布前必填项（RESEARCH Pitfall 3）
     *
     * 主题切换后需清除视图缓存，确保新主题立即生效。
     */
    /**
     * 主题切换预检查门禁（#28）
     *
     * 目标主题不支持已发布内容用到的版式或区块时，必须先勾确认才放行。
     * 不勾就中止保存——**整份设置都不保存**，而不是只跳过 active_theme 一项：
     * 悄悄忽略用户改的某一个字段，比整体不保存更难发现。
     *
     * confirm_theme_switch 不是 SiteSettings 的属性，用完必须摘掉，
     * 否则 spatie settings 的 fill() 会因为未知键抛异常。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function guardThemeSwitch(array $data): array
    {
        $confirmed = ($data['confirm_theme_switch'] ?? false) === true;

        unset($data['confirm_theme_switch']);

        $target = (string) ($data['active_theme'] ?? '');
        $issues = $this->themeSwitchIssues($target);

        if ($issues === [] || $confirmed) {
            return $data;
        }

        Notification::make()
            ->danger()
            ->title('已取消保存：目标主题不支持部分已发布内容')
            ->body('共 '.count($issues).' 个已发布页面会掉内容。要么先在目标主题里补上对应视图，要么勾上「仍要切换」再保存。')
            ->persistent()
            ->send();

        throw new Halt;
    }

    /**
     * 切到目标主题会掉内容的已发布页面
     *
     * 目标与当前一致时直接返回空：进设置页只改个电话号码，不该看到主题告警。
     *
     * @return list<array{id: int, title: string, slug: string, reasons: list<string>}>
     */
    protected function themeSwitchIssues(string $target): array
    {
        /** @var array<string, string> $allowed */
        $allowed = config('filamentboot-site.themes', []);

        if ($target === '' || ! array_key_exists($target, $allowed)) {
            return [];
        }

        if ($target === SiteServiceProvider::resolveActiveTheme()) {
            return [];
        }

        return rescue(
            fn (): array => app(ThemeSwitchCheck::class)->inspect($target)['pages'],
            [],
            report: false,
        );
    }

    /**
     * 受影响页面清单（表单里显示）
     *
     * 逐页列出「哪一页因为什么会掉内容」，只给个数字没法据此决定要不要切。
     * 超过 10 页折叠成计数：设置页不是内容清单页。
     */
    protected function themeSwitchWarning(string $target): HtmlString
    {
        $issues = $this->themeSwitchIssues($target);
        $shown  = array_slice($issues, 0, 10);

        $rows = '';

        foreach ($shown as $issue) {
            $rows .= '<li><span class="font-medium">'.e($issue['title']).'</span>'
                .' <span class="opacity-60">/'.e($issue['slug']).'</span> — '
                .e(implode('、', $issue['reasons']))
                .'</li>';
        }

        $more = count($issues) > count($shown)
            ? '<p class="mt-2 opacity-70">另有 '.(count($issues) - count($shown)).' 个页面同样受影响。</p>'
            : '';

        return new HtmlString(
            '<ul class="list-disc ps-5 space-y-1 text-sm">'.$rows.'</ul>'.$more
            .'<p class="mt-2 text-sm opacity-70">只检查了已发布页面。草稿与归档页现在不对外，等它们发布时目标主题可能又变了。</p>'
        );
    }

    protected function afterSave(): void
    {
        Artisan::call('view:clear');

        // 主题目录换了，清单的每请求缓存必须跟着丢，否则同一请求内后续读到的还是旧主题
        ThemeManifest::flush();

        $this->logScriptChanges();

        $this->notifyMissingSettings();
    }

    /**
     * 把自定义代码块的变更写入操作日志
     *
     * 该字段能在前台执行任意 JS，权限之外必须留下「谁在什么时候改成了什么」的痕迹。
     */
    protected function logScriptChanges(): void
    {
        if ($this->scriptSnapshot === []) {
            return;
        }

        $settings = rescue(fn (): SiteSettings => app(SiteSettings::class), null, report: false);

        if ($settings === null) {
            return;
        }

        $changed = [];

        foreach (self::SCRIPT_FIELDS as $field) {
            $old = $this->scriptSnapshot[$field] ?? '';
            $new = (string) $settings->{$field};

            if ($old !== $new) {
                $changed[$field] = [
                    'old' => mb_substr($old, 0, 500),
                    'new' => mb_substr($new, 0, 500),
                ];
            }
        }

        if ($changed === []) {
            return;
        }

        $causer = app(ActivityLogger::class)->currentCauser();

        activity('admin')
            ->causedBy($causer)
            ->withProperties(['action' => 'update', 'model' => 'SiteSettings', 'changed' => $changed])
            ->event('update')
            ->log('修改官网自定义前台代码');
    }

    /**
     * 缺少发布前必填项时给出可操作告警
     */
    protected function notifyMissingSettings(): void
    {
        $missing = app(SiteHealthCheck::class)->missing();

        if ($missing === []) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('站点尚未达到发布标准')
            ->body('还需补齐：'.implode('、', $missing).'。未配置的栏目不会在前台渲染。')
            ->persistent()
            ->send();
    }
}
