<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormSubmissionAnswer extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'submission_id', 'question_id', 'student_answer', 
        'is_correct', 'points_earned'
    ];

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function question()
    {
        return $this->belongsTo(FormQuestion::class, 'question_id');
    }
}
