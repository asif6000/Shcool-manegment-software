<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework', function (Blueprint $table): void {
            // Existing rows were already visible to students, so they are
            // published; new homework starts as a draft.
            $table->string('status', 16)->default('published')->after('due_date');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->index(['school_id', 'status', 'due_date'], 'homework_status_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('homework', function (Blueprint $table): void {
            $table->dropIndex('homework_status_due_index');
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
