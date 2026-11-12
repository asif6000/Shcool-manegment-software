<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 60)->nullable();
            $table->string('code', 30)->unique();
            $table->string('slug', 80)->unique();
            $table->string('eiin', 20)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('phone_alt', 30)->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('division', 60)->nullable();
            $table->string('district', 60)->nullable();
            $table->string('upazila', 60)->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('motto')->nullable();
            $table->unsignedSmallInteger('established_year')->nullable();
            $table->string('timezone', 60)->default('Asia/Dhaka');
            $table->char('currency', 3)->default('BDT');
            $table->string('locale', 5)->default('en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('group_name', 50)->default('general');
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->timestamps();

            $table->unique(['school_id', 'key'], 'settings_school_key_unique');
            $table->index(['school_id', 'group_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('schools');
    }
};
