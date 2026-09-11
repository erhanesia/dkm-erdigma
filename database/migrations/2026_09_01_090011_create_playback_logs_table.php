<?php

declare(strict_types=1);

use App\Enums\PlaybackStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The evidence trail behind "adzan sering tidak terdengar".
     *
     * A row is created the moment a playback is scheduled and updated when the
     * player reports back. `peak_audio_level` is the measured output RMS, so a
     * row can say "we played it, but nothing came out" — which no amount of
     * server-side logging could previously tell us.
     */
    public function up(): void
    {
        Schema::create('playback_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audio_zone_id')->constrained('audio_zones')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('audio_track_id')->nullable()->constrained('audio_tracks')->nullOnDelete();
            $table->string('type', 20);
            $table->string('prayer', 20)->nullable();
            $table->string('reference_key', 120);
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default(PlaybackStatus::Pending->value);
            $table->decimal('peak_audio_level', 5, 4)->nullable();
            $table->decimal('average_audio_level', 5, 4)->nullable();
            $table->unsignedTinyInteger('volume')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->boolean('is_acknowledged')->default(false);
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            // One row per zone per scheduled slot — makes plan generation idempotent.
            $table->unique(['audio_zone_id', 'reference_key']);
            // Drives the "mark overdue as missed" sweep.
            $table->index(['status', 'scheduled_at']);
            // Drives the per-zone monitoring listing and reliability charts.
            $table->index(['audio_zone_id', 'scheduled_at']);
            // Drives the unresolved-issues widget on the dashboard.
            $table->index(['is_acknowledged', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playback_logs');
    }
};
