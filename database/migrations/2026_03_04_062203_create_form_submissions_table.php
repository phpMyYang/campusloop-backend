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
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->foreignUuid('form_id')->constrained('forms')->onDelete('cascade'); 
            $table->foreignUuid('student_id')->constrained('users')->onDelete('cascade'); 
            $table->foreignUuid('classwork_id')->nullable()->constrained('classworks')->onDelete('set null'); 
            $table->integer('score')->default(0); 
            $table->timestamp('started_at'); 
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
        Schema::dropIfExists('form_submissions');
    }
};
