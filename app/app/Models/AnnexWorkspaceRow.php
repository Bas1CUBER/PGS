<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property array<string, mixed> $data
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AnnexWorkspaceRow extends Model
{
    protected $table = 'annex_workspace_rows';

    protected $fillable = [
        'slug',
        'data',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
