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
        Schema::table('announcements', function (Blueprint $table) {
            // Tatanggalin natin yung lumang status dahil dynamic na siya sa Model natin ngayon
            $table->dropColumn('status');
            
            // Idadagdag natin ang mga bagong scheduling columns
            $table->timestamp('publish_from')->nullable()->after('link');
            $table->timestamp('valid_until')->nullable()->after('publish_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Ito ang ibabalik kung sakaling mag php artisan migrate:rollback tayo
            $table->string('status')->default('active')->after('link');
            $table->dropColumn(['publish_from', 'valid_until']);
        });
    }
};
