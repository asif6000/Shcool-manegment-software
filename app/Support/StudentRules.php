<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Validation\Rule;

/**
 * The single source of student field rules, used by both the admission form and
 * the bulk importer so an imported row can never pass a weaker check than a
 * hand-typed one.
 */
class StudentRules
{
    /**
     * The admin admission form also opens the student's login, so it carries the
     * sign-in mobile and the initial password. The bulk importer does not, which
     * is why those two rules live here and not in profile().
     *
     * @return array<string, array<int, mixed>>
     */
    public static function save(int $schoolId, ?int $studentId = null): array
    {
        $rules = array_merge(self::profile($schoolId), [
            'phone' => self::signInMobile($studentId),
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'password' => $studentId === null
                ? ['required', 'string', 'confirmed', 'min:8', 'max:60']
                : ['nullable', 'string', 'confirmed', 'min:8', 'max:60'],
        ]);

        return $studentId === null
            ? array_merge($rules, self::admissionRequirements($schoolId))
            : $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function import(int $schoolId): array
    {
        return self::profile($schoolId);
    }

    /**
     * A hand-admitted student arrives with a class place and a guardian contact,
     * so those are compulsory here. Editing keeps them optional because a profile
     * migrated from another register may legitimately be missing one.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function admissionRequirements(int $schoolId): array
    {
        $belongsToSchool = function (string $table, bool $softDeletes) use ($schoolId): array {
            /** @var \Illuminate\Validation\Rules\Exists $rule */
            $rule = Rule::exists($table, 'id')->where('school_id', $schoolId);

            return ['required', 'integer', $softDeletes ? $rule->whereNull('deleted_at') : $rule];
        };

        return [
            'guardian_name' => ['required', 'string', 'max:120'],
            'guardian_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'present_address' => ['required', 'string', 'max:500'],
            // Only classes and sections are soft-deletable; academic sessions are
            // not, so the deleted_at guard cannot be applied to every table.
            'academic_session_id' => $belongsToSchool('academic_sessions', false),
            'class_id' => $belongsToSchool('classes', true),
            'section_id' => $belongsToSchool('sections', true),
            'roll_no' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }

    /**
     * The mobile number is the student's sign-in identifier, so it has to be a
     * number the login lookup can actually match and it has to be free across
     * every account in the system, not just within this school.
     *
     * @return array<int, mixed>
     */
    public static function signInMobile(?int $studentId): array
    {
        return [
            'required',
            'string',
            'max:30',
            function (string $attribute, mixed $value, \Closure $fail) use ($studentId): void {
                $mobile = AuthenticationService::normaliseMobile(trim((string) $value));

                if (preg_match(AuthenticationService::MOBILE_PATTERN, $mobile) !== 1) {
                    $fail(__('Enter a valid Bangladeshi mobile number, e.g. 01XXXXXXXXX.'));

                    return;
                }

                $ownUserId = $studentId === null
                    ? null
                    : Student::query()->whereKey($studentId)->value('user_id');

                $owner = User::query()
                    ->withTrashed()
                    ->when($ownUserId !== null, fn ($q) => $q->where('id', '!=', $ownUserId))
                    ->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($mobile): void {
                        $q->where('mobile_number', $mobile)->orWhere('phone', $mobile);
                    })
                    ->first();

                if ($owner !== null) {
                    $fail($owner->deleted_at !== null
                        ? __('That mobile number is reserved by an archived account. Contact the administrator.')
                        : __('That mobile number already signs in to another account.'));
                }
            },
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private static function profile(int $schoolId): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])],
            'religion' => ['nullable', 'string', 'max:20'],
            'nationality' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'present_address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(Student::STATUSES)],
            'admission_date' => ['required', 'date'],

            'guardian_name' => ['nullable', 'string', 'max:120'],
            'guardian_relation' => ['nullable', Rule::in(['father', 'mother', 'guardian', 'other'])],
            'guardian_phone' => ['nullable', 'required_with:guardian_name', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'guardian_email' => ['nullable', 'email', 'max:120'],
            'guardian_occupation' => ['nullable', 'string', 'max:80'],
            'guardian_address' => ['nullable', 'string', 'max:500'],

            'academic_session_id' => [
                'nullable', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'class_id' => [
                'nullable', 'required_with:academic_session_id', 'integer',
                Rule::exists('classes', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'section_id' => [
                'nullable', 'required_with:academic_session_id', 'integer',
                Rule::exists('sections', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'roll_no' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'enrolled_on' => ['nullable', 'date'],
        ];
    }
}
