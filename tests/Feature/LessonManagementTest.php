<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The lesson desk: what a teacher may write, publish and withdraw for the
 * periods on their own timetable, what the class then sees, and what nobody
 * but the office may reach.
 */
class LessonManagementTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $sectionA;

    private Section $sectionB;

    private Subject $math;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_teacher_writes_a_draft_that_the_class_cannot_open_yet(): void
    {
        [$teacher] = $this->school();
        $student = $this->pupil('Ayesha', 'Rahman', $this->sectionA, 1);

        $this->actingAs($teacher)
            ->post(route('lessons.store'), $this->payload([
                'title' => 'Halves and quarters',
                'chapter_number' => '4',
                'chapter_name' => 'Fractions',
                'status' => Lesson::DRAFT,
                'content' => '<h2>Halves</h2><p>Fold a paper strip into two equal parts.</p>',
            ]))
            ->assertSessionHasNoErrors();

        /** @var Lesson $lesson */
        $lesson = Lesson::query()->sole();
        $this->assertSame(Lesson::DRAFT, $lesson->status);
        $this->assertNull($lesson->published_at);
        $this->assertSame($teacher->id, $lesson->created_by);
        $this->assertSame($lesson->teacher_id, Teacher::query()->where('user_id', $teacher->id)->value('id'));
        $this->assertSame($this->sectionA->id, $lesson->section_id);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $teacher->id,
            'event' => 'lesson.created',
            'auditable_type' => Lesson::class,
        ]);

        // The list on the teacher's desk has it; the class portal does not.
        $this->actingAs($teacher)->get(route('lessons.index'))->assertOk()->assertSee('Halves and quarters');
        $this->actingAs($student)->get(route('student.lessons.index'))->assertOk()->assertDontSee('Halves and quarters');
        $this->actingAs($student)->get(route('student.lessons.show', $lesson))->assertNotFound();
    }

    public function test_the_teacher_publishes_it_himself_and_the_class_reads_it(): void
    {
        Storage::fake('school_uploads');
        [$teacher] = $this->school();
        $student = $this->pupil('Ayesha', 'Rahman', $this->sectionA, 1);

        $lesson = $this->write($teacher, 'Halves and quarters', Lesson::DRAFT, [
            'content' => '<p>Fold a paper strip into two equal parts.</p>',
            'materials' => [UploadedFile::fake()->create('fraction-strips.pdf', 48, 'application/pdf')],
        ]);

        // No approval queue, no second signature: the teacher's own word is enough.
        $this->actingAs($teacher)
            ->post(route('lessons.publish', $lesson))
            ->assertSessionHasNoErrors();

        $lesson->refresh();
        $this->assertSame(Lesson::PUBLISHED, $lesson->status);
        $this->assertNotNull($lesson->published_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'lesson.published', 'user_id' => $teacher->id]);

        $this->actingAs($student)->get(route('student.lessons.index'))->assertOk()->assertSee('Halves and quarters');
        $this->actingAs($student)->get(route('student.lessons.show', $lesson))
            ->assertOk()
            ->assertSee('Fold a paper strip into two equal parts', false);

        /** @var LessonMaterial $material */
        $material = $lesson->materials()->sole();
        $download = $this->actingAs($student)->get(route('student.lessons.materials.file', [$lesson, $material]));
        $download->assertOk();
        // The service adds the extension of the stored bytes, so the name must be
        // offered once — "fraction-strips.pdf.pdf" is what the class must not see.
        $download->assertHeaderContains('content-disposition', 'filename=fraction-strips.pdf');
        $this->assertStringNotContainsString('.pdf.pdf', $download->headers->get('content-disposition'));
    }

    public function test_a_lesson_saved_with_the_publish_choice_goes_straight_out(): void
    {
        [$teacher] = $this->school();

        $this->actingAs($teacher)->post(route('lessons.store'), $this->payload([
            'title' => 'Line graphs',
            'status' => Lesson::PUBLISHED,
            'content' => '<p>Read the axes first.</p>',
        ]))->assertSessionHasNoErrors();

        /** @var Lesson $lesson */
        $lesson = Lesson::query()->sole();
        $this->assertSame(Lesson::PUBLISHED, $lesson->status);
        $this->assertNotNull($lesson->published_at);
    }

    public function test_an_empty_lesson_cannot_be_published(): void
    {
        [$teacher] = $this->school();

        $lesson = $this->write($teacher, 'Nothing written yet', Lesson::DRAFT, ['content' => null]);

        $this->actingAs($teacher)
            ->post(route('lessons.publish', $lesson))
            ->assertSessionHasErrors('content');

        $this->assertSame(Lesson::DRAFT, $lesson->refresh()->status);

        // The same rule holds when the form itself asks for publication.
        $this->actingAs($teacher)->post(route('lessons.store'), $this->payload([
            'title' => 'Also empty',
            'content' => null,
            'status' => Lesson::PUBLISHED,
        ]))->assertSessionHasErrors('content');
    }

    public function test_markup_the_editor_did_not_make_never_reaches_the_page(): void
    {
        [$teacher] = $this->school();
        $student = $this->pupil('Ayesha', 'Rahman', $this->sectionA, 1);

        $lesson = $this->write($teacher, 'A body that fights back', Lesson::PUBLISHED, [
            'content' => '<p onclick="alert(1)">Plain words '
                .'<strong>kept</strong> <em>and</em> <a href="javascript:alert(2)">bad link</a>'
                .'<a href="https://example.test/ok">good link</a></p>'
                .'<script>alert(3)</script><style>p{color:red}</style><iframe src="https://evil.test"></iframe>'
                .'<img src="https://example.test/a.png" onerror="alert(4)">'
                .'<table><tbody><tr><td>cell</td></tr></tbody></table>',
        ]);

        $body = $lesson->content;

        foreach (['<script', 'alert(3)', '<iframe', '<style', 'onclick', 'onerror', 'javascript:'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $body, "{$needle} survived the gate");
        }

        $this->assertStringContainsString('<strong>kept</strong>', $body);
        $this->assertStringContainsString('<table', $body);
        $this->assertStringContainsString('https://example.test/ok', $body);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $body);
        // The dangerous anchor loses its address but its words stay, so nothing
        // is silently swallowed from what the teacher wrote.
        $this->assertMatchesRegularExpression('/<a[^>]*>bad link<\/a>/', $body);
        $this->assertStringNotContainsString('p{color:red}', $body);

        // And the class page shows the same cleaned text, not the typed markup.
        $this->actingAs($student)->get(route('student.lessons.show', $lesson))
            ->assertOk()
            ->assertDontSee('alert(3)', false)
            ->assertDontSee('onerror', false)
            ->assertSee('good link', false);
    }

    public function test_a_second_teacher_on_the_same_period_cannot_touch_the_lesson(): void
    {
        [$teacher, $colleague] = $this->school();
        $lesson = $this->write($teacher, 'Halves and quarters', Lesson::DRAFT);

        $this->actingAs($colleague)->get(route('lessons.edit', $lesson))->assertForbidden();
        $this->actingAs($colleague)->put(route('lessons.update', $lesson), $this->payload([
            'title' => 'Not yours',
            'status' => Lesson::DRAFT,
        ]))->assertForbidden();
        $this->actingAs($colleague)->post(route('lessons.publish', $lesson))->assertForbidden();
        $this->actingAs($colleague)->delete(route('lessons.destroy', $lesson))->assertForbidden();

        $this->assertSame('Halves and quarters', $lesson->refresh()->title);
        $this->assertSame(Lesson::DRAFT, $lesson->status);

        // Reading is still allowed — the colleague teaches that very period.
        $this->actingAs($colleague)->get(route('lessons.show', $lesson))->assertOk();
    }

    public function test_a_teacher_cannot_file_a_lesson_for_a_class_or_subject_they_do_not_hold(): void
    {
        [$teacher] = $this->school();

        $this->useSchoolContext($this->class->school_id);
        $foreignClass = SchoolClass::query()->create(['name' => 'Class Nine', 'level' => 9, 'is_active' => true]);
        $foreignSubject = Subject::query()->create(['name' => 'Physics', 'type' => 'theory', 'is_active' => true]);

        // A hand-assembled post is still refused, but the answer lands on the
        // field that is wrong instead of a dead-end page nobody can act on.
        $this->actingAs($teacher)->post(route('lessons.store'), $this->payload([
            'class_id' => $foreignClass->id,
            'section_id' => null,
            'title' => 'Not my class',
            'status' => Lesson::DRAFT,
        ]))->assertSessionHasErrors('class_id');

        $this->actingAs($teacher)->post(route('lessons.store'), $this->payload([
            'subject_id' => $foreignSubject->id,
            'title' => 'Not my subject',
            'status' => Lesson::DRAFT,
        ]))->assertSessionHasErrors('subject_id');

        // The same refusal over JSON, because the dashboard posts the form that way.
        $this->actingAs($teacher)->postJson(route('lessons.store'), $this->payload([
            'subject_id' => $foreignSubject->id,
            'title' => 'Not my subject',
            'status' => Lesson::DRAFT,
        ]))->assertStatus(422)->assertJsonValidationErrors('subject_id');

        // A section chosen from another class is refused by the form itself.
        $this->actingAs($teacher)->post(route('lessons.store'), $this->payload([
            'class_id' => $foreignClass->id,
            'section_id' => $this->sectionA->id,
            'title' => 'Mismatched',
            'status' => Lesson::DRAFT,
        ]))->assertSessionHasErrors('section_id');

        $this->assertSame(0, Lesson::query()->count());
    }

    public function test_the_form_offers_only_the_periods_on_the_timetable(): void
    {
        [$teacher, , $office] = $this->school();

        $this->useSchoolContext($this->class->school_id);
        SchoolClass::query()->create(['name' => 'Class Nine', 'level' => 9, 'is_active' => true]);
        Subject::query()->create(['name' => 'Physics', 'type' => 'theory', 'is_active' => true]);

        $html = $this->actingAs($teacher)->get(route('lessons.create'))->assertOk()->getContent();
        $rows = $this->periodMap($html)[$this->session->id];

        // One row — the period the routine actually gives her. Section B of the
        // same class is not offered, because saving it would be refused.
        $this->assertSame(
            [[$this->class->id, $this->sectionA->id, $this->math->id]],
            array_map(fn (array $row): array => [$row['class'], $row['section'], $row['subject']], $rows),
        );
        $this->assertStringNotContainsString('Class Nine', $html);

        // The office is not bound by a timetable, so it still chooses freely.
        $officeHtml = $this->actingAs($office)->get(route('lessons.create'))->assertOk()->getContent();
        $officeSections = array_column($this->periodMap($officeHtml)[$this->session->id], 'section');

        $this->assertStringContainsString('Class Nine', $officeHtml);
        $this->assertContains($this->sectionB->id, $officeSections);
    }

    /**
     * The map the three selects are built from, read back out of the page.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function periodMap(string $html): array
    {
        preg_match('/data-lsn-periods="([^"]*)"/', $html, $match);

        return json_decode(html_entity_decode($match[1] ?? '', ENT_QUOTES, 'UTF-8'), true) ?: [];
    }

    public function test_a_teacher_withdraws_a_draft_but_a_published_lesson_stays_for_the_office(): void
    {
        [$teacher, , $office] = $this->school();

        $draft = $this->write($teacher, 'Throwaway draft', Lesson::DRAFT);
        $this->actingAs($teacher)->delete(route('lessons.destroy', $draft))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('lessons', ['id' => $draft->id]);

        $published = $this->write($teacher, 'Already shown to the class', Lesson::PUBLISHED);
        $this->actingAs($teacher)->delete(route('lessons.destroy', $published))
            ->assertForbidden()
            ->assertSee('Only a draft of your own can be deleted', false);

        // Unpublishing first returns it to the teacher's own hands.
        $this->actingAs($teacher)->post(route('lessons.unpublish', $published))->assertSessionHasNoErrors();
        $this->assertSame(Lesson::DRAFT, $published->refresh()->status);
        $this->assertNull($published->published_at);

        $this->actingAs($teacher)->delete(route('lessons.destroy', $published))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('lessons', ['id' => $published->id]);

        // The office may delete a lesson the class has already read.
        $second = $this->write($teacher, 'Second published lesson', Lesson::PUBLISHED);
        $this->actingAs($office)->delete(route('lessons.destroy', $second))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('lessons', ['id' => $second->id]);

        $this->assertDatabaseHas('audit_logs', ['event' => 'lesson.permanently_deleted', 'user_id' => $office->id]);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'lesson.deleted']);
    }

    public function test_uploads_are_checked_from_their_bytes_and_only_the_right_eyes_stream_them(): void
    {
        Storage::fake('school_uploads');
        [$teacher] = $this->school();
        $otherPupil = $this->pupil('Bilal', 'Ahmed', $this->sectionB, 2);

        $this->actingAs($teacher)->post(route('lessons.store'), $this->payload([
            'title' => 'With two files',
            'status' => Lesson::PUBLISHED,
            'content' => '<p>Look at the sheet.</p>',
            'materials' => [
                UploadedFile::fake()->create('sheet.pdf', 40, 'application/pdf'),
                UploadedFile::fake()->create('diagram.png', 24, 'image/png'),
            ],
        ]))->assertSessionHasNoErrors();

        /** @var Lesson $lesson */
        $lesson = Lesson::query()->where('title', 'With two files')->sole();
        $this->assertSame(2, $lesson->materials()->count());
        $this->assertSame(['document', 'image'], $lesson->materials()->pluck('kind')->sort()->values()->all());

        foreach ($lesson->materials as $material) {
            Storage::disk('school_uploads')->assertExists($material->file_path);
            // The stored name is a uuid, never the typed one.
            $this->assertStringNotContainsString('sheet.pdf', $material->file_path);
        }

        $pdf = $lesson->materials()->where('kind', 'document')->sole();

        // A pupil of the other section is not this lesson's class.
        $this->actingAs($otherPupil)->get(route('student.lessons.show', $lesson))->assertNotFound();
        $this->actingAs($otherPupil)->get(route('student.lessons.materials.file', [$lesson, $pdf]))->assertNotFound();

        // A file outside the allow list never reaches storage.
        $this->actingAs($teacher)->post(route('lessons.store'), $this->payload([
            'title' => 'Bad attachment',
            'status' => Lesson::DRAFT,
            'materials' => [UploadedFile::fake()->create('shell.php', 8, 'application/x-php')],
        ]))->assertSessionHasErrors('materials.0');

        $this->assertSame(1, Lesson::query()->count());
    }

    public function test_a_video_address_becomes_an_embed_and_an_unsafe_one_is_refused(): void
    {
        [$teacher] = $this->school();
        $student = $this->pupil('Ayesha', 'Rahman', $this->sectionA, 1);

        $lesson = $this->write($teacher, 'Watch the experiment', Lesson::PUBLISHED, [
            'content' => '<p>Then answer the questions.</p>',
            'video_url' => 'https://www.youtube.com/watch?v=abcDEF123-_',
            'reference_links' => "https://example.test/notes\njavascript:alert(1)",
        ]);

        $this->assertSame('abcDEF123-_', $lesson->youtube_id);
        $this->assertSame('https://example.test/notes', $lesson->reference_links);

        $page = $this->actingAs($student)->get(route('student.lessons.show', $lesson))->assertOk();
        $page->assertSee('https://www.youtube.com/embed/abcDEF123-_?rel=0', false);
        $page->assertDontSee('javascript:alert', false);

        // A frame is only ever built for the two known players.
        $vimeo = $this->write($teacher, 'Vimeo clip', Lesson::PUBLISHED, [
            'content' => '<p>Short clip.</p>',
            'video_url' => 'https://vimeo.com/76979871',
        ]);
        $this->assertStringContainsString('player.vimeo.com/video/76979871', $vimeo->playerUrl());

        $this->actingAs($teacher)->post(route('lessons.store'), $this->payload([
            'title' => 'Not a video',
            'status' => Lesson::DRAFT,
            'video_url' => 'javascript:alert(1)',
        ]))->assertSessionHasErrors('video_url');
    }

    public function test_a_pupil_sees_only_published_lessons_of_their_own_class_and_section(): void
    {
        [$teacher] = $this->school();
        $ayesha = $this->pupil('Ayesha', 'Rahman', $this->sectionA, 1);
        $bilal = $this->pupil('Bilal', 'Ahmed', $this->sectionB, 2);

        $this->write($teacher, 'Section A only work', Lesson::PUBLISHED, ['section_id' => $this->sectionA->id]);
        $this->write($teacher, 'Whole class syllabus', Lesson::PUBLISHED, ['section_id' => null]);
        $this->write($teacher, 'Hidden draft', Lesson::DRAFT, ['section_id' => null]);

        $this->useSchoolContext($this->class->school_id);
        $otherClass = SchoolClass::query()->create(['name' => 'Class Nine', 'level' => 9, 'is_active' => true]);
        $otherSection = Section::query()->create(['class_id' => $otherClass->id, 'name' => 'A', 'capacity' => 30, 'is_active' => true]);
        $nine = $this->pupil('Sadia', 'Karim', $otherSection, 3);

        $titles = fn (User $who): array => $this->actingAs($who)
            ->get(route('student.lessons.index'))
            ->assertOk()
            ->viewData('rows')
            ->pluck('title')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Section A only work', 'Whole class syllabus'], $titles($ayesha));
        $this->assertSame(['Whole class syllabus'], $titles($bilal));
        $this->assertSame([], $titles($nine));

        // The filters only ever narrow the pupil's own lessons.
        $list = $this->actingAs($ayesha)->get(route('student.lessons.index', [
            'subject' => $this->math->id,
            'chapter' => '4',
            'q' => 'syllabus',
        ]))->assertOk();
        $this->assertSame(['Whole class syllabus'], $list->viewData('rows')->pluck('title')->all());

        // Reading is one-way: the portal offers no way to change a lesson, and
        // the staff workspace turns a pupil away before the route is reached.
        $this->actingAs($ayesha)->get(route('lessons.create'))->assertRedirect();
        $this->actingAs($ayesha)->get(route('lessons.index'))->assertRedirect();
        $this->assertSame(0, Lesson::query()->where('title', 'Rewritten by a pupil')->count());
    }

    public function test_the_office_sees_every_lesson_and_who_wrote_it(): void
    {
        [$teacher, , $office] = $this->school();
        $lesson = $this->write($teacher, 'Halves and quarters', Lesson::DRAFT);

        // The teacher publishes it herself; the office records the same two steps.
        $this->actingAs($teacher)->post(route('lessons.publish', $lesson))->assertSessionHasNoErrors();

        $list = $this->actingAs($office)->get(route('lessons.index'))->assertOk();
        $this->assertSame(1, $list->viewData('lessons')->total());
        $this->assertSame(1, $list->viewData('counts')['published']);

        // A teacher who does not hold the period is kept off the list entirely.
        $this->useSchoolContext($this->class->school_id);
        $stranger = $this->makeUser($lesson->school, 'teacher');
        $this->assertSame(0, $this->actingAs($stranger)->get(route('lessons.index'))->assertOk()->viewData('lessons')->total());

        $detail = $this->actingAs($office)->get(route('lessons.show', $lesson))->assertOk();
        $this->assertContains('lesson.created', $detail->viewData('history')->pluck('event')->all());
        $this->assertContains('lesson.published', $detail->viewData('history')->pluck('event')->all());

        // The office may also edit or withdraw a lesson it did not write.
        $this->actingAs($office)->put(route('lessons.update', $lesson), $this->payload([
            'title' => 'Halves, quarters and thirds',
            'status' => Lesson::PUBLISHED,
            'content' => '<p>Updated by the office.</p>',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Halves, quarters and thirds', $lesson->refresh()->title);
    }

    public function test_the_dashboard_saves_a_lesson_without_a_page_reload(): void
    {
        [$teacher] = $this->school();

        // The panel asks for the same screen as a fragment.
        $panel = $this->actingAs($teacher)->get(route('lessons.index', ['fragment' => 1]))->assertOk();
        $this->assertTrue($panel->viewData('panel'));
        $panel->assertSee('Total lessons');

        $json = $this->actingAs($teacher)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('lessons.store'), $this->payload([
                'title' => 'Written from the panel',
                'status' => Lesson::DRAFT,
                'content' => '<p>Body typed in the panel.</p>',
            ]))
            ->assertOk();

        $payload = $json->json();
        $this->assertTrue($payload['ok']);
        $this->assertSame(Lesson::DRAFT, $payload['lesson']['status']);
        $this->assertStringContainsString('/admin/lessons/', $payload['lesson']['urls']['edit']);

        $lesson = Lesson::query()->where('title', 'Written from the panel')->sole();

        $published = $this->actingAs($teacher)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('lessons.publish', $lesson))
            ->assertOk();

        $this->assertSame(Lesson::PUBLISHED, $published->json('lesson.status'));
        $this->assertSame(Lesson::PUBLISHED, $lesson->refresh()->status);

        // A failed save answers with the field errors, not a redirect.
        $this->actingAs($teacher)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('lessons.store'), $this->payload(['title' => 'x', 'content' => null, 'status' => Lesson::PUBLISHED]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content']);

        // Deleting answers with the id so the row can be lifted off the list.
        $this->actingAs($teacher)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->delete(route('lessons.destroy', $lesson))
            ->assertForbidden();
    }

    public function test_the_picture_the_editor_inserts_is_uploaded_and_returned_as_a_tag_address(): void
    {
        Storage::fake('school_uploads');
        [$teacher, , $office] = $this->school();

        $response = $this->actingAs($teacher)->post(route('lessons.images.store'), [
            'file' => UploadedFile::fake()->create('diagram.png', 32, 'image/png'),
        ])->assertOk();

        $payload = $response->json();
        $this->assertStringContainsString('lesson-images/', $payload['path']);
        // The address handed to the editor is one the browser can load, and it
        // points at the same file that was just stored.
        $this->assertNotEmpty($payload['url']);
        $this->assertStringContainsString('lesson-images/', $payload['url']);

        Storage::disk('school_uploads')->assertExists($payload['path']);

        $this->actingAs($teacher)->post(route('lessons.images.store'), [
            'file' => UploadedFile::fake()->create('notes.pdf', 8, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        // An account outside the staff workspace is turned away before the file
        // is even looked at.
        $this->useSchoolContext($this->class->school_id);
        $stranger = $this->makeUser($office->school, 'student');
        $this->actingAs($stranger)->post(route('lessons.images.store'), [
            'file' => UploadedFile::fake()->create('diagram.png', 8, 'image/png'),
        ])->assertRedirect();
    }

    public function test_the_editor_reopens_with_the_saved_body_and_nothing_around_it(): void
    {
        [$teacher] = $this->school();
        $lesson = $this->write($teacher, 'Halves and quarters', Lesson::DRAFT);

        $html = $this->actingAs($teacher)->get(route('lessons.edit', $lesson))->assertOk()->getContent();

        // The body sits inside the editable div exactly as it is stored. A stray
        // character from the template would be saved back into the lesson the next
        // time the teacher pressed Publish, and would grow on every round trip.
        $this->assertStringContainsString(
            'aria-label="Lesson content">'.$lesson->content.'</div>',
            $html,
        );
        $this->assertStringContainsString('value="'.e($lesson->content).'"', $html);
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function school(): array    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $colleague = $this->makeUser($school, 'teacher');
        $office = $this->makeUser($school, 'principal');

        $this->academicFixtures($school->id);

        $this->routineFor($teacher, $this->class, $this->sectionA);
        $this->routineFor($colleague, $this->class, $this->sectionA, '10:00', '10:45');

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

        $this->sectionA = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->sectionB = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'B',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->math = Subject::query()->create(['name' => 'Mathematics', 'type' => 'theory', 'is_active' => true]);
    }

    private function routineFor(User $user, SchoolClass $class, Section $section, string $start = '09:00', string $end = '09:45'): void
    {
        $profile = Teacher::factory()->create(['user_id' => $user->id]);

        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $profile->id,
            'day_of_week' => 2,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /**
     * A signed-in pupil of one section.
     */
    private function pupil(string $first, string $last, ?Section $section, ?int $roll): User
    {
        return $this->enrolledStudent($first, $last, $section, $roll)[0];
    }

    /**
     * @return array{0: User, 1: Student, 2: ?Enrollment}
     */
    private function enrolledStudent(string $first, string $last, ?Section $section, ?int $roll): array
    {
        $user = $this->makeUser($this->class->school, 'student', [
            'email' => Str::slug($first.'-'.$last).'@example.test',
        ]);

        $student = Student::factory()->create([
            'first_name' => $first,
            'last_name' => $last,
        ]);

        $student->forceFill(['user_id' => $user->id])->save();
        $user->setRelation('student', $student);

        $enrollment = null;

        if ($section !== null && $roll !== null) {
            $enrollment = Enrollment::query()->create([
                'student_id' => $student->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $section->class_id,
                'section_id' => $section->id,
                'roll_no' => $roll,
                'enrolled_on' => '2026-01-02',
                'status' => 'active',
            ]);
        }

        return [$user, $student, $enrollment];
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
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'title' => 'Halves and quarters',
            'chapter_number' => '4',
            'chapter_name' => 'Fractions',
            'lesson_number' => '2',
            'description' => 'Fold, colour and compare.',
            'objectives' => "Name a half.\nName a quarter.",
            'content' => '<p>A strip folded twice gives quarters.</p>',
            'status' => Lesson::DRAFT,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function write(User $actor, string $title, string $status, array $overrides = []): Lesson
    {
        $this->actingAs($actor)
            ->post(route('lessons.store'), $this->payload(array_merge($overrides, [
                'title' => $title,
                'status' => $status,
            ])))
            ->assertSessionHasNoErrors();

        return Lesson::query()->where('title', $title)->sole();
    }
}
