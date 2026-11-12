<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUser;
use App\Models\AttendanceRule;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\AttendanceDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin interface for face recognition attendance configuration.
 *
 * This controller provides:
 * 1) Face Recognition Setup with enable/disable, Student/Teacher/Both selection
 * 2) Device Management with device details and connection testing
 * 3) Face Enrollment with search and mapping
 * 4) Attendance Rules configuration
 * 5) Dashboard statistics
 */
class AdminFaceController extends Controller
{
    public function __construct(
        private readonly AttendanceDeviceService $devices,
    ) {}

    /**
     * Show main Face Recognition setup page.
     */
    public function index(Request $request): View
    {
        $this->requirePermission('attendance.devices.manage');

        // Get all face recognition devices
        $faceDevices = AttendanceDevice::query()
            ->where('kind', 'face')
            ->withCount('mappings')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        // Get rules for current school
        $rules = AttendanceRule::query()
            ->where('school_id', $request->user()->school_id)
            ->orderByDesc('priority')
            ->get()
            ->first();

        if ($rules === null) {
            // Create default rule if none exists
            $rules = AttendanceRule::create([
                'school_id' => $request->user()->school_id,
                'name' => 'Default Attendance Rules',
                'start_time' => '08:30:00',
                'end_time' => '15:00:00',
                'late_threshold_minutes' => 15,
                'very_late_threshold_minutes' => 60,
                'window_hours' => 12.00,
                'duplicate_interval_seconds' => 300,
                'allow_holiday_attendance' => false,
                'skip_weekends' => true,
                'working_days' => [1, 2, 3, 4, 5], // Sunday-Thursday
                'send_sms_on_present' => true,
                'send_sms_on_late' => false,
                'send_sms_to_guardian' => true,
                'max_checks_per_day' => 10,
                'is_active' => true,
                'priority' => 0,
            ]);
        }

        return view('admin.attendance.face.index', [
            'devices' => $faceDevices,
            'rules' => $rules,
        ]);
    }

    /**
     * Toggle face recognition system (enable/disable).
     */
    public function toggleSystem(Request $request): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        $data = $request->validate([
            'is_enabled' => 'required|boolean',
        ]);

        // Update all face devices to match enabled status
        AttendanceDevice::query()
            ->where('kind', 'face')
            ->where('school_id', $request->user()->school_id)
            ->update([
                'is_active' => $data['is_enabled'],
            ]);

