<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $title_id
 * @property string $sub_letter
 * @property string $sub_label
 * @property string $page_slug
 * @property bool $has_builder_page
 * @property int $sort_order
 */
class RoadmapItem extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'roadmap_items';

    protected $fillable = [
        'title_id',
        'sub_letter',
        'sub_label',
        'page_slug',
        'has_builder_page',
        'sort_order',
    ];

    protected $casts = [
        'has_builder_page' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<RoadmapTitle, $this>
     */
    public function title(): BelongsTo
    {
        return $this->belongsTo(RoadmapTitle::class, 'title_id');
    }

    /**
     * @return HasMany<RoadmapBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(RoadmapBlock::class, 'item_id')->orderBy('sort_order');
    }
}
