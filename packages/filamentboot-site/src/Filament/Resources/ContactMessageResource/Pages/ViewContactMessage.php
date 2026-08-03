<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Models\ContactMessageNote;
use Illuminate\Support\Facades\Auth;

/**
 * 查看询盘详情页（含跟进备注入口）
 */
class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    /**
     * 头部 Action：添加跟进备注
     *
     * 备注写入权限沿用询盘的 update 权限（ContactMessagePolicy），
     * 不为跟进单独开权限点——能改询盘状态的人本来就在跟进这条线索。
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addNote')
                ->label('添加跟进备注')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->authorize(fn (): bool => Auth::user()?->can('update', $this->getRecord()) ?? false)
                ->schema([
                    Textarea::make('body')
                        ->label('跟进内容')
                        ->required()
                        ->maxLength(2000)
                        ->rows(4)
                        ->placeholder('例如：已电话联系，客户希望下周三上门量房。'),
                ])
                ->action(function (array $data): void {
                    /** @var ContactMessage $record */
                    $record = $this->getRecord();

                    ContactMessageNote::create([
                        'message_id'    => $record->getKey(),
                        'admin_user_id' => Auth::id(),
                        'body'          => $data['body'],
                    ]);

                    // 重新加载关系，让详情页时间线立即显示新备注
                    $record->unsetRelation('notes');

                    Notification::make()
                        ->success()
                        ->title('跟进备注已添加')
                        ->send();
                }),
        ];
    }
}
