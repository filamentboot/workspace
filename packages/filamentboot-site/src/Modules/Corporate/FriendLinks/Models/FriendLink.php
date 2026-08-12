<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\FriendLinks\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 友情链接模型
 *
 * 由 filamentboot-site:content-type:sync 按「friend_link」内容类型声明生成。
 */
class FriendLink extends Model
{
    /** @var string */
    protected $table = 'site_friend_links';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'url',
        'logo',
        'is_enabled',
        'sort',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
