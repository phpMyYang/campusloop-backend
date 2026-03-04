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
        Schema::create('final_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('users')->onDelete('cascade'); 
            $table->foreignUuid('subject_id')->constrained('subjects')->onDelete('cascade'); 
            $table->foreignUuid('teacher_id')->constrained('users')->onDelete('cascade'); 
            $table->string('school_year'); 
            $table->enum('semester', ['1st', '2nd']); 
            $table->decimal('grade', 5, 2); 
            $table->enum('status', ['pending', 'approved', 'declined'])->default('pending'); 
            $table->text('admin_feedback')->nullable();
            $table->timestamps(); 
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('final_grades');
    }
};
