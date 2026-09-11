<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records where each day's times came from.
 *
 * The schedule is now filled from the officially published Kemenag times, with
 * the local astronomical calculation as the fallback for days the API could not
 * supply. Both are legitimate, but they are not the same thing — one matches the
 * printed schedule exactly, the other is within about a minute of it.
 *
 * Without this column, a day that quietly fell back to calculation is
 * indistinguishable from one that did not, and "why is Asr a minute off on the
 * 14th" has no answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prayer_schedules', function (Blueprint $table): void {
            $table->string('source', 20)->default('calculated')->after('is_manual_override');
        });
    }

    public function down(): void
    {
        Schema::table('prayer_schedules', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
