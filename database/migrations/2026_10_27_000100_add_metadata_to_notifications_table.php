<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->after('type');
            $table->string('dedupe_key', 191)->nullable()->unique('notifications_dedupe_unique')->after('read_at');

            $table->index(['notifiable_type', 'notifiable_id', 'category'], 'notifications_category_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_dedupe_unique');
            $table->dropIndex('notifications_category_index');
            $table->dropColumn(['category', 'dedupe_key']);
        });
    }
};
