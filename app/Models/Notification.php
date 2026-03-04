<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 
        'description', 
        'link', 
        'is_read'
    ];

    protected $casts = [
        'is_read' => 'boolean', // Ensures true/false output sa API
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}