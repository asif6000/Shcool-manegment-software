<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('push_subscription_id')->nullable()->constrained('push_subscriptions')->nullOnDelete();
            $table->string('channel', 20)->default('webpush');
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->string('error_message', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('notification_id')
                ->references('id')
                ->on('notifications')
                ->nullOnDelete();

            $table->unique(
                ['notification_id', 'push_subscription_id', 'channel'],
                'push_delivery_logs_unique'
            );
            $table->index(['user_id', 'status', 'created_at'], 'push_delivery_logs_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_delivery_logs');
    }
};
