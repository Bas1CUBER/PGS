<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $url
 * @property string $status
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon|null $archived_at
 */
class Survey extends Model
{
    protected $table = 'surveys';

    public $timestamps = false;

    protected $fillable = [
        'title',
        'url',
        'status',
        'created_by',
        'created_at',
        'archived_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /**
     * @param  Builder<Survey>  $query
     * @return Builder<Survey>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
