<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework', function (Blueprint $table): void {
            // Classwork used to be written into study_materials by a second
            // form, so one save produced two unlinked rows. Both kinds now live
            // here and the column says which one each row is. Rows that already
            // existed are homework, which is what the default gives them.
            $table->string('category', 16)->default('homework')->after('title');
            // Classwork is "what we did today" and has no deadline, so the due
            // date stops being mandatory for it.
            $table->date('due_date')->nullable()->change();
            $table->index(['school_id', 'category', 'status'], 'homework_category_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('homework', function (Blueprint $table): void {
            $table->dropIndex('homework_category_status_index');
            $table->dropColumn('category');
            $table->date('due_date')->nullable(false)->change();
        });
    }
};
