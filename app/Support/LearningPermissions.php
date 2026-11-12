<?php

namespace App\Support;

/**
 * The learning screens belong to the teaching staff. The school admin keeps the
 * timetable and the staff assignments, but cannot write homework, classwork or
 * lessons, and cannot grant itself those screens back through the role matrix.
 *
 * homework.monitor is deliberately absent from these lists: it is the office's
 * read-only view of the same records, and it has no write route at all.
 */
final class LearningPermissions
{
    public const SCHOOL_ADMIN_EXCLUDED = [
        'homework.view',
        'homework.manage',
        'lessons.view',
        'lessons.manage',
        'materials.view',
        'materials.manage',
    ];

    /**
     * Lesson plans are readable by the principal for oversight, but setting and
     * handing out homework or classwork is the teacher's own work.
     */
    public const TEACHER_ONLY = [
        'homework.view',
        'homework.manage',
        'materials.view',
        'materials.manage',
    ];
}
