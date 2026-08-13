<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliverableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $form_type
 * @property string|null $title
 * @property string|null $focal_person
 * @property string|null $division
 * @property Carbon|null $target_date
 * @property DeliverableStatus|null $status
 * @property Carbon|null $actual_date
 * @property string|null $mov_file
 * @property Carbon $created_at
 * @property int|null $uploaded_by
 */
class Deliverable extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'p_deliverables';

    protected $fillable = [
        'form_type',
        'title',
        'focal_person',
        'division',
        'target_date',
        'status',
        'actual_date',
        'mov_file',
        'uploaded_by',
    ];

    protected $casts = [
        'status' => DeliverableStatus::class,
        'target_date' => 'date',
        'actual_date' => 'date',
        'uploaded_by' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
