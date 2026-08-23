<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property string|null $form_data
 * @property string|null $pdf_file
 * @property Carbon $created_at
 */
class OperationsReview extends Model
{
    protected $table = 'operations_review';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'form_data',
        'pdf_file',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
