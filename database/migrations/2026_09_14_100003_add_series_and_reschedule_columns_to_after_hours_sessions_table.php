<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two things a session did not know about itself.
     *
     * `series_id` ties a meeting to the standing appointment that produced it;
     * sessions scheduled one at a time — every existing row — keep it null.
     * `rescheduled_from` holds the time a session was first set for, so a moved
     * meeting can say so instead of silently showing a new date; null means it
     * never moved.
     */
    public function up(): void
    {
        Schema::table('after_hours_sessions', function (Blueprint $table): void {
            $table->foreignId('series_id')->nullable()->after('mentoring_group_id')
                ->constrained('after_hours_session_series')->nullOnDelete();
            $table->dateTime('rescheduled_from')->nullable()->after('ends_at');
            $table->string('reschedule_reason', 255)->nullable()->after('rescheduled_from');
        });
    }

    /**
     * Rolling back discards which sessions were moved, from when, and why.
     */
    public function down(): void
    {
        Schema::table('after_hours_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('series_id');
            $table->dropColumn(['rescheduled_from', 'reschedule_reason']);
        });
    }
};
