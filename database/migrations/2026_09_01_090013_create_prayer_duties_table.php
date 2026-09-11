<?php

declare(strict_types=1);

use App\Enums\DutyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily muezzin/imam roster, one row per prayer per day.
     */
    public function up(): void
    {
        Schema::create('prayer_duties', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('prayer', 20);
            $table->foreignId('muadzin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('imam_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default(DutyStatus::Assigned->value);
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['date', 'prayer']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_duties');
    }
};
