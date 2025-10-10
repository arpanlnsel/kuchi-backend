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
        'logout_time',
        'is_logout',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'last_login_time' => 'datetime',
            'logout_time' => 'datetime',
            'is_logout' => 'boolean',
        ];
    }

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}