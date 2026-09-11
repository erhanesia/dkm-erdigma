<?php

declare(strict_types=1);

use App\Enums\DeviceCommandStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbound instruction queue. The player polls it, so the DKM board can fix
     * a silent room from the office instead of walking there.
     */
    public function up(): void
    {
        Schema::create('device_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->json('payload')->nullable();
            $table->string('status', 20)->default(DeviceCommandStatus::Pending->value);
            $table->string('result_message', 255)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
    }
};
