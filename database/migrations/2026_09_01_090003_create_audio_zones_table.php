<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per room that has speakers — the CEO room, the lobby, the prayer
     * room. Everything about audio is scoped to a zone so a room can be turned
     * on or off without touching the others.
     */
    public function up(): void
    {
        Schema::create('audio_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('floor', 50)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedTinyInteger('default_volume')->default(80);
            $table->boolean('is_adhan_enabled')->default(true);
            $table->boolean('is_murottal_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_zones');
    }
};
