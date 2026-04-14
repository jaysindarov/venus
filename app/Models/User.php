<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasApiTokens;
    use HasRoles;
    use Billable;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'avatar_url',
        'role',
        'email_verified_at',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at'    => 'datetime',
            'password'          => 'hashed',
            'is_banned'         => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $user): void {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    public function socialAccounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], strict: true);
    }

    public function isBanned(): bool
    {
        return $this->is_banned;
    }
}
