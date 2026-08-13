<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoadmapBlockType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $item_id
 * @property RoadmapBlockType $block_type
 * @property int $sort_order
 * @property array<string, mixed> $content
 */
class RoadmapBlock extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'roadmap_page_blocks';

    protected $fillable = [
        'item_id',
        'block_type',
        'sort_order',
        'content',
    ];

    protected $casts = [
        'block_type' => RoadmapBlockType::class,
        'content' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<RoadmapItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(RoadmapItem::class, 'item_id');
    }
}
