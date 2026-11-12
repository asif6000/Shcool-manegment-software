<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Role hierarchy
    |--------------------------------------------------------------------------
    |
    | EduNest is a single-school system. The school administrator is the
    | highest role; higher levels may manage lower levels, but nobody can
    | assign a role at or above their own level.
    |
    */

    'roles' => [
        'school_admin' => ['level' => 90, 'label' => 'School Admin'],
        'principal' => ['level' => 70, 'label' => 'Principal'],
        'accountant' => ['level' => 60, 'label' => 'Accountant'],
        'teacher' => ['level' => 50, 'label' => 'Teacher'],
        'student' => ['level' => 20, 'label' => 'Student'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Staff workspace roles
    |--------------------------------------------------------------------------
    |
    | Only these roles may enter the /admin workspace. Student accounts are
    | served by the /student portal and never see staff data.
    |
    */

    'workspace_roles' => ['school_admin', 'principal', 'accountant', 'teacher'],

    /*
    |--------------------------------------------------------------------------
    | Permission catalogue (Phase 1)
    |--------------------------------------------------------------------------
    |
    | Later phases append academic, attendance, fee, exam and reporting
    | permissions to this catalogue. Permission names are stable API contracts.
    |
    */

    'permissions' => [
        'Dashboard' => [
            'dashboard.view',
        ],
        'User management' => [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.reset-password',
        ],
        'Roles' => [
            'roles.view',
        ],
        'School' => [
            'school.view',
            'school.update',
        ],
        'Academic setup' => [
            'academic.view',
            'academic.manage',
        ],
        'Students' => [
            'students.view',
            'students.create',
            'students.update',
            'students.delete',
            'students.export',
            'admissions.view',
            'admissions.manage',
        ],
        'Staff' => [
            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',
            'staff.export',
            'leave.view',
            'leave.manage',
            'leave.apply',
            'salary.manage',
        ],
        'Attendance' => [
            'attendance.view',
            'attendance.mark',
            'attendance.submit',
            'attendance.approve',
            'attendance.report',
            'attendance.qr.manage',
            'attendance.devices.manage',
        ],
        'Fees' => [
            'fees.view',
            'fees.manage',
            'fees.collect',
            'fees.collect.scoped',
            'fees.reverse',
            'fees.export',
            'fees.verify',
            'fees.discounts.manage',
            'fees.discounts.approve',
        ],
        'Accounting' => [
            'expenses.view',
            'expenses.manage',
            'expenses.approve',
            'expenses.approve.high',
            'payroll.view',
            'payroll.manage',
            'cash.view',
            'bank.view',
            'bank.manage',
            'vouchers.view',
            'vouchers.manage',
            'accounts.close',
            'accountants.view',
            'accountants.manage',
        ],
        'Examinations' => [
            'exams.view',
            'exams.manage',
            'marks.enter',
            'marks.submit',
            'marks.approve',
            'results.view',
            'results.publish',
        ],
        'Reports' => [
            'reports.view',
            'reports.export',
        ],
        'Communication' => [
            'lessons.view',
            'lessons.manage',
            'homework.view',
            'homework.manage',
            'homework.monitor',
            'materials.view',
            'materials.manage',
            'videos.view',
            'videos.manage',
            'notices.view',
            'notices.manage',
            'timetable.view',
            'timetable.manage',
        ],
        'Audit trail' => [
            'audit.view',
        ],
    ],

    'demo_password' => env('DEMO_SEED_PASSWORD', 'Demo@1234'),

    'uploads' => [
        'school_logo' => [
            'disk' => 'school_uploads',
            'path' => 'schools',
            'max_kb' => 2048,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'user_avatar' => [
            'disk' => 'school_uploads',
            'path' => 'avatars',
            'max_kb' => 2048,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'student_photo' => [
            'disk' => 'school_uploads',
            'path' => 'students',
            // Any resolution and ratio is accepted, so the only ceiling is the
            // one needed for storage: 5 MB covers a full-quality phone camera
            // frame without letting a single profile soak up the disk.
            'max_kb' => 5120,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'admission_photo' => [
            'disk' => 'school_uploads',
            'path' => 'admissions',
            'max_kb' => 2048,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'teacher_photo' => [
            'disk' => 'school_uploads',
            'path' => 'teachers',
            'max_kb' => 5120,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'hero_banner' => [
            'disk' => 'school_uploads',
            'path' => 'site',
            'max_kb' => 4096,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'homework_attachment' => [
            'disk' => 'school_uploads',
            'path' => 'homework',
            'max_kb' => 4096,
            'mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'webp'],
        ],
        'homework_submission' => [
            'disk' => 'school_uploads',
            'path' => 'submissions',
            'max_kb' => 4096,
            'mimes' => ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'webp'],
        ],
        'study_material' => [
            'disk' => 'school_uploads',
            'path' => 'materials',
            'max_kb' => 8192,
            'mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'webp'],
        ],
        'class_video' => [
            'disk' => 'school_uploads',
            'path' => 'videos',
            'max_kb' => 20480,
            'mimes' => ['mp4', 'webm', 'ogv', 'mov'],
        ],
        'lesson_attachment' => [
            'disk' => 'school_uploads',
            'path' => 'lessons',
            'max_kb' => 8192,
            'mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'webp'],
        ],
        // Pictures the editor drops into the lesson body. They are read straight
        // from the uploads folder, so only image types are accepted here.
        'lesson_image' => [
            'disk' => 'school_uploads',
            'path' => 'lesson-images',
            'max_kb' => 4096,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
    ],

    'pagination' => [
        'per_page' => 15,
    ],

    'locales' => ['en', 'bn'],

];
