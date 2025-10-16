<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'events';
    
    protected $primaryKey = 'event_id';
    
    public $incrementing = false;
    
    protected $keyType = 'string';

    protected $fillable = [
        'title',
        'description',
        'venue',
        'status',
        'start_time',
        'end_time',
        'show_video_details',
        'event_location',
        'main_image',
        'event_images',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'show_video_details' => 'boolean',
            'event_images' => 'array',
        ];
    }

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // Relationship with Videos
    public function videos()
    {
        return $this->hasMany(EventVideo::class, 'event_id', 'event_id');
    }

    // Accessor for main image URL
    public function getMainImageUrlAttribute()
    {
        if ($this->main_image) {
            return url('storage/events/' . $this->main_image);
        }
        return null;
    }

    // Accessor for event images URLs
    public function getEventImagesUrlsAttribute()
    {
        if ($this->event_images && is_array($this->event_images)) {
            return array_map(function ($image) {
                return url('storage/events/' . $image);
            }, $this->event_images);
        }
        return [];
    }
}