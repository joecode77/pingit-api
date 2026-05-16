<?php

// database/migrations/2026_05_16_112116_add_dns_resolution_ms_to_monitor_checks_table.php

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
        Schema::table('monitor_checks', function (Blueprint $table) {
            $table->integer('dns_resolution_ms')->nullable()->after('response_time_ms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitor_checks', function (Blueprint $table) {
            $table->dropColumn('dns_resolution_ms');
        });
    }
};