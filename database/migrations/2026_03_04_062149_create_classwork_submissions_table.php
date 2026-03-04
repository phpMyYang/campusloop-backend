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
        Schema::create('classwork_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->foreignUuid('classwork_id')->constrained('classworks')->onDelete('cascade'); 
            $table->foreignUuid('student_id')->constrained('users')->onDelete('cascade'); 
            $table->enum('status', ['pending', 'missing', 'late_submission', 'graded']); 
            $table->decimal('grade', 5, 2)->nullable(); 
            $table->text('teacher_feedback')->nullable(); 
            $table->timestamp('submitted_at')->nullable(); 
            $table->timestamps(); 
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classwork_submissions');
    }
};
