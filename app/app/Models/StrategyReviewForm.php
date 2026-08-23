<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $form_data
 * @property string|null $pdf_filename
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class StrategyReviewForm extends Model
{
    protected $table = 'strategy_review_forms';

    protected $fillable = [
        'employee_id',
        'form_data',
        'pdf_filename',
        'status',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
