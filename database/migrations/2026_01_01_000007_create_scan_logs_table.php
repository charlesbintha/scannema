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
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('invitation_id')->constrained('invitations')->onDelete('cascade');
            $table->foreignId('device_id')->nullable()->constrained('devices')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('checkpoint_id')->nullable()->constrained('checkpoints')->onDelete('set null');
            $table->string('qr_payload', 255);
            $table->enum('result', ['VALID', 'ALREADY_SCANNED', 'INVALID', 'BLOCKED', 'CANCELLED', 'ERROR'])->default('ERROR');
            $table->string('error_message', 255)->nullable();
            $table->string('device_ip', 45)->nullable();
            $table->timestamps();

            $table->index('event_id');
            $table->index('invitation_id');
            $table->index('device_id');
            $table->index('user_id');
            $table->index('checkpoint_id');
            $table->index('result');
            $table->index('qr_payload');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
