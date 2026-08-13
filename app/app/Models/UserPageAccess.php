<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property bool $roadmaps
 * @property bool $scorecard
 * @property bool $performance_assessment
 * @property bool $cascading
 * @property bool $governance
 * @property Carbon|null $updated_at
 */
class UserPageAccess extends Model
{
    public $timestamps = false;

    protected $table = 'user_page_access';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'roadmaps',
        'scorecard',
        'performance_assessment',
        'cascading',
        'governance',
    ];

    protected $casts = [
        'roadmaps' => 'boolean',
        'scorecard' => 'boolean',
        'performance_assessment' => 'boolean',
        'cascading' => 'boolean',
        'governance' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
