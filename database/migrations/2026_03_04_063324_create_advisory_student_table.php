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
        Schema::create('advisory_student', function (Blueprint $table) {
           $table->uuid('id')->primary();
            $table->foreignUuid('advisory_class_id')->constrained('advisory_classes')->onDelete('cascade');
            $table->foreignUuid('student_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisory_student');
    }
};
