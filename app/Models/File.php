<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_id', 'name', 'path', 'file_extension', 'file_size', 
        'attachable_type', 'attachable_id'
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function attachable()
    {
        return $this->morphTo();
    }
}
