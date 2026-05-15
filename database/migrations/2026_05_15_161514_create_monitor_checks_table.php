<?php

// database/migrations/2026_05_15_161514_create_monitor_checks_table.php

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
        Schema::create('monitor_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->integer('status_code')->default(0);
            $table->integer('response_time_ms')->nullable();
            $table->boolean('is_up')->default(false);
            $table->timestamp('checked_at');

            // Indexes for frequently queried columns
            $table->index('monitor_id');
            $table->index('checked_at');
            $table->index('is_up');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitor_checks');
    }
};