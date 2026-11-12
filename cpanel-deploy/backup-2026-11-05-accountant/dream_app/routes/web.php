<?php

use App\Http\Controllers\AcademicController;
use App\Http\Controllers\AccountantFeeCollectionController;
use App\Http\Controllers\AccountantManagementController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\ApplicantAdmissionController;
use App\Http\Controllers\AttendanceMethodController;
use App\Http\Controllers\AttendanceQrController;
use App\Http\Controllers\AttendanceSubmissionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\StudentMobileAuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CashbookController;
use App\Http\Controllers\ClassVideoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamMarkController;
use App\Http\Controllers\ExamResultController;
use App\Http\Controllers\ExamSubjectController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FeeCollectionController;
use App\Http\Controllers\FeeDiscountController;
use App\Http\Controllers\FeeInvoiceController;
use App\Http\Controllers\FeeLedgerController;
use App\Http\Controllers\FeePaymentController;
use App\Http\Controllers\FeeStructureController;
use App\Http\Controllers\FeeTypeController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\GradeScaleController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\HomeBannerController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarkSubmissionController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicLiveController;
use App\Http\Controllers\PublicNoticeController;
use App\Http\Controllers\PublicResultController;
use App\Http\Controllers\PublicRoutineController;
use App\Http\Controllers\PublicTeacherController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShortcutDashboardController;
use App\Http\Controllers\StaffAttendanceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StudentAttendanceController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentIdCardController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudentLeaveController;
use App\Http\Controllers\StudentPortalAcademicController;
use App\Http\Controllers\StudentPortalAccountController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\StudentPortalHomeworkController;
use App\Http\Controllers\StudentPortalLeaveController;
use App\Http\Controllers\StudentPortalLessonController;
use App\Http\Controllers\StudentPortalMaterialController;
use App\Http\Controllers\StudentPortalResultController;
use App\Http\Controllers\StudentPromotionController;
use App\Http\Controllers\StudyMaterialController;
use App\Http\Controllers\TargetedNotificationController;
use App\Http\Controllers\TeacherHomeworkController;
use App\Http\Controllers\TeacherLeaveController;
use App\Http\Controllers\TeacherWorkController;
use App\Http\Controllers\TeacherWorkspaceController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WebPushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

// One installable PWA serves the public website and every signed-in portal.
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/manifest.json', [PwaController::class, 'manifest'])->name('pwa.manifest.json');
Route::get('/pwa/version', [PwaController::class, 'version'])->name('pwa.version');

// Public school website pages (no sign-in required).
Route::get('/website/live', PublicLiveController::class)
    ->middleware('throttle:website-live')
    ->name('public.live');
Route::get('/notices', [PublicNoticeController::class, 'index'])->name('public.notices.index');
Route::get('/verify/{student}', [StudentIdCardController::class, 'verify'])
    ->middleware('signed')
    ->whereNumber('student')
    ->name('student.verify');
Route::get('/verify/{student}/photo', [StudentIdCardController::class, 'photo'])
    ->middleware('signed')
    ->whereNumber('student')
    ->name('student.verify.photo');
Route::get('/notices/{notice}', [PublicNoticeController::class, 'show'])
    ->whereNumber('notice')
    ->name('public.notices.show');
Route::get('/results', [PublicResultController::class, 'index'])->middleware('throttle:30,1')->name('public.results.index');
Route::get('/teachers', [PublicTeacherController::class, 'index'])->name('public.teachers.index');
Route::get('/routines', [PublicRoutineController::class, 'index'])->name('public.routines.index');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:signup')
        ->name('register.store');

    // Student account sign-in: mobile number + password only.
    Route::get('/student/login', [StudentMobileAuthController::class, 'loginForm'])
        ->name('student.login');
    Route::post('/student/login', [StudentMobileAuthController::class, 'login'])
        ->name('student.login.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active.account', 'school.context', 'password.changed'])
    ->group(function (): void {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])
            ->name('notifications.preferences.update');

        Route::post('/notifications/push/subscribe', [WebPushSubscriptionController::class, 'store'])
            ->middleware('throttle:push-subscribe')
            ->name('notifications.push.subscribe');
        Route::delete('/notifications/push/unsubscribe', [WebPushSubscriptionController::class, 'destroy'])
            ->name('notifications.push.unsubscribe');
    });

Route::middleware(['auth', 'active.account', 'school.context', 'password.changed', 'admission.applicant'])
    ->prefix('admissions')
    ->name('applicant.')
    ->group(function (): void {
        Route::get('/', [ApplicantAdmissionController::class, 'dashboard'])->name('dashboard');
        Route::get('/apply', [ApplicantAdmissionController::class, 'create'])->name('apply');
        Route::post('/apply', [ApplicantAdmissionController::class, 'store'])
            ->middleware('throttle:admission-apply')
            ->name('apply.store');

        Route::get('/{admission}', [ApplicantAdmissionController::class, 'show'])
            ->whereNumber('admission')
            ->name('show');
        Route::get('/{admission}/photo', [ApplicantAdmissionController::class, 'photo'])
            ->whereNumber('admission')
            ->name('photo');

        Route::get('/password/change', [ApplicantAdmissionController::class, 'password'])->name('password.change');
        Route::put('/password', [ApplicantAdmissionController::class, 'updatePassword'])->name('password.update');

        Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
    });

