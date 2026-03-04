<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormSubmission extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'form_id', 'student_id', 'classwork_id', 
        'score', 'started_at', 'submitted_at'
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers()
    {
        return $this->hasMany(FormSubmissionAnswer::class, 'submission_id');
    }
}
