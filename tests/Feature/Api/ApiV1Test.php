<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_api_login_returns_a_token_and_user(): void
    {
        $school = $this->makeSchool(['name' => 'API School']);
        $this->makeUser($school, 'school_admin', ['email' => 'api.admin@example.test']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'api.admin@example.test',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'api.admin@example.test')
            ->assertJsonPath('data.user.school.name', 'API School')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'roles']]]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_api_login_rejects_invalid_credentials_with_an_error_envelope(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'teacher', ['email' => 'api.teacher@example.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'api.teacher@example.test',
            'password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['email']]);
    }

    public function test_api_login_rejects_inactive_users(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'teacher', [
            'email' => 'api.inactive@example.test',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'api.inactive@example.test',
            'password' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_token_can_fetch_profile_and_logout(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'school_admin', ['email' => 'api.profile@example.test']);
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'api.profile@example.test');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_api_user_list_is_scoped_to_the_authenticated_school(): void
    {
        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();

        $admin = $this->makeUser($schoolA, 'school_admin', ['name' => 'Scoped Admin']);
        $this->makeUser($schoolB, 'teacher', ['name' => 'Foreign Teacher']);
        $token = $this->tokenFor($admin);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonMissing(['name' => 'Foreign Teacher']);

        $this->assertSame('Scoped Admin', $response->json('data.0.name'));
    }

    public function test_api_user_show_returns_403_for_another_schools_user(): void
    {
        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();

        $admin = $this->makeUser($schoolA, 'school_admin');
        $foreign = $this->makeUser($schoolB, 'teacher');
        $token = $this->tokenFor($admin);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/users/'.$foreign->id)
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_api_dashboard_requires_permission(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $token = $this->tokenFor($teacher);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.students', 0);
    }

    public function test_api_school_profile_is_resolved_from_the_authenticated_account(): void
    {
        $school = $this->makeSchool(['name' => 'Canonical API School']);
        $admin = $this->makeUser($school, 'school_admin');
        $token = $this->tokenFor($admin);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/school')
            ->assertOk()
            ->assertJsonPath('data.name', 'Canonical API School');
    }

    public function test_api_blocks_temporary_password_accounts_until_password_is_changed(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'school_admin', [
            'email' => 'api.temporary@example.test',
            'must_change_password' => true,
        ]);
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'password_change_required');

        $user->forceFill(['must_change_password' => false])->save();
        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard')
            ->assertOk();
    }

    public function test_api_user_creation_uses_server_side_school_resolution(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $token = $this->tokenFor($admin);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/users', [
                'name' => 'API Created Teacher',
                'mobile_number' => '01744000061',
                'email' => 'api.created@example.test',
                'role' => 'teacher',
                'password' => 'Secret@123',
                'password_confirmation' => 'Secret@123',
                'school_id' => $otherSchool->id,
            ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $created = User::query()->where('email', 'api.created@example.test')->firstOrFail();
        $this->assertSame($school->id, $created->school_id);
        $this->assertSame('01744000061', $created->mobile_number);
    }

    private function tokenFor(User $user): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();

        return (string) $response->json('data.token');
    }
}
