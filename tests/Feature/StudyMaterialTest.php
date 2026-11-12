<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class StudyMaterialTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_teacher_shares_a_file_that_the_class_can_open_later(): void
    {
        Storage::fake('school_uploads');
        [$teacher] = $this->school();

        $this->actingAs($teacher)
            ->post(route('materials.store'), $this->payload([
                'file' => UploadedFile::fake()->create('chapter-4.pdf', 320, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors();

        /** @var StudyMaterial $material */
        $material = StudyMaterial::query()->sole();

        $this->assertSame('Chapter 4 worksheet', $material->title);
        $this->assertSame($this->class->id, $material->class_id);
        $this->assertSame($this->section->id, $material->section_id);
        $this->assertSame($teacher->id, $material->created_by);
        $this->assertNotNull($material->file_path);
        $this->assertSame(320 * 1024, $material->file_size);

        Storage::disk('school_uploads')->assertExists($material->file_path);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $teacher->id,
            'event' => 'material.created',
            'auditable_type' => StudyMaterial::class,
        ]);

        $download = $this->actingAs($teacher)->get(route('materials.file', $material));
        $download->assertOk();
        // The stored name is a uuid with the extension of the bytes, and that is
        // what the browser is offered.
        $download->assertHeaderContains('content-disposition', 'chapter-4.pdf');
        $download->assertHeader('x-content-type-options', 'nosniff');
    }

    public function test_a_file_type_outside_the_allow_list_is_refused(): void
    {
        Storage::fake('school_uploads');
        [$teacher] = $this->school();

        $this->actingAs($teacher)
            ->post(route('materials.store'), $this->payload([
                'file' => UploadedFile::fake()->create('lesson.php', 12, 'application/x-php'),
            ]))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, StudyMaterial::query()->count());
    }

    public function test_a_download_name_cannot_smuggle_a_header(): void
    {
        Storage::fake('school_uploads');
        [$teacher] = $this->school();

        $this->actingAs($teacher)
            ->post(route('materials.store'), $this->payload([
                'file' => UploadedFile::fake()->create('x";drop table y.pdf', 40, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors();

        /** @var StudyMaterial $material */
        $material = StudyMaterial::query()->sole();
        // The original name survives only as a label for the screen.
        $this->assertSame('x";drop table y.pdf', $material->file_name);

        // The download header is built from the safe characters, so neither the
        // quote nor the semicolon can end the filename and start a new parameter.
        $this->actingAs($teacher)->get(route('materials.file', $material))
            ->assertOk()
            ->assertHeaderContains('content-disposition', 'xdrop table y.pdf');
    }

    public function test_a_teacher_cannot_share_material_for_a_class_that_is_not_theirs(): void
    {
        Storage::fake('school_uploads');
        [$teacher] = $this->school();

        $this->useSchoolContext($this->class->school_id);
        $foreign = SchoolClass::query()->create(['name' => 'Class Nine', 'level' => 9, 'is_active' => true]);

        $this->actingAs($teacher)
            ->post(route('materials.store'), $this->payload([
                'class_id' => $foreign->id,
                'section_id' => null,
                'file' => UploadedFile::fake()->create('nine.pdf', 40, 'application/pdf'),
            ]))
            ->assertForbidden();

        $this->assertSame(0, StudyMaterial::query()->count());
    }

    public function test_a_teacher_sees_only_their_own_materials_and_the_principal_sees_none(): void
    {
        Storage::fake('school_uploads');
        [$teacher, , $principal] = $this->school();

        $this->useSchoolContext($this->class->school_id);
        $otherClass = SchoolClass::query()->create(['name' => 'Class Nine', 'level' => 9, 'is_active' => true]);
        $otherSection = Section::query()->create(['class_id' => $otherClass->id, 'name' => 'A', 'capacity' => 30, 'is_active' => true]);
        $otherTeacher = $this->makeUser($this->class->school, 'teacher');
        $this->routineFor($otherTeacher, $otherClass, $otherSection);

        $this->share($teacher, 'Chapter 4 worksheet', $this->class, $this->section);
        $this->share($teacher, 'Algebra suggestion', $this->class, $this->section);
        $this->share($otherTeacher, 'Nine work sheet', $otherClass, $otherSection);

        $mine = $this->actingAs($teacher)->get(route('materials.index'))->assertOk();
        $this->assertSame(2, $mine->viewData('materials')->total());
        // Asserted on the rows, not the page: the colleague's own flash message
        // is still in the session and would show up as page text.
        $this->assertSame(
            ['Algebra suggestion', 'Chapter 4 worksheet'],
            $mine->viewData('materials')->pluck('title')->sort()->values()->all(),
        );

        $theirs = $this->actingAs($otherTeacher)->get(route('materials.index'))->assertOk();
        $this->assertSame(1, $theirs->viewData('materials')->total());

        // Classwork belongs to teachers; even the principal is refused.
        $this->actingAs($principal)->get(route('materials.index'))->assertForbidden();
    }

    public function test_a_whole_class_material_reaches_every_section_the_teacher_holds(): void
    {
        Storage::fake('school_uploads');
        [$teacher] = $this->school();

        // The shared sheet is filed against the class, not a section.
        $this->share($teacher, 'Term syllabus', $this->class, null);

        $mine = $this->actingAs($teacher)->get(route('materials.index'))->assertOk();
        $this->assertSame(1, $mine->viewData('materials')->total());
        $mine->assertSee('Whole class');
    }

    public function test_a_teacher_cannot_open_a_file_from_a_class_they_do_not_teach(): void
    {
        Storage::fake('school_uploads');
        [$teacher] = $this->school();

        $this->useSchoolContext($this->class->school_id);
        $otherClass = SchoolClass::query()->create(['name' => 'Class Nine', 'level' => 9, 'is_active' => true]);
        $otherSection = Section::query()->create(['class_id' => $otherClass->id, 'name' => 'A', 'capacity' => 30, 'is_active' => true]);
        $otherTeacher = $this->makeUser($this->class->school, 'teacher');
        $this->routineFor($otherTeacher, $otherClass, $otherSection);

        $foreign = $this->share($otherTeacher, 'Nine work sheet', $otherClass, $otherSection);

        $this->actingAs($teacher)->get(route('materials.file', $foreign))->assertForbidden();
        $this->actingAs($otherTeacher)->get(route('materials.file', $foreign))->assertOk();
    }

    public function test_a_material_from_another_school_cannot_be_listed_or_opened(): void
    {
        Storage::fake('school_uploads');
        [$teacher] = $this->school();
        $material = $this->share($teacher, 'Chapter 4 worksheet', $this->class, $this->section);

        $otherSchool = $this->makeSchool();
        $outsider = $this->makeUser($otherSchool, 'teacher');

        $list = $this->actingAs($outsider)->get(route('materials.index'))->assertOk();
        $this->assertSame(0, $list->viewData('materials')->total());
        $list->assertSee('No material shared yet');

        $this->actingAs($outsider)->get(route('materials.file', $material))->assertNotFound();
    }

    public function test_withdrawing_a_material_takes_it_off_the_list(): void
    {
        Storage::fake('school_uploads');
        [$teacher] = $this->school();
        $material = $this->share($teacher, 'Chapter 4 worksheet', $this->class, $this->section);

        $this->actingAs($teacher)
            ->delete(route('materials.destroy', $material))
            ->assertRedirect(route('materials.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('study_materials', ['id' => $material->id]);
        $this->assertSame(0, $this->actingAs($teacher)->get(route('materials.index'))->assertOk()->viewData('materials')->total());

        // The row is gone, so the old download link no longer resolves.
        $this->actingAs($teacher)->get(route('materials.file', $material))->assertNotFound();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $teacher->id,
            'event' => 'study_material.permanently_deleted',
        ]);
    }

    public function test_only_teachers_may_open_the_materials_screen(): void
    {
        [$teacher, , $principal] = $this->school();
        $school = School::query()->sole();
        $accountant = $this->makeUser($school, 'accountant');
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($accountant)->get(route('materials.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('materials.create'))->assertForbidden();

        // The school admin assigns classes to teachers; classwork itself is not
        // theirs to create or browse.
        $this->actingAs($admin)->get(route('materials.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('materials.create'))->assertForbidden();

        // The principal is not a teacher either.
        $this->actingAs($principal)->get(route('materials.index'))->assertForbidden();
        $this->actingAs($principal)->get(route('materials.create'))->assertForbidden();

        $this->actingAs($teacher)->get(route('materials.index'))->assertOk();
    }

    public function test_the_upload_form_only_offers_classes_the_teacher_actually_holds(): void
    {
        [$teacher] = $this->school();

        $this->useSchoolContext($this->class->school_id);
        SchoolClass::query()->create(['name' => 'Class Nine', 'level' => 9, 'is_active' => true]);

        $form = $this->actingAs($teacher)->get(route('materials.create'))->assertOk();
        $this->assertSame(
            [$this->class->id],
            $form->viewData('classes')->pluck('id')->all(),
        );
        $this->assertSame([$this->subject->id], $form->viewData('subjects')->pluck('id')->all());
    }

    public function test_a_material_needs_a_file_before_it_can_be_shared(): void
    {
        [$teacher] = $this->school();

        $this->actingAs($teacher)->post(route('materials.store'), [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'title' => 'Nothing attached',
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, StudyMaterial::query()->count());
    }

    public function test_classwork_can_be_notes_only_and_stays_apart_from_materials(): void
    {
        [$teacher, $colleague] = $this->school();

        $this->actingAs($teacher)->post(route('materials.store'), $this->payload([
            'title' => 'Today: fraction circles',
            'kind' => StudyMaterial::CLASSWORK,
            'work_date' => now()->toDateString(),
            'description' => 'Cut the circles and compare the halves.',
        ]))->assertSessionHasNoErrors();

        $classwork = StudyMaterial::query()->sole();
        $this->assertSame(StudyMaterial::CLASSWORK, $classwork->kind);
        $this->assertNull($classwork->file_path);

        // The lists keep the two kinds apart. The first request only clears
        // the "is now shared" flash so it cannot satisfy the text checks.
        $this->actingAs($teacher)->get(route('materials.index'))->assertOk();

        $this->actingAs($teacher)
            ->get(route('materials.index', ['kind' => StudyMaterial::MATERIAL]))
            ->assertOk()
            ->assertDontSee('Today: fraction circles');

        $this->actingAs($teacher)
            ->get(route('materials.index', ['kind' => StudyMaterial::CLASSWORK]))
            ->assertOk()
            ->assertSee('Today: fraction circles');

        // A study material still needs a document.
        $this->actingAs($teacher)->post(route('materials.store'), $this->payload([
            'title' => 'Missing suggestion paper',
        ]))->assertSessionHasErrors('file');

        // Editing a text entry does not require a file again.
        $this->actingAs($teacher)->put(route('materials.update', $classwork), $this->payload([
            'title' => 'Today: fraction strips',
            'kind' => StudyMaterial::CLASSWORK,
            'work_date' => now()->toDateString(),
            'description' => 'Compare the halves, then the quarters.',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Today: fraction strips', $classwork->refresh()->title);
        $this->assertDatabaseHas('audit_logs', ['event' => 'material.updated', 'user_id' => $teacher->id]);

        // Search finds it by title.
        $this->actingAs($teacher)
            ->get(route('materials.index', ['kind' => StudyMaterial::CLASSWORK, 'q' => 'fraction strips']))
            ->assertOk()
            ->assertSee('Today: fraction strips');

        // A teacher with no timetable at all cannot edit someone else's work.
        $stranger = $this->makeUser($classwork->school, 'teacher');

        $this->actingAs($stranger)
            ->put(route('materials.update', $classwork), $this->payload(['title' => 'Not mine']))
            ->assertForbidden();

        $this->assertSame('Today: fraction strips', $classwork->refresh()->title);
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function school(): array
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $colleague = $this->makeUser($school, 'teacher');
        $office = $this->makeUser($school, 'principal');

        $this->academicFixtures($school->id);

        $this->routineFor($teacher, $this->class, $this->section);
        $this->routineFor($colleague, $this->class, $this->section, '10:00', '10:45');

        return [$teacher, $colleague, $office];
    }

    private function academicFixtures(int $schoolId): void
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

        $this->subject = Subject::query()->firstOrCreate(
            ['name' => 'Mathematics'],
            ['type' => 'theory', 'is_active' => true],
        );
    }

    private function routineFor(User $user, SchoolClass $class, Section $section, string $start = '09:00', string $end = '09:45'): void
    {
        $profile = Teacher::query()->where('user_id', $user->id)->first()
            ?? Teacher::factory()->create(['user_id' => $user->id]);

        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $profile->id,
            'day_of_week' => 2,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'title' => 'Chapter 4 worksheet',
            'description' => 'Read the worked examples before Friday.',
        ], $overrides);
    }

    private function share(User $actor, string $title, SchoolClass $class, ?Section $section): StudyMaterial
    {
        $this->actingAs($actor)
            ->post(route('materials.store'), $this->payload([
                'title' => $title,
                'class_id' => $class->id,
                'section_id' => $section?->id,
                'file' => UploadedFile::fake()->create('sheet.pdf', 64, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors();

        return StudyMaterial::query()->where('title', $title)->sole();
    }
}
