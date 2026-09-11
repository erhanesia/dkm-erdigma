<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets DKM decide which sessions appear on the public site.
     *
     * The public page shows only the title, topic, time, place and the ustadz
     * leading it — never who attends or whether they turned up. Even so, a
     * session can be internal for reasons the data cannot know (a closed
     * evaluation, a group with a sensitive topic), so this is the switch for
     * taking one off the public page without deleting it.
     *
     * Defaults to visible: a mosque schedule is normally something to announce,
     * and hiding by default would make the public page look empty and broken.
     */
    public function up(): void
    {
        Schema::table('after_hours_sessions', function (Blueprint $table): void {
            $table->boolean('is_public')->default(true)->after('is_qr_enabled');

            // The public listing filters on exactly these three columns.
            $table->index(['is_public', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('after_hours_sessions', function (Blueprint $table): void {
            $table->dropIndex(['is_public', 'status', 'starts_at']);
            $table->dropColumn('is_public');
        });
    }
};
