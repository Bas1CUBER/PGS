<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $role
 * @property bool $enabled
 * @property Carbon|null $end_time
 * @property string|null $message
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 */
class DeadlineControl extends Model
{
    public $timestamps = false;

    protected $table = 'deadline_controls';

    protected $primaryKey = 'role';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'role',
        'enabled',
        'end_time',
        'message',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'end_time' => 'datetime',
        'updated_by' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isOpen(): bool
    {
        return ! $this->enabled || $this->end_time === null || $this->end_time->isFuture();
    }
}
