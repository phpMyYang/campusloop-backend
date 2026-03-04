<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdvisoryClass extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['teacher_id', 'section', 'school_year', 'capacity'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'advisory_student', 'advisory_class_id', 'student_id')
                    ->withPivot('id', 'deleted_at')
                    ->withTimestamps();
    }
}
