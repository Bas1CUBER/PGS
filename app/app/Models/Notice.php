<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $notice_id
 * @property string|null $title
 * @property string|null $description
 * @property string|null $image
 * @property string|null $video
 * @property Carbon $created_at
 */
class Notice extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'notices';

    protected $primaryKey = 'notice_id';

    protected $fillable = [
        'title',
        'description',
        'image',
        'video',
    ];
}
