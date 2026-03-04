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
        Schema::create('strands', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID Primary Key 
            $table->string('name'); 
            $table->text('description'); 
            $table->timestamps(); 
            $table->softDeletes(); // Recycle Bin Capability 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strands');
    }
};
