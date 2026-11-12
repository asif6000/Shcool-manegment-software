<?php

/*
|--------------------------------------------------------------------------
| Permanent delete policy
|--------------------------------------------------------------------------
|
| These lists decide what a delete button in the admin panel is allowed to do.
| They are read by App\Services\PermanentDeleteService, which walks the real
| foreign key graph (App\Support\ForeignKeyMap) and applies them in this order:
|
|   1. a table listed in "protected" is never removed, not even when the
|      database would happily cascade it away for us;
|   2. a child table named under "detachable" for its parent, or one whose
|      foreign key is CASCADE, belongs to the record and is deleted with it;
|   3. a child whose foreign key is SET NULL is left in place and simply loses
|      the reference;
|   4. anything else that still points at the record blocks the delete and the
|      admin is told what is holding it.
|
| Rule 4 is the reason this file exists: an unlisted relationship blocks with a
| message instead of either throwing a raw constraint error or cascading
| history away unnoticed.
|
*/

return [

    /*
    | Rows that are accounting or academic evidence. Deleting them is a
    | cancellation, reversal or adjustment entry, never a DELETE statement, so
    | the ordinary delete button refuses even when the database would allow it.
    */
    'protected' => [
        // money
        'fee_invoices',
        'fee_invoice_items',
        'fee_invoice_discounts',
        'fee_payments',
        'fee_payment_allocations',
        'fee_receipts',
        'payment_gateway_transactions',
        'cash_transactions',
        'vouchers',
        'expenses',
        'salary_payments',
        // student academic history
        'exam_marks',
        'mark_submissions',
        'student_results',
        'student_attendances',
        'student_promotions',
        'attendance_submissions',
        // staff history
        'staff_attendances',
        'staff_leaves',
        // proof that any of this happened
        'audit_logs',
        'backup_logs',
    ],

    /*
    | Children that RESTRICT their parent but are still part of the parent's
    | own setup rather than a record of something that happened. Deleting the
    | parent takes these with it; every other relationship has to be cleared
    | by an admin first.
    */
    'detachable' => [
        'students' => ['enrollments', 'fee_student_assignments', 'student_attendance_cards'],
        'teachers' => ['class_routines', 'class_teachers', 'salary_structures', 'teacher_assignments'],
        'employees' => ['class_routines', 'salary_structures'],
        'subjects' => ['class_subjects', 'class_routines', 'teacher_assignments'],
        'classes' => ['sections', 'class_subjects', 'class_routines', 'class_teachers', 'teacher_assignments'],
        'sections' => ['class_routines', 'teacher_assignments'],
        'academic_sessions' => [],
        'guardians' => [],
    ],

    /*
    | Tables that carry no foreign key to their owner, so the database cannot
    | clean them up. Without these the delete leaves rows behind that point at
    | an id that no longer exists. "column" is matched against the owner's key
    | and "morph", when set, is also required to equal the owner's class.
    */
    'cleanup' => [
        'users' => [
            ['table' => 'model_has_roles', 'column' => 'model_id', 'morph' => 'model_type'],
            ['table' => 'model_has_permissions', 'column' => 'model_id', 'morph' => 'model_type'],
            ['table' => 'personal_access_tokens', 'column' => 'tokenable_id', 'morph' => 'tokenable_type'],
            ['table' => 'sessions', 'column' => 'user_id'],
            ['table' => 'notifications', 'column' => 'notifiable_id', 'morph' => 'notifiable_type'],
        ],
    ],

    /*
    | Stored uploads removed once the row that owns them is gone. The service
    | only deletes inside the profile's own directory, so a tampered column
    | cannot reach elsewhere on the disk.
    */
    'files' => [
        'students' => ['photo_path' => 'student_photo'],
        'users' => ['avatar_path' => 'user_avatar'],
        'teachers' => ['photo_path' => 'teacher_photo'],
        'employees' => ['photo_path' => 'teacher_photo'],
        'admissions' => ['photo_path' => 'admission_photo'],
        'schools' => ['logo_path' => 'school_logo'],
        'homework' => ['attachment_path' => 'homework_attachment'],
        'homework_submissions' => ['attachment_path' => 'homework_submission'],
        'study_materials' => ['file_path' => 'study_material'],
        'class_videos' => ['file_path' => 'class_video'],
        'lesson_materials' => ['file_path' => 'lesson_attachment'],
    ],

    /*
    | Records whose delete button also has to be armed by typing DELETE, in the
    | dialog and again as a hidden "permanent_delete" field on the form. The
    | service enforces the field on the server, so a scripted request cannot
    | skip the dialog.
    */
    'critical' => [
        'students',
        'teachers',
        'employees',
        'users',
        'guardians',
        'classes',
        'sections',
        'subjects',
        'academic_sessions',
        'exams',
    ],

    /*
    | Wording used in the refusal message. A table missing from this list is
    | named by its table name, which is still actionable, just less friendly.
    */
    'labels' => [
        'academic_sessions' => 'academic sessions',
        'admissions' => 'admission applications',
        'attendance_device_users' => 'device student mappings',
        'attendance_submissions' => 'attendance submissions',
        'audit_logs' => 'audit log entries',
        'backup_logs' => 'backup records',
        'cash_transactions' => 'cash transactions',
        'class_routines' => 'timetable periods',
        'class_subjects' => 'class subject mappings',
        'class_teachers' => 'class assignments',
        'class_videos' => 'class videos',
        'enrollments' => 'class enrollments',
        'exam_marks' => 'exam marks',
        'exam_subjects' => 'exam papers',
        'expenses' => 'expense claims',
        'fee_invoice_items' => 'invoice lines',
        'fee_invoices' => 'fee invoices',
        'fee_payment_allocations' => 'payment allocations',
        'fee_payments' => 'fee payments',
        'fee_receipts' => 'fee receipts',
        'fee_student_assignments' => 'fee assignments',
        'fee_structures' => 'fee structures',
        'homework' => 'homework assignments',
        'homework_submissions' => 'student answers',
        'lessons' => 'lessons',
        'leave_types' => 'leave types',
        'mark_submissions' => 'submitted mark sheets',
        'notices' => 'notices',
        'payment_gateway_transactions' => 'online payment transactions',
        'salary_payments' => 'salary payments',
        'salary_structures' => 'salary structures',
        'sections' => 'sections',
        'staff_attendances' => 'staff attendance records',
        'staff_leaves' => 'staff leave records',
        'student_attendance_cards' => 'attendance QR cards',
        'student_attendances' => 'student attendance records',
        'student_guardians' => 'guardian links',
        'student_leaves' => 'student leave requests',
        'student_promotions' => 'class promotions',
        'student_results' => 'published results',
        'study_materials' => 'study materials',
        'subjects' => 'subjects',
        'vouchers' => 'vouchers',
    ],

];
