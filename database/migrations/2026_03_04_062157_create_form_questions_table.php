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
        Schema::create('form_questions', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->foreignUuid('form_id')->constrained('forms')->onDelete('cascade'); 
            $table->string('section')->nullable(); 
            $table->string('instruction')->nullable(); 
            $table->text('text'); 
            $table->enum('type', ['multiple_choice', 'short_answer']); 
            $table->json('choices')->nullable(); 
            $table->string('correct_answer'); 
            $table->integer('points'); 
            $table->timestamps(); 
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_questions');
    }
};
