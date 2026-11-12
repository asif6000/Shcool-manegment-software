<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class StudentImportTest extends TestCase
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

        Storage::disk('local')->deleteDirectory('student-imports');

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);
        $this->admin = $this->makeUser($this->school, 'school_admin');

        $this->session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::query()->create(['name' => 'Class Six', 'level' => 6, 'is_active' => true]);

        $this->section = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);
    }

    private function upload(string $name, string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $csv);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, valid: int, invalid: int, fatal: list<string>, unknown: list<string>}
     */
    private function preview(string $name, string $csv): array
    {
        $response = $this->actingAs($this->admin)->post(route('student_import.preview'), ['csv' => $this->upload($name, $csv)]);
        $response->assertOk();

        $batch = $response->viewData('batch');
        $this->assertIsArray($batch);

        return $batch;
    }

    private function token(string $name, string $csv): string
    {
        $this->actingAs($this->admin)
            ->post(route('student_import.preview'), ['csv' => $this->upload($name, $csv)])
            ->assertOk();

        $files = Storage::disk('local')->files('student-imports');

        $this->assertCount(1, $files, 'A preview must keep exactly the sheet it was shown.');

        return basename($files[0]);
    }

    public function test_a_confirmed_sheet_creates_students_with_guardian_and_auto_roll(): void
    {
        $csv = "first_name,last_name,gender,date_of_birth,status,admission_date,guardian_name,guardian_relation,guardian_phone,class,section,session\n"
            ."Rafia,Islam,female,2014-03-11,active,2026-01-05,Imran Islam,father,01711223344,Class Six,A,2026\n"
            ."Tanvir,Hossain,male,2013.08.22,active,2026-01-05,Jahangir Hossain,father,01812334455,Class Six,A,2026\n";

        $token = $this->token('class6.csv', $csv);
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}\.csv$/', $token);
        $this->assertSame(0, Student::query()->count(), 'The preview must not write anything.');

        $this->actingAs($this->admin)
            ->post(route('student_import.store'), ['token' => $token, 'confirm' => '1'])
            ->assertRedirect(route('students.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, Student::query()->count());

        $rafia = Student::query()->where('first_name', 'Rafia')->firstOrFail();
        $this->assertSame('STU', substr((string) $rafia->student_id, 0, 3));
        $this->assertSame('01711223344', $rafia->guardians->first()?->phone);
        $this->assertSame('father', $rafia->guardians->first()?->relation);

        $tanvir = Student::query()->where('first_name', 'Tanvir')->firstOrFail();
        $this->assertSame('2013-08-22', $tanvir->date_of_birth->toDateString(), 'A dotted date must become a real date.');

        // Both rows sit in the same class and section, so the second roll moves on.
        $this->assertSame(
            [1, 2],
            Enrollment::query()->whereIn('student_id', [$rafia->id, $tanvir->id])->orderBy('roll_no')->pluck('roll_no')->map(fn ($v): int => (int) $v)->all()
        );

        $this->assertDatabaseHas('audit_logs', ['event' => 'student.bulk_import']);
        $this->assertSame($this->section->id, $rafia->enrollments()->first()?->section_id);
    }

    public function test_a_row_naming_an_unknown_class_is_blocked_while_the_good_row_still_imports(): void
    {
        $csv = "first_name,gender,date_of_birth,status,admission_date,class\n"
            ."Sadia,female,2014-05-01,active,2026-01-05,Class Ten\n"
            ."Nabila,female,2014-06-01,active,2026-01-05,Class Six\n";

        $batch = $this->preview('mixed.csv', $csv);

        $this->assertSame(1, $batch['valid']);
        $this->assertSame(1, $batch['invalid']);
        $this->assertArrayHasKey('class_id', $batch['rows'][0]['errors']);

        $this->actingAs($this->admin)
            ->post(route('student_import.store'), ['token' => $this->token('mixed.csv', $csv), 'confirm' => '1'])
            ->assertSessionHas('warning');

        $this->assertSame(1, Student::query()->count());
        $this->assertSame('Nabila', Student::query()->firstOrFail()->first_name);
        $this->assertDatabaseMissing('students', ['first_name' => 'Sadia']);
    }

    public function test_a_child_already_admitted_cannot_be_imported_twice(): void
    {
        $this->actingAs($this->admin)->post(route('students.store'), [
            'first_name' => 'Rafia',
            'gender' => 'female',
            'date_of_birth' => '2014-03-11',
            'status' => 'active',
            'admission_date' => '2026-01-05',
            'phone' => '01811223344',
            'password' => 'Demo@1234',
            'password_confirmation' => 'Demo@1234',
            'present_address' => 'House 1, Road 2, Dhaka',
            'guardian_name' => 'Imran Islam',
            'guardian_phone' => '01711223344',
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 1,
        ])->assertSessionHasNoErrors();

        $csv = "first_name,gender,date_of_birth,status,admission_date,guardian_phone\n"
            ."Rafia,female,2014-03-11,active,2026-01-05,01711223344\n";

        $batch = $this->preview('twin.csv', $csv);

        $this->assertSame(0, $batch['valid']);
        $this->assertSame(1, $batch['invalid']);
        $this->assertSame(1, Student::query()->count());
    }

    public function test_one_number_cannot_be_claimed_by_two_rows_of_the_same_sheet(): void
    {
        $csv = "first_name,gender,date_of_birth,status,admission_date,student_id\n"
            ."Amina,female,2014-01-01,active,2026-01-05,STU-2026-0007\n"
            ."Bilal,male,2014-02-02,active,2026-01-05,STU-2026-0007\n";

        $batch = $this->preview('clash.csv', $csv);

        $this->assertSame(1, $batch['valid']);
        $this->assertArrayHasKey('student_id', $batch['rows'][1]['errors']);

        $this->actingAs($this->admin)
            ->post(route('student_import.store'), ['token' => $this->token('clash.csv', $csv), 'confirm' => '1']);

        $this->assertSame(1, Student::query()->count());
        $this->assertSame('STU-2026-0007', Student::query()->firstOrFail()->student_id, 'A number supplied by the sheet must survive the import.');
    }

    public function test_bangla_headings_are_read_and_an_unknown_column_is_reported(): void
    {
        $csv = "নাম,পদবি,লিঙ্গ,জন্ম_তারিখ,অবস্থা,ভর্তির_তারিখ,শ্রেণি,নিক\n"
            ."Sumaiya,Akter,female,2014-04-18,active,2026-01-05,Class Six,123-456\n";

        $batch = $this->preview('bangla.csv', $csv);

        $this->assertSame(['নিক'], $batch['unknown']);
        $this->assertSame(1, $batch['valid'], 'Bengali headings must map to real fields.');

        $rows = $batch['rows'];
        $this->assertSame('Sumaiya', $rows[0]['data']['first_name']);
        $this->assertSame($this->class->id, $rows[0]['data']['class_id']);
    }

    public function test_a_date_the_system_cannot_read_blocks_the_row_instead_of_guessing(): void
    {
        $csv = "first_name,gender,date_of_birth,status,admission_date\n"
            ."Rehana,female,১২ই মার্চ 2014,active,2026-01-05\n";

        $batch = $this->preview('baddate.csv', $csv);

        $this->assertSame(0, $batch['valid']);
        $this->assertArrayHasKey('date_of_birth', $batch['rows'][0]['errors']);
        $this->assertSame(0, Student::query()->count());
    }

    public function test_the_import_screen_needs_the_permission_the_admission_form_needs(): void
    {
        $accountant = $this->makeUser($this->school, 'accountant');
        $teacher = $this->makeUser($this->school, 'teacher');

        foreach ([$accountant, $teacher] as $user) {
            $this->actingAs($user)->get(route('student_import.index'))->assertForbidden();
            $this->actingAs($user)->get(route('student_import.template'))->assertForbidden();
        }

        $this->actingAs($teacher)
            ->post(route('student_import.store'), ['token' => str_repeat('a', 36).'.csv', 'confirm' => '1'])
            ->assertForbidden();

        $this->assertSame(0, Student::query()->count());
    }

    public function test_the_template_is_header_only_so_no_example_row_can_be_imported_by_mistake(): void
    {
        $response = $this->actingAs($this->admin)->get(route('student_import.template'));
        $response->assertOk();

        $lines = array_values(array_filter(array_map('trim', explode("\n", $response->streamedContent()))));

        $this->assertCount(1, $lines, 'The template must carry one header line and nothing else.');
        $this->assertStringContainsString('first_name', $lines[0]);
        $this->assertStringNotContainsString('Rafia', $lines[0]);
    }

    public function test_a_forged_or_missing_token_is_refused_and_nothing_is_written(): void
    {
        $this->actingAs($this->admin)
            ->post(route('student_import.store'), ['token' => '../../anything.csv', 'confirm' => '1'])
            ->assertSessionHasErrors('token');

        $this->actingAs($this->admin)
            ->post(route('student_import.store'), ['token' => str_repeat('f', 36).'.csv', 'confirm' => '1'])
            ->assertRedirect(route('student_import.index'));

        $this->actingAs($this->admin)
            ->post(route('student_import.store'), ['token' => str_repeat('0', 36).'.csv'])
            ->assertSessionHasErrors('confirm');

        $this->assertSame(0, Student::query()->count());
    }

    public function test_a_sheet_without_a_name_column_is_refused_before_any_row_is_built(): void
    {
        $csv = "phone,email\n01711223344,a@b.com\n";

        $batch = $this->preview('headless.csv', $csv);

        $this->assertSame(['The first row must be the header, and it needs a name column (first_name, name or নাম).'], $batch['fatal']);
        $this->assertSame(0, $batch['valid']);
    }

    public function test_two_rows_claiming_the_same_roll_are_caught_before_the_sheet_is_confirmed(): void
    {
        $csv = "first_name,gender,date_of_birth,status,admission_date,session,class,section,roll_no\n"
            ."One,female,2014-01-01,active,2026-01-05,2026,Class Six,A,7\n"
            ."Two,female,2014-02-02,active,2026-01-05,2026,Class Six,A,7\n";

        $batch = $this->preview('rolls.csv', $csv);

        $this->assertSame(1, $batch['valid']);
        $this->assertArrayHasKey('roll_no', $batch['rows'][1]['errors']);
    }
}
