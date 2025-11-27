<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Otp extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'phone_no',
        'otp',
        'expires_at',
        'is_verified',
        'user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    /**
     * Check if OTP is expired
     */
    public function isExpired()
    {
        return $this->expires_at < now();
    }

    /**
     * Check if OTP is valid
     */
    public function isValid($otp)
    {
        return $this->otp === $otp && !$this->isExpired() && !$this->is_verified;
    }

    /**
     * Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}