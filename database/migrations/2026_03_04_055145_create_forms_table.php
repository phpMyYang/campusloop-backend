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
        Schema::create('forms', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->foreignUuid('creator_id')->constrained('users')->onDelete('cascade'); 
            $table->string('name'); 
            $table->text('instruction'); 
            $table->integer('timer'); // in minutes 
            $table->boolean('is_shuffle_questions')->default(false); 
            $table->boolean('is_focus_mode')->default(false); // Anti-cheat toggle 
            $table->uuid('duplicate_from_id')->nullable(); // Para sa cloned forms 
            $table->timestamps(); 
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
