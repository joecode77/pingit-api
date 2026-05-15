<?php

// database/migrations/2026_05_15_143434_create_monitors_table.php

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
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('url');
            $table->integer('check_interval')->default(5);
            $table->integer('threshold')->default(3);
            $table->integer('response_time_threshold_ms')->nullable();
            $table->enum('http_method', ['GET', 'HEAD'])->default('GET');
            $table->boolean('follow_redirects')->default(true);
            $table->json('custom_headers')->nullable();
            $table->enum('status', ['pending', 'up', 'degraded', 'down', 'paused'])->default('pending');
            $table->boolean('is_checking')->default(false);
            $table->integer('consecutive_failures')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes for frequently queried columns
            $table->index('user_id');
            $table->index('status');
            $table->index('next_check_at');
            $table->index('deleted_at');

            // A user cannot have two monitors with the same URL
            $table->unique(['user_id', 'url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};