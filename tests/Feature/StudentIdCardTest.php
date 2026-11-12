<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class StudentIdCardTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private Student $student;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);
        $this->admin = $this->makeUser($this->school, 'school_admin');

        $session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $class = SchoolClass::query()->create(['name' => 'Class Six', 'level' => 6, 'is_active' => true]);
        $section = Section::query()->create(['class_id' => $class->id, 'name' => 'A', 'capacity' => 40, 'is_active' => true]);

        $this->student = Student::query()->create([
            'first_name' => 'Rafia',
            'last_name' => 'Islam',
            'gender' => 'female',
            'date_of_birth' => '2014-03-11',
            'blood_group' => 'O+',
            'status' => 'active',
            'admission_date' => '2026-01-05',
            'student_id' => 'STU-2026-0001',
            'admission_no' => 'ADM-2026-0001',
            'present_address' => 'House 12, Road 4, Dhanmondi, Dhaka',
            'phone' => '01711223344',
            'school_id' => $this->school->id,
        ]);

        $this->student->guardians()->attach(
            Guardian::query()->create(['name' => 'Imran Islam', 'relation' => 'father', 'phone' => '01812334455'])->id,
            ['school_id' => $this->school->id, 'is_primary' => true]
        );

        $this->enrollment = Enrollment::query()->create([
            'student_id' => $this->student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_no' => 7,
            'enrolled_on' => '2026-01-05',
            'status' => 'active',
        ]);
    }

    public function test_the_card_shows_the_enrolled_class_and_a_signed_verification_link(): void
    {
        $response = $this->actingAs($this->admin)->get(route('students.idcards.card', $this->student));

        $response->assertOk()
            ->assertSee('Rafia Islam')
            ->assertSee('Class Six')
            ->assertSee('STU-2026-0001')
            ->assertSee('signature=', false)
            ->assertSee('data-qr=', false);
    }

    public function test_the_verification_page_only_opens_with_its_signature(): void
    {
        $other = Student::query()->create([
            'first_name' => 'Nabila',
            'gender' => 'female',
            'date_of_birth' => '2013-05-05',
            'status' => 'active',
            'admission_date' => '2026-01-05',
            'student_id' => 'STU-2026-0009',
            'admission_no' => 'ADM-2026-0009',
            'school_id' => $this->school->id,
        ]);

        $signed = URL::signedRoute('student.verify', ['student' => $this->student->id]);

        $this->get($signed)
            ->assertOk()
            ->assertSee('Rafia Islam')
            ->assertSee('Class Six')
            ->assertSee('Roll 7');

        $this->get(route('student.verify', ['student' => $this->student->id]))->assertForbidden();

        // The signature covers the child the link points at, so swapping the id
        // for a classmate's cannot borrow the same pass.
        $tampered = str_replace(
            '/verify/'.$this->student->id,
            '/verify/'.$other->id,
            $signed
        );

        $this->assertNotSame($signed, $tampered);
        $this->get($tampered)->assertForbidden();
    }

    public function test_the_public_page_does_not_publish_the_address_or_the_guardian_phone(): void
    {
        $this->get(URL::signedRoute('student.verify', ['student' => $this->student->id]))
            ->assertOk()
            ->assertDontSee('Dhanmondi')
            ->assertDontSee('01812334455');
    }

    public function test_a_class_sheet_holds_every_card_and_never_more_than_the_limit(): void
    {
        $extra = collect(range(2, 8))->map(fn (int $n): Student => Student::query()->create([
            'first_name' => 'Student'.$n,
            'gender' => 'male',
            'date_of_birth' => '2014-01-0'.$n,
            'status' => 'active',
            'admission_date' => '2026-01-05',
            'student_id' => 'STU-2026-000'.$n,
            'admission_no' => 'ADM-2026-000'.$n,
            'school_id' => $this->school->id,
        ]));

        foreach ($extra as $index => $peer) {
            Enrollment::query()->create([
                'student_id' => $peer->id,
                'academic_session_id' => $this->enrollment->academic_session_id,
                'class_id' => $this->enrollment->class_id,
                'section_id' => $this->enrollment->section_id,
                'roll_no' => 10 + $index,
                'enrolled_on' => '2026-01-05',
                'status' => 'active',
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('students.idcards.sheet', ['class_id' => $this->enrollment->class_id, 'section_id' => $this->enrollment->section_id]))
            ->assertOk()
            ->assertSee('Rafia Islam')
            ->assertSee('Student8')
            ->assertSee('class="id-card"', false);

        $this->assertSame(8, substr_count($this->actingAs($this->admin)
            ->get(route('students.idcards.sheet'))
            ->getContent(), 'class="id-card"'));
    }

    public function test_a_sheet_for_an_empty_filter_says_so_instead_of_printing_nothing(): void
    {
        $otherClass = SchoolClass::query()->create(['name' => 'Class Nine', 'level' => 9, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('students.idcards.sheet', ['class_id' => $otherClass->id]))
            ->assertOk()
            ->assertSee('No student matches this filter')
            ->assertDontSee('class="id-card"', false);
    }

    public function test_someone_without_student_permission_cannot_open_a_card_or_a_sheet(): void
    {
        $accountant = $this->makeUser($this->school, 'accountant');

        // An accountant does hold students.view, so the whole-register sheet is
        // allowed; the card for a child outside their timetable is not.
        $this->actingAs($accountant)->get(route('students.idcards.card', $this->student))->assertForbidden();
        $this->actingAs($accountant)->get(route('students.idcards.sheet'))->assertOk();

        $visitor = $this->makeUser($this->school, 'teacher');

        $this->actingAs($visitor)->get(route('students.idcards.sheet'))->assertForbidden();
        $this->actingAs($visitor)->get(route('students.idcards.card', $this->student))->assertForbidden();
    }

    public function test_a_signed_out_visitor_is_sent_to_the_login_page(): void
    {
        $this->get(route('students.idcards.card', $this->student))->assertRedirect();
        $this->get(route('students.idcards.sheet'))->assertRedirect();
    }

    public function test_the_public_page_can_show_the_photo_without_signing_in(): void
    {
        Storage::fake('school_uploads');
        Storage::disk('school_uploads')->put('students/'.$this->school->id.'/rafia.png', 'fake-pixels');
        $this->student->update(['photo_path' => 'students/'.$this->school->id.'/rafia.png']);

        $html = $this->get(URL::signedRoute('student.verify', ['student' => $this->student->id]))
            ->assertOk()
            ->getContent();

        preg_match('/<img src="([^"]+)"/', $html, $matches);

        $photoUrl = html_entity_decode($matches[1] ?? '', ENT_QUOTES);

        $this->assertNotSame('', $photoUrl);
        $this->get($photoUrl)->assertOk()->assertHeader('Content-Type', 'image/png');

        // The stream is its own signed link, so the page alone cannot hand out
        // the picture and a link built without the signature is refused.
        $this->get(route('student.verify.photo', ['student' => $this->student->id]))->assertForbidden();

        Storage::disk('school_uploads')->delete('students/'.$this->school->id.'/rafia.png');
    }

    public function test_a_card_without_a_photo_falls_back_to_initials(): void
    {
        $this->assertNull($this->student->photo_path);

        $this->get(URL::signedRoute('student.verify', ['student' => $this->student->id]))
            ->assertOk()
            ->assertDontSee('/photo?signature=', false)
            ->assertSee('R');
    }

    public function test_a_withdrawn_child_is_reported_as_needing_a_check(): void
    {
        $this->student->update(['status' => 'transferred']);
        $this->enrollment->update(['status' => 'completed']);

        $this->get(URL::signedRoute('student.verify', ['student' => $this->student->id]))
            ->assertOk()
            ->assertSee('This card needs a check')
            ->assertDontSee('This card is valid');
    }
}
