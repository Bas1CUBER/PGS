<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $measure_id
 * @property int $year_id
 * @property string|null $value
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 */
class ImpactScorecardValue extends Model
{
    protected $table = 'impact_scorecard_values';

    protected $fillable = [
        'measure_id',
        'year_id',
        'value',
    ];

    protected $casts = [
        'measure_id' => 'integer',
        'year_id' => 'integer',
    ];
}
