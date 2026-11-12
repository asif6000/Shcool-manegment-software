<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use App\Support\StudentRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk student intake. Rows are mapped from a CSV by name, validated with the
 * same rules as the admission form and then handed to StudentService::create()
 * one at a time, so an import can never take a second path around the guardian
 * linking, roll numbering or audit logging that a hand-typed admission gets.
 */
class StudentImportService
{
    public const MAX_ROWS = 500;

    private const DIRECTORY = 'student-imports';

    private const TOKEN_PATTERN = '/^[a-f0-9-]{36}\.csv$/';

    private const MAX_BYTES = 4 * 1024 * 1024;

    /**
     * Header aliases per destination field. English and Bangla spellings both
     * work because sheets are prepared in either language.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        'first_name' => ['first_name', 'name', 'student_name', 'নাম', 'প্রথম_নাম'],
        'last_name' => ['last_name', 'surname', 'পদবি'],
        'gender' => ['gender', 'sex', 'লিঙ্গ'],
        'date_of_birth' => ['date_of_birth', 'dob', 'birth_date', 'জন্ম_তারিখ', 'জন্মতারিখ'],
        'blood_group' => ['blood_group', 'bloodgroup', 'রক্তের_গ্রুপ'],
        'religion' => ['religion', 'ধর্ম'],
        'nationality' => ['nationality', 'জাতীয়তাবাদ'],
        'email' => ['email', 'ইমেইল'],
        'phone' => ['phone', 'mobile', 'mobile_number', 'contact', 'phone_number', 'ফোন', 'মোবাইল'],
        'present_address' => ['present_address', 'address', 'বর্তমান_ঠিকানা', 'ঠিকানা'],
        'permanent_address' => ['permanent_address', 'স্থায়ী_ঠিকানা'],
        'status' => ['status', 'অবস্থা'],
        'admission_date' => ['admission_date', 'joining_date', 'ভর্তির_তারিখ'],
        'guardian_name' => ['guardian_name', 'guardian', 'father_name', 'অভিভাবকের_নাম'],
        'guardian_relation' => ['guardian_relation', 'relation', 'সম্পর্ক'],
        'guardian_phone' => ['guardian_phone', 'guardian_mobile', 'অভিভাবকের_ফোন'],
        'guardian_email' => ['guardian_email', 'অভিভাবকের_ইমেইল'],
        'guardian_occupation' => ['guardian_occupation', 'occupation', 'পেশা'],
        'guardian_address' => ['guardian_address', 'অভিভাবকের_ঠিকানা'],
        'academic_session_id' => ['session', 'academic_session', 'সেশন'],
        'class_id' => ['class', 'grade', 'class_name', 'শ্রেণি'],
        'section_id' => ['section', 'division', 'শাখা'],
        'roll_no' => ['roll_no', 'roll', 'রোল'],
        'enrolled_on' => ['enrolled_on', 'enrollment_date'],
        'student_id' => ['student_id', 'স্টুডেন্ট_আইডি'],
        'admission_no' => ['admission_no', 'admission_number', 'ভর্তি_নম্বর'],
    ];

    /** @var array<string, string> */
    private const GENDERS = [
        'male' => 'male', 'm' => 'male', 'boy' => 'male', 'ছেলে' => 'male', 'পুরুষ' => 'male',
        'female' => 'female', 'f' => 'female', 'girl' => 'female', 'মেয়ে' => 'female', 'নারী' => 'female',
        'other' => 'other', 'অন্য' => 'other',
    ];

    /** @var array<string, string> */
    private const STATUSES = [
        'active' => 'active', 'studying' => 'active', 'চালু' => 'active', 'সক্রিয়' => 'active',
        'inactive' => 'inactive', 'নিষ্ক্রিয়' => 'inactive',
        'graduated' => 'graduated', 'passed' => 'graduated', 'উত্তীর্ণ' => 'graduated',
        'transferred' => 'transferred', 'transfer' => 'transferred', 'স্থানান্তর' => 'transferred',
    ];

