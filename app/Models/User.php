<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'github_id',
        'github_username',
        'github_token',
        'avatar_url',
        'email',
    ];

    protected $hidden = [
        'github_token',
    ];

    protected function casts(): array
    {
        return [
            'github_token' => 'encrypted',
        ];
    }
}
