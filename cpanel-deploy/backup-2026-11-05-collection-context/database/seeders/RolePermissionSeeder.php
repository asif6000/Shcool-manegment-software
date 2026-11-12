<?php

namespace Database\Seeders;

use App\Services\FeeCollectionAuthority;
use App\Support\LearningPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * The office assigns subjects; the teaching itself belongs to the teacher.
     * Homework and classwork (stored as study materials) are therefore not the
     * school admin's screens at all: no sidebar group, no dashboard tile, and
     * the URLs answer 403. The principal keeps the lesson permissions for
     * oversight but not homework/classwork, and the role checks in the policies
     * refuse the office even if the matrix is edited.
     */
    private const SCHOOL_ADMIN_EXCLUDED = LearningPermissions::SCHOOL_ADMIN_EXCLUDED;

    /**
     * Fee collection is an accountant cash-desk action. The admin keeps every
     * fee configuration, report and verification screen so it can monitor the
     * takings, but must not hold a permission that can record a payment.
     */
    private const SCHOOL_ADMIN_MONITORING_ONLY = FeeCollectionAuthority::MONITORING_ONLY;

    /**
     * Permissions are defined once in config/school.php so the UI matrix,
     * seed data and future module gates stay on the same catalogue.
     */
    public function run(): void
    {
        $all = collect(config('school.permissions', []))->flatten()->unique()->values();

        $all->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));

        $matrix = [
            'school_admin' => $all->reject(
                fn (string $permission): bool => in_array($permission, [
                    ...self::SCHOOL_ADMIN_EXCLUDED,
                    ...self::SCHOOL_ADMIN_MONITORING_ONLY,
                ], true)
            )->values()->all(),
            'principal' => [
                'dashboard.view',
                'users.view',
                'roles.view',
                'school.view',
                'school.update',
                'audit.view',
                'academic.view',
                'academic.manage',
                'students.view',
                'students.create',
                'students.update',
                'students.delete',
                'students.export',
                'admissions.view',
                'admissions.manage',
                'staff.view',
                'staff.create',
                'staff.update',
                'staff.export',
                'leave.view',
                'leave.manage',
                'leave.apply',
                'salary.manage',
                'attendance.view',
                'attendance.mark',
                'attendance.approve',
                'attendance.report',
                'attendance.qr.manage',
                'fees.view',
                'expenses.view',
                'expenses.manage',
                'expenses.approve',
                'expenses.approve.high',
                'vouchers.view',
                'vouchers.manage',
                'bank.view',
                'bank.manage',
                'accounts.close',
                'fees.verify',
                'fees.discounts.approve',
                'payroll.view',
                'exams.view',
                'exams.manage',
                'marks.enter',
                'marks.approve',
                'results.view',
                'results.publish',
                'reports.view',
                'reports.export',
                'lessons.view',
                'lessons.manage',
                'videos.view',
                'videos.manage',
                'notices.view',
                'notices.manage',
                'timetable.view',
            ],
            'accountant' => [
                'dashboard.view',
                'school.view',
                'students.view',
                'students.export',
                'fees.view',
                'fees.manage',
                'fees.collect',
                'fees.reverse',
                'fees.export',
                'fees.verify',
                'fees.discounts.manage',
                'expenses.view',
                'expenses.manage',
                'expenses.approve',
                'payroll.view',
                'payroll.manage',
                'cash.view',
                'bank.view',
                'vouchers.view',
                'vouchers.manage',
                'accounts.close',
                'audit.view',
                'reports.view',
                'reports.export',
            ],
            /*
             * A teacher gets the working modules — lessons, attendance, homework,
             * classwork and marks entry — plus the self-service rows they need to
             * read their own routine, notices and leave. The student register, the
             * exam list and the official results screens are deliberately absent:
             * the roster a teacher needs is served inside those modules,
             * narrowed by the timetable, and only the office may approve or
             * publish a result.
             */
            'teacher' => [
                'dashboard.view',
                'attendance.view',
                'attendance.mark',
                'attendance.submit',
                'attendance.report',
                'lessons.view',
                'lessons.manage',
                'homework.view',
                'homework.manage',
                'materials.view',
                'materials.manage',
                'marks.enter',
                'marks.submit',
                'notices.view',
                'timetable.view',
                'leave.apply',
            ],
            'student' => [
                'dashboard.view',
                'notices.view',
                'results.view',
            ],
        ];

        foreach ((array) config('school.roles') as $name => $meta) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($matrix[$name] ?? []);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
