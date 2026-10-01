<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->enum('payment_status', ['UNPAID', 'PAID'])->default('UNPAID');
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users');
            $table->index(['event_id', 'payment_status']);
        });
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('invitation_id')->nullable()->change();
            $table->enum('result', ['VALID', 'ALREADY_SCANNED', 'INVALID', 'BLOCKED', 'CANCELLED', 'UNPAID', 'ERROR'])->default('ERROR')->change();
        });
        Schema::create('auth_sessions', function (Blueprint $table) {
            $table->char('token_hash', 64)->primary();
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('event_users', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained('events');
            $table->foreignId('user_id')->constrained('users');
            $table->primary(['event_id', 'user_id']);
        });
        Schema::create('payment_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->foreignId('invitation_id')->constrained('invitations');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // Payment evidence must not be silently deleted by a rollback.
        throw new RuntimeException('Irreversible payment migration: restore a verified backup if required.');
    }
};
