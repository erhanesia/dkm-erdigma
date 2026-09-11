<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-room, per-prayer adhan configuration. This is what makes it possible
     * to keep the CEO room quieter than the lobby without editing a script.
     */
    public function up(): void
    {
        Schema::create('zone_prayer_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audio_zone_id')->constrained('audio_zones')->cascadeOnDelete();
            $table->string('prayer', 20);
            $table->boolean('is_adhan_enabled')->default(true);
            $table->boolean('is_tarhim_enabled')->default(false);
            $table->boolean('is_iqamah_enabled')->default(false);
            $table->unsignedTinyInteger('volume')->default(80);
            $table->smallInteger('offset_minutes')->default(0);
            $table->unsignedSmallInteger('tarhim_lead_minutes')->default(10);
            $table->unsignedSmallInteger('iqamah_delay_minutes')->default(10);
            $table->foreignId('adhan_track_id')->nullable()->constrained('audio_tracks')->nullOnDelete();
            $table->foreignId('tarhim_track_id')->nullable()->constrained('audio_tracks')->nullOnDelete();
            $table->foreignId('iqamah_track_id')->nullable()->constrained('audio_tracks')->nullOnDelete();
            $table->timestamps();

            $table->unique(['audio_zone_id', 'prayer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_prayer_settings');
    }
};
