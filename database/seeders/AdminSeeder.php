<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Idempotent admin account for every environment. Re-running this seeder
     * never resets an existing password, name or flag, and never touches any
     * other user.
     */
    public function run(): void
    {
        $role = Role::query()->where('name', 'school_admin')->first();

        if ($role === null) {
            $this->command?->error('  school_admin role missing. Run: php artisan db:seed --class=RolePermissionSeeder');

            return;
        }

        $school = School::query()->where('is_active', true)->orderBy('id')->first();

        if ($school === null) {
            $this->command?->error('  No active school found. Create the school before seeding the admin.');

            return;
        }

        $user = User::withTrashed()->firstOrNew(['email' => 'drms2027@gmail.com']);
        $isNew = ! $user->exists;

        $attributes = [
            'school_id' => $user->school_id ?? $school->id,
            'name' => $user->name ?? 'DRMS Admin',
            'deleted_at' => null,
        ];

        if ($isNew) {
            // Initial demo credentials; must_change_password forces a new
            // password on the first sign-in.
            $attributes += [
                'mobile_number' => $user->mobile_number ?? '01700000000',
                'password' => 'admin123',
                'is_active' => true,
                'must_change_password' => true,
                'email_verified_at' => now(),
            ];
        }

        $user->forceFill($attributes)->save();

        if (! $user->hasRole('school_admin')) {
            $user->syncRoles([$role->name]);
        }

        $this->command?->info($isNew
            ? '  Admin created: drms2027@gmail.com (password admin123, change forced on first login)'
            : '  Admin already present: '.$user->email.' — left unchanged.');
    }
}