Route::middleware(['auth', 'active.account', 'school.context', 'password.changed', 'student.portal'])
    ->prefix('student')
    ->name('student.')
    ->group(function (): void {
        Route::get('/', [StudentPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [StudentPortalController::class, 'profile'])->name('profile');
        Route::get('/photo', [StudentPortalController::class, 'photo'])->name('photo');
        Route::post('/photo', [StudentPortalController::class, 'storePhoto'])
            ->middleware('throttle:profile-photo')
            ->name('photo.store');
        Route::delete('/photo', [StudentPortalController::class, 'destroyPhoto'])->name('photo.destroy');
        Route::get('/attendance', [StudentPortalController::class, 'attendance'])->name('attendance');

        Route::get('/routine', [StudentPortalAcademicController::class, 'routine'])->name('routine');
        Route::get('/calendar', [StudentPortalAcademicController::class, 'calendar'])->name('calendar');

        Route::get('/notices', [StudentPortalAcademicController::class, 'notices'])->name('notices.index');
        Route::get('/notices/{notice}', [StudentPortalAcademicController::class, 'notice'])
            ->whereNumber('notice')
            ->name('notices.show');

        Route::prefix('homework')->name('homework.')->group(function (): void {
            Route::get('/', [StudentPortalHomeworkController::class, 'index'])->name('index');
            Route::get('/{homework}', [StudentPortalHomeworkController::class, 'show'])
                ->whereNumber('homework')
                ->name('show');
            Route::post('/{homework}/submit', [StudentPortalHomeworkController::class, 'submit'])
                ->whereNumber('homework')
                ->middleware('throttle:homework-submit')
                ->name('submit');
            Route::get('/{homework}/attachment', [StudentPortalHomeworkController::class, 'attachment'])
                ->whereNumber('homework')
                ->name('attachment');
            Route::get('/submissions/{submission}/file', [StudentPortalHomeworkController::class, 'submissionFile'])
                ->whereNumber('submission')
                ->name('submission.file');
        });

        Route::prefix('materials')->name('materials.')->group(function (): void {
            Route::get('/', [StudentPortalMaterialController::class, 'index'])->name('index');
            Route::get('/{material}/file', [StudentPortalMaterialController::class, 'file'])
                ->whereNumber('material')->name('file');
        });

        Route::prefix('lessons')->name('lessons.')->group(function (): void {
            Route::get('/', [StudentPortalLessonController::class, 'index'])->name('index');
            Route::get('/{lesson}', [StudentPortalLessonController::class, 'show'])
                ->whereNumber('lesson')->name('show');
            Route::get('/{lesson}/materials/{material}/file', [StudentPortalLessonController::class, 'materialFile'])
                ->whereNumber('lesson')->whereNumber('material')->name('materials.file');
        });

        Route::prefix('leave')->name('leave.')->group(function (): void {
            Route::get('/', [StudentPortalLeaveController::class, 'index'])->name('index');
            Route::post('/', [StudentPortalLeaveController::class, 'store'])
                ->middleware('throttle:student-leave')
                ->name('store');
            Route::delete('/{leave}', [StudentPortalLeaveController::class, 'cancel'])
                ->whereNumber('leave')
                ->name('cancel');
        });

        Route::get('/fees', [StudentPortalController::class, 'fees'])->name('fees.index');
        Route::get('/fees/{invoice}', [StudentPortalController::class, 'invoice'])
            ->whereNumber('invoice')
            ->name('fees.show');
        Route::get('/payments/{payment}/receipt', [StudentPortalController::class, 'receipt'])
            ->whereNumber('payment')
            ->name('fees.receipt');

        Route::get('/results', [StudentPortalResultController::class, 'index'])->name('results.index');
        Route::get('/results/{exam}', [StudentPortalResultController::class, 'show'])
            ->whereNumber('exam')
            ->name('results.show');

        Route::get('/password', [StudentPortalController::class, 'password'])->name('password.change');
        Route::put('/password', [StudentPortalController::class, 'updatePassword'])->name('password.update');

        Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
    });

Route::middleware(['auth', 'active.account', 'school.context', 'password.changed', 'staff.workspace'])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/', fn () => redirect()->route('dashboard'));

        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/shortcuts', [ShortcutDashboardController::class, 'index'])->name('shortcuts.index');
        Route::get('/dashboard/results', [DashboardController::class, 'results'])->name('dashboard.results');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/password', [ProfileController::class, 'password'])->name('profile.password.change');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
        Route::post('/users/{user}/reset-link', [UserController::class, 'sendResetLink'])->name('users.reset-link');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('/roles/permissions', [RoleController::class, 'update'])->name('roles.permissions.update');

        Route::get('/school', [SchoolController::class, 'index'])->name('schools.index');
        Route::get('/school/profile', [SchoolController::class, 'show'])->name('schools.show');
        Route::get('/school/profile/edit', [SchoolController::class, 'edit'])->name('schools.edit');
        Route::put('/school/profile', [SchoolController::class, 'update'])->name('schools.update');

        Route::get('/banner', [HomeBannerController::class, 'edit'])->name('banner.edit');
        Route::post('/banner', [HomeBannerController::class, 'update'])->name('banner.update');
        Route::delete('/banner', [HomeBannerController::class, 'destroy'])->name('banner.destroy');

        Route::get('/academic', [AcademicController::class, 'index'])->name('academic.index');

        Route::post('/academic/sessions', [AcademicController::class, 'storeSession'])->name('academic.sessions.store');
        Route::put('/academic/sessions/{session}', [AcademicController::class, 'updateSession'])->name('academic.sessions.update');
        Route::patch('/academic/sessions/{session}/current', [AcademicController::class, 'setCurrentSession'])->name('academic.sessions.current');
        Route::delete('/academic/sessions/{session}', [AcademicController::class, 'destroySession'])->name('academic.sessions.destroy');

        Route::post('/academic/classes', [AcademicController::class, 'storeClass'])->name('academic.classes.store');
        Route::put('/academic/classes/{class}', [AcademicController::class, 'updateClass'])->name('academic.classes.update');
        Route::delete('/academic/classes/{class}', [AcademicController::class, 'destroyClass'])->name('academic.classes.destroy');

        Route::post('/academic/sections', [AcademicController::class, 'storeSection'])->name('academic.sections.store');
        Route::put('/academic/sections/{section}', [AcademicController::class, 'updateSection'])->name('academic.sections.update');
        Route::delete('/academic/sections/{section}', [AcademicController::class, 'destroySection'])->name('academic.sections.destroy');

        Route::post('/academic/subjects', [AcademicController::class, 'storeSubject'])->name('academic.subjects.store');
        Route::put('/academic/subjects/{subject}', [AcademicController::class, 'updateSubject'])->name('academic.subjects.update');
        Route::delete('/academic/subjects/{subject}', [AcademicController::class, 'destroySubject'])->name('academic.subjects.destroy');

        Route::post('/academic/curriculum', [AcademicController::class, 'updateCurriculum'])->name('academic.curriculum.update');

        Route::post('/academic/holidays', [AcademicController::class, 'storeHoliday'])->name('academic.holidays.store');
        Route::put('/academic/holidays/{holiday}', [AcademicController::class, 'updateHoliday'])->name('academic.holidays.update');
        Route::delete('/academic/holidays/{holiday}', [AcademicController::class, 'destroyHoliday'])->name('academic.holidays.destroy');

        Route::prefix('academic/promotions')->name('promotions.')->group(function (): void {
            Route::get('/', [StudentPromotionController::class, 'index'])->name('index');
            Route::post('/preview', [StudentPromotionController::class, 'preview'])->name('preview');
            Route::post('/', [StudentPromotionController::class, 'store'])->name('store');
            Route::get('/report', [StudentPromotionController::class, 'report'])->name('report');
            Route::get('/report/export', [StudentPromotionController::class, 'export'])->name('report.export');
        });

        Route::get('/students/export', [StudentController::class, 'export'])->name('students.export');
        Route::get('/students/import', [StudentImportController::class, 'form'])->name('student_import.index');
        Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('student_import.template');
        Route::post('/students/import/preview', [StudentImportController::class, 'preview'])->name('student_import.preview');
        Route::post('/students/import', [StudentImportController::class, 'store'])->name('student_import.store');
        Route::get('/students/id-cards', [StudentIdCardController::class, 'sheet'])->name('students.idcards.sheet');
        Route::get('/students/{student}/id-card', [StudentIdCardController::class, 'card'])->name('students.idcards.card');
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
        Route::get('/students/{student}/photo', [StudentController::class, 'photo'])->name('students.photo');
        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::patch('/students/{student}/archive', [StudentController::class, 'archive'])->name('students.archive');
        Route::delete('/students/{student}/login', [StudentController::class, 'destroyLoginAccount'])->name('students.login.destroy');
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])
            ->whereNumber('student')
            ->name('students.destroy');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::post('/students/{student}/enrollments', [StudentController::class, 'storeEnrollment'])->name('students.enrollments.store');
        Route::put('/students/{student}/enrollments/{enrollment}', [StudentController::class, 'updateEnrollment'])->name('students.enrollments.update');

        Route::post('/students/{student}/portal', [StudentPortalAccountController::class, 'store'])->name('students.portal.store');
        Route::post('/students/{student}/portal/reset', [StudentPortalAccountController::class, 'reset'])->name('students.portal.reset');
        Route::patch('/students/{student}/portal/status', [StudentPortalAccountController::class, 'status'])->name('students.portal.status');

        Route::get('/guardians', [GuardianController::class, 'index'])->name('guardians.index');
        Route::delete('/guardians/{guardian}', [GuardianController::class, 'destroy'])
            ->whereNumber('guardian')
            ->name('guardians.destroy');

        Route::get('/admissions', [AdmissionController::class, 'index'])->name('admissions.index');
        Route::get('/admissions/create', [AdmissionController::class, 'create'])->name('admissions.create');
        Route::post('/admissions', [AdmissionController::class, 'store'])->name('admissions.store');
        Route::get('/admissions/{admission}/edit', [AdmissionController::class, 'edit'])->name('admissions.edit');
        Route::put('/admissions/{admission}', [AdmissionController::class, 'update'])->name('admissions.update');
        Route::post('/admissions/{admission}/approve', [AdmissionController::class, 'approve'])->name('admissions.approve');
        Route::post('/admissions/{admission}/status', [AdmissionController::class, 'updateStatus'])->name('admissions.status');
        Route::post('/admissions/{admission}/sections', [AdmissionController::class, 'storeSection'])->name('admissions.sections.store');
        Route::post('/admissions/{admission}/reject', [AdmissionController::class, 'reject'])->name('admissions.reject');
        Route::get('/admissions/{admission}/photo', [AdmissionController::class, 'photo'])->whereNumber('admission')->name('admissions.photo');
        Route::get('/admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');

        Route::prefix('notices')->name('notices.')->group(function (): void {
            Route::get('/', [NoticeController::class, 'index'])->name('index');
            Route::get('/create', [NoticeController::class, 'create'])->name('create');
            Route::post('/', [NoticeController::class, 'store'])->name('store');
            Route::get('/{notice}/edit', [NoticeController::class, 'edit'])->whereNumber('notice')->name('edit');
            Route::put('/{notice}', [NoticeController::class, 'update'])->whereNumber('notice')->name('update');
            Route::post('/{notice}/publish', [NoticeController::class, 'publish'])->whereNumber('notice')->name('publish');
            Route::post('/{notice}/unpublish', [NoticeController::class, 'unpublish'])->whereNumber('notice')->name('unpublish');
            Route::patch('/{notice}/pin', [NoticeController::class, 'togglePin'])->whereNumber('notice')->name('pin');
            Route::delete('/{notice}', [NoticeController::class, 'destroy'])->whereNumber('notice')->name('destroy');
        });

        Route::get('/notifications/compose', [TargetedNotificationController::class, 'create'])
            ->name('notifications.compose');
        Route::post('/notifications/compose', [TargetedNotificationController::class, 'store'])
            ->name('notifications.compose.store');

        Route::prefix('timetable')->name('timetable.')->group(function (): void {
            Route::get('/', [TimetableController::class, 'index'])->name('index');
            Route::post('/', [TimetableController::class, 'store'])->name('store');
            Route::put('/{slot}', [TimetableController::class, 'update'])->whereNumber('slot')->name('update');
            Route::delete('/{slot}', [TimetableController::class, 'destroy'])->whereNumber('slot')->name('destroy');
        });

        Route::prefix('my')->name('teacher.')->group(function (): void {
            Route::get('/profile', [TeacherWorkspaceController::class, 'profile'])->name('profile');
            Route::get('/profile/photo', [TeacherWorkspaceController::class, 'photo'])->name('profile.photo');
            Route::post('/profile/photo', [TeacherWorkspaceController::class, 'storePhoto'])
                ->middleware('throttle:profile-photo')
                ->name('profile.photo.store');
            Route::delete('/profile/photo', [TeacherWorkspaceController::class, 'destroyPhoto'])->name('profile.photo.destroy');
            Route::get('/classes', [TeacherWorkspaceController::class, 'classes'])->name('classes');
            Route::get('/routine', [TeacherWorkspaceController::class, 'routine'])->name('routine');
            Route::get('/leave', [TeacherLeaveController::class, 'index'])->name('leave.index');
            Route::post('/leave', [TeacherLeaveController::class, 'store'])->name('leave.store');
            Route::get('/work/create', [TeacherWorkController::class, 'create'])->name('work.create');
            Route::post('/work', [TeacherWorkController::class, 'store'])->name('work.store');
        });

        Route::prefix('homework')->name('teacher.homework.')->group(function (): void {
        // Homework belongs to teachers. They are limited to the periods on their
        // own timetable; the school admin and principal are refused.
            Route::get('/', [TeacherHomeworkController::class, 'index'])->name('index');
            Route::get('/create', [TeacherHomeworkController::class, 'create'])->name('create');
            Route::post('/', [TeacherHomeworkController::class, 'store'])->name('store');
            Route::get('/submissions/{submission}/file', [TeacherHomeworkController::class, 'submissionFile'])
                ->whereNumber('submission')->name('submissions.file');
            Route::post('/submissions/{submission}/review', [TeacherHomeworkController::class, 'review'])
                ->whereNumber('submission')->name('submissions.review');
            Route::post('/{homework}/publish', [TeacherHomeworkController::class, 'publish'])
                ->whereNumber('homework')->name('publish');
            Route::post('/{homework}/unpublish', [TeacherHomeworkController::class, 'unpublish'])
                ->whereNumber('homework')->name('unpublish');
            Route::post('/{homework}/cancel', [TeacherHomeworkController::class, 'cancel'])
                ->whereNumber('homework')->name('cancel');
            Route::get('/{homework}/edit', [TeacherHomeworkController::class, 'edit'])->whereNumber('homework')->name('edit');
            Route::put('/{homework}', [TeacherHomeworkController::class, 'update'])->whereNumber('homework')->name('update');
            Route::get('/{homework}/attachment', [TeacherHomeworkController::class, 'attachment'])
                ->whereNumber('homework')->name('attachment');
            Route::delete('/{homework}', [TeacherHomeworkController::class, 'destroy'])->whereNumber('homework')->name('destroy');
            Route::get('/{homework}', [TeacherHomeworkController::class, 'show'])->whereNumber('homework')->name('show');
        });

        Route::prefix('materials')->name('materials.')->group(function (): void {
            Route::get('/', [StudyMaterialController::class, 'index'])->name('index');
            Route::get('/create', [StudyMaterialController::class, 'create'])->name('create');
            Route::post('/', [StudyMaterialController::class, 'store'])->name('store');
            Route::post('/{material}/publish', [StudyMaterialController::class, 'publish'])
                ->whereNumber('material')->name('publish');
            Route::post('/{material}/unpublish', [StudyMaterialController::class, 'unpublish'])
                ->whereNumber('material')->name('unpublish');
            Route::get('/{material}/edit', [StudyMaterialController::class, 'edit'])
                ->whereNumber('material')->name('edit');
            Route::put('/{material}', [StudyMaterialController::class, 'update'])
                ->whereNumber('material')->name('update');
            Route::get('/{material}/file', [StudyMaterialController::class, 'file'])
                ->whereNumber('material')->name('file');
            Route::delete('/{material}', [StudyMaterialController::class, 'destroy'])
                ->whereNumber('material')->name('destroy');
        });

        Route::prefix('lessons')->name('lessons.')->group(function (): void {
            Route::get('/', [LessonController::class, 'index'])->name('index');
            Route::get('/create', [LessonController::class, 'create'])->name('create');
            Route::post('/', [LessonController::class, 'store'])->name('store');
            Route::post('/images', [LessonController::class, 'uploadImage'])->name('images.store');
            Route::get('/{lesson}/edit', [LessonController::class, 'edit'])
                ->whereNumber('lesson')->name('edit');
            Route::put('/{lesson}', [LessonController::class, 'update'])
                ->whereNumber('lesson')->name('update');
            Route::post('/{lesson}/publish', [LessonController::class, 'publish'])
                ->whereNumber('lesson')->name('publish');
            Route::post('/{lesson}/unpublish', [LessonController::class, 'unpublish'])
                ->whereNumber('lesson')->name('unpublish');
            Route::get('/{lesson}/materials/{material}/file', [LessonController::class, 'materialFile'])
                ->whereNumber('lesson')->whereNumber('material')->name('materials.file');
            Route::delete('/{lesson}', [LessonController::class, 'destroy'])
                ->whereNumber('lesson')->name('destroy');
            Route::get('/{lesson}', [LessonController::class, 'show'])
                ->whereNumber('lesson')->name('show');
        });

        Route::prefix('class-videos')->name('videos.')->group(function (): void {
            Route::get('/', [ClassVideoController::class, 'index'])->name('index');
            Route::get('/create', [ClassVideoController::class, 'create'])->name('create');
            Route::post('/', [ClassVideoController::class, 'store'])->name('store');
            Route::get('/{video}/edit', [ClassVideoController::class, 'edit'])
                ->whereNumber('video')->name('edit');
            Route::put('/{video}', [ClassVideoController::class, 'update'])
                ->whereNumber('video')->name('update');
            Route::post('/{video}/website', [ClassVideoController::class, 'toggleWebsite'])
                ->whereNumber('video')->name('website');
            Route::delete('/{video}', [ClassVideoController::class, 'destroy'])
                ->whereNumber('video')->name('destroy');
        });

        Route::get('/student-leaves', [StudentLeaveController::class, 'index'])->name('student_leaves.index');
        Route::post('/student-leaves/{leave}/decide', [StudentLeaveController::class, 'decide'])
            ->whereNumber('leave')->name('student_leaves.decide');

        Route::prefix('staff')->name('staff.')->group(function (): void {
            Route::get('/', [StaffController::class, 'index'])->name('index');

            Route::get('/teachers/create', [StaffController::class, 'createTeacher'])->name('teachers.create');
            Route::post('/teachers', [StaffController::class, 'storeTeacher'])->name('teachers.store');
            Route::get('/teachers/{teacher}/photo', [StaffController::class, 'teacherPhoto'])->name('teachers.photo');
            Route::get('/teachers/{teacher}/edit', [StaffController::class, 'editTeacher'])->name('teachers.edit');
            Route::put('/teachers/{teacher}', [StaffController::class, 'updateTeacher'])->name('teachers.update');
            Route::delete('/teachers/{teacher}', [StaffController::class, 'destroyTeacher'])->name('teachers.destroy');
            Route::get('/teachers/{teacher}', [StaffController::class, 'showTeacher'])->name('teachers.show');

            Route::get('/employees/create', [StaffController::class, 'createEmployee'])->name('employees.create');
            Route::post('/employees', [StaffController::class, 'storeEmployee'])->name('employees.store');
            Route::get('/employees/{employee}/photo', [StaffController::class, 'employeePhoto'])->name('employees.photo');
            Route::get('/employees/{employee}/edit', [StaffController::class, 'editEmployee'])->name('employees.edit');
            Route::put('/employees/{employee}', [StaffController::class, 'updateEmployee'])->name('employees.update');
            Route::delete('/employees/{employee}', [StaffController::class, 'destroyEmployee'])->name('employees.destroy');
            Route::get('/employees/{employee}', [StaffController::class, 'showEmployee'])->name('employees.show');

            Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
            Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
            Route::post('/leaves/{staffLeave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
            Route::post('/leaves/{staffLeave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');

            Route::post('/leave-types', [LeaveController::class, 'storeType'])->name('leave-types.store');
            Route::put('/leave-types/{leaveType}', [LeaveController::class, 'updateType'])->name('leave-types.update');
            Route::delete('/leave-types/{leaveType}', [LeaveController::class, 'destroyType'])->name('leave-types.destroy');

            Route::get('/salary', [SalaryController::class, 'index'])->name('salary.index');
            Route::post('/salary', [SalaryController::class, 'store'])->name('salary.store');
            Route::put('/salary/{salaryStructure}', [SalaryController::class, 'update'])->name('salary.update');
            Route::delete('/salary/{salaryStructure}', [SalaryController::class, 'destroy'])->name('salary.destroy');
        });

        Route::prefix('attendance')->name('attendance.')->group(function (): void {
            Route::get('/students', [StudentAttendanceController::class, 'index'])->name('students.index');
            Route::post('/students', [StudentAttendanceController::class, 'store'])->name('students.store');
            Route::get('/students/report', [StudentAttendanceController::class, 'report'])->name('students.report');
            Route::get('/students/report/export', [StudentAttendanceController::class, 'export'])->name('students.export');

            Route::get('/submissions', [AttendanceSubmissionController::class, 'index'])->name('submissions.index');
            Route::post('/submissions', [AttendanceSubmissionController::class, 'store'])->name('submissions.store');
            Route::post('/submissions/{submission}/{action}', [AttendanceSubmissionController::class, 'decide'])
                ->whereNumber('submission')
                ->whereIn('action', ['approve', 'reject'])
                ->name('submissions.decide');

            Route::get('/staff', [StaffAttendanceController::class, 'index'])->name('staff.index');
            Route::post('/staff', [StaffAttendanceController::class, 'store'])->name('staff.store');

            Route::get('/qr', [AttendanceQrController::class, 'index'])->name('qr.index');
            Route::post('/qr/scan', [AttendanceQrController::class, 'scan'])
                ->middleware('throttle:attendance-scan')
                ->name('qr.scan');

            Route::post('/cards/{student}', [AttendanceQrController::class, 'issue'])
                ->whereNumber('student')
                ->name('cards.issue');
            Route::get('/cards/{student}/print', [AttendanceQrController::class, 'printCard'])
                ->whereNumber('student')
                ->name('cards.print');
            Route::post('/cards/{card}/revoke', [AttendanceQrController::class, 'revoke'])
                ->whereNumber('card')
                ->name('cards.revoke');

            Route::get('/methods', [AttendanceMethodController::class, 'index'])->name('methods.index');
            Route::post('/methods/devices', [AttendanceMethodController::class, 'register'])->name('methods.devices.register');
            Route::post('/methods/devices/{device}/rotate', [AttendanceMethodController::class, 'rotate'])
                ->whereNumber('device')
                ->name('methods.devices.rotate');
            Route::post('/methods/devices/{device}/toggle', [AttendanceMethodController::class, 'toggle'])
                ->whereNumber('device')
                ->name('methods.devices.toggle');
            Route::post('/methods/devices/{device}/mappings', [AttendanceMethodController::class, 'mapUser'])
                ->whereNumber('device')
                ->name('methods.devices.map');
            Route::delete('/methods/devices/{device}/mappings/{mapping}', [AttendanceMethodController::class, 'unmapUser'])
                ->whereNumber('device')
                ->whereNumber('mapping')
                ->name('methods.devices.unmap');
        });

        Route::prefix('fees')->name('fees.')->group(function (): void {
            Route::get('/', [FeeInvoiceController::class, 'index'])->name('index');
            Route::get('/create', [FeeInvoiceController::class, 'create'])->name('create');
            Route::post('/generate', [FeeInvoiceController::class, 'generate'])->name('generate');
            Route::get('/export', [FeeInvoiceController::class, 'export'])->name('export');
            Route::get('/dues', [FeePaymentController::class, 'dues'])->name('dues');
            Route::get('/counter', [AccountantFeeCollectionController::class, 'index'])->name('counter');
            Route::post('/counter', [AccountantFeeCollectionController::class, 'store'])->name('counter.store');
            Route::get('/collect', [FeeCollectionController::class, 'index'])->name('collect');
            Route::post('/collect', [FeeCollectionController::class, 'store'])->name('collect.store');
            Route::get('/ledger', [FeeLedgerController::class, 'index'])->name('ledger');
            Route::get('/ledger/export', [FeeLedgerController::class, 'export'])->name('ledger.export');
            Route::get('/receipts', [FeePaymentController::class, 'receipts'])->name('receipts');
            Route::get('/verifications', [FeePaymentController::class, 'verifications'])->name('verifications');

            Route::get('/discounts', [FeeDiscountController::class, 'index'])->name('discounts.index');
            Route::post('/discounts/{discount}/approve', [FeeDiscountController::class, 'approve'])
                ->whereNumber('discount')
                ->name('discounts.approve');
            Route::post('/discounts/{discount}/reject', [FeeDiscountController::class, 'reject'])
                ->whereNumber('discount')
                ->name('discounts.reject');

            Route::get('/types', [FeeTypeController::class, 'index'])->name('types.index');
            Route::post('/types', [FeeTypeController::class, 'store'])->name('types.store');
            Route::put('/types/{feeType}', [FeeTypeController::class, 'update'])
                ->whereNumber('feeType')
                ->name('types.update');
            Route::post('/types/{feeType}/toggle', [FeeTypeController::class, 'toggle'])
                ->whereNumber('feeType')
                ->name('types.toggle');

            Route::get('/structures', [FeeStructureController::class, 'index'])->name('structures.index');
            Route::post('/structures', [FeeStructureController::class, 'store'])->name('structures.store');
            Route::post('/structures/copy', [FeeStructureController::class, 'copy'])->name('structures.copy');
            Route::put('/structures/{feeStructure}', [FeeStructureController::class, 'update'])
                ->whereNumber('feeStructure')
                ->name('structures.update');
            Route::post('/structures/{feeStructure}/toggle', [FeeStructureController::class, 'toggle'])
                ->whereNumber('feeStructure')
                ->name('structures.toggle');

            Route::post('/payments/{payment}/verify', [FeePaymentController::class, 'verify'])
                ->whereNumber('payment')
                ->name('payments.verify');
            Route::post('/payments/{payment}/reject-verification', [FeePaymentController::class, 'rejectVerification'])
                ->whereNumber('payment')
                ->name('payments.reject-verification');

            Route::get('/payments/{payment}/receipt', [FeePaymentController::class, 'receipt'])
                ->whereNumber('payment')
                ->name('receipt');
            Route::post('/payments/{payment}/reverse', [FeePaymentController::class, 'reverse'])
                ->whereNumber('payment')
                ->name('payments.reverse');

            Route::get('/{invoice}', [FeeInvoiceController::class, 'show'])
                ->whereNumber('invoice')
                ->name('show');
            Route::post('/{invoice}/cancel', [FeeInvoiceController::class, 'cancel'])
                ->whereNumber('invoice')
                ->name('cancel');
            Route::post('/{invoice}/discounts', [FeeInvoiceController::class, 'storeDiscount'])
                ->whereNumber('invoice')
                ->name('discounts.store');
            Route::delete('/{invoice}/discounts/{discount}', [FeeInvoiceController::class, 'destroyDiscount'])
                ->whereNumber('invoice')
                ->whereNumber('discount')
                ->name('discounts.destroy');
            Route::post('/{invoice}/payments', [FeePaymentController::class, 'store'])
                ->whereNumber('invoice')
                ->name('payments.store');
        });

        Route::prefix('expenses')->name('expenses.')->group(function (): void {
            Route::get('/', [ExpenseController::class, 'index'])->name('index');
            Route::get('/create', [ExpenseController::class, 'create'])->name('create');
            Route::post('/', [ExpenseController::class, 'store'])->name('store');
            Route::get('/export', [ExpenseController::class, 'export'])->name('export');

            Route::get('/categories', [ExpenseCategoryController::class, 'index'])->name('categories');
            Route::post('/categories', [ExpenseCategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [ExpenseCategoryController::class, 'update'])
                ->whereNumber('category')
                ->name('categories.update');

            Route::get('/{expense}', [ExpenseController::class, 'show'])
                ->whereNumber('expense')
                ->name('show');
            Route::post('/{expense}/approve', [ExpenseController::class, 'approve'])
                ->whereNumber('expense')
                ->name('approve');
            Route::post('/{expense}/reject', [ExpenseController::class, 'reject'])
                ->whereNumber('expense')
                ->name('reject');
            Route::post('/{expense}/cancel', [ExpenseController::class, 'cancel'])
                ->whereNumber('expense')
                ->name('cancel');
        });

        Route::prefix('cashbook')->name('cashbook.')->group(function (): void {
            Route::get('/', [CashbookController::class, 'index'])->name('index');
            Route::get('/closing', [CashbookController::class, 'closing'])->name('closing');
            Route::get('/export', [CashbookController::class, 'export'])->name('export');
            Route::post('/{transaction}/reconcile', [CashbookController::class, 'reconcile'])
                ->whereNumber('transaction')
                ->name('reconcile');
        });

        Route::prefix('bank')->name('bank.')->group(function (): void {
            Route::get('/', [BankAccountController::class, 'index'])->name('index');
            Route::post('/', [BankAccountController::class, 'store'])->name('store');
            Route::put('/{bankAccount}', [BankAccountController::class, 'update'])
                ->whereNumber('bankAccount')
                ->name('update');
        });

        Route::prefix('vouchers')->name('vouchers.')->group(function (): void {
            Route::get('/', [VoucherController::class, 'index'])->name('index');
            Route::get('/create', [VoucherController::class, 'create'])->name('create');
            Route::post('/', [VoucherController::class, 'store'])->name('store');
            Route::get('/export', [VoucherController::class, 'export'])->name('export');
            Route::get('/{voucher}', [VoucherController::class, 'show'])
                ->whereNumber('voucher')
                ->name('show');
            Route::post('/{voucher}/cancel', [VoucherController::class, 'cancel'])
                ->whereNumber('voucher')
                ->name('cancel');
        });

        Route::prefix('accountants')->name('accountants.')->group(function (): void {
            Route::get('/', [AccountantManagementController::class, 'index'])->name('index');
            Route::get('/create', [AccountantManagementController::class, 'create'])->name('create');
            Route::post('/', [AccountantManagementController::class, 'store'])->name('store');
            Route::get('/daily', [AccountantManagementController::class, 'daily'])->name('daily');
            Route::get('/monthly', [AccountantManagementController::class, 'monthly'])->name('monthly');
            Route::get('/export', [AccountantManagementController::class, 'export'])->name('export');
            Route::get('/{accountant}', [AccountantManagementController::class, 'show'])
                ->whereNumber('accountant')
                ->name('show');
        });

        Route::prefix('reports')->name('reports.')->group(function (): void {
            Route::get('/', [FinancialReportController::class, 'index'])->name('index');
            Route::get('/collection', [FinancialReportController::class, 'collection'])->name('collection');
            Route::get('/students', [FinancialReportController::class, 'students'])->name('students');
            Route::get('/classes', [FinancialReportController::class, 'classes'])->name('classes');
            Route::get('/fee-types', [FinancialReportController::class, 'feeTypes'])->name('fee-types');
            Route::get('/methods', [FinancialReportController::class, 'methods'])->name('methods');
            Route::get('/collectors', [FinancialReportController::class, 'collectors'])->name('collectors');
            Route::get('/expenses', [FinancialReportController::class, 'expenses'])->name('expenses');
            Route::get('/statement', [FinancialReportController::class, 'statement'])->name('statement');
            Route::get('/export/{report}', [FinancialReportController::class, 'export'])
                ->whereIn('report', ['collection', 'students', 'classes', 'fee-types', 'methods', 'collectors', 'months', 'expenses', 'statement'])
                ->name('export');
        });

        Route::prefix('exams')->name('exams.')->group(function (): void {
            Route::get('/', [ExamController::class, 'index'])->name('index');
            Route::get('/create', [ExamController::class, 'create'])->name('create');
            Route::post('/', [ExamController::class, 'store'])->name('store');

            Route::get('/grade-scale', [GradeScaleController::class, 'edit'])->name('grade-scale.edit');
            Route::put('/grade-scale', [GradeScaleController::class, 'update'])->name('grade-scale.update');

            Route::get('/{exam}', [ExamController::class, 'show'])->whereNumber('exam')->name('show');
            Route::get('/{exam}/edit', [ExamController::class, 'edit'])->whereNumber('exam')->name('edit');
            Route::put('/{exam}', [ExamController::class, 'update'])->whereNumber('exam')->name('update');
            Route::delete('/{exam}', [ExamController::class, 'destroy'])->whereNumber('exam')->name('destroy');

            Route::post('/{exam}/papers', [ExamSubjectController::class, 'store'])
                ->whereNumber('exam')->name('papers.store');
            Route::delete('/{exam}/papers/{paper}', [ExamSubjectController::class, 'destroy'])
                ->whereNumber('exam')->whereNumber('paper')->name('papers.destroy');

            Route::get('/{exam}/marks', [ExamMarkController::class, 'index'])->whereNumber('exam')->name('marks.index');
            Route::post('/{exam}/marks', [ExamMarkController::class, 'store'])->whereNumber('exam')->name('marks.store');
            Route::post('/{exam}/marks/submit', [ExamMarkController::class, 'submit'])
                ->whereNumber('exam')->name('marks.submit');

            Route::get('/{exam}/results', [ExamResultController::class, 'index'])
                ->whereNumber('exam')->name('results.index');
            Route::get('/{exam}/results/export', [ExamResultController::class, 'export'])
                ->whereNumber('exam')->name('results.export');
            Route::post('/{exam}/results/publish', [ExamResultController::class, 'publish'])
                ->whereNumber('exam')->name('results.publish');
            Route::post('/{exam}/results/unpublish', [ExamResultController::class, 'unpublish'])
                ->whereNumber('exam')->name('results.unpublish');
            Route::get('/{exam}/results/{student}', [ExamResultController::class, 'reportCard'])
                ->whereNumber('exam')->whereNumber('student')->name('results.show');
        });

        Route::prefix('marks')->name('marks.')->group(function (): void {
            Route::get('/submissions', [MarkSubmissionController::class, 'index'])->name('submissions.index');
            Route::post('/submissions/{submission}/{action}', [MarkSubmissionController::class, 'decide'])
                ->whereNumber('submission')
                ->whereIn('action', ['approve', 'reject'])
                ->name('submissions.decide');
        });

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/search', SearchController::class)->name('search');
        Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
    });
