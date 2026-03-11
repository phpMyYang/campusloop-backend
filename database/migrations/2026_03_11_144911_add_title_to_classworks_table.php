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
        Schema::table('classworks', function (Blueprint $table) {
            // Nilagyan natin ng nullable() para hindi mag-error kapag may existing data ka na sa database na walang title
            $table->string('title')->nullable()->after('classroom_id'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classworks', function (Blueprint $table) {
            // Para kung sakaling i-rollback mo, tatanggalin lang niya ang title column
            $table->dropColumn('title'); 
        });
    }
};
