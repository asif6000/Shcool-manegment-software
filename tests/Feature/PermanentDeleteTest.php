<?php

namespace Tests\Feature;

use App\Exceptions\PermanentDeleteDenied;
use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\ClassVideo;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceItem;
use App\Models\Guardian;
use App\Models\Holiday;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\LeaveType;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\Notice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StaffLeave;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Voucher;
use App\Services\PermanentDeleteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Every admin delete button goes through one engine: the row leaves the table
 * for good, the records that belong to it follow a written policy, and anything
 * that counts as financial, academic or audit history refuses to be destroyed.
 */
class PermanentDeleteTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $principal;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->admin = $this->makeUser($this->school, 'school_admin');
        $this->principal = $this->makeUser($this->school, 'principal');
        $this->useSchoolContext($this->school->id);

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

        $this->subject = Subject::query()->create(['name' => 'Bengali']);

        $this->actingAs($this->admin);
    }

    private function makeStudent(string $firstName = 'Nusrat', array $overrides = []): Student
    {
        $sequence = Student::withTrashed()->count() + 1;

        $student = Student::query()->create(array_merge([
            'student_id' => 'S-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'admission_no' => 'ADM-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'first_name' => $firstName,
            'gender' => 'female',
            'date_of_birth' => '2014-03-12',
            'admission_date' => '2026-01-02',
            'phone' => '017'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
        ], $overrides));

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        return $student;
    }

    /**
     * Critical records only delete when the confirmation dialog hands over the
     * typed word, exactly as the browser form does.
     */
    private function deleteRoute(string $name, mixed $parameters, bool $typed = true): TestResponse
    {
        $url = route($name, [$parameters instanceof Model ? $parameters->getRouteKey() : $parameters]);

        return $typed
            ? $this->call('DELETE', $url, ['permanent_delete' => 'DELETE'])
            : $this->delete($url);
    }

    /**
     * A portal login of one's own: a student account with the mobile number and
     * hashed password the admin sets on the create screen.
     */
    private function giveLogin(Student $student): User
    {
        $account = $this->makeUser($this->school, 'student', [
            'email' => null,
            'mobile_number' => $student->phone,
            'password' => bcrypt('Demo@1234'),
        ]);

        $student->forceFill(['user_id' => $account->id])->save();

        return $account;
    }

    public function test_a_student_delete_takes_the_profile_login_photo_and_qr_card(): void
    {
        Storage::fake('school_uploads');

        $student = $this->makeStudent('Nusrat', ['photo_path' => 'students/2026/nusrat.jpg']);
        $account = $this->giveLogin($student);
        $photo = $student->photo_path;

        Storage::disk('school_uploads')->put($photo, 'jpeg-bytes');

        DB::table('student_attendance_cards')->insert([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'token_hash' => str_repeat('a', 64),
            'issued_at' => now(),
        ]);

        $this->deleteRoute('students.destroy', $student)->assertSessionHasNoErrors();

        $this->assertSame(0, Student::withTrashed()->whereKey($student->id)->count(), 'A hidden row would keep the pupil on file.');
        $this->assertSame(0, User::withTrashed()->whereKey($account->id)->count(), 'The login goes with the profile.');
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $account->id)->count());
        $this->assertSame(0, DB::table('enrollments')->where('student_id', $student->id)->count());
        $this->assertSame(0, DB::table('student_attendance_cards')->where('student_id', $student->id)->count());
        Storage::disk('school_uploads')->assertMissing($photo);

        $this->assertDatabaseHas('audit_logs', ['event' => 'student.permanently_deleted', 'user_id' => $this->admin->id]);

        $payload = (string) DB::table('audit_logs')->where('event', 'student.permanently_deleted')->value('old_values');
        $this->assertStringNotContainsString('Nusrat', $payload);
        $this->assertStringNotContainsString($student->phone, $payload);
        $this->assertArrayHasKey('record_id', json_decode($payload, true) ?: []);
    }

    /**
     * Full cascade test — every invoice/item/payment/allocation/receipt +
     * attendance + marks + result disappears with the profile when typed DELETE
     * arrives. Other students stay untouched.
     */
    public function test_a_student_delete_removes_attendance_fees_and_marks_with_the_profile(): void
    {
        $student = $this->makeStudent('Raihan', ['photo_path' => 'students/2026/raihan.jpg']);
        $account = $this->giveLogin($student);

        // Create fee types needed for items
        \App\Models\FeeType::query()->create([
            'school_id' => $this->school->id,
            'code' => 'TUITION',
            'name' => 'Tuition Fee',
            'is_active' => true,
        ]);

        \App\Models\FeeType::query()->create([
            'school_id' => $this->school->id,
            'code' => 'BOOK',
            'name' => 'Book Bundle',
            'is_active' => true,
        ]);

        // Invoice with items + allocations + receipt
        $invoice = FeeInvoice::query()->create([
            'school_id' => $this->school->id,
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'invoice_no' => 'INV-' . str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'total' => 5000.00,
        ]);

        $tuitionFeeType = \App\Models\FeeType::query()->where('code', 'TUITION')->firstOrFail();
        $bookFeeType = \App\Models\FeeType::query()->where('code', 'BOOK')->firstOrFail();

        FeeInvoiceItem::query()->create([
            'school_id' => $this->school->id,
            'fee_invoice_id' => $invoice->id,
            'fee_type_id' => $tuitionFeeType->id,
            'description' => 'Tuition fee Q1',
            'quantity' => 1,
            'unit_amount' => 3000.00,
            'line_total' => 3000.00,
        ]);

        FeeInvoiceItem::query()->create([
            'school_id' => $this->school->id,
            'fee_invoice_id' => $invoice->id,
            'fee_type_id' => $bookFeeType->id,
            'description' => 'Textbook bundle',
            'quantity' => 1,
            'unit_amount' => 2000.00,
            'line_total' => 2000.00,
        ]);

        $payment = \App\Models\FeePayment::query()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'amount' => 5000.00,
            'method' => 'bkash',
            'transaction_ref' => 'BKSH' . rand(10000000, 99999999),
            'paid_at' => '2026-03-15',
            'status' => 'posted',
            'verification_status' => 'verified',
            'idempotency_key' => 'pay_' . $invoice->id . '_' . uniqid('', true),
        ]);

        \App\Models\FeePaymentAllocation::query()->create([
            'school_id' => $this->school->id,
            'fee_payment_id' => $payment->id,
            'fee_invoice_id' => $invoice->id,
            'amount' => 5000.00,
        ]);

        \App\Models\FeeReceipt::query()->create([
            'school_id' => $this->school->id,
            'fee_payment_id' => $payment->id,
            'receipt_no' => 'REC-' . date('Ymd') . '-' . str_pad((int) \App\Models\FeeReceipt::max('id') + 1, 4, '0', STR_PAD_LEFT),
            'snapshot' => json_encode(['amount' => 5000.00]),
            'snapshot_hash' => sha1(json_encode(['amount' => 5000.00])),
        ]);

        // Attendance rows (present days)
        foreach (range(1, 10) as $day) {
            $date = '2026-03-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            \App\Models\StudentAttendance::query()->create([
                'school_id' => $this->school->id,
                'student_id' => $student->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'attendance_date' => $date,
                'status' => 'present',
                'source' => 'manual',
                'marked_by' => $this->admin->id,
            ]);
        }

        // Exam marks + result
        $exam = Exam::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Mid-term',
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-20',
        ]);

        $examSubject = ExamSubject::query()->create([
            'exam_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'title' => 'Mid-term MCQ',
            'total_marks' => 100,
        ]);

        ExamMark::query()->create([
            'school_id' => $this->school->id,
            'exam_subject_id' => $examSubject->id,
            'student_id' => $student->id,
            'marks_obtained' => 85,
            'is_absent' => false,
        ]);

        \App\Models\StudentResult::query()->create([
            'school_id' => $this->school->id,
            'exam_id' => $examSubject->exam_id,
            'student_id' => $student->id,
            'status' => 'published',
        ]);

        // Photo + QR card
        $photo = $student->photo_path;
        Storage::disk('school_uploads')->put($photo, 'jpeg-bytes');

        DB::table('student_attendance_cards')->insert([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'token_hash' => str_repeat('a', 64),
            'issued_at' => now(),
        ]);

        $response = $this->deleteRoute('students.destroy', $student);
        $response->assertSessionHasNoErrors();

        // Root rows gone
        $this->assertSame(0, Student::withTrashed()->whereKey($student->id)->count());
        $this->assertSame(0, User::withTrashed()->whereKey($account->id)->count());
        $this->assertSame(0, Enrollment::where('student_id', $student->id)->count());

        // Financial cascade gone
        $this->assertSame(0, FeeInvoice::where('id', $invoice->id)->count());
        $this->assertSame(0, \App\Models\FeeInvoiceItem::where('fee_invoice_id', $invoice->id)->count());
        $this->assertSame(0, \App\Models\FeePayment::where('id', $payment->id)->count());
        $this->assertSame(0, \App\Models\FeePaymentAllocation::where('fee_payment_id', $payment->id)->count());
        $this->assertSame(0, \App\Models\FeeReceipt::where('fee_payment_id', $payment->id)->count());

        // Attendance + marks + result gone
        $this->assertSame(0, StudentAttendance::where('student_id', $student->id)->count());
        $this->assertSame(0, ExamMark::where('student_id', $student->id)->count());
        $this->assertSame(0, \App\Models\StudentResult::where('student_id', $student->id)->count());

        // File + QR gone
        Storage::disk('school_uploads')->assertMissing($photo);
        $this->assertSame(0, DB::table('student_attendance_cards')->where('student_id', $student->id)->count());

        // Audit log with history_consumed marker
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'student.permanently_deleted',
            'user_id' => $this->admin->id,
        ]);

        $payload = json_decode((string) DB::table('audit_logs')->where('event', 'student.permanently_deleted')->value('old_values'), true);
        $this->assertTrue($payload['history_consumed'] ?? false, 'Audit must flag that related history was consumed.');
        $this->assertArrayHasKey('record_id', $payload);
        $this->assertArrayHasKey('related_rows_removed', $payload);
    }

    /**
     * Delete one student — other students keep their rows. The shared class/
     * section/subject/exam rows are untouched, enrollments for sibling stay
     * active. This matches requirement 8: no cross-student damage.
     */
    public function test_another_student_keeps_data_when_one_is_purged(): void
    {
        $victim = $this->makeStudent('Ayesha', ['photo_path' => 'students/2026/ayesha.jpg']);
        $buddy = $this->makeStudent('Bilal', ['photo_path' => 'students/2026/bilal.jpg']);

        // Create a second subject for marks tests
        $subject2 = Subject::query()->create(['name' => 'Mathematics']);

        // Create an exam for buddy's marks
        $exam = Exam::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Term exam',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-07',
        ]);

        $paper2 = ExamSubject::query()->create([
            'exam_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject2->id,
            'title' => 'Term MCQ',
            'total_marks' => 100,
        ]);

        FeeInvoice::query()->create([
            'school_id' => $this->school->id,
            'academic_session_id' => $this->session->id,
            'student_id' => $victim->id,
            'invoice_no' => 'INV-VICTIM',
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'total' => 1000.00,
        ]);

        \App\Models\FeePayment::query()->create([
            'school_id' => $this->school->id,
            'student_id' => $buddy->id,
            'amount' => 2000.00,
            'method' => 'cash',
            'paid_at' => '2026-03-15',
            'status' => 'posted',
            'idempotency_key' => 'pay_buddy_' . uniqid('', true),
        ]);

        StudentAttendance::query()->create([
            'school_id' => $this->school->id,
            'student_id' => $victim->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => '2026-03-10',
            'status' => 'present',
        ]);

        ExamMark::query()->create([
            'school_id' => $this->school->id,
            'exam_subject_id' => $paper2->id,
            'student_id' => $buddy->id,
            'marks_obtained' => 90,
        ]);

        $accountVictim = $this->giveLogin($victim);

        Storage::disk('school_uploads')->put($victim->photo_path, 'jpeg');
        Storage::disk('school_uploads')->put($buddy->photo_path, 'jpeg');

        $this->deleteRoute('students.destroy', $victim);

        // Victim gone (soft-deleted)
        $this->assertSame(0, Student::withTrashed()->whereKey($victim->id)->count());
        $this->assertSame(0, User::withTrashed()->whereKey($accountVictim->id)->count());

        // Buddy survives completely
        $this->assertSame(1, Student::withTrashed()->whereKey($buddy->id)->count());
        $this->assertDatabaseHas('students', ['id' => $buddy->id, 'first_name' => 'Bilal']);
        $this->assertDatabaseHas('enrollments', ['student_id' => $buddy->id]);
        $this->assertNotNull($buddy->fresh()->photo_path);
        Storage::disk('school_uploads')->assertExists($buddy->photo_path);

        // Only victim's fee/attendance gone; buddy's stays
        $this->assertSame(0, FeeInvoice::where('student_id', $victim->id)->count());
        $this->assertSame(0, StudentAttendance::where('student_id', $victim->id)->count());
        $this->assertSame(1, \App\Models\FeePayment::where('student_id', $buddy->id)->count());
        $this->assertSame(1, ExamMark::where('student_id', $buddy->id)->count());

        // Shared tables untouched
        $this->assertSame(1, SchoolClass::where('name', 'Class Six')->count());
        $this->assertSame(1, Section::where('class_id', $this->class->id)->where('name', 'A')->count());
        $this->assertSame(1, Subject::where('name', 'Bengali')->count());
    }

    public function test_a_notice_row_leaves_the_table_instead_of_being_hidden(): void    {
        $notice = Notice::query()->create([
            'title' => 'Eid holiday',
            'body' => 'The school stays shut for two days.',
            'audience' => 'all',
            'published_at' => now(),
        ]);

        $this->deleteRoute('notices.destroy', $notice, typed: false)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('notices', ['id' => $notice->id]);
        $this->assertSame(0, Notice::withTrashed()->whereKey($notice->id)->count(), 'A soft delete would keep the row.');
        $this->assertDatabaseHas('audit_logs', ['event' => 'notice.permanently_deleted', 'user_id' => $this->admin->id]);
    }

    public function test_a_homework_delete_takes_the_answers_that_belong_to_it(): void
    {
        $student = $this->makeStudent();
        $teacher = $this->makeUser($this->school, 'teacher');
        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);

        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $profile->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '09:45',
        ]);

        $homework = Homework::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $profile->id,
            'created_by' => $teacher->id,
            'title' => 'Poetry dictation',
            'description' => 'Write the first stanza.',
            'assigned_date' => '2026-02-02',
            'due_date' => '2026-02-04',
        ]);

        $answer = HomeworkSubmission::query()->create([
            'homework_id' => $homework->id,
            'student_id' => $student->id,
        ]);

        $this->actingAs($teacher)
            ->deleteRoute('teacher.homework.destroy', $homework, typed: false)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('homework', ['id' => $homework->id]);
        $this->assertSame(0, HomeworkSubmission::query()->whereKey($answer->id)->count());
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_a_shared_file_disappears_with_its_row(): void
    {
        Storage::fake('school_uploads');

        $teacher = $this->makeUser($this->school, 'teacher');
        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);

        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $profile->id,
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '10:45',
        ]);

        $material = StudyMaterial::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $profile->id,
            'created_by' => $teacher->id,
            'title' => 'Chapter 4 worksheet',
            'file_path' => 'materials/2026/worksheet.pdf',
            'file_name' => 'worksheet.pdf',
        ]);

        Storage::disk('school_uploads')->put($material->file_path, 'pdf-bytes');
        Storage::disk('school_uploads')->assertExists($material->file_path);

        $this->actingAs($teacher)
            ->deleteRoute('materials.destroy', $material, typed: false)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('study_materials', ['id' => $material->id]);
        Storage::disk('school_uploads')->assertMissing($material->file_path);
    }

    public function test_a_class_video_row_and_its_upload_are_both_gone(): void
    {
        Storage::fake('school_uploads');

        $video = ClassVideo::query()->forceCreate([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'title' => 'Sports day highlights',
            'source' => ClassVideo::UPLOAD,
            'file_path' => 'videos/2026/highlights.mp4',
            'file_name' => 'highlights.mp4',
        ]);

        Storage::disk('school_uploads')->put($video->file_path, 'mp4-bytes');

        $this->deleteRoute('videos.destroy', $video, typed: false)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('class_videos', ['id' => $video->id]);
        Storage::disk('school_uploads')->assertMissing($video->file_path);
    }

    public function test_a_lesson_takes_its_own_material_rows_and_files_with_it(): void
    {
        Storage::fake('school_uploads');

        $lesson = Lesson::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'title' => 'The water cycle',
        ]);

        $material = LessonMaterial::query()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Diagram slide',
            'file_path' => 'lessons/2026/slide.pdf',
        ]);

        Storage::disk('school_uploads')->put($material->file_path, 'pdf-bytes');

        $this->actingAs($this->principal)
            ->deleteRoute('lessons.destroy', $lesson, typed: false)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
        $this->assertSame(0, LessonMaterial::query()->whereKey($material->id)->count(), 'A child row would be left orphaned.');
        Storage::disk('school_uploads')->assertMissing($material->file_path);
    }

    public function test_a_timetable_period_is_removed_for_good(): void
    {
        $slot = ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '09:45:00',
        ]);

        $this->deleteRoute('timetable.destroy', $slot, typed: false)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('class_routines', ['id' => $slot->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'class_routine.permanently_deleted']);
    }

    public function test_a_guardian_delete_removes_the_link_but_not_the_student(): void
    {
        $student = $this->makeStudent();

        $guardian = Guardian::query()->create([
            'name' => 'Kamal Jahan',
            'phone' => '01798765432',
            'relation' => 'father',
        ]);

        DB::table('student_guardians')->insert([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'is_primary' => true,
        ]);

        $this->deleteRoute('guardians.destroy', $guardian)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('guardians', ['id' => $guardian->id]);
        $this->assertSame(0, DB::table('student_guardians')->where('guardian_id', $guardian->id)->count());
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_a_class_that_still_holds_students_is_refused_with_a_reason(): void
    {
        $student = $this->makeStudent();

        $this->deleteRoute('academic.classes.destroy', $this->class);

        $this->assertStringContainsString('class enrollments', (string) session('error'));
        $this->assertDatabaseHas('classes', ['id' => $this->class->id]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertSame(1, Enrollment::query()->count(), 'A refused delete leaves nothing behind.');
    }

    public function test_a_class_with_nothing_in_it_is_deleted_with_its_sections(): void
    {
        $spare = SchoolClass::query()->create(['name' => 'Class Seven', 'level' => 7, 'is_active' => true]);

        $spareSection = Section::query()->create([
            'class_id' => $spare->id,
            'name' => 'B',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $this->deleteRoute('academic.classes.destroy', $spare)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(0, SchoolClass::withTrashed()->whereKey($spare->id)->count());
        $this->assertSame(0, Section::withTrashed()->whereKey($spareSection->id)->count(), 'Sections belong to the class.');
    }

    public function test_an_exam_that_already_holds_marks_cannot_be_deleted(): void
    {
        $student = $this->makeStudent();

        $exam = Exam::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Term test',
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-08',
        ]);

        $paper = ExamSubject::query()->create([
            'exam_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'full_marks' => 100,
        ]);

        ExamMark::query()->create([
            'exam_subject_id' => $paper->id,
            'student_id' => $student->id,
            'marks_obtained' => 78,
        ]);

        $this->deleteRoute('exams.destroy', $exam);

        $this->assertStringContainsString('exam marks', (string) session('error'));
        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
        $this->assertDatabaseHas('exam_marks', ['exam_subject_id' => $paper->id]);
    }

    public function test_a_draft_exam_with_only_its_papers_is_deleted_wholesale(): void
    {
        $exam = Exam::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Class test',
            'start_date' => '2026-04-02',
            'end_date' => '2026-04-02',
        ]);

        $paper = ExamSubject::query()->create([
            'exam_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'full_marks' => 50,
        ]);

        $this->deleteRoute('exams.destroy', $exam)->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
        $this->assertSame(0, ExamSubject::query()->whereKey($paper->id)->count(), 'Papers belong to the exam.');
        $this->assertDatabaseHas('subjects', ['id' => $this->subject->id]);
    }

    public function test_a_teacher_with_leave_history_is_refused_but_a_clean_one_goes_with_its_login(): void
    {
        $leaveType = LeaveType::query()->create(['name' => 'Sick leave', 'is_paid' => true]);

        $account = $this->makeUser($this->school, 'teacher');

        $teacher = Teacher::query()->create([
            'user_id' => $account->id,
            'employee_code' => 'T-01',
            'first_name' => 'Rina',
            'gender' => 'female',
            'phone' => '01711111111',
            'joining_date' => '2026-01-01',
        ]);

        StaffLeave::query()->create([
            'leave_type_id' => $leaveType->id,
            'teacher_id' => $teacher->id,
            'start_date' => '2026-02-10',
            'end_date' => '2026-02-11',
            'total_days' => 2,
            'reason' => 'Fever',
        ]);

        $this->deleteRoute('staff.teachers.destroy', $teacher);

        $this->assertStringContainsString('staff leave records', (string) session('error'));
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseHas('users', ['id' => $account->id]);

        $clean = Teacher::query()->create([
            'employee_code' => 'T-02',
            'first_name' => 'Mina',
            'gender' => 'female',
            'phone' => '01722222222',
            'joining_date' => '2026-01-01',
        ]);

        $this->deleteRoute('staff.teachers.destroy', $clean)->assertSessionHasNoErrors();

        $this->assertSame(0, Teacher::withTrashed()->whereKey($clean->id)->count());
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
    }

    public function test_financial_and_attendance_history_refuses_the_delete_button(): void
    {
        $student = $this->makeStudent();

        $invoice = FeeInvoice::query()->create([
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'invoice_no' => 'INV-2026-0001',
            'issue_date' => '2026-02-01',
            'due_date' => '2026-02-28',
            'status' => 'open',
        ]);

        $category = ExpenseCategory::query()->create(['name' => 'Utilities', 'code' => 'UTIL']);

        $expense = Expense::query()->create([
            'expense_category_id' => $category->id,
            'expense_no' => 'EXP-0001',
            'title' => 'Electricity bill',
            'amount' => 1200,
            'expense_date' => '2026-02-03',
        ]);

        $voucher = Voucher::query()->create([
            'voucher_no' => 'VCH-0001',
            'type' => 'payment',
            'voucher_date' => '2026-02-03',
            'amount' => 500,
            'description' => 'Stationery',
        ]);

        $attendance = StudentAttendance::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-02-02',
            'status' => 'present',
        ]);

        $records = [
            [$invoice, 'fee invoice'],
            [$expense, 'expense claim'],
            [$voucher, 'voucher'],
            [$attendance, 'attendance record'],
        ];

        foreach ($records as [$record, $label]) {
            try {
                $this->app->make(PermanentDeleteService::class)->delete($record, ['label' => $label]);
                $this->fail($label.' should never be destroyed by a delete button.');
            } catch (PermanentDeleteDenied $exception) {
                $this->assertStringContainsString('cancelled, reversed or adjusted', $exception->getMessage());
            }
        }

        $this->assertDatabaseHas('fee_invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);
        $this->assertDatabaseHas('student_attendances', ['id' => $attendance->id]);
    }

    public function test_a_delete_that_never_carries_the_typed_word_is_refused(): void
    {
        $spare = SchoolClass::query()->create(['name' => 'Class Eight', 'level' => 8, 'is_active' => true]);

        $this->deleteRoute('academic.classes.destroy', $spare, typed: false)
            ->assertSessionHas('error');

        $this->assertStringContainsString('Type DELETE', (string) session('error'));
        $this->assertDatabaseHas('classes', ['id' => $spare->id]);

        $this->deleteRoute('academic.classes.destroy', $spare)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('classes', ['id' => $spare->id]);
    }

    public function test_a_deleted_login_leaves_no_token_role_or_session_behind(): void
    {
        $account = $this->makeUser($this->school, 'teacher');

        $account->createToken('mobile')->plainTextToken;

        DB::table('sessions')->insert([
            'id' => 'session-for-'.$account->id,
            'user_id' => $account->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        DB::table('notifications')->insert([
            'id' => '01HZY0000000000000000000AB',
            'type' => 'App\\Notifications\\Reminder',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $account->id,
            'data' => json_encode(['body' => 'Fee due']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteRoute('users.destroy', $account)->assertSessionHasNoErrors();

        $this->assertSame(0, User::withTrashed()->whereKey($account->id)->count(), 'The account row must be gone.');
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $account->id)->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $account->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $account->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $account->id)->count());
    }

    public function test_a_holiday_and_a_subject_with_nothing_hanging_off_them_are_deleted(): void
    {
        $holiday = Holiday::query()->create([
            'name' => 'Pohela Boishakh',
            'start_date' => '2026-04-14',
            'end_date' => '2026-04-14',
        ]);

        $spare = Subject::query()->create(['name' => 'Physical education']);

        $this->deleteRoute('academic.holidays.destroy', $holiday, typed: false)->assertSessionHasNoErrors();
        $this->deleteRoute('academic.subjects.destroy', $spare)->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
        $this->assertDatabaseMissing('subjects', ['id' => $spare->id]);
        $this->assertDatabaseHas('subjects', ['id' => $this->subject->id]);
    }

    public function test_roles_without_the_permission_cannot_delete_at_all(): void
    {
        $notice = Notice::query()->create([
            'title' => 'Staff meeting',
            'body' => 'Room 2 at four.',
            'audience' => 'staff',
        ]);

        $teacher = $this->makeUser($this->school, 'teacher');
        $accountant = $this->makeUser($this->school, 'accountant');

        $guardian = Guardian::query()->create([
            'name' => 'Kamal Jahan',
            'phone' => '01798765432',
            'relation' => 'father',
        ]);

        $this->actingAs($teacher)
            ->delete(route('notices.destroy', $notice))
            ->assertForbidden();

        $this->actingAs($accountant)
            ->delete(route('guardians.destroy', $guardian))
            ->assertForbidden();

        $this->assertDatabaseHas('notices', ['id' => $notice->id]);
        $this->assertDatabaseHas('guardians', ['id' => $guardian->id]);
    }
}
