<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $strategic_goal
 * @property string $success_indicator
 * @property string $division_accountable
 * @property string|null $annual_target
 * @property string|null $quarter1_target
 * @property string|null $quarter2_target
 * @property string|null $quarter3_target
 * @property string|null $quarter4_target
 * @property string|null $remarks
 */
class PerformanceTarget extends Model
{
    protected $table = 'performance_targets';

    public $timestamps = false;

    protected $fillable = [
        'strategic_goal',
        'success_indicator',
        'division_accountable',
        'annual_target',
        'quarter1_target',
        'quarter2_target',
        'quarter3_target',
        'quarter4_target',
        'remarks',
    ];
}
