<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A halaqah: one mentor (ustadz) and the employees they are responsible for,
     * around ten per group.
     */
    public function up(): void
    {
        Schema::create('mentoring_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 40)->unique();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('capacity')->default(10);
            $table->string('description', 255)->nullable();
            $table->string('default_location', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['mentor_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentoring_groups');
    }
};
