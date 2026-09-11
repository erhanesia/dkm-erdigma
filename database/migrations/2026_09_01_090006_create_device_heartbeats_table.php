<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rolling record of device check-ins. Gaps in this table are exactly what a
     * power cut looks like, which is how the uptime chart is built.
     */
    public function up(): void
    {
        Schema::create('device_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->boolean('is_audio_unlocked')->default(false);
            $table->decimal('audio_level', 5, 4)->nullable();
            $table->unsignedTinyInteger('volume')->nullable();
            $table->boolean('is_online_browser')->default(true);
            $table->string('app_version', 30)->nullable();
            $table->timestamp('reported_at');

            $table->index(['device_id', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_heartbeats');
    }
};
