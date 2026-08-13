<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property int $sort_order
 */
class RoadmapTitle extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'roadmap_titles';

    protected $fillable = [
        'title',
        'sort_order',
    ];

    /**
     * @return HasMany<RoadmapItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RoadmapItem::class, 'title_id')->orderBy('sort_order');
    }
}
