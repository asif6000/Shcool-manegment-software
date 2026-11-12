<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class AdmissionApplicationTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guests_are_redirected_from_the_application_workspace(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $this->get(route('applicant.dashboard'))->assertRedirect(route('login'));
        $this->get(route('applicant.apply'))->assertRedirect(route('login'));
        $this->post(route('applicant.apply.store'), $this->applicationPayload())
            ->assertRedirect(route('login'));

        $this->assertSame(0, Admission::query()->count());
    }

    public function test_anonymous_json_submissions_are_refused(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $this->postJson(route('applicant.apply.store'), $this->applicationPayload())
            ->assertStatus(401);

        $this->assertSame(0, Admission::query()->count());
    }

    public function test_signup_creates_a_student_account_and_signs_the_applicant_in(): void
    {
        $school = $this->makeSchool();

        $this->post(route('register.store'), [
            'name' => 'Rifat Chowdhury',
            'mobile_number' => '01712345678',
            'password' => 'Applicant2026',
            'password_confirmation' => 'Applicant2026',
        ])
            ->assertRedirect(route('applicant.apply'))
            ->assertSessionHasNoErrors();

        $applicant = User::query()->where('mobile_number', '01712345678')->firstOrFail();

        $this->assertSame($school->id, $applicant->school_id);
        $this->assertTrue($applicant->hasRole('student'));
        $this->assertNull($applicant->email);
        $this->assertNotSame('Applicant2026', $applicant->password);
        $this->assertTrue(Hash::check('Applicant2026', $applicant->password));
        $this->assertFalse($applicant->must_change_password);
        $this->assertAuthenticatedAs($applicant);
        $this->assertSame(route('applicant.dashboard'), route($applicant->homeRouteName()));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.registered',
            'user_id' => $applicant->id,
            'school_id' => $school->id,
        ]);
    }

    public function test_signup_requires_no_email_address(): void
    {
        $this->makeSchool();

        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee('name="email"', false);

        $this->post(route('register.store'), [
            'name' => 'No Email Needed',
            'mobile_number' => '01812345678',
            'password' => 'Applicant2026',
            'password_confirmation' => 'Applicant2026',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['mobile_number' => '01812345678', 'email' => 'no.email@example.test']);
    }

    public function test_signup_refuses_weak_passwords_and_taken_mobile_numbers(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'student', ['mobile_number' => '01987654321']);

        $this->post(route('register.store'), [
            'name' => 'Only Letters',
            'mobile_number' => '01711122233',
            'password' => 'onlyletterpassword',
            'password_confirmation' => 'onlyletterpassword',
        ])->assertSessionHasErrors('password');

        $this->post(route('register.store'), [
            'name' => 'Second Try',
            'mobile_number' => '01987654321',
            'password' => 'Applicant2026',
            'password_confirmation' => 'Applicant2026',
        ])->assertSessionHasErrors('mobile_number');

        $this->assertGuest();
        $this->assertSame(1, User::query()->count());
    }

    public function test_signup_is_rate_limited(): void
    {
        $this->makeSchool();

        // A school office signs its families up from one shared connection, so
        // the per-address cap has to cover a minute of real applicants rather
        // than three. Each attempt here uses its own mobile number so the only
        // bucket being filled is the address one, and sends no password so
        // that nobody is actually created or signed in.
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('register.store'), [
                'name' => 'Attempt '.$attempt,
                'mobile_number' => '0171112220'.$attempt,
            ])->assertRedirect();
        }

        $this->post(route('register.store'), [
            'name' => 'Seventh attempt',
            'mobile_number' => '01711122207',
        ])->assertStatus(429);

        $this->assertSame(0, User::query()->count());
    }

    public function test_the_application_form_is_protected_by_a_csrf_token(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);
        $applicant = $this->applicant($school);

        $this->actingAs($applicant)
            ->get(route('applicant.apply'))
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_a_signed_in_applicant_submits_one_pending_application(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);
        $applicant = $this->applicant($school);

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^APP-2026-\d{4}$/', $admission->application_no);
        $this->assertSame(Admission::PENDING, $admission->status);
        $this->assertSame($applicant->id, $admission->user_id);
        $this->assertSame($school->id, (int) $admission->school_id);
        $this->assertSame('Sadia', $admission->first_name);
        $this->assertNull($admission->student_id);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'admission.created',
            'school_id' => $school->id,
            'user_id' => $applicant->id,
        ]);

        $this->actingAs($applicant)
            ->get(route('applicant.dashboard'))
            ->assertOk()
            ->assertSee($admission->application_no);

        $this->actingAs($applicant)
            ->get(route('applicant.show', $admission))
            ->assertOk()
            ->assertSee($admission->application_no)
            ->assertSee(__(ucfirst(Admission::PENDING)));
    }

    public function test_the_account_is_never_taken_from_the_request_payload(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);
        $applicant = $this->applicant($school);
        $other = $this->applicant($school, ['email' => 'other@example.test']);

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), [
                ...$this->applicationPayload(),
                'user_id' => $other->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($applicant->id, Admission::query()->firstOrFail()->user_id);
    }

    public function test_a_second_application_is_blocked_until_the_first_is_decided(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);
        $applicant = $this->applicant($school);
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), $this->applicationPayload(['first_name' => 'Again']))
            ->assertSessionHasErrors('academic_session_id');

        $this->assertSame(1, Admission::query()->count());

        $this->actingAs($admin)
            ->post(route('admissions.reject', $admission), ['reason' => 'Documents incomplete.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), $this->applicationPayload(['first_name' => 'Replied']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Admission::query()->count());
        $this->assertSame(Admission::PENDING, Admission::query()->latest('id')->firstOrFail()->status);
    }

    public function test_an_enrolled_student_is_sent_to_the_portal_instead_of_the_form(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $user = $this->applicant($school, ['email' => 'enrolled@example.test']);
        Student::factory()->create()->forceFill(['user_id' => $user->id])->save();

        $this->assertSame(route('student.dashboard'), route($user->homeRouteName()));

        $this->actingAs($user)
            ->get(route('applicant.apply'))
            ->assertRedirect(route('student.dashboard'));

        $this->actingAs($user)
            ->post(route('applicant.apply.store'), $this->applicationPayload())
            ->assertSessionHasErrors('admission');

        $this->assertSame(0, Admission::query()->count());
    }

    public function test_a_pending_applicant_reaches_the_portal_no_further_than_the_application_desk(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);
        $applicant = $this->applicant($school);

        $this->actingAs($applicant)
            ->get(route('student.dashboard'))
            ->assertRedirect(route('applicant.dashboard'));
    }

    public function test_staff_accounts_cannot_enter_the_applicant_workspace(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');

        // Browsers are returned to their own workspace instead of a dead-end
        // 403 page; JSON callers keep the explicit refusal.
        $this->actingAs($admin)->get(route('applicant.dashboard'))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($teacher)->get(route('applicant.apply'))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($admin)
            ->post(route('applicant.apply.store'), $this->applicationPayload())
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->getJson(route('applicant.dashboard'))
            ->assertForbidden();
        $this->actingAs($admin)
            ->postJson(route('applicant.apply.store'), $this->applicationPayload())
            ->assertForbidden();

        $this->assertSame(0, Admission::query()->count());
    }

    public function test_one_applicant_cannot_read_another_applicants_record(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $owner = $this->applicant($school, ['email' => 'owner@example.test']);
        $stranger = $this->applicant($school, ['email' => 'stranger@example.test']);

        Storage::fake('school_uploads');

        $this->actingAs($owner)
            ->post(route('applicant.apply.store'), [
                ...$this->applicationPayload(),
                'photo' => $this->photo('owner.png'),
            ])
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();

        $this->actingAs($stranger)->get(route('applicant.show', $admission))->assertNotFound();
        $this->actingAs($stranger)->get(route('applicant.photo', $admission))->assertNotFound();

        $this->actingAs($owner)->get(route('applicant.show', $admission))->assertOk();
    }

    public function test_walk_in_applications_stay_out_of_the_applicant_workspace(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $admin = $this->makeUser($school, 'school_admin');
        $applicant = $this->applicant($school);

        $this->actingAs($admin)
            ->post(route('admissions.store'), $this->applicationPayload(['first_name' => 'Walkin']))
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();

        $this->assertNull($admission->user_id);
        $this->assertMatchesRegularExpression('/^APP-2026-\d{4}$/', $admission->application_no);

        $this->actingAs($applicant)->get(route('applicant.show', $admission))->assertNotFound();
        $this->actingAs($applicant)->get(route('applicant.dashboard'))->assertOk()->assertDontSee($admission->application_no);
    }

    public function test_approving_an_online_application_links_the_account_and_carries_the_photo(): void
    {
        Storage::fake('school_uploads');

        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $applicant = $this->applicant($school, ['email' => 'approved@example.test']);
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), [
                ...$this->applicationPayload(),
                'photo' => $this->photo('sadia.png'),
            ])
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();

        $this->assertTrue($admission->hasPhoto());
        $this->assertStringStartsWith('admissions/'.$school->id.'/', $admission->photo_path);
        Storage::disk('school_uploads')->assertExists($admission->photo_path);

        $this->actingAs($applicant)
            ->get(route('applicant.photo', $admission))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($admin)
            ->post(route('admissions.approve', $admission), ['section_id' => $this->section->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $student = Student::query()->firstOrFail();

        $this->assertSame($applicant->id, $student->user_id);
        $this->assertMatchesRegularExpression('/^ADM-2026-\d{4}$/', $student->admission_no);
        $this->assertStringStartsWith('students/'.$school->id.'/', (string) $student->photo_path);
        Storage::disk('school_uploads')->assertExists((string) $student->photo_path);

        $admission->refresh();
        $this->assertSame(Admission::APPROVED, $admission->status);
        $this->assertSame($student->id, $admission->student_id);

        $this->assertSame(1, $applicant->notifications()->count());
        $this->assertSame('admission', $applicant->notifications()->firstOrFail()->category);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'student.account_linked',
            'user_id' => $admin->id,
        ]);

        $this->assertSame(route('student.dashboard'), route($applicant->fresh()->homeRouteName()));
        $this->actingAs($applicant)->get(route('student.dashboard'))->assertOk();
        $this->actingAs($applicant)->get(route('applicant.dashboard'))->assertOk();

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), $this->applicationPayload())
            ->assertSessionHasErrors('admission');
    }

    public function test_the_admission_list_offers_an_approve_action_that_opens_the_student_portal(): void
    {
        Storage::fake('school_uploads');

        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $applicant = $this->applicant($school, ['mobile_number' => '01744000031']);
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admissions.index'))
            ->assertOk()
            ->assertSee('id="statusModal'.$admission->id.'"', false)
            ->assertSee(route('admissions.status', $admission), false)
            ->assertSee('data-status-preset="approved"', false)
            ->assertSee('value="approved"', false);

        $this->actingAs($admin)
            ->post(route('admissions.status', $admission), [
                'status' => 'approved',
                'section_id' => $this->section->id,
                'status_for' => $admission->id,
            ])
            ->assertSessionHasNoErrors();

        $student = Student::query()->firstOrFail();

        $this->assertSame($applicant->id, $student->user_id);
        $this->assertSame(route('student.dashboard'), route($applicant->fresh()->homeRouteName()));
        $this->actingAs($applicant)->get(route('student.dashboard'))->assertOk();

        $this->post(route('logout'))->assertRedirect();

        $this->post(route('login.store'), [
            'role' => 'student',
            'mobile_number' => '01744000031',
            'password' => 'password',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('student.dashboard'));

        $this->get(route('student.dashboard'))->assertOk()->assertSee('Sadia');

        $this->actingAs($admin)
            ->get(route('admissions.index'))
            ->assertOk()
            ->assertDontSee('data-status-preset="approved"', false);
    }

    public function test_the_approve_action_is_hidden_when_the_class_has_no_sections(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();
        $this->section->delete();

        $this->actingAs($admin)
            ->get(route('admissions.index'))
            ->assertOk()
            ->assertDontSee('data-status-preset="approved"', false)
            ->assertSee(__('Add a section to the applied class before approving.'));
    }

    public function test_a_missing_section_can_be_added_from_the_approval_dialog(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();
        $this->section->delete();

        $this->actingAs($admin)
            ->get(route('admissions.index'))
            ->assertOk()
            ->assertSee('id="addSectionForm'.$admission->id.'"', false)
            ->assertSee(route('admissions.sections.store', $admission), false)
            ->assertSee(__('Add section'));

        $this->actingAs($admin)
            ->from(route('admissions.index'))
            ->post(route('admissions.sections.store', $admission), [
                'name' => 'A',
                'capacity' => 35,
                'status' => 'approved',
                'status_for' => $admission->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admissions.index'))
            ->assertSessionHas('reopen_status', $admission->id);

        $section = Section::query()->where('class_id', $this->class->id)->firstOrFail();

        $this->assertSame('A', $section->name);
        $this->assertSame(35, $section->capacity);
        $this->assertDatabaseHas('audit_logs', ['event' => 'academic.section_created']);

        $this->actingAs($admin)
            ->post(route('admissions.status', $admission), [
                'status' => 'approved',
                'section_id' => $section->id,
                'status_for' => $admission->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Admission::APPROVED, $admission->fresh()->status);
    }

    public function test_adding_a_section_from_the_dialog_requires_academic_setup_access(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher', ['email' => 'section-teacher@example.test']);

        $this->actingAs($admin)
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();
        $this->section->delete();

        $this->actingAs($teacher)
            ->post(route('admissions.sections.store', $admission), ['name' => 'B'])
            ->assertForbidden();

        $this->assertSame(0, Section::query()->where('class_id', $this->class->id)->count());
    }

    public function test_adding_a_section_from_the_dialog_uses_the_applied_class(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();

        $otherClass = SchoolClass::query()->create([
            'name' => 'Class Nine',
            'level' => 9,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admissions.sections.store', $admission), ['name' => 'A'])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('admissions.sections.store', $admission), [
                'name' => 'B',
                'class_id' => $otherClass->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Section::query()->where('class_id', $otherClass->id)->count());
        $this->assertTrue(
            Section::query()->where('class_id', $this->class->id)->where('name', 'B')->exists()
        );
    }

    public function test_the_admission_list_updates_an_application_status(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admissions.index'))
            ->assertOk()
            ->assertSee('id="statusModal'.$admission->id.'"', false)
            ->assertSee(route('admissions.status', $admission), false);

        $this->actingAs($admin)
            ->from(route('admissions.index'))
            ->post(route('admissions.status', $admission), [
                'status' => 'waitlisted',
                'status_for' => $admission->id,
            ])
            ->assertRedirect(route('admissions.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('waitlisted', $admission->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'admission.status_changed',
            'user_id' => $admin->id,
        ]);
    }

    public function test_marking_an_application_rejected_stores_the_reason_and_reopening_clears_it(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admissions.status', $admission), [
                'status' => 'rejected',
                'reason' => 'Documents missing',
            ])
            ->assertSessionHasNoErrors();

        $admission->refresh();
        $this->assertSame(Admission::REJECTED, $admission->status);
        $this->assertSame('Documents missing', $admission->rejection_reason);
        $this->assertSame($admin->id, $admission->reviewed_by);

        $this->actingAs($admin)
            ->post(route('admissions.status', $admission), ['status' => 'pending'])
            ->assertSessionHasNoErrors();

        $admission->refresh();
        $this->assertSame(Admission::PENDING, $admission->status);
        $this->assertNull($admission->rejection_reason);
        $this->assertNull($admission->reviewed_by);
        $this->assertNull($admission->reviewed_at);
    }

    public function test_the_status_action_approves_with_a_section_and_locks_the_approved_record(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admissions.status', $admission), ['status' => 'approved'])
            ->assertSessionHasErrors('section_id');

        $this->assertSame(0, Student::query()->count());

        $this->actingAs($admin)
            ->post(route('admissions.status', $admission), [
                'status' => 'approved',
                'section_id' => $this->section->id,
                'roll_no' => 7,
            ])
            ->assertSessionHasNoErrors();

        $admission->refresh();
        $this->assertSame(Admission::APPROVED, $admission->status);

        $student = Student::query()->firstOrFail();
        $this->assertSame(7, (int) $student->enrollments()->firstOrFail()->roll_no);

        $this->actingAs($admin)
            ->from(route('admissions.index'))
            ->post(route('admissions.status', $admission), ['status' => 'pending'])
            ->assertSessionHasErrors('status');

        $this->assertSame(Admission::APPROVED, $admission->fresh()->status);
    }

    public function test_only_admissions_managers_can_change_a_status(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher', ['email' => 'teacher@example.test']);

        $this->actingAs($admin)
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();

        $this->actingAs($teacher)
            ->post(route('admissions.status', $admission), ['status' => 'waitlisted'])
            ->assertForbidden();

        $this->assertSame(Admission::PENDING, $admission->fresh()->status);
    }

    public function test_rejection_publishes_only_the_reason_and_not_the_internal_notes(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);

        $applicant = $this->applicant($school, ['email' => 'rejected@example.test']);
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $admission = Admission::query()->firstOrFail();
        $admission->forceFill(['notes' => 'Private office note about seats'])->save();

        $this->actingAs($admin)
            ->post(route('admissions.reject', $admission), ['reason' => 'The birth certificate is unreadable.'])
            ->assertSessionHasNoErrors();

        $admission->refresh();

        $this->assertSame(Admission::REJECTED, $admission->status);
        $this->assertSame('The birth certificate is unreadable.', $admission->rejection_reason);
        $this->assertSame('Private office note about seats', $admission->notes);
        $this->assertSame($admin->id, $admission->reviewed_by);
        $this->assertNotNull($admission->reviewed_at);
        $this->assertSame(0, Student::query()->count());

        $this->assertSame(1, $applicant->notifications()->count());
        $this->assertSame('admission', $applicant->notifications()->firstOrFail()->category);

        $this->actingAs($applicant)
            ->get(route('applicant.show', $admission))
            ->assertOk()
            ->assertSee('The birth certificate is unreadable.')
            ->assertDontSee('Private office note about seats');

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Admission::query()->count());
    }

    public function test_unsafe_photos_are_refused(): void
    {
        Storage::fake('school_uploads');

        $school = $this->makeSchool();
        $this->fixtures($school->id);
        $applicant = $this->applicant($school);

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), [
                ...$this->applicationPayload(),
                'photo' => UploadedFile::fake()->createWithContent('payload.txt', '<?php echo 1; ?>'),
            ])
            ->assertSessionHasErrors('photo');

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), [
                ...$this->applicationPayload(),
                'photo' => $this->photo('stamp.png', 60, 60),
            ])
            ->assertSessionHasErrors(['photo' => 'The photo must be between 100x100 and 4000x4000 pixels.']);

        $this->assertSame(0, Admission::query()->count());
    }

    public function test_admission_requests_are_validated_on_the_server(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);
        $applicant = $this->applicant($school);

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), $this->applicationPayload([
                'date_of_birth' => now()->subYears(2)->toDateString(),
                'guardian_phone' => 'call me later',
                'gender' => 'unknown',
                'academic_session_id' => 9999,
            ]))
            ->assertSessionHasErrors([
                'date_of_birth',
                'guardian_phone',
                'gender',
                'academic_session_id',
            ]);

        $this->assertSame(0, Admission::query()->count());
    }

    public function test_application_submissions_are_throttled_per_account(): void
    {
        $school = $this->makeSchool();
        $this->fixtures($school->id);
        $applicant = $this->applicant($school);

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), $this->applicationPayload())
            ->assertSessionHasNoErrors();

        for ($attempt = 2; $attempt <= 5; $attempt++) {
            $this->actingAs($applicant)
                ->post(route('applicant.apply.store'), $this->applicationPayload())
                ->assertSessionHasErrors('academic_session_id');
        }

        $this->actingAs($applicant)
            ->post(route('applicant.apply.store'), $this->applicationPayload())
            ->assertStatus(429);

        $this->assertSame(1, Admission::query()->count());
    }

    private function fixtures(int $schoolId): void
    {
        $this->useSchoolContext($schoolId);

        $this->session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::query()->create([
            'name' => 'Class Six',
            'level' => 6,
            'is_active' => true,
        ]);

        $this->section = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);
    }

    private function applicant(School $school, array $overrides = []): User
    {
        return $this->makeUser($school, 'student', [
            'email' => 'applicant@example.test',
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function applicationPayload(array $overrides = []): array
    {
        return [
            'academic_session_id' => $this->session->id,
            'applied_class_id' => $this->class->id,
            'first_name' => 'Sadia',
            'last_name' => 'Islam',
            'gender' => 'female',
            'date_of_birth' => '2014-04-19',
            'guardian_name' => 'Shahid Islam',
            'guardian_phone' => '01912345678',
            'guardian_email' => 'guardian@example.test',
            'address' => 'House 12, Road 4, Dhanmondi, Dhaka',
            ...$overrides,
        ];
    }

    /**
     * A real PNG built without GD: the upload service sniffs the file contents,
     * so a faked mime type would not prove anything.
     */
    private function photo(string $name, int $width = 200, int $height = 200): UploadedFile
    {
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data))
            .$type.$data.pack('N', crc32($type.$data));

        $scanline = str_repeat("\x00", 1 + $width * 3);

        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('N2', $width, $height).chr(8).chr(2).chr(0).chr(0).chr(0))
            .$chunk('IDAT', gzcompress(str_repeat($scanline, $height)))
            .$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
