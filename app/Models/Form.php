<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'creator_id', 'name', 'instruction', 'timer', 
        'is_shuffle_questions', 'is_focus_mode', 'duplicate_from_id'
    ];

    protected $casts = [
        'is_shuffle_questions' => 'boolean',
        'is_focus_mode' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    // Naka-ready na para sa next batch natin!
    public function questions()
    {
        return $this->hasMany(FormQuestion::class); 
    }
}