    /** @var array<string, string> */
    private const RELATIONS = [
        'father' => 'father', 'dad' => 'father', 'পিতা' => 'father',
        'mother' => 'mother', 'mom' => 'mother', 'মা' => 'mother',
        'guardian' => 'guardian', 'অভিভাবক' => 'guardian',
        'other' => 'other', 'অন্য' => 'other',
    ];

    /** @var array<string, array<string, int>> */
    private array $lookups = [];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StudentService $students,
    ) {}

    /**
     * The blank sheet the uploader fills in: header row only, so no example row
     * can ever be mistaken for a real child on a confirm click.
     */
    public function template(): StreamedResponse
    {
        $headers = array_keys(self::ALIASES);

        return response()->streamDownload(function () use ($headers): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            fclose($handle);
        }, 'student-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Field names the sheet is allowed to carry, for the on-screen legend.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return array_keys(self::ALIASES);
    }

    public function storeUpload(UploadedFile $file): string
    {
        $this->assertReadableFile($file);

        $token = (string) Str::uuid().'.csv';
        Storage::disk('local')->put($this->path($token), (string) file_get_contents($file->getRealPath()));

        return $token;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, valid: int, invalid: int, fatal: list<string>, unknown: list<string>}
     */
    public function analyse(string $token): array
    {
        $this->forgetStale();

        return $this->read($this->absolute($token));
    }

    /**
     * Creates every row that passes validation. A row that fails is reported and
     * skipped; one mistyped date in a four hundred row sheet must not throw away
     * the other three hundred and ninety nine admissions.
     *
     * @return array{imported: int, failed: int, failures: list<array{line: int, name: string, reason: string}>}
     */
    public function commit(User $actor, string $token): array
    {
        $batch = $this->analyse($token);

        if ($batch['fatal'] !== []) {
            throw ValidationException::withMessages(['csv' => $batch['fatal'][0]]);
        }

        if ($batch['valid'] === 0) {
            throw ValidationException::withMessages(['csv' => __('None of the rows in this sheet are ready to import.')]);
        }

        $imported = 0;
        $failures = [];
        $numbers = [];

        foreach ($batch['rows'] as $row) {
            if (! $row['valid']) {
                $failures[] = [
                    'line' => $row['line'],
                    'name' => $row['name'],
                    'reason' => implode(' ', array_merge(...array_map(fn (array $m): array => $m, array_values($row['errors'])))),
                ];

                continue;
            }

            try {
                $student = $this->students->create($actor, $row['data']);
                $imported++;
                $numbers[] = $student->student_id;
            } catch (\Throwable $exception) {
                $failures[] = [
                    'line' => $row['line'],
                    'name' => $row['name'],
                    'reason' => $exception instanceof ValidationException
                        ? (string) (collect($exception->errors())->flatten()->first() ?? $exception->getMessage())
                        : __('This row could not be saved.'),
                ];
            }
        }

        $this->audit->log(
            event: 'student.bulk_import',
            auditable: null,
            description: __(':imported students imported, :failed rows skipped', [
                'imported' => $imported,
                'failed' => count($failures),
            ]),
            newValues: [
                'imported' => $imported,
                'failed' => count($failures),
                'first_numbers' => array_slice($numbers, 0, 20),
            ],
            actor: $actor,
            schoolId: $this->schoolId(),
        );

        $this->forget($token);

        return ['imported' => $imported, 'failed' => count($failures), 'failures' => $failures];
    }

    public function forget(string $token): void
    {
        Storage::disk('local')->delete($this->path($token));
    }

    private function assertReadableFile(UploadedFile $file): void
    {
        $name = strtolower((string) $file->getClientOriginalName());

        abort_unless(
            $file->getSize() !== false && $file->getSize() <= self::MAX_BYTES,
            422,
            __('The file is larger than :size MB. Split it into smaller sheets.', ['size' => self::MAX_BYTES / 1024 / 1024])
        );

        abort_unless(
            str_ends_with($name, '.csv') || str_ends_with($name, '.txt') || str_ends_with($name, '.tsv'),
            422,
            __('Only a .csv file exported from Excel, Google Sheets or LibreOffice can be imported.')
        );
    }

    /**
     * @return array{rows: list<array<string, mixed>>, valid: int, invalid: int, fatal: list<string>, unknown: list<string>}
     */
    private function read(string $absolute): array
    {
        $handle = fopen($absolute, 'rb');
        abort_if($handle === false, 422, __('The uploaded sheet could not be read. Upload it again.'));

        $delimiter = $this->delimiter($handle);
        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        $columns = $this->mapColumns(is_array($header) ? $header : []);
        $fatal = [];

        if (! in_array('first_name', $columns['map'], true)) {
            $fatal[] = __('The first row must be the header, and it needs a name column (first_name, name or নাম).');
        }

        $rows = [];
        $seen = [];
        $line = 1;

        if ($fatal === []) {
            while (($raw = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $line++;
                $cells = array_map(
                    fn ($value): string => is_string($value) ? $this->clean($value) : '',
                    $raw
                );

                if (implode('', $cells) === '') {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    $fatal[] = __('Only the first :max rows are imported. Put the rest in a second file.', ['max' => self::MAX_ROWS]);

                    break;
                }

                $rows[] = $this->buildRow($line, $cells, $columns['map'], $seen);
            }
        }

        fclose($handle);

        if ($rows === [] && $fatal === []) {
            $fatal[] = __('The sheet has a header but no student rows.');
        }

        $this->markRollCollisions($rows);

        return [
            'rows' => $rows,
            'valid' => count(array_filter($rows, fn (array $row): bool => $row['valid'])),
            'invalid' => count(array_filter($rows, fn (array $row): bool => ! $row['valid'])),
            'fatal' => array_values(array_unique($fatal)),
            'unknown' => $columns['unknown'],
        ];
    }

    /**
     * @param  list<string>  $cells
     * @param  array<int, string>  $map
     * @param  array<string, int>  $seen
     * @return array<string, mixed>
     */
    private function buildRow(int $line, array $cells, array $map, array &$seen): array
    {
        $data = [];

        foreach ($map as $index => $field) {
            $value = $cells[$index] ?? '';

            if ($value === '') {
                continue;
            }

            $data[$field] = $this->normalise($field, $value);
        }

        $errors = $this->duplicateErrors($line, $data, $seen);
        $data = $this->resolveLookups($data, $errors);

        $flat = [];

        foreach ($errors as $field => $message) {
            $flat[$field] = is_array($message) ? $message : [$message];
        }

        foreach (validator($data, StudentRules::import($this->schoolId()))->errors()->toArray() as $field => $messages) {
            $flat[$field] = array_merge($flat[$field] ?? [], $messages);
        }

        $flat = array_map(fn (array $messages): array => array_values(array_unique($messages)), $flat);

        return [
            'line' => $line,
            'data' => $data,
            'errors' => $flat,
            'valid' => $flat === [],
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'class' => $data['class_id'] ?? null,
            'section' => $data['section_id'] ?? null,
            'roll' => $data['roll_no'] ?? null,
            'student_id' => $data['student_id'] ?? null,
        ];
    }

    private function normalise(string $field, string $value): string
    {
        return match ($field) {
            'gender' => self::GENDERS[mb_strtolower($value)] ?? $value,
            'status' => self::STATUSES[mb_strtolower($value)] ?? $value,
            'guardian_relation' => self::RELATIONS[mb_strtolower($value)] ?? $value,
            'admission_date', 'date_of_birth', 'enrolled_on' => (string) $this->parseDate($value),
            default => $value,
        };
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,4})[\/\-.](\d{1,2})[\/\-.](\d{1,4})$/', $value, $parts) === 1) {
            [$day, $month, $year] = match (true) {
                strlen($parts[1]) === 4 => [(int) $parts[3], (int) $parts[2], (int) $parts[1]],
                strlen($parts[3]) === 4 => [(int) $parts[1], (int) $parts[2], (int) $parts[3]],
                default => [(int) $parts[2], (int) $parts[1], (int) $parts[3]],
            };

            return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : $value;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('Y-m-d', $timestamp);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     * @return array<string, mixed>
     */
    private function resolveLookups(array $data, array &$errors): array
    {
        if (filled($data['academic_session_id'] ?? null)) {
            $name = (string) $data['academic_session_id'];
            $id = $this->lookup('sessions')[mb_strtolower($name)] ?? null;

            if ($id === null) {
                $errors['academic_session_id'] = __('No academic session called ":name" exists. Create it in Academic setup first.', ['name' => $name]);
                unset($data['academic_session_id']);
            } else {
                $data['academic_session_id'] = $id;
            }
        }

        if (filled($data['class_id'] ?? null)) {
            $name = (string) $data['class_id'];
            $classId = $this->lookup('classes')[mb_strtolower($this->slug($name))] ?? null;

            if ($classId === null) {
                $errors['class_id'] = __('No class called ":name" exists in this school.', ['name' => $name]);
                unset($data['class_id'], $data['section_id']);
            } else {
                $data['class_id'] = $classId;

                if (filled($data['section_id'] ?? null)) {
                    $sectionName = (string) $data['section_id'];
                    $sectionId = $this->lookup('sections')[$classId.'|'.mb_strtolower($this->slug($sectionName))] ?? null;

                    if ($sectionId === null) {
                        $errors['section_id'] = __('Section ":section" does not belong to class ":class".', ['section' => $sectionName, 'class' => $name]);
                        unset($data['section_id']);
                    } else {
                        $data['section_id'] = $sectionId;
                    }
                }
            }
        } elseif (filled($data['section_id'] ?? null)) {
            $errors['section_id'] = __('Give a class before a section.');
            unset($data['section_id']);
        }

        if (filled($data['roll_no'] ?? null) && (empty($data['class_id']) || empty($data['academic_session_id']))) {
            $errors['roll_no'] = __('A roll number needs a session and a class with it.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $seen
     * @return array<string, list<string>>
     */
    private function duplicateErrors(int $line, array $data, array &$seen): array
    {
        $errors = [];

        foreach (['student_id' => 'Student ID', 'admission_no' => 'Admission no'] as $field => $label) {
            $value = (string) ($data[$field] ?? '');

            if ($value === '') {
                continue;
            }

            $key = $field.':'.$value;

            if (isset($seen[$key])) {
                $errors[$field][] = __('This :label repeats a row already in this file (line :line).', [
                    'label' => __($label),
                    'line' => $seen[$key],
                ]);

                continue;
            }

            $seen[$key] = $line;

            if (Student::query()->withTrashed()
                ->where('school_id', $this->schoolId())
                ->where($field, $value)
                ->exists()) {
                $errors[$field][] = __('This :label is already used by an existing student.', ['label' => __($label)]);
            }
        }

        $first = trim((string) ($data['first_name'] ?? ''));
        $dob = trim((string) ($data['date_of_birth'] ?? ''));
        $phone = trim((string) ($data['guardian_phone'] ?? ''));

        if ($first !== '' && $dob !== '' && $this->parseDate($dob) !== null) {
            $identity = mb_strtolower($first.'|'.$dob.'|'.$phone);
            $key = 'identity:'.$identity;

            if (isset($seen[$key])) {
                $errors['first_name'][] = __('The same name, date of birth and guardian phone appear on line :line of this file.', [
                    'line' => $seen[$key],
                ]);
            } else {
                $seen[$key] = $line;

                $twin = Student::query()
                    ->where('school_id', $this->schoolId())
                    ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($first)])
                    ->where('date_of_birth', $this->parseDate($dob))
                    ->when($phone !== '', fn ($query) => $query
                        ->whereHas('guardians', fn ($guardian) => $guardian->where('phone', $phone)))
                    ->exists();

                if ($twin) {
                    $errors['first_name'][] = __('A student with this name, date of birth and guardian is already admitted.');
                }
            }
        }

        return $errors;
    }

    /**
     * Two rows can each look valid alone yet claim the same roll in the same
     * class, section and session; only the second one can lose.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function markRollCollisions(array &$rows): void
    {
        $taken = [];

        foreach ($rows as &$row) {
            $data = $row['data'];

            if (! $row['valid'] || empty($data['roll_no']) || empty($data['class_id'])) {
                continue;
            }

            $key = ($data['academic_session_id'] ?? '-').'|'.$data['class_id'].'|'.($data['section_id'] ?? '-').'|'.$data['roll_no'];

            if (isset($taken[$key])) {
                $row['valid'] = false;
                $row['errors']['roll_no'] = array_merge($row['errors']['roll_no'] ?? [], [
                    __('Roll :roll is already used on line :line of this file.', ['roll' => $data['roll_no'], 'line' => $taken[$key]]),
                ]);

                continue;
            }

            $taken[$key] = $row['line'];
        }
    }

    /**
     * @param  list<mixed>  $header
     * @return array{map: array<int, string>, unknown: list<string>}
     */
    private function mapColumns(array $header): array
    {
        $map = [];
        $unknown = [];
        $used = [];

        foreach ($header as $index => $label) {
            $slug = $this->slug((string) $label);

            if ($slug === '') {
                continue;
            }

            $field = $this->fieldFor($slug);

            if ($field === null) {
                $unknown[] = trim((string) $label);

                continue;
            }

            if (isset($used[$field])) {
                $unknown[] = trim((string) $label);

                continue;
            }

            $used[$field] = true;
            $map[$index] = $field;
        }

        return ['map' => $map, 'unknown' => $unknown];
    }

    private function fieldFor(string $slug): ?string
    {
        static $index = null;

        if ($index === null) {
            $index = [];

            foreach (self::ALIASES as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $index[$this->slug($alias)] = $field;
                }
            }
        }

        return $index[$slug] ?? null;
    }

    private function slug(string $value): string
    {
        $value = $this->clean($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '_', $value) ?? '';

        return mb_strtolower(trim($value, '_'));
    }

    private function clean(string $value): string
    {
        return trim(str_replace(["\xEF\xBB\xBF", "\xc2\xa0", "\0"], '', $value));
    }

    /**
     * Excel in several locales writes semicolon separated files and Google Sheets
     * can export tabs; sniffing the header line means the owner converts nothing.
     */
    private function delimiter($handle): string
    {
        $line = fgets($handle) ?: '';
        rewind($handle);

        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($counts);
        $best = (string) array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    /**
     * @return array<string, int>
     */
    private function lookup(string $kind): array
    {
        if (isset($this->lookups[$kind])) {
            return $this->lookups[$kind];
        }

        return $this->lookups[$kind] = match ($kind) {
            'sessions' => AcademicSession::query()
                ->where('school_id', $this->schoolId())
                ->get(['id', 'name'])
                ->mapWithKeys(fn (AcademicSession $session): array => [mb_strtolower(trim($session->name)) => (int) $session->id])
                ->all(),
            'classes' => SchoolClass::query()
                ->where('school_id', $this->schoolId())
                ->whereNull('deleted_at')
                ->get(['id', 'name'])
                ->mapWithKeys(fn (SchoolClass $class): array => [mb_strtolower($this->slug($class->name)) => (int) $class->id])
                ->all(),
            'sections' => Section::query()
                ->whereHas('schoolClass', fn ($query) => $query->where('school_id', $this->schoolId())->whereNull('deleted_at'))
                ->get(['id', 'name', 'class_id'])
                ->mapWithKeys(fn (Section $section): array => [
                    $section->class_id.'|'.mb_strtolower($this->slug($section->name)) => (int) $section->id,
                ])
                ->all(),
            default => [],
        };
    }

    private function path(string $token): string
    {
        abort_unless(preg_match(self::TOKEN_PATTERN, $token) === 1, 404);

        return self::DIRECTORY.'/'.$token;
    }

    private function absolute(string $token): string
    {
        $disk = Storage::disk('local');

        abort_unless($disk->exists($this->path($token)), 404, __('The preview has expired. Upload the sheet again.'));

        return $disk->path($this->path($token));
    }

    private function forgetStale(): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if ($disk->lastModified($file) < now()->subHours(3)->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }

    private function schoolId(): int
    {
        return app(SchoolContext::class)->id();
    }
}
