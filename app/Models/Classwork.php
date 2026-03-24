<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classwork extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'classroom_id', 'title', 'type', 'instruction', 'points', 
        'deadline', 'form_id', 'link'
    ];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function form() 
    { 
        return $this->belongsTo(Form::class, 'form_id'); 
    }

    public function files() 
    { 
        return $this->morphMany(File::class, 'attachable'); 
    }

    public function classwork_submissions()
    {
        return $this->hasMany(ClassworkSubmission::class); 
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
