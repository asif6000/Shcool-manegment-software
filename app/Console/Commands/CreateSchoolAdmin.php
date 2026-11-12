<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSchoolAdmin extends Command
{
    protected $signature = 'school:admin
        {email : Email address for the administrator}
        {--name= : Display name}
        {--password= : Password; a random password is generated when omitted}
        {--school-name= : School name, used only when no school exists yet}
        {--school-code= : Short school code, used only when no school exists yet}';

    protected $description = 'Create a School Admin account for this single-school installation';

    public function handle(AuditLogger $auditLogger): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->error('An account with that email already exists.');

            return self::FAILURE;
        }

        $school = School::query()->first();

        if ($school === null) {
            $schoolName = (string) ($this->option('school-name') ?: $this->ask('School name'));
            $schoolCode = (string) ($this->option('school-code') ?: $this->ask('Short school code (e.g. ENS)'));

            if (trim($schoolName) === '' || trim($schoolCode) === '') {
                $this->error('A school name and code are required for the first installation.');

                return self::FAILURE;
            }

            $school = School::query()->create([
                'name' => trim($schoolName),
                'code' => Str::upper(trim($schoolCode)),
                'slug' => Str::slug($schoolName),
                'timezone' => 'Asia/Dhaka',
                'currency' => 'BDT',
                'locale' => 'en',
                'is_active' => true,
            ]);
        }

        $password = (string) ($this->option('password') ?: Str::password(12));
        $generated = $this->option('password') === null;
        $name = (string) ($this->option('name') ?: Str::headline(Str::before($email, '@')));

        DB::transaction(function () use ($email, $name, $password, $school, $auditLogger): void {
            $user = new User;
            $user->fill(['name' => $name, 'email' => $email, 'is_active' => true]);
            $user->school_id = $school->id;
            $user->password = $password;
            $user->must_change_password = true;
            $user->email_verified_at = now();
            $user->save();

            $user->syncRoles(['school_admin']);

            $auditLogger->log(
                event: 'user.created',
                auditable: $user,
                description: __('Initial school administrator :name was created', ['name' => $user->name]),
                newValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'school_admin',
                    'school_id' => $school->id,
                ],
                schoolId: $school->id,
            );
        });

        $this->info("School admin ready for {$school->name}.");
        $this->line("Email: {$email}");

        if ($generated) {
            $this->warn("Temporary password: {$password}");
            $this->line('This password is shown once. Store it securely.');
        }

        $this->line('The password must be changed at first sign-in.');

        return self::SUCCESS;
    }
}
