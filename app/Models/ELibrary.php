<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ELibrary extends Model
{
    use HasUuids, SoftDeletes;

    // Optional: Tukuyin ang exact table name kung minsan nagkakamali ang Laravel pluralization sa "libraries"
    protected $table = 'e_libraries';

    protected $fillable = [
        'creator_id', 
        'title', 
        'description', 
        'status', 
        'admin_feedback'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    // Polymorphic connection para sa mismong documents/books na i-a-attach
    public function files()
    {
        return $this->morphMany(File::class, 'attachable');
    }
}