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
        'publish_from',
        'valid_until'
    ];

    protected $casts = [
        'publish_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    protected $appends = ['status'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function getStatusAttribute()
    {
        $now = now();
        
        if ($this->publish_from && $now->lt($this->publish_from)) {
            return 'Pending'; // Kung ang current time ay bago pa sa publish_from
        } elseif ($this->valid_until && $now->gt($this->valid_until)) {
            return 'Done'; // Kung ang current time ay lampas na sa valid_until
        }
        
        return 'Published'; // Kung nasa loob na ng valid date range
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