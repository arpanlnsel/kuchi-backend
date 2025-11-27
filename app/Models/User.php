<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'phone_no',
        'password',
        'role',
        'isActive',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'isActive' => 'boolean',
        ];
    }

    // JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey(); // This should return the UUID
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // Role check methods
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isSales()
    {
        return $this->role === 'sales';
    }

    public function isUser()
    {
        return $this->role === 'user';
    }

    // Relationship with MataData
    public function mataData()
    {
        return $this->hasMany(MataData::class, 'user_id', 'id');
    }

    // Relationship with OTPs
    public function otps()
    {
        return $this->hasMany(Otp::class, 'user_id', 'id');
    }

    public function locations()
    {
        return $this->hasMany(UserLocation::class);
    }

    public function latestLocation()
    {
        return $this->hasOne(UserLocation::class)->latestOfMany();
    }
}
