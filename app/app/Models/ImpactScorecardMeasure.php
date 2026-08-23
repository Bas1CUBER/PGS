<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $impact
 * @property string $measure
 * @property string|null $bl
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 */
class ImpactScorecardMeasure extends Model
{
    protected $table = 'impact_scorecard_measures';

    protected $fillable = [
        'impact',
        'measure',
        'bl',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
