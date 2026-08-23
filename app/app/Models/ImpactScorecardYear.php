<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $year
 * @property int $sort_order
 * @property Carbon $created_at
 */
class ImpactScorecardYear extends Model
{
    protected $table = 'impact_scorecard_years';

    public $timestamps = false;

    protected $fillable = [
        'year',
        'sort_order',
        'created_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
    ];
}