        return redirect()
            ->route('admin.attendance.face.index')
            ->with('success', $data['is_enabled']
                ? __('Face recognition attendance has been enabled.')
                : __('Face recognition attendance has been disabled.'));
    }

    /**
     * Select which user types can use face recognition.
     */
    public function updateUserTypes(Request $request): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        $data = $request->validate([
            'user_types' => 'required|array|min:1',
            'user_types.*' => 'in:students,teachers,both',
        ]);

        // Store user type preference in session or settings
        // For now, we'll store in the existing settings table
        \App\Models\Setting::query()->updateOrCreate(
            ['key' => 'face_recognition_user_types'],
            [
                'value' => json_encode($data['user_types']),
                'type' => 'json',
                'group_name' => 'attendance',
            ]
        );

        return redirect()
            ->route('admin.attendance.face.index')
            ->with('success', __('User types updated successfully'));
    }

    /**
     * Show device registration form.
     */
    public function registerDevice(Request $request): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'serial' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:100'],
            'firmware' => ['nullable', 'string', 'max:50'],
            'lan_ip' => ['nullable', 'ip', 'max:45'],
            'api_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['nullable', 'string', 'max:50'],
        ]);

        [$device, $secret] = $this->devices->register($request->user(), [
            'name' => $validated['name'],
            'kind' => 'face',
            'serial' => $validated['serial'],
            'location' => $validated['location'],
            'config' => array_filter([
                'model' => $validated['model'] ?? null,
                'firmware' => $validated['firmware'] ?? null,
                'lan_ip' => $validated['lan_ip'] ?? null,
                'api_port' => $validated['api_port'] ?? null,
                'protocol' => $validated['protocol'] ?? null,
            ]),
        ]);

        return redirect()
            ->route('admin.attendance.face.index')
            ->with('device_secret', ['name' => $device->name, 'secret' => $secret])
            ->with('success', __('Face device ":name" was registered successfully.', ['name' => $device->name]));
    }

    /**
     * Test device connection via real network probe (TCP / HTTP socket).
     */
    public function testConnection(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        abort_unless($device->kind === 'face', 404);

        $config = is_array($device->config) ? $device->config : [];
        $lanIp = trim((string) ($config['lan_ip'] ?? ''));
        $port = (int) ($config['api_port'] ?? 80);

        // Check for missing or incomplete IP (such as "192.168.1.")
        if ($lanIp === '' || ! filter_var($lanIp, FILTER_VALIDATE_IP)) {
            $device->forceFill([
                'status' => AttendanceDevice::STATUS_ERROR,
                'last_error' => 'Device IP is missing, invalid or incomplete (configured: "'.$lanIp.'").',
            ])->save();

            return redirect()
                ->route('admin.attendance.face.index')
                ->with('error', __(
                    'Connection test blocked: Device ":name" does not have a complete, valid IP address (current: ":ip"). Please complete Wi-Fi setup on the physical device and assign a valid static IPv4 address.',
                    ['name' => $device->name, 'ip' => $lanIp ?: 'not set']
                ));
        }

        if ($port < 1 || $port > 65535) {
            $port = 80;
        }

        // Perform real TCP socket connection probe with strict 3-second timeout
        $startTime = microtime(true);
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($lanIp, $port, $errno, $errstr, 3.0);
        $latencyMs = round((microtime(true) - $startTime) * 1000);

        if (! $fp) {
            $errorMessage = $errstr ?: 'Network unreachable or connection timed out';
            $device->forceFill([
                'status' => AttendanceDevice::STATUS_DISCONNECTED,
                'last_error' => "TCP {$lanIp}:{$port} probe failed: {$errorMessage} (code {$errno})",
            ])->save();

            return redirect()
                ->route('admin.attendance.face.index')
                ->with('error', __(
                    'Connection failed to :name at :ip::port (:error). If the school uses cPanel cloud hosting and the device is on a private Wi-Fi network, a Local Windows Bridge Agent is required.',
                    ['name' => $device->name, 'ip' => $lanIp, 'port' => $port, 'error' => $errorMessage]
                ));
        }

        fclose($fp);

        $device->forceFill([
            'status' => AttendanceDevice::STATUS_READY,
            'last_seen_at' => now(),
            'last_error' => null,
        ])->save();

        return redirect()
            ->route('admin.attendance.face.index')
            ->with('success', __(
                'Device :name at :ip::port is reachable via TCP! (Latency: :latency ms).',
                ['name' => $device->name, 'ip' => $lanIp, 'port' => $port, 'latency' => $latencyMs]
            ));
    }

    /**
     * Display face enrollment screen.
     */
    public function enroll(Request $request): View
    {
        $this->requirePermission('attendance.devices.manage');

        $searchType = $request->query('type', 'student');
        $searchQuery = $request->query('q', '');
        $deviceId = $request->query('device_id');

        $availableDevices = AttendanceDevice::query()
            ->where('kind', 'face')
            ->where('is_active', true)
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        $searchResults = collect();
        $existingMappings = collect();

        if (!empty($searchQuery)) {
            if ($searchType === 'student') {
                $searchResults = Student::query()
                    ->where(function ($q) use ($searchQuery) {
                        $q->where('full_name', 'like', "%{$searchQuery}%")
                            ->orWhere('student_id', 'like', "%{$searchQuery}%")
                            ->orWhere('email', 'like', "%{$searchQuery}%");
                    })
                    ->limit(20)
                    ->get();
            } else {
                $searchResults = \App\Models\Teacher::query()
                    ->where(function ($q) use ($searchQuery) {
                        $q->where('full_name', 'like', "%{$searchQuery}%")
                            ->orWhere('employee_code', 'like', "%{$searchQuery}%");
                    })
                    ->limit(20)
                    ->get();
            }

            // Check existing mappings for these users
            if (!empty($deviceId)) {
                $device = AttendanceDevice::find((int)$deviceId);
                if ($device) {
                    $existingMappings = AttendanceDeviceUser::query()
                        ->where('attendance_device_id', $deviceId)
                        ->whereIn('student_id', $searchResults->pluck('id'))
                        ->get(['student_id'])
                        ->pluck('student_id')
                        ->toArray();
                }
            }
        }

        return view('admin.attendance.face.enroll', [
            'searchType' => $searchType,
            'searchQuery' => $searchQuery,
            'searchResults' => $searchResults,
            'availableDevices' => $availableDevices,
            'deviceId' => $deviceId,
            'existingMappings' => $existingMappings,
        ]);
    }

    /**
     * Perform face enrollment mapping.
     */
    public function mapUser(Request $request): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        $validated = $request->validate([
            'device_id' => 'required|exists:attendance_devices,id',
            'user_type' => 'required|in:students,teachers',
            'user_id' => 'required|integer',
            'device_user_id' => 'required|string|max:191',
        ]);

        $device = AttendanceDevice::find((int)$validated['device_id']);

        abort_unless($device->kind === 'face', 404);
        abort_unless($device->is_active, 422, 'Device is not active');

        if ($validated['user_type'] === 'students') {
            $student = Student::query()->findOrFail((int)$validated['user_id']);
        } else {
            // FIX: Teacher instances are never instanceof Student, causing the
            // abort(404) to fire unconditionally. Teacher face mapping is not
            // supported by the current DB schema (FK references students table).
            return redirect()
                ->route('admin.attendance.face.enroll', [
                    'device_id' => $validated['device_id'],
                    'type'      => $validated['user_type'],
                ])
                ->with('error', __('Teacher face enrollment is not yet supported. Only student mapping is currently available.'));
        }

        try {
            $this->devices->mapUser(
                $device,
                $validated['device_user_id'],
                $student,
                $request->user()
            );

            return redirect()
                ->route('admin.attendance.face.enroll', [
                    'device_id' => $validated['device_id'],
                    'type' => $validated['user_type'],
                ])
                ->with('success', __('Device user ID ":id" mapped to :name successfully.', [
                    'id' => $validated['device_user_id'],
                    'name' => $student->full_name,
                ]));
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()
                ->route('admin.attendance.face.enroll', [
                    'device_id' => $validated['device_id'],
                    'type' => $validated['user_type'],
                ])
                ->with('error', __('Duplicate mapping detected. This user or device_user_id is already mapped on this device.'));
        }
    }

    /**
     * Update attendance rules.
     */
    public function updateRules(Request $request): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s',
            'late_threshold_minutes' => 'required|integer|min:1|max:120',
            'very_late_threshold_minutes' => 'required|integer|min:15|max:240',
            'window_hours' => 'required|numeric|min:6|max:24',
            'duplicate_interval_seconds' => 'required|integer|min:30|max:3600',
            'allow_holiday_attendance' => 'boolean',
            'skip_weekends' => 'boolean',
            'working_days' => 'sometimes|array|min:1',
            'working_days.*' => 'in:0,1,2,3,4,5,6',
            'send_sms_on_present' => 'boolean',
            'send_sms_on_late' => 'boolean',
            'send_sms_to_guardian' => 'boolean',
            'max_checks_per_day' => 'required|integer|min:2|max:50',
        ]);

        $rule = AttendanceRule::query()
            ->where('school_id', $request->user()->school_id)
            ->orderBy('priority')
            ->first();

        if ($rule === null) {
            $validated['priority'] = 0;
            AttendanceRule::create(array_merge($validated, ['school_id' => $request->user()->school_id]));
        } else {
            $rule->update(array_filter($validated));
        }

        return redirect()
            ->route('admin.attendance.face.index')
            ->with('success', __('Attendance rules have been updated successfully.'));
    }

    /**
     * Unmap a user from a device.
     */
    public function unmapUser(Request $request, AttendanceDeviceUser $mapping): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        abort_unless(in_array($mapping->device->kind, ['fingerprint', 'face'], true), 404);

        $userId = $mapping->student_id;
        $mapping->delete();

        return redirect()
            ->back()
            ->with('success', __('The device user mapping was removed successfully.'));
    }

    /**
     * Delete a face device.
     */
    public function deleteDevice(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        abort_unless($device->kind === 'face', 404);

        // Check for existing mappings
        $mappingCount = $device->mappings()->count();

        if ($mappingCount > 0) {
            return redirect()
                ->route('admin.attendance.face.index')
                ->with('error', __(
                    'Cannot delete device. It has :count active mappings. Please remove all mappings first.',
                    ['count' => $mappingCount]
                ));
        }

        $device->delete();

        return redirect()
            ->route('admin.attendance.face.index')
            ->with('success', __('Face device ":name" was deleted successfully.', ['name' => $device->name]));
    }

    /**
     * Rotate secret for a face device.
     */
    public function rotateSecret(Request $request, AttendanceDevice $device): RedirectResponse
    {
        $this->requirePermission('attendance.devices.manage');

        abort_unless($device->kind === 'face', 404);

        $secret = $this->devices->rotateSecret($device, $request->user());

        return redirect()
            ->route('admin.attendance.face.index')
            ->with('device_secret', ['name' => $device->name, 'secret' => $secret])
            ->with('success', __('Credentials for device ":name" were rotated successfully.', ['name' => $device->name]));
    }
}
