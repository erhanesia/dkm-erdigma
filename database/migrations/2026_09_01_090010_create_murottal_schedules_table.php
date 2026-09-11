<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tilawah/murottal windows per room. `days_of_week` holds ISO weekday
     * numbers so a schedule can run on working days only.
     */
    public function up(): void
    {
        Schema::create('murottal_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audio_zone_id')->constrained('audio_zones')->cascadeOnDelete();
            $table->foreignId('audio_track_id')->nullable()->constrained('audio_tracks')->nullOnDelete();
            $table->string('name', 120);
            $table->time('start_time');
            $table->time('end_time');
            $table->json('days_of_week');
            $table->unsignedTinyInteger('volume')->default(60);
            $table->boolean('is_loop')->default(true);
            $table->boolean('stops_before_adhan')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['audio_zone_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('murottal_schedules');
    }
};
