<?php

namespace Tests\Feature\Console;

use App\Models\School;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_command_bootstraps_the_school_and_first_admin(): void
    {
        $this->artisan('school:admin', [
            'email' => 'Head@Example.test',
            '--name' => 'Head Teacher',
            '--password' => 'Secure@123',
            '--school-name' => 'Example Model School',
            '--school-code' => 'ems',
        ])->assertSuccessful();

        $school = School::query()->sole();

        $this->assertSame('Example Model School', $school->name);
        $this->assertSame('EMS', $school->code);

        $user = User::query()->where('email', 'head@example.test')->sole();

        $this->assertSame($school->id, $user->school_id);
        $this->assertTrue($user->hasRole('school_admin'));
        $this->assertTrue($user->must_change_password);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.created',
            'school_id' => $school->id,
        ]);
    }

    public function test_the_command_reuses_an_existing_school(): void
    {
        $school = School::factory()->create(['name' => 'Existing School']);

        $this->artisan('school:admin', [
            'email' => 'admin2@example.test',
            '--password' => 'Secure@123',
        ])->assertSuccessful();

        $this->assertSame(1, School::query()->count());
        $this->assertSame($school->id, User::query()->where('email', 'admin2@example.test')->sole()->school_id);
    }

    public function test_duplicate_emails_are_rejected(): void
    {
        $school = School::factory()->create();
        User::factory()->forSchool($school)->withRole('teacher')->create(['email' => 'taken@example.test']);

        $this->artisan('school:admin', [
            'email' => 'taken@example.test',
            '--password' => 'Secure@123',
        ])->assertFailed();
    }
}
