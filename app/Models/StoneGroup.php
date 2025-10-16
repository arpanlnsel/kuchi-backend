<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoneGroup extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'stone_groups';
    
    protected $primaryKey = 'stonegroup_ID';
    
    public $incrementing = false;
    
    protected $keyType = 'string';

    protected $fillable = [
        'stonegroup_GUID',
        'stonegroup_name',
        'stonegroup_shortname',
        'is_disabled',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_disabled' => 'boolean',
        ];
    }

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // Remove the auto-generating boot method since GUID is manual now
}