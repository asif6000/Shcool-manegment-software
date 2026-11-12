<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUser;
use App\Models\AttendanceEvent;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentAttendanceCard;
use App\Services\Attendance\DeviceAdapterManager;
use App\Services\AttendanceDeviceService;
use App\Services\StudentAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Honest overview of every attendance method.
 *
 * Manual and QR are built into the application and are always available.
 * Fingerprint and face are reported as NOT_CONFIGURED until a real adapter is
 * registered in config/attendance.php and a device has authenticated; nothing
 * here simulates a connected terminal.
 */
class AttendanceMethodController extends Controller
{
    /** Information the school must obtain from a vendor before a driver is written. */
    private const MANUFACTURER_CHECKLIST = [
        'Exact device model, firmware and whether the terminal pushes events or has to be polled.',
        'Transport and protocol: HTTPS webhook, TCP socket, MQTT, SFTP export or a vendor SDK.',
        'Authentication method and how request signatures are computed and verified.',
        'Event payload schema, including a stable event identifier usable for idempotency.',
        'How device user IDs are created, exported and kept in sync with student admissions.',
        'Clock and timezone behaviour, NTP support and the maximum acceptable event delay.',
        'Whether the vendor stores biometric templates, where, and for how long.',
        'Written consent and retention terms needed for face templates, including minors.',
        'Sandbox or test device access, plus a support contact for integration issues.',
    ];

    public function __construct(
        private readonly DeviceAdapterManager $adapters,
        private readonly AttendanceDeviceService $devices,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('attendance.view');

        $canManage = $request->user()->can('attendance.devices.manage');

        $devices = AttendanceDevice::query()
            ->withCount('mappings')
            ->withCount(['events' => fn ($query) => $query->where('received_at', '>=', now()->subDay())])
            ->orderBy('kind')
            ->orderBy('name')
            ->get();

        $methods = [
            'manual' => [
                'key' => 'manual',
                'label' => __('Manual register'),
                'state' => 'ready',
                'icon' => 'bi-ui-checks',
                'summary' => __('Teachers mark a class from the register; corrections are audited and locked.'),
                'metric' => __(':count marked today', ['count' => StudentAttendance::query()
                    ->where('source', StudentAttendanceService::SOURCE_MANUAL)
                    ->where('attendance_date', today()->toDateString())
                    ->count()]),
                'route' => route('attendance.students.index'),
                'action' => __('Open register'),
            ],
            'qr' => [
                'key' => 'qr',
                'label' => __('ID card QR'),
                'state' => 'ready',
                'icon' => 'bi-qr-code-scan',
                'summary' => __('Cards carry a random code; the server checks the card, session and working day.'),
                'metric' => __(':cards active · :scans scans today', [
                    'cards' => StudentAttendanceCard::query()->active()->count(),
                    'scans' => AttendanceEvent::query()
                        ->where('source', 'qr')
                        ->where('status', AttendanceEvent::STATUS_APPLIED)
                        ->whereDate('occurred_at', today())
                        ->count(),
                ]),
                'route' => route('attendance.qr.index'),
                'action' => __('Open scanner'),
            ],
        ];

        foreach (['fingerprint', 'face'] as $kind) {
            $methods[$kind] = $this->biometricMethod($kind, $devices);
        }

        $class = $request->filled('class_id')
            ? SchoolClass::query()->with(['sections' => fn ($query) => $query->orderBy('name')])->find((int) $request->query('class_id'))
            : null;
        $section = $class !== null && $request->filled('section_id')
            ? $class->sections->firstWhere('id', (int) $request->query('section_id'))
            : null;

        return view('attendance.methods', [
            'methods' => $methods,
            'devices' => $devices,
            'canManage' => $canManage,
            'classes' => $canManage
                ? SchoolClass::query()->with(['sections' => fn ($query) => $query->orderBy('name')])->orderBy('level')->get()
                : collect(),
            'class' => $class,
            'section' => $section,
            'roster' => $class !== null ? $this->roster($class, $section) : collect(),
            'mappings' => $canManage
                ? AttendanceDeviceUser::query()->with('student')->orderBy('device_user_id')->get()->groupBy('attendance_device_id')
                : collect(),
            'recent' => AttendanceEvent::query()
                ->whereIn('source', ['qr', 'fingerprint', 'face'])
                ->with(['student', 'device'])
                ->latest('received_at')
                ->limit(15)
                ->get(),
            'checklist' => self::MANUFACTURER_CHECKLIST,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'string', 'in:'.implode(',', AttendanceDevice::KINDS)],
            'serial' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);

        ['device' => $device, 'secret' => $secret] = $this->devices->register($request->user(), $data);

        return redirect()
            ->route('attendance.methods.index')
            ->with('device_secret', ['name' => $device->name, 'secret' => $secret])
            ->with('success', __('The device was registered. Copy its secret now; it is not shown again.'));
    }

