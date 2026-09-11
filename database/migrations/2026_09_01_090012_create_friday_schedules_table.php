<?php

declare(strict_types=1);

use App\Enums\DutyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friday_schedules', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->foreignId('khatib_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('imam_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('muadzin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_khatib_name', 120)->nullable();
            $table->string('external_khatib_origin', 150)->nullable();
            $table->string('theme', 200)->nullable();
            $table->text('notes')->nullable();
            $table->string('location', 120)->nullable();
            $table->time('start_time')->nullable();
            $table->string('status', 20)->default(DutyStatus::Draft->value);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friday_schedules');
    }
};
