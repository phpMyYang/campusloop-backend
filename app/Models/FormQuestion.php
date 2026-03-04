<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormQuestion extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'form_id', 'section', 'instruction', 'text', 
        'type', 'choices', 'correct_answer', 'points'
    ];

    protected $casts = [
        'choices' => 'array', // Para automatic maging array ang JSON galing database
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
