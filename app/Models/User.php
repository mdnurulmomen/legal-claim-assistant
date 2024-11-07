<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Casts\Attribute;

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

    public function partner(): HasOne
    {
        return $this->hasOne(Partner::class);
    }

    /**
     * Get the affiliate record associated with the user.
     */
    public function affiliate(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Affiliate::class);
    }

    /**
     * Get the affiliate record associated with the user.
     */
    public function accountManager()
    {
        return $this->hasOneThrough(User::class, AccountManager::class, 'affiliate_id', 'id', 'id', 'user_id');
    }

    public function postingDocs(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(PartnerPlatformConnection::class, User::class, 'master_user_id', 'user_id', 'id', 'id');
    }

    public function childUsers()
    {
        return $this->hasMany(User::class, 'master_user_id', 'id');
    }
}
