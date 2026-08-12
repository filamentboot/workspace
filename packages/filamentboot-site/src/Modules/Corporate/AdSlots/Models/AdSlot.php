<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\AdSlots\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 广告位模型
 *
 * 由 filamentboot-site:content-type:sync 按「ad_slot」内容类型声明生成。
 */
class AdSlot extends Model
{
    /** @var string */
    protected $table = 'site_ad_slots';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'image',
        'link_url',
        'position',
        'starts_at',
        'ends_at',
        'is_enabled',
        'sort',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
        'is_enabled' => 'boolean',
    ];
}
