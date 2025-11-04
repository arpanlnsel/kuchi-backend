<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyPolicy extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'create_privacy_policy';
    
    protected $fillable = [
        'title',
        'content',
        'is_active',
        'user_id', // Add this
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Add relationship to User model
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
