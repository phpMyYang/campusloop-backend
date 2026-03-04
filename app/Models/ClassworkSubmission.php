<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassworkSubmission extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'classwork_id', 'student_id', 'status', 
        'grade', 'teacher_feedback', 'submitted_at'
    ];

    public function classwork()
    {
        return $this->belongsTo(Classwork::class); 
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id'); 
    }
}