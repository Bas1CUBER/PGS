<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $objective
 * @property string|null $target_audience
 * @property string|null $message
 * @property string|null $channel
 * @property string|null $timeframe
 * @property string|null $requirements
 * @property string|null $responsible_person
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property string $status
 */
class CommunicationPlanRoadmap extends Model
{
    protected $table = 'communication_plan_roadmap';

    public $timestamps = false;

    protected $fillable = [
        'objective',
        'target_audience',
        'message',
        'channel',
        'timeframe',
        'requirements',
        'responsible_person',
        'created_by',
        'created_at',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
