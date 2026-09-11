<?php

declare(strict_types=1);

use App\Enums\AudioTrackType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_tracks', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('type', 20)->default(AudioTrackType::Murottal->value);
            $table->string('reciter', 120)->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Covers both the "pick the default adhan" lookup and the type filter.
            $table->index(['type', 'is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_tracks');
    }
};
