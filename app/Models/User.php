<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'logo',
        'phone',
        'workspace',
        'role',
        'admin_role_id',
        'is_test',
        'send_email',
        'email_verified_at',
        'password',
        'api_token',
        'data',
        'status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'data' => 'array',
            'status' => 'boolean',
            'send_email' => 'boolean',
            'is_test' => 'boolean'
        ];
    }

    public function adminRole(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class);
    }

    /**
     * Get the affiliate record associated with the user.
     */
    public function affiliate(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Affiliate::class);
    }

    /**
     * Get the affiliate record associated partner_platform_connections.
     */
    public function postingDocs(): HasMany
    {
        return $this->hasMany(partnerPlatformConnections::class);
    }
}
