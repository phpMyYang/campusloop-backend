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
        Schema::create('classrooms', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->foreignUuid('creator_id')->constrained('users')->onDelete('cascade'); 
            $table->string('section'); 
            $table->foreignUuid('strand_id')->constrained('strands')->onDelete('cascade'); 
            $table->enum('grade_level', ['11', '12']); 
            $table->foreignUuid('subject_id')->constrained('subjects')->onDelete('cascade'); 
            $table->integer('capacity'); 
            $table->string('schedule'); 
            $table->string('color_bg'); 
            $table->string('code')->unique(); // 9-char generated 
            $table->string('school_year'); 
            $table->enum('semester', ['1st', '2nd']); 
            $table->timestamps(); 
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
