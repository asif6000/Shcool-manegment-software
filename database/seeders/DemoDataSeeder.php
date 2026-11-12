<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\ClassRoutine;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\FeeInvoice;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ExamResultService;
use App\Services\ExamService;
use App\Services\FeeInvoiceService;
use App\Services\FeePaymentService;
use App\Services\GradeService;
use App\Support\SchoolContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    /**
     * Local development data only. Credentials come from DEMO_SEED_PASSWORD
     * and are never used outside the local/testing environments.
     */
    public function run(): void
    {
        $password = (string) config('school.demo_password', 'Demo@1234');

        DB::transaction(function () use ($password): void {
            $school = $this->upsertSchool([
                'name' => 'EduNest Model School',
                'short_name' => 'EduNest',
                'code' => 'ENS',
                'slug' => 'edunest-model-school',
                'eiin' => '123456',
                'email' => 'info@edunest.test',
                'phone' => '01711000001',
                'phone_alt' => '025550100',
                'website' => 'https://edunest.test',
                'address' => 'House 12, Road 5, Dhanmondi',
                'division' => 'Dhaka',
                'district' => 'Dhaka',
                'upazila' => 'Dhanmondi',
                'postcode' => '1205',
                'motto' => 'Knowledge · Character · Service',
                'established_year' => 1998,
            ]);

            $this->seedSchool($school, $password, 28);

            app(SchoolContext::class)->clear();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertSchool(array $attributes): School
    {
        return School::query()->updateOrCreate(
            ['code' => $attributes['code']],
            $attributes + [
                'timezone' => 'Asia/Dhaka',
                'currency' => 'BDT',
                'locale' => 'en',
                'is_active' => true,
            ],
        );
    }

    private function upsertUser(
        ?string $email,
        string $name,
        ?School $school,
        string $role,
        string $password,
        ?string $mobileNumber = null,
        bool $mustChangePassword = false,
    ): User {
        // Find by email if provided, otherwise by mobile_number
        if ($email !== null) {
            $user = User::withTrashed()->firstOrNew(['email' => $email]);
        } elseif ($mobileNumber !== null) {
            $user = User::withTrashed()->firstOrNew(['mobile_number' => $mobileNumber]);
        } else {
            $user = new User;
        }

        $user->forceFill([
            'school_id' => $school?->id,
            'name' => $name,
            'email' => $email,
            'mobile_number' => $mobileNumber,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'password' => $password,
            'is_active' => true,
            'must_change_password' => $mustChangePassword,
            'deleted_at' => null,
        ])->save();

        $user->syncRoles([$role]);

        return $user;
    }

    private function seedSchool(School $school, string $password, int $studentCount): void
    {
        $context = app(SchoolContext::class);

        $context->run($school->id, function () use ($school, $password, $studentCount): void {
            // Create demo accounts with specific credentials as per requirements
            $admin = $this->upsertUser(
                'drms2027@gmail.com',
                'DRMS Admin',
                $school,
                'school_admin',
                'admin123',
                '01700000000',
                true,
            );

            $teacherUser = $this->upsertUser(
                null,
                'DRMS Teacher',
                $school,
                'teacher',
                'Teacher@2027',
                '01700000002',
                true,
            );

            $accountant = $this->upsertUser(
                null,
                'DRMS Accountant',
                $school,
                'accountant',
                'Accountant@2027',
                '01700000003',
                true,
            );

            $session = $this->seedAcademicStructure();
            $this->seedTeachers($teacherUser);
            $this->seedEmployees($accountant);
            $this->seedStudents($school, $admin, $session, $studentCount);
            $this->seedActivity($school, $admin, $admin);
            $this->seedExams($session, $admin);
            $this->seedClassRoutines($session, $teacherUser);

            $student = $this->seedStudentPortal($school, $session, 'Student@2027', '01700000001');

            if ($student !== null) {
                $this->seedAttendance($student, $session, $admin);
                $this->seedFees($student, $session, $accountant);
            }

            // Log created accounts
            $this->command?->info("  Admin login: drms2027@gmail.com (admin123)");
            $this->command?->info("  Teacher login: 01700000002 (Teacher@2027)");
            $this->command?->info("  Accountant login: 01700000003 (Accountant@2027)");
        });
    }

    private function seedAcademicStructure(): AcademicSession
    {
        $year = (int) now()->year;

        $previous = AcademicSession::query()->firstOrCreate(
            ['name' => (string) ($year - 1)],
            [
                'start_date' => ($year - 1).'-01-01',
                'end_date' => ($year - 1).'-12-31',
                'status' => 'completed',
                'is_current' => false,
            ],
        );

        $current = AcademicSession::query()->firstOrCreate(
            ['name' => (string) $year],
            [
                'start_date' => $year.'-01-01',
                'end_date' => $year.'-12-31',
                'status' => 'active',
                'is_current' => true,
            ],
        );

        if (! $current->is_current) {
            AcademicSession::query()->whereKeyNot($current->id)->update(['is_current' => false]);
            $current->forceFill(['is_current' => true, 'status' => 'active'])->save();
        }

        AcademicSession::query()->firstOrCreate(
            ['name' => (string) ($year + 1)],
            [
                'start_date' => ($year + 1).'-01-01',
                'end_date' => ($year + 1).'-12-31',
                'status' => 'upcoming',
                'is_current' => false,
            ],
        );

        $classDefinitions = [
            ['Play', 0],
            ['Nursery', 0],
            ['Class 1', 1],
            ['Class 2', 2],
            ['Class 3', 3],
            ['Class 4', 4],
            ['Class 5', 5],
            ['Class 6', 6],
            ['Class 7', 7],
            ['Class 8', 8],
        ];

        foreach ($classDefinitions as [$name, $level]) {
            $class = SchoolClass::query()->firstOrCreate(
                ['name' => $name],
                ['level' => $level, 'is_active' => true],
            );

            foreach (['A', 'B'] as $sectionName) {
                Section::query()->firstOrCreate(
                    ['class_id' => $class->id, 'name' => $sectionName],
                    ['capacity' => 45, 'is_active' => true],
                );
            }
        }

        $subjects = [
            ['Bangla', 'BAN', 'theory'],
            ['English', 'ENG', 'theory'],
            ['Mathematics', 'MAT', 'theory'],
            ['Science', 'SCI', 'theory'],
            ['Social Science', 'SSC', 'theory'],
            ['Religion', 'REL', 'theory'],
            ['ICT', 'ICT', 'practical'],
        ];

        foreach ($subjects as [$name, $code, $type]) {
            Subject::query()->firstOrCreate(
                ['name' => $name],
                ['code' => $code, 'type' => $type, 'is_active' => true],
            );
        }

        return $current;
    }

    private function seedTeachers(User $teacherUser): void
    {
        // Link the demo teacher user to the first teacher record
        Teacher::query()->updateOrCreate(
            ['employee_code' => 'TCH-1001'],
            [
                'user_id' => $teacherUser->id,
                'first_name' => 'DRMS',
                'last_name' => 'Teacher',
                'gender' => 'female',
                'date_of_birth' => now()->subYears(35)->toDateString(),
                'phone' => $teacherUser->mobile_number,
                'email' => 'teacher@drms.test',
                'designation' => 'Senior Teacher',
                'joining_date' => now()->subYears(3)->toDateString(),
                'qualification' => 'M.Ed',
                'specialization' => 'Mathematics',
                'employment_type' => 'full_time',
                'status' => 'active',
            ],
        );
    }

    private function seedClassRoutines(AcademicSession $session, User $teacherUser): void
    {
        $teacher = Teacher::query()->where('user_id', $teacherUser->id)->first();

        if ($teacher === null) {
            return;
        }

        $classes = SchoolClass::query()->where('is_active', true)->get();
        $mathSubject = Subject::query()->where('code', 'MAT')->first();

        if ($mathSubject === null) {
            return;
        }

        // Create class routines for the teacher - Mathematics class for Class 6 and 7
        foreach ($classes as $class) {
            if (! in_array($class->level, [6, 7], true)) {
                continue;
            }

            foreach ($class->sections as $section) {
                // Create 3 periods per week for each section
                $periods = [
                    ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '09:45', 'room' => 'Room 101'],
                    ['day_of_week' => 2, 'start_time' => '10:00', 'end_time' => '10:45', 'room' => 'Room 101'],
                    ['day_of_week' => 4, 'start_time' => '11:00', 'end_time' => '11:45', 'room' => 'Room 101'],
                ];

                foreach ($periods as $period) {
                    ClassRoutine::query()->firstOrCreate(
                        [
                            'academic_session_id' => $session->id,
                            'class_id' => $class->id,
                            'section_id' => $section->id,
                            'subject_id' => $mathSubject->id,
                            'teacher_id' => $teacher->id,
                            'day_of_week' => $period['day_of_week'],
                            'start_time' => $period['start_time'],
                        ],
                        [
                            'end_time' => $period['end_time'],
                            'room' => $period['room'],
                        ],
                    );
                }
            }
        }

        $this->command?->info('  Seeded class routines for teacher.');
    }

    private function seedEmployees(User $accountant): void
    {
        // Link the demo accountant user to the employee record
        Employee::query()->updateOrCreate(
            ['employee_code' => 'EMP-1001'],
            [
                'user_id' => $accountant->id,
                'first_name' => 'DRMS',
                'last_name' => 'Accountant',
                'gender' => 'female',
                'phone' => $accountant->mobile_number,
                'email' => 'accountant@drms.test',
                'designation' => 'Accountant',
                'department' => 'Accounts',
                'joining_date' => now()->subYears(4)->toDateString(),
                'employment_type' => 'full_time',
                'status' => 'active',
            ],
        );
    }

    private function seedStudents(School $school, User $admin, AcademicSession $session, int $count): void
    {
        if (Student::query()->exists()) {
            return;
        }

        $classes = SchoolClass::query()->orderBy('level')->orderBy('name')->get();

        if ($classes->isEmpty()) {
            return;
        }

        $sections = Section::query()
            ->whereIn('class_id', $classes->pluck('id'))
            ->orderBy('name')
            ->get()
            ->groupBy('class_id');

        $rollCounters = [];

        for ($index = 0; $index < $count; $index++) {
            $class = $classes[$index % $classes->count()];
            $classSections = $sections->get($class->id);

            if ($classSections === null || $classSections->isEmpty()) {
                continue;
            }

            $section = $classSections[$index % $classSections->count()];
            $rollCounters[$section->id] = ($rollCounters[$section->id] ?? 0) + 1;

            $admissionDate = now()
                ->subDays(fake()->numberBetween(0, 500))
                ->startOfDay();

            $student = Student::factory()->create([
                'admission_date' => $admissionDate->toDateString(),
                'created_by' => $admin->id,
            ]);

            Enrollment::query()->create([
                'student_id' => $student->id,
                'academic_session_id' => $session->id,
                'class_id' => $class->id,
                'section_id' => $section->id,
                'roll_no' => $rollCounters[$section->id],
                'enrolled_on' => $admissionDate->toDateString(),
                'status' => 'active',
            ]);

            $guardian = Guardian::query()->create([
                'name' => fake()->name('male'),
                'relation' => 'father',
                'phone' => '016'.fake()->numerify('########'),
                'email' => null,
                'occupation' => fake()->randomElement(['Business', 'Service', 'Teacher', 'Farmer', 'Doctor']),
                'address' => fake()->address(),
            ]);

            $student->guardians()->attach($guardian->id, [
                'school_id' => $school->id,
                'is_primary' => true,
                'is_emergency_contact' => true,
            ]);
        }

        $this->command?->info('  Seeded '.Student::query()->count()." students for {$school->name}.");
    }

    /**
     * The first active student gets a working portal login so the student
     * workspace can be reviewed locally without creating an account by hand.
     */
    private function seedStudentPortal(School $school, AcademicSession $session, string $password, string $mobileNumber): ?Student
    {
        /** @var Student|null $student */
        $student = Student::query()
            ->where('status', 'active')
            ->whereHas('enrollments', fn ($query) => $query
                ->where('academic_session_id', $session->id)
                ->where('status', 'active'))
            ->orderBy('id')
            ->first();

        if ($student === null) {
            return null;
        }

        $user = $this->upsertUser(
            null,
            'DRMS Student',
            $school,
            'student',
            $password,
            $mobileNumber,
            true,
        );

        Student::query()
            ->where('user_id', $user->id)
            ->whereKeyNot($student->id)
            ->update(['user_id' => null]);

        $student->forceFill(['user_id' => $user->id])->save();

        $this->command?->info("  Student portal login: {$mobileNumber} ({$student->student_id}).");

        return $student;
    }

    private function seedExams(AcademicSession $session, User $admin): void
    {
        $grades = app(GradeService::class);
        $grades->ensureDefaultScale();

        if (Exam::query()->exists()) {
            return;
        }

        $examService = app(ExamService::class);
        $resultService = app(ExamResultService::class);

        $start = Carbon::parse($session->start_date)->addMonths(2)->startOfDay();
        $end = $start->copy()->addDays(9);

        if ($end->gt($session->end_date)) {
            $end = Carbon::parse($session->end_date)->startOfDay();
            $start = $end->copy()->subDays(9);
        }

        $exam = $examService->create($admin, [
            'academic_session_id' => $session->id,
            'name' => 'First Term Examination',
            'exam_type' => 'term',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'description' => __('Demo exam generated by the local seeder.'),
        ]);

        $subjects = Subject::query()
            ->whereIn('code', ['BAN', 'ENG', 'MAT', 'SCI', 'SSC'])
            ->orderBy('id')
            ->get();

        $classIds = Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->distinct()
            ->pluck('class_id');

        $papers = [];

        foreach ($classIds as $classId) {
            foreach ($subjects as $subject) {
                $papers[] = $examService->addPaper($admin, $exam, [
                    'class_id' => $classId,
                    'subject_id' => $subject->id,
                    'exam_date' => $start->copy()->addDays($subject->id % 8)->toDateString(),
                    'start_time' => '10:00',
                    'end_time' => '13:00',
                    'full_marks' => 100,
                    'pass_marks' => 33,
                ]);
            }
        }

        foreach ($papers as $paper) {
            $enrollments = $examService->enrollments($exam, $paper->class_id);
            $rows = [];

            foreach ($enrollments as $enrollment) {
                $studentId = (int) $enrollment->student_id;
                $subjectId = (int) $paper->subject_id;
                $absent = ($studentId + $subjectId) % 23 === 0;
                $percentage = 38 + (($studentId * 7 + $subjectId * 13) % 58);

                $rows[(string) $studentId] = [
                    'marks_obtained' => $absent ? null : round(100 * $percentage / 100, 2),
                    'is_absent' => $absent,
                    'remarks' => null,
                ];
            }

            if ($rows !== []) {
                $examService->saveMarks($admin, $exam, $paper, ['marks' => $rows]);
            }
        }

        $count = $resultService->publish($admin, $exam);

        $this->command?->info("  Seeded exam results for {$count} students.");
    }

    private function seedAttendance(Student $student, AcademicSession $session, User $markedBy): void
    {
        $enrollment = $student->enrollments()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->first();

        if ($enrollment === null) {
            return;
        }

        $date = now()->startOfDay();
        $days = 0;

        while ($days < 30 && $date->gt(now()->subDays(60))) {
            if (! $date->isWeekend()) {
                $status = match (true) {
                    $days % 13 === 4 => 'absent',
                    $days % 17 === 6 => 'leave',
                    $days % 9 === 2 => 'late',
                    default => 'present',
                };

                StudentAttendance::query()->firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_date' => $date->toDateString(),
                    ],
                    [
                        'enrollment_id' => $enrollment->id,
                        'academic_session_id' => $session->id,
                        'class_id' => $enrollment->class_id,
                        'section_id' => $enrollment->section_id,
                        'status' => $status,
                        'late_minutes' => $status === 'late' ? 12 : 0,
                        'marked_by' => $markedBy->id,
                    ],
                );

                $days++;
            }

            $date->subDay();
        }
    }

    private function seedFees(Student $student, AcademicSession $session, User $accountant): void
    {
        $tuition = FeeType::query()->firstOrCreate(
            ['code' => 'TUI'],
            ['name' => 'Tuition Fee', 'frequency' => 'monthly', 'is_active' => true],
        );

        $exam = FeeType::query()->firstOrCreate(
            ['code' => 'EXM'],
            ['name' => 'Examination Fee', 'frequency' => 'one_time', 'is_active' => true],
        );

        foreach (SchoolClass::query()->orderBy('level')->get() as $class) {
            FeeStructure::query()->firstOrCreate(
                [
                    'academic_session_id' => $session->id,
                    'class_id' => $class->id,
                    'fee_type_id' => $tuition->id,
                ],
                [
                    'name' => 'Monthly tuition',
                    'amount' => $class->level >= 6 ? 2500 : 1800,
                    'due_day' => 10,
                    'is_active' => true,
                ],
            );

            FeeStructure::query()->firstOrCreate(
                [
                    'academic_session_id' => $session->id,
                    'class_id' => $class->id,
                    'fee_type_id' => $exam->id,
                ],
                [
                    'name' => 'Examination fee',
                    'amount' => 800,
                    'due_day' => 10,
                    'is_active' => true,
                ],
            );
        }

        if (FeeInvoice::query()->exists()) {
            return;
        }

        $enrollment = $student->enrollments()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->first();

        if ($enrollment === null) {
            return;
        }

        $invoiceService = app(FeeInvoiceService::class);
        $paymentService = app(FeePaymentService::class);

        $months = [
            ['month' => now()->subMonthNoOverflow()->startOfMonth(), 'types' => [$tuition->id, $exam->id], 'paid' => 1.0],
            ['month' => now()->startOfMonth(), 'types' => [$tuition->id], 'paid' => 0.5],
        ];

        foreach ($months as $index => $definition) {
            $month = $definition['month'];

            $result = $invoiceService->generate($accountant, [
                'academic_session_id' => $session->id,
                'class_id' => $enrollment->class_id,
                'section_id' => $enrollment->section_id,
                'fee_type_ids' => $definition['types'],
                'billing_period' => $month->format('Y-m'),
                'issue_date' => $month->toDateString(),
                'due_date' => $month->copy()->day(10)->toDateString(),
                'notes' => __('Demo invoice generated by the local seeder.'),
            ]);

            foreach ($result['invoices'] as $invoice) {
                $amount = round((float) $invoice->due_total * $definition['paid'], 2);

                if ($amount <= 0) {
                    continue;
                }

                $paymentService->collect($accountant, $invoice, [
                    'amount' => $amount,
                    'method' => $index === 0 ? 'cash' : 'bkash',
                    'transaction_ref' => $index === 0 ? null : 'BKASH-DEMO-'.strtoupper($month->format('Y-m')),
                    'paid_at' => $month->copy()->addDays(8)->setTime(10, 30)->toDateTimeString(),
                    'idempotency_key' => 'demo-'.strtolower($invoice->invoice_no),
                    'notes' => __('Demo payment generated by the local seeder.'),
                ]);
            }
        }

        $this->command?->info('  Seeded fee invoices, receipts and cash entries.');
    }

    private function seedActivity(School $school, User $admin, ?User $principal): void
    {
        if (AuditLog::query()->where('school_id', $school->id)->exists()) {
            return;
        }

        $entries = [
            ['school.updated', 'School profile was updated', 12],
            ['user.created', 'A new teacher account was created', 45],
            ['user.status_changed', 'Inactive account was re-activated', 130],
            ['role.permissions_updated', 'Role permissions were reviewed', 240],
        ];

        foreach ($entries as [$event, $description, $minutesAgo]) {
            AuditLog::query()->create([
                'school_id' => $school->id,
                'user_id' => $principal?->id ?? $admin->id,
                'event' => $event,
                'auditable_type' => School::class,
                'auditable_id' => $school->id,
                'description' => $description,
                'old_values' => null,
                'new_values' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Demo data seeder',
                'created_at' => Carbon::now()->subMinutes($minutesAgo),
            ]);
        }
    }
}
