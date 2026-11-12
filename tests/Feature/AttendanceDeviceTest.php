<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUser;
use App\Models\AttendanceEvent;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesSchoolData;
use Tests\Fixtures\Adapters\FakeFingerprintAdapter;
use Tests\TestCase;

/**
 * The fingerprint assertions run against a clearly labelled test adapter.
 * Nothing here contacts hardware; the NOT_CONFIGURED tests describe the state
 * of a real installation without a contracted provider.
 */
class AttendanceDeviceTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-28 09:00:00'));

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->admin = $this->makeUser($this->school, 'school_admin');

        $this->useSchoolContext($this->school->id);

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_terminal_without_an_adapter_refuses_every_event(): void
    {
        [$device, $secret] = $this->device();

        $this->assertSame(AttendanceDevice::STATUS_NOT_CONFIGURED, $device->fresh()->status);
        $this->assertSame(hash('sha256', $secret), $device->fresh()->secret_hash);
        $this->assertNotSame($secret, $device->fresh()->secret_hash);

        $this->ingest($device, $secret, [$this->event('evt-1', '1001')])
            ->assertStatus(503)
            ->assertJson(['status' => 'not_configured']);

        $this->assertSame(0, AttendanceEvent::query()->count());
        $this->assertSame(0, StudentAttendance::query()->count());
    }

    public function test_invalid_credentials_are_refused_and_rotation_invalidates_the_old_secret(): void
    {
        [$device, $secret] = $this->device();

        $this->ingest($device, 'wrong-secret', [$this->event('evt-1', '1001')])
            ->assertStatus(401)
            ->assertJson(['status' => 'unauthenticated']);

        $this->actingAs($this->admin)
            ->post(route('attendance.methods.devices.rotate', $device))
            ->assertRedirect()
            ->assertSessionHas('device_secret');

        $newSecret = session('device_secret')['secret'];
        $this->assertNotSame($secret, $newSecret);

        $this->ingest($device, $secret, [$this->event('evt-1', '1001')])->assertStatus(401);
        $this->ingest($device, $newSecret, [$this->event('evt-1', '1001')])->assertStatus(503);
    }

    public function test_a_mapped_punch_marks_attendance_once_and_retries_stay_idempotent(): void
    {
        $this->configureFakeAdapter();

        [$device, $secret] = $this->device();
        [$student] = $this->student();

        $this->actingAs($this->admin)
            ->post(route('attendance.methods.devices.map', $device), [
                'device_user_id' => '1001',
                'student_id' => $student->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $first = $this->ingest($device, $secret, [$this->event('evt-1', '1001')])
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        $this->assertSame('applied', $first->json('results.0.status'));

        $attendance = StudentAttendance::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('present', $attendance->status);
        $this->assertSame('fingerprint', $attendance->source);
        $this->assertSame(1, AttendanceEvent::query()->count());

        $device->refresh();
        $this->assertSame(AttendanceDevice::STATUS_READY, $device->status);
        $this->assertNotNull($device->last_synced_at);

        // Same external reference: a provider retry changes nothing.
        $this->ingest($device, $secret, [$this->event('evt-1', '1001')])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'applied');

        $this->assertSame(1, AttendanceEvent::query()->count());
        $this->assertSame(1, StudentAttendance::query()->count());

        // A fresh punch on the same day is recorded but does not re-mark.
        $this->ingest($device, $secret, [$this->event('evt-2', '1001', now()->addMinutes(1))])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'duplicate');

        $this->assertSame(2, AttendanceEvent::query()->count());
        $this->assertSame(1, StudentAttendance::query()->count());
    }

    public function test_delayed_and_skewed_punches_are_refused(): void
    {
        $this->configureFakeAdapter();

        [$device, $secret] = $this->device();
        [$student] = $this->student();
        $this->map($device, '1001', $student);

        $stale = $this->ingest($device, $secret, [$this->event('evt-old', '1001', now()->subDays(5))])
            ->assertOk();

        $this->assertSame('stale_event', $stale->json('results.0.reason'));

        $skewed = $this->ingest($device, $secret, [$this->event('evt-ahead', '1001', now()->addHour())])
            ->assertOk();

        $this->assertSame('clock_skew', $skewed->json('results.0.reason'));

        $this->assertSame(0, StudentAttendance::query()->count());
        $this->assertSame(2, AttendanceEvent::query()->count());
    }

    public function test_a_corrected_or_leave_record_is_never_overwritten(): void
    {
        $this->configureFakeAdapter();

        [$device, $secret] = $this->device();
        [$lockedStudent, $lockedEnrollment] = $this->student('Corrected', 'Student');
        [$onLeave, $leaveEnrollment] = $this->student('Leave', 'Student');

        StudentAttendance::query()->create([
            'student_id' => $lockedStudent->id,
            'enrollment_id' => $lockedEnrollment->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'absent',
            'source' => 'manual',
            'locked_at' => now(),
            'correction_reason' => 'Parent confirmed absence',
        ]);

        StudentAttendance::query()->create([
            'student_id' => $onLeave->id,
            'enrollment_id' => $leaveEnrollment->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'leave',
            'source' => 'manual',
        ]);

        $this->map($device, '1001', $lockedStudent);
        $this->map($device, '1002', $onLeave);

        $response = $this->ingest($device, $secret, [
            $this->event('evt-locked', '1001'),
            $this->event('evt-leave', '1002'),
        ])->assertOk();

        $this->assertSame('skipped', $response->json('results.0.status'));
        $this->assertSame('locked', $response->json('results.0.reason'));
        $this->assertSame('skipped', $response->json('results.1.status'));
        $this->assertSame('protected_status', $response->json('results.1.reason'));

        $this->assertSame('absent', StudentAttendance::query()->where('student_id', $lockedStudent->id)->firstOrFail()->status);
        $this->assertSame('leave', StudentAttendance::query()->where('student_id', $onLeave->id)->firstOrFail()->status);
    }

    public function test_an_unmapped_device_user_is_recorded_as_rejected_and_never_guessed(): void
    {
        $this->configureFakeAdapter();

        [$device, $secret] = $this->device();
        $this->student();

        $response = $this->ingest($device, $secret, [$this->event('evt-unknown', '9999')])
            ->assertOk();

        $this->assertSame('rejected', $response->json('results.0.status'));
        $this->assertSame('unknown_device_user', $response->json('results.0.reason'));

        $event = AttendanceEvent::query()->firstOrFail();
        $this->assertNull($event->student_id);
        $this->assertSame('9999', $event->device_user_id);
        $this->assertSame(0, StudentAttendance::query()->count());
    }

    public function test_the_event_payload_is_validated(): void
    {
        $this->configureFakeAdapter();

        [$device, $secret] = $this->device();

        $this->ingest($device, $secret, [])
            ->assertStatus(422)
            ->assertJson(['status' => 'invalid']);

        $this->ingest($device, $secret, [['external_ref' => 'evt-1']])
            ->assertStatus(422);

        $tooMany = collect(range(1, 101))
            ->map(fn (int $i): array => $this->event('evt-'.$i, '1001'))
            ->all();

        $this->ingest($device, $secret, $tooMany)->assertStatus(422);

        $this->assertSame(0, AttendanceEvent::query()->count());
    }

    public function test_face_stays_not_configured_even_when_fingerprint_has_an_adapter(): void
    {
        $this->configureFakeAdapter();

        [$device, $secret] = $this->device('face');

        $this->assertSame('face', $device->kind);

        $this->ingest($device, $secret, [$this->event('evt-1', '1001')])
            ->assertStatus(503)
            ->assertJson(['status' => 'not_configured']);

        $this->assertSame(AttendanceDevice::STATUS_NOT_CONFIGURED, $device->fresh()->status);
    }

    public function test_the_methods_page_reports_honest_states_and_is_school_scoped(): void
    {
        [$device] = $this->device();

        $this->actingAs($this->admin)
            ->get(route('attendance.methods.index'))
            ->assertOk()
            ->assertSee('Attendance methods')
            ->assertSee($device->name)
            ->assertSee('No provider has been selected.')
            ->assertSee('Face recognition safeguards')
            ->assertSee('Written consent');

        $this->configureFakeAdapter();

        $this->actingAs($this->admin)
            ->get(route('attendance.methods.index'))
            ->assertOk()
            ->assertSee('A driver is registered on the server.');

        $other = $this->makeSchool();
        $stranger = $this->makeUser($other, 'school_admin');

        $this->actingAs($stranger)
            ->get(route('attendance.methods.index'))
            ->assertOk()
            ->assertDontSee($device->name);

        $teacher = $this->makeUser($this->school, 'teacher');

        $this->actingAs($teacher)
            ->get(route('attendance.methods.index'))
            ->assertOk()
            ->assertDontSee('Terminal name');
    }

    public function test_mappings_are_unique_and_protected_across_schools(): void
    {
        [$device] = $this->device();
        [$first] = $this->student();

        $this->map($device, '1001', $first);

        $this->actingAs($this->admin)
            ->post(route('attendance.methods.devices.map', $device), [
                'device_user_id' => '2002',
                'student_id' => $first->id,
            ])
            ->assertSessionHasErrors('device_user_id');

        $this->assertSame(1, AttendanceDeviceUser::query()->count());

        $mapping = AttendanceDeviceUser::query()->firstOrFail();

        $other = $this->makeSchool();
        $stranger = $this->makeUser($other, 'school_admin');

        $this->actingAs($stranger)
            ->delete(route('attendance.methods.devices.unmap', [$device, $mapping]))
            ->assertNotFound();

        $this->useSchoolContext($this->school->id);

        $this->assertSame(1, AttendanceDeviceUser::query()->count());
    }

    private function configureFakeAdapter(): void
    {
        config([
            'attendance.devices.drivers.fingerprint' => 'fake-fingerprint',
            'attendance.devices.adapters.fake-fingerprint' => FakeFingerprintAdapter::class,
        ]);
    }

    /**
     * @return array{0: AttendanceDevice, 1: string}
     */
    private function device(string $kind = 'fingerprint'): array
    {
        $this->actingAs($this->admin)
            ->post(route('attendance.methods.devices.register'), [
                'name' => 'Gate Alpha',
                'kind' => $kind,
                'serial' => 'SN-001',
            ])
            ->assertRedirect(route('attendance.methods.index'))
            ->assertSessionHas('device_secret');

        $secret = session('device_secret')['secret'];

        return [AttendanceDevice::query()->latest('id')->firstOrFail(), $secret];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function ingest(AttendanceDevice $device, string $secret, array $events): TestResponse
    {
        return $this->withHeaders([
            'X-Device-Id' => (string) $device->id,
            'X-Device-Secret' => $secret,
        ])->postJson('/api/v1/attendance/events', ['events' => $events]);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $ref, string $deviceUserId, ?Carbon $at = null): array
    {
        return [
            'external_ref' => $ref,
            'user_id' => $deviceUserId,
            'occurred_at' => ($at ?? now())->toIso8601String(),
        ];
    }

    private function map(AttendanceDevice $device, string $deviceUserId, Student $student): void
    {
        $this->actingAs($this->admin)
            ->post(route('attendance.methods.devices.map', $device), [
                'device_user_id' => $deviceUserId,
                'student_id' => $student->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    /**
     * @return array{0: Student, 1: Enrollment}
     */
    private function student(string $first = 'Ayesha', string $last = 'Rahman'): array
    {
        $student = Student::factory()->create(['first_name' => $first, 'last_name' => $last]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => Enrollment::query()->where('academic_session_id', $this->session->id)->count() + 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        return [$student, $enrollment];
    }
}
