<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_submission_answers', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->foreignUuid('submission_id')->constrained('form_submissions')->onDelete('cascade'); 
            $table->foreignUuid('question_id')->constrained('form_questions')->onDelete('cascade');
            $table->text('student_answer'); 
            $table->boolean('is_correct'); 
            $table->integer('points_earned'); 
            $table->timestamps(); 
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_submission_answers');
    }
};
