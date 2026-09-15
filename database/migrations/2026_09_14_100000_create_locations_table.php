<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Places a session or a halaqah can meet in — typed once, picked from a
     * list afterwards.
     *
     * `name` is unique, and under the table's case-insensitive collation that
     * also keeps "musholla erdigma" from sitting beside "Musholla Erdigma".
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
