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
        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->string('code'); 
            $table->string('description'); 
            $table->foreignUuid('strand_id')->constrained('strands')->onDelete('cascade'); 
            $table->enum('grade_level', ['11', '12']); 
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
        Schema::dropIfExists('subjects');
    }
};
