<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AttendanceEvent;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentAttendanceCard;
use App\Models\Teacher;
use App\Models\User;
use App\Services\StudentAttendanceCardService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class AttendanceQrTest extends TestCase
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

        // A scan always records today, and the service rightly refuses on a
        // closed day, so the clock is moved onto the next working day. Without
        // this the suite only passes on part of the week.
        $working = (array) config('attendance.working_days', [1, 2, 3, 4, 5]);
        $day = Carbon::now()->startOfDay();

        while (! in_array(ClassRoutine::dayIndexOf($day), $working, true)) {
            $day->addDay();
        }

        $this->travelTo($day);
    }

    public function test_a_card_is_issued_with_a_hashed_token_and_prints_once(): void
    {
        $student = $this->student();

        $response = $this->actingAs($this->admin)
            ->post(route('attendance.cards.issue', $student))
            ->assertRedirect(route('attendance.cards.print', $student));

        $token = session('attendance_card_token');

        $this->assertIsString($token);
        $this->assertMatchesRegularExpression('/^atn1_[a-f0-9]{64}$/', $token);

        $card = StudentAttendanceCard::query()->firstOrFail();
        $this->assertSame(hash('sha256', $token), $card->token_hash);
        $this->assertNotSame($token, $card->token_hash);
        $this->assertNull($card->revoked_at);

        $response->assertSessionHas('success');

        $this->withSession(['attendance_card_token' => $token])
            ->actingAs($this->admin)
            ->get(route('attendance.cards.print', $student))
            ->assertOk()
            ->assertSee('Scan for attendance')
            ->assertSee($token)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_a_scan_marks_only_today_and_never_duplicates(): void
    {
        $student = $this->student();
        $token = $this->token($student);

        $first = $this->actingAs($this->admin)
            ->postJson(route('attendance.qr.scan'), ['token' => $token])
            ->assertOk()
            ->assertJson(['status' => 'applied']);

        $this->assertStringContainsString($student->full_name, $first->json('message'));

        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $student->id,
            'status' => 'present',
            'source' => 'qr',
        ]);

        $event = AttendanceEvent::query()->firstOrFail();
        $this->assertSame('applied', $event->status);
        $this->assertSame('qr', $event->source);
        $this->assertNotNull($event->student_attendance_id);

        $this->actingAs($this->admin)
            ->postJson(route('attendance.qr.scan'), ['token' => $token])
            ->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, StudentAttendance::query()->count());
        $this->assertSame(2, AttendanceEvent::query()->count());
        $this->assertSame('duplicate', AttendanceEvent::query()->latest('id')->first()->status);
    }

    public function test_an_unknown_or_revoked_card_is_refused(): void
    {
        $student = $this->student();
        $issued = $this->actingAs($this->admin)->post(route('attendance.cards.issue', $student));
        $token = session('attendance_card_token');

        $this->actingAs($this->admin)
            ->postJson(route('attendance.qr.scan'), ['token' => 'atn1_'.str_repeat('0', 64)])
            ->assertStatus(422)
            ->assertJson(['status' => 'rejected']);

        $card = StudentAttendanceCard::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('attendance.cards.revoke', $card), ['reason' => 'Lost card'])
            ->assertRedirect();

        $this->assertNotNull($card->fresh()->revoked_at);

        $this->actingAs($this->admin)
            ->postJson(route('attendance.qr.scan'), ['token' => $token])
            ->assertStatus(422)
            ->assertJson(['status' => 'rejected']);

        $this->assertSame(0, StudentAttendance::query()->count());
    }

    public function test_reissuing_a_card_invalidates_the_old_code(): void
    {
        $student = $this->student();

        $this->actingAs($this->admin)->post(route('attendance.cards.issue', $student));
        $old = session('attendance_card_token');

        $this->actingAs($this->admin)->post(route('attendance.cards.issue', $student));
        $new = session('attendance_card_token');

        $this->assertNotSame($old, $new);
        $this->assertSame(2, StudentAttendanceCard::query()->count());
        $this->assertSame(1, StudentAttendanceCard::query()->whereNull('revoked_at')->count());

        $this->actingAs($this->admin)
            ->postJson(route('attendance.qr.scan'), ['token' => $old])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->postJson(route('attendance.qr.scan'), ['token' => $new])
            ->assertOk()
            ->assertJson(['status' => 'applied']);
    }

    public function test_a_card_from_another_school_cannot_be_scanned(): void
    {
        $other = $this->makeSchool();

        $this->useSchoolContext($other->id);
        $otherStudent = Student::factory()->create(['first_name' => 'Zara', 'last_name' => 'Karim']);
        $otherEnrollment = Enrollment::query()->create([
            'student_id' => $otherStudent->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 2,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);
        $otherToken = app(StudentAttendanceCardService::class)->issue($otherStudent, $this->makeUser($other, 'school_admin'))['token'];

        $this->useSchoolContext($this->school->id);

        $this->actingAs($this->admin)
            ->postJson(route('attendance.qr.scan'), ['token' => $otherToken])
            ->assertStatus(422)
            ->assertJson(['status' => 'rejected']);

        $this->assertSame(0, StudentAttendance::query()->where('student_id', $otherStudent->id)->count());
        $this->assertNotNull($otherEnrollment);
    }

    public function test_a_teacher_cannot_scan_a_student_outside_their_timetable(): void
    {
        $student = $this->student();
        $token = $this->token($student);

        $teacher = $this->makeUser($this->school, 'teacher');
        Teacher::factory()->create(['user_id' => $teacher->id]);

        $response = $this->actingAs($teacher)
            ->postJson(route('attendance.qr.scan'), ['token' => $token])
            ->assertOk()
            ->assertJson(['status' => 'rejected']);

        $this->assertStringContainsString('not in your assigned classes', $response->json('message'));
        $this->assertSame(0, StudentAttendance::query()->count());
        $this->assertSame('rejected', AttendanceEvent::query()->firstOrFail()->status);
        $this->assertSame('out_of_scope', AttendanceEvent::query()->firstOrFail()->reason);
    }

    public function test_the_manual_fallback_redirects_with_a_flash_message(): void
    {
        $student = $this->student();
        $token = $this->token($student);

        $this->actingAs($this->admin)
            ->post(route('attendance.qr.scan'), ['token' => $token])
            ->assertRedirect(route('attendance.qr.index'))
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post(route('attendance.qr.scan'), ['token' => 'not-a-card'])
            ->assertRedirect(route('attendance.qr.index'))
            ->assertSessionHas('warning');
    }

    public function test_the_scanner_page_and_events_stay_inside_the_school(): void
    {
        $student = $this->student();

        $this->actingAs($this->admin)
            ->get(route('attendance.qr.index'))
            ->assertOk()
            ->assertSee('Scan a card')
            ->assertSee('ID cards');

        $this->actingAs($this->admin)
            ->get(route('attendance.qr.index', ['class_id' => $this->class->id]))
            ->assertOk()
            ->assertSee($student->full_name);

        $other = $this->makeSchool();
        $stranger = $this->makeUser($other, 'school_admin');

        $this->actingAs($stranger)
            ->get(route('attendance.qr.index'))
            ->assertOk()
            ->assertDontSee($student->full_name);
    }

    private function student(string $first = 'Ayesha', string $last = 'Rahman'): Student
    {
        $student = Student::factory()->create(['first_name' => $first, 'last_name' => $last]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        return $student;
    }

    private function token(Student $student): string
    {
        return app(StudentAttendanceCardService::class)->issue($student, $this->admin)['token'];
    }
}
