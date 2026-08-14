<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PasswordResetCode extends Model
{
    protected $fillable = [
        'email',
        'code_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'used_at',
    ];

    protected $hidden = [
        'code_hash',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
