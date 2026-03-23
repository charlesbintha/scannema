<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->integer('ticket_number');
            $table->string('code', 100);
            $table->string('qr_payload', 255);
            $table->string('guest_name', 255)->nullable();
            $table->string('guest_email', 255)->nullable();
            $table->string('guest_phone', 20)->nullable();
            $table->enum('status', ['NOT_SCANNED', 'SCANNED', 'CANCELLED', 'BLOCKED'])->default('NOT_SCANNED');
            $table->dateTime('scanned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('event_id');
            $table->index('status');
            $table->index('ticket_number');
            $table->index('qr_payload');
            $table->unique(['event_id', 'ticket_number']);
            $table->unique(['event_id', 'qr_payload']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
