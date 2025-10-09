<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MataData extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mata_data';
    protected $primaryKey = 'mata_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'device_name',
        'device_type',
        'last_login_time',
        'user_id',
    ];

    protected $casts = [
        'last_login_time' => 'datetime',
    ];

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}