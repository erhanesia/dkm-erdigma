<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A halaqah's standing appointment: the same weekday and hours, every one
     * or two weeks, between two dates.
     *
     * The series records only the pattern. Each meeting it produces is still
     * an ordinary row in `after_hours_sessions`, so attendance, QR check-in, the
     * reports and the public page need to know nothing about series at all.
     *
     * `weekday` is ISO: 1 is Monday, 7 is Sunday.
     */
    public function up(): void
    {
        Schema::create('after_hours_session_series', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mentoring_group_id')->constrained('mentoring_groups')->cascadeOnDelete();
            $table->string('topic', 200);
            $table->unsignedTinyInteger('weekday');
            $table->unsignedTinyInteger('interval_weeks');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('after_hours_session_series');
    }
};
