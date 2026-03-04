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
        Schema::create('advisory_classes', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->foreignUuid('teacher_id')->constrained('users')->onDelete('cascade');
            $table->string('section'); 
            $table->string('school_year'); 
            $table->integer('capacity'); 
            $table->timestamps(); 
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisory_classes');
    }
};
