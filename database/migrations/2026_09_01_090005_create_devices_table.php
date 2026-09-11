<?php

declare(strict_types=1);

use App\Enums\DeviceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A device is whatever browser is left open on the player page in a room.
     * `token_hash` never stores the plain secret, and the last known audio level
     * lets the dashboard tell "the browser is running" apart from "sound is
     * actually coming out of the speakers".
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audio_zone_id')->constrained('audio_zones')->cascadeOnDelete();
            $table->string('name', 120);
            $table->char('token_hash', 64)->unique();
            $table->string('token_preview', 40);
            $table->string('status', 20)->default(DeviceStatus::NeverConnected->value);
            $table->unsignedTinyInteger('volume')->default(80);
            $table->boolean('is_audio_unlocked')->default(false);
            $table->decimal('last_audio_level', 5, 4)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->ipAddress('last_ip_address')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('plan_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['audio_zone_id', 'is_active']);
            $table->index('status');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
