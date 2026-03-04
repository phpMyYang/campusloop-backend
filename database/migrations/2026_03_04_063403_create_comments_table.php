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
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade'); 
            $table->uuidMorphs('commentable'); // Best practice para sa polymorphic UUIDs 
            $table->foreignUuid('parent_id')->nullable()->constrained('comments')->onDelete('cascade'); 
            $table->text('content'); 
            $table->timestamps(); 
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
