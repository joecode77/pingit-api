<?php

// database/migrations/2026_05_15_210840_create_notification_channels_table.php

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
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['email', 'webhook']);
            $table->string('value');
            $table->boolean('notify_on_down')->default(true);
            $table->boolean('notify_on_recovery')->default(true);
            $table->boolean('notify_on_degraded')->default(false);
            $table->timestamps();

            $table->index('monitor_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_channels');
    }
};