<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per WhatsApp message the system intends to send through WOW.
 *
 * The row is written *before* the request goes out, and the unique key is what
 * makes the whole thing safe: the announcer runs every minute, and without a
 * claim staked in the database a slow response, an overlapping run, or a
 * restart would each put the same reminder in the group twice.
 *
 * It doubles as the log. When somebody asks why the group did not get a
 * reminder, the answer is a row here — with the payload that was sent and
 * whatever WOW replied — rather than a guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notifications', function (Blueprint $table): void {
            $table->id();

            // What kind of announcement: a prayer reminder, or the Friday one
            // that carries the roster.
            $table->string('kind', 30);

            // What it is about. Together with `kind` these identify the event
            // exactly once, which is what the unique index below enforces.
            $table->date('reference_date');
            $table->string('prayer', 20)->nullable();

            $table->string('status', 20);
            $table->text('message');

            /** What was posted and what came back, for when something looks wrong. */
            $table->json('payload')->nullable();
            $table->text('response')->nullable();
            $table->text('error')->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'reference_date', 'prayer']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
};
