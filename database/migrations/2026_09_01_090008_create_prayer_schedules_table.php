<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pre-computed prayer times, one row per day. Storing them rather than
     * recalculating on every request keeps the player's plan endpoint cheap and
     * lets the board override a single day when needed.
     */
    public function up(): void
    {
        Schema::create('prayer_schedules', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->time('fajr');
            $table->time('sunrise');
            $table->time('dhuhr');
            $table->time('asr');
            $table->time('maghrib');
            $table->time('isha');
            $table->boolean('is_manual_override')->default(false);
            $table->string('calculation_method', 30)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_schedules');
    }
};
