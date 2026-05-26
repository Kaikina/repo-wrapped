<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepoStat extends Model
{
    protected $table = 'repo_stats';

    protected $fillable = [
        'owner',
        'repo',
        'username',
        'stats',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'stats'       => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function isStale(): bool
    {
        return $this->computed_at->diffInHours(now()) >= 1;
    }

    public function isExpired(): bool
    {
        return $this->computed_at->diffInHours(now()) >= 24;
    }
}
