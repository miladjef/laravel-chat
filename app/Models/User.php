<?php

namespace App\Models;

use App\Support\DisplayNameNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'uuid',
        'display_name',
        'normalized_name',
        'last_seen_at',
    ];

    protected $hidden = [
        'remember_token',
        'normalized_name',
    ];

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty('display_name') || ! $user->normalized_name) {
                $user->normalized_name = DisplayNameNormalizer::normalize((string) $user->display_name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
