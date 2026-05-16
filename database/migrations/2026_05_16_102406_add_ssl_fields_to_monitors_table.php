<?php

// database/migrations/2026_05_16_102406_add_ssl_fields_to_monitors_table.php

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
        Schema::table('monitors', function (Blueprint $table) {
            $table->boolean('ssl_check_enabled')->default(true)->after('custom_headers');
            $table->boolean('ssl_valid')->nullable()->after('ssl_check_enabled');
            $table->timestamp('ssl_expires_at')->nullable()->after('ssl_valid');
            $table->integer('ssl_days_remaining')->nullable()->after('ssl_expires_at');
            $table->integer('ssl_alert_days_before')->default(14)->after('ssl_days_remaining');
            $table->boolean('ssl_alert_sent')->default(false)->after('ssl_alert_days_before');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn([
                'ssl_check_enabled',
                'ssl_valid',
                'ssl_expires_at',
                'ssl_days_remaining',
                'ssl_alert_days_before',
                'ssl_alert_sent',
            ]);
        });
    }
};