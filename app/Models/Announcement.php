<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'creator_id', 
        'title', 
        'content', 
        'link', 
        'status'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    // Polymorphic connection para sa attachments ng announcement
    public function files()
    {
        return $this->morphMany(File::class, 'attachable');
    }

    // Polymorphic connection para sa comment section ng announcement
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}