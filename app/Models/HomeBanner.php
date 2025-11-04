<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeBanner extends Model
{
    use HasFactory;

    protected $table = 'home_banner';

    protected $fillable = [
        'banner_title',
        'priority',
        'device_type',
        'image',
        'create_user_id',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Relationship with User table  
    public function creator()
    {
        return $this->belongsTo(User::class, 'create_user_id', 'id');
    }

    // Accessor for full image URL
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return url('storage/banners/' . $this->image);
        }
        return null;
    }
}
