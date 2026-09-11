<?php

declare(strict_types=1);

use App\Enums\SessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('after_hours_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mentoring_group_id')->constrained('mentoring_groups')->cascadeOnDelete();
            $table->foreignId('mentor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 150);
            $table->string('topic', 200)->nullable();
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('location', 120)->nullable();
            $table->string('status', 20)->default(SessionStatus::Scheduled->value);
            $table->string('qr_token', 64)->unique();
            $table->boolean('is_qr_enabled')->default(true);
            $table->text('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['mentoring_group_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('after_hours_sessions');
    }
};
