<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('kind', 20);
            $table->string('driver')->nullable();
            $table->string('serial')->nullable();
            $table->string('location')->nullable();

            // Only the hash of the device secret is stored. The plain secret is
            // shown once when the device is registered.
            $table->string('secret_hash', 64)->nullable();

            $table->string('status', 20)->default('not_configured');
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'kind', 'status'], 'attendance_devices_kind_status_index');
            $table->index(['school_id', 'serial'], 'attendance_devices_serial_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_devices');
    }
};
