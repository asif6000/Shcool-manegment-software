<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class ProfileAndLocaleTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_temporary_password_holder_is_forced_to_change_password(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'teacher', ['must_change_password' => true]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('profile.password.change'));
    }

    public function test_user_can_update_profile(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'teacher');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated.profile@example.test',
                'phone' => '01700000001',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated.profile@example.test', $user->email);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'profile.updated',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_change_password_and_temporary_flag_is_cleared(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'teacher', ['must_change_password' => true]);
        $user->createToken('existing-device');

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'NewSecret@123',
                'password_confirmation' => 'NewSecret@123',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NewSecret@123', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'password_changed',
            'user_id' => $user->id,
        ]);
    }

    public function test_current_password_must_match_when_changing_password(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'teacher');

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'NewSecret@123',
                'password_confirmation' => 'NewSecret@123',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_user_can_switch_locale(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'teacher');
        $user->forceFill(['locale' => 'en'])->save();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('locale.update'), ['locale' => 'bn'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('locale', 'bn');

        $this->assertSame('bn', $user->fresh()->locale);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'teacher');

        $this->actingAs($user)
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }
}
