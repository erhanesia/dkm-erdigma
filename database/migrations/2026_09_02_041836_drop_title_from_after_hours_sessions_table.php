<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes `title`, promoting `topic` to the label.
 *
 * Every session is an after-hours session, so the title was the same words on
 * every record and told a reader nothing. What actually distinguishes one from
 * another is the subject, which `topic` already held — it was simply demoted
 * beneath a field that carried no information.
 *
 * Two columns for one idea is also two places to look, and they had already
 * drifted: some records said "Kajian Tafsir Surat Al-Kahfi" in `title` and the
 * verses in `topic`, others repeated themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rows whose subject only ever lived in `title` would otherwise lose it.
        DB::table('after_hours_sessions')
            ->where(function ($query): void {
                $query->whereNull('topic')->orWhere('topic', '');
            })
            ->update(['topic' => DB::raw('title')]);

        Schema::table('after_hours_sessions', function (Blueprint $table): void {
            $table->dropColumn('title');
        });

        // Now that it is the label, it has to be there.
        Schema::table('after_hours_sessions', function (Blueprint $table): void {
            $table->string('topic', 200)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('after_hours_sessions', function (Blueprint $table): void {
            $table->string('title', 150)->default('')->after('mentor_id');
            $table->string('topic', 200)->nullable()->change();
        });

        // Reversing puts the subject back in both, since which of the two it
        // came from is no longer recorded anywhere.
        DB::table('after_hours_sessions')->update(['title' => DB::raw('topic')]);
    }
};
