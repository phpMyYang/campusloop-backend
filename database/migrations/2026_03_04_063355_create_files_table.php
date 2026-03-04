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
        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->foreignUuid('owner_id')->constrained('users')->onDelete('cascade'); 
            $table->string('name'); 
            $table->string('path'); 
            $table->string('file_extension'); 
            $table->integer('file_size'); 
            $table->nullableUuidMorphs('attachable'); // Best practice para sa polymorphic UUIDs (creates attachable_type at attachable_id) 
            $table->timestamps(); 
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
