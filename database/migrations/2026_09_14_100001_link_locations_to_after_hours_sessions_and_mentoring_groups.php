<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Points sessions and halaqah at a row in `locations`.
     *
     * The text columns stay. On a session, `location` is the snapshot of the
     * name it was held under, so renaming a place later does not rewrite where
     * past meetings happened; on a halaqah, `default_location` keeps every
     * screen that already reads it working unchanged.
     *
     * Every place typed so far is copied into the list and linked, so the
     * dropdown opens on the names people have been using rather than empty.
     */
    public function up(): void
    {
        Schema::table('after_hours_sessions', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->after('location')
                ->constrained('locations')->nullOnDelete();
        });

        Schema::table('mentoring_groups', function (Blueprint $table): void {
            $table->foreignId('default_location_id')->nullable()->after('default_location')
                ->constrained('locations')->nullOnDelete();
        });

        $this->backfill('after_hours_sessions', 'location', 'location_id');
        $this->backfill('mentoring_groups', 'default_location', 'default_location_id');
    }

    /**
     * Drops only the links. The text columns were never touched, so every name
     * a screen shows survives the rollback.
     */
    public function down(): void
    {
        Schema::table('after_hours_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::table('mentoring_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_location_id');
        });
    }

    /**
     * Runs once per distinct name rather than once per row: in practice there
     * are only a handful of them.
     */
    private function backfill(string $table, string $nameColumn, string $idColumn): void
    {
        $names = DB::table($table)
            ->whereNotNull($nameColumn)
            ->distinct()
            ->pluck($nameColumn);

        foreach ($names as $raw) {
            $name = Str::squish((string) $raw);

            if ($name === '') {
                continue;
            }

            // The collation compares names case-insensitively, so a second
            // spelling of the same place finds the row the first one made.
            $locationId = DB::table('locations')->where('name', $name)->value('id')
                ?? DB::table('locations')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table($table)->where($nameColumn, $raw)->update([$idColumn => $locationId]);
        }
    }
};