    public function rotate(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        $secret = $this->devices->rotateSecret($device, $request->user());

        return redirect()
            ->route('attendance.methods.index')
            ->with('device_secret', ['name' => $device->name, 'secret' => $secret])
            ->with('success', __('New credentials were generated for :name. The old secret no longer works.', ['name' => $device->name]));
    }

    public function toggle(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        $this->devices->setActive($device, ! $device->is_active, $request->user());

        return redirect()
            ->route('attendance.methods.index')
            ->with('success', $device->is_active
                ? __(':name was disabled.', ['name' => $device->name])
                : __(':name was enabled.', ['name' => $device->name]));
    }

    public function mapUser(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        $data = $request->validate([
            'device_user_id' => ['required', 'string', 'max:191'],
            'student_id' => ['required', 'integer'],
        ]);

        $student = Student::query()->findOrFail((int) $data['student_id']);

        $this->devices->mapUser($device, $data['device_user_id'], $student, $request->user());

        return redirect()
            ->route('attendance.methods.index')
            ->with('success', __('Device user :user is now mapped to :name.', [
                'user' => $data['device_user_id'],
                'name' => $student->full_name,
            ]));
    }

    public function unmapUser(Request $request, AttendanceDevice $device, AttendanceDeviceUser $mapping): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        abort_unless((int) $mapping->attendance_device_id === (int) $device->id, 404);

        $mapping->delete();

        return redirect()
            ->route('attendance.methods.index')
            ->with('success', __('The device user mapping was removed.'));
    }

    /**
     * @param  Collection<int, AttendanceDevice>  $devices
     * @return array<string, mixed>
     */
    private function biometricMethod(string $kind, Collection $devices): array
    {
        $configured = $this->adapters->configured($kind);
        $own = $devices->where('kind', $kind);
        $states = $own->map(fn (AttendanceDevice $device): string => $device->effectiveStatus());

        $state = match (true) {
            $states->contains(AttendanceDevice::STATUS_READY) => AttendanceDevice::STATUS_READY,
            $states->contains(AttendanceDevice::STATUS_ERROR) => AttendanceDevice::STATUS_ERROR,
            ! $configured || $own->isEmpty() => AttendanceDevice::STATUS_NOT_CONFIGURED,
            default => AttendanceDevice::STATUS_DISCONNECTED,
        };

        $lastSync = $own->max('last_synced_at');

        return [
            'key' => $kind,
            'label' => $kind === 'face' ? __('Face recognition') : __('Fingerprint'),
            'state' => $state,
            'icon' => $kind === 'face' ? 'bi-person-bounding-box' : 'bi-fingerprint',
            'summary' => $configured
                ? __('A driver is registered on the server. The method is only operational once a terminal authenticates and its punches are accepted.')
                : __('No provider has been selected. Events are refused until a real adapter is configured and verified with hardware.'),
            'metric' => $own->isEmpty()
                ? __('No terminal registered')
                : __(':count terminal(s) · last sync :time', [
                    'count' => $own->count(),
                    'time' => $lastSync ? Carbon::parse($lastSync)->diffForHumans() : __('never'),
                ]),
            'route' => null,
            'action' => null,
        ];
    }

    /**
     * @return Collection<int, Enrollment>
     */
    private function roster(SchoolClass $class, ?Section $section): Collection
    {
        $session = AcademicSession::query()
            ->where('is_current', true)
            ->orderByDesc('start_date')
            ->first();

        if ($session === null) {
            return collect();
        }

        return Enrollment::query()
            ->with(['student', 'section'])
            ->where('academic_session_id', $session->id)
            ->where('class_id', $class->id)
            ->when($section !== null, fn ($query) => $query->where('section_id', $section->id))
            ->where('status', 'active')
            ->whereHas('student')
            ->orderBy('section_id')
            ->orderBy('roll_no')
            ->get();
    }
}
