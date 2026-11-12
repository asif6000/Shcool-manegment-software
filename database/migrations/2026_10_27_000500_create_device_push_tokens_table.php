<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->text('token');
            $table->char('token_hash', 64)->unique('device_push_tokens_hash_unique');
            $table->string('device_name', 100)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active'], 'device_push_tokens_user_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_push_tokens');
    }
};
