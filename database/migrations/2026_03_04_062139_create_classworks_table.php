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
        Schema::create('classworks', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->foreignUuid('classroom_id')->constrained('classrooms')->onDelete('cascade'); 
            $table->enum('type', ['assignment', 'activity', 'quiz', 'exam', 'material']); 
            $table->text('instruction'); 
            $table->integer('points')->nullable(); 
            $table->timestamp('deadline')->nullable(); 
            $table->foreignUuid('form_id')->nullable()->constrained('forms')->onDelete('set null'); 
            $table->string('link')->nullable(); 
            $table->timestamps(); 
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classworks');
    }
};
