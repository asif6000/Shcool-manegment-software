<?php

// Read-only snapshot of one student account, used to prove the delete path
// against the real development database. Pass the sign-in mobile number.

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$phone = $argv[1] ?? null;

if ($phone === null) {
    fwrite(STDERR, "usage: php scratch-student-state.php <mobile>\n");
    exit(1);
}

$student = DB::table('students')->where('phone', $phone)->first();

if ($student === null) {
    echo "student: ABSENT (phone $phone)\n";

    $orphan = DB::table('users')->where('mobile_number', $phone)->first();
    echo 'login: '.($orphan ? 'PRESENT id='.$orphan->id : 'ABSENT')."\n";
    exit(0);
}

$photoExists = $student->photo_path
    ? (Storage::disk('school_uploads')->exists($student->photo_path) ? 'yes' : 'no')
    : 'none';

printf("student: PRESENT id=%d user_id=%s photo=%s(%s)\n", $student->id, (string) $student->user_id, (string) $student->photo_path, $photoExists);
printf("  enrollments=%d cards=%d guardians=%d leaves=%d submissions=%d\n",
    DB::table('enrollments')->where('student_id', $student->id)->count(),
    DB::table('student_attendance_cards')->where('student_id', $student->id)->count(),
    DB::table('student_guardians')->where('student_id', $student->id)->count(),
    DB::table('student_leaves')->where('student_id', $student->id)->count(),
    DB::table('homework_submissions')->where('student_id', $student->id)->count(),
);
printf("  history: invoices=%d attendance=%d marks=%d results=%d promotions=%d\n",
    DB::table('fee_invoices')->where('student_id', $student->id)->count(),
    DB::table('student_attendances')->where('student_id', $student->id)->count(),
    DB::table('exam_marks')->where('student_id', $student->id)->count(),
    DB::table('student_results')->where('student_id', $student->id)->count(),
    DB::table('student_promotions')->where('student_id', $student->id)->count(),
);

if ($student->user_id) {
    $user = DB::table('users')->where('id', $student->user_id)->first();
    printf("login: %s must_change=%s roles=%d tokens=%d sessions=%d\n",
        $user ? 'PRESENT id='.$user->id : 'ABSENT',
        $user ? (string) $user->must_change_password : '-',
        DB::table('model_has_roles')->where('model_id', $student->user_id)->count(),
        DB::table('personal_access_tokens')->where('tokenable_id', $student->user_id)->count(),
        DB::table('sessions')->where('user_id', $student->user_id)->count(),
    );
} else {
    echo "login: none linked\n";
}

printf("audit: student.permanently_deleted rows=%d\n",
    DB::table('audit_logs')->where('event', 'student.permanently_deleted')->count(),
);
