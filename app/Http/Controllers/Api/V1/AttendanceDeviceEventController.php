<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Attendance\DeviceAdapterManager;
use App\Services\AttendanceDeviceService;
use App\Services\AttendanceEventService;
use App\Support\SchoolContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Push endpoint for fingerprint and face terminals.
 *
 * No vendor protocol is assumed: a device authenticates with the ID and secret
 * issued in the admin screen and posts neutral punch records. The endpoint
 * refuses all traffic while no adapter is configured for the device kind, so a
 * dashboard can never show a biometric method as working when it is not.
 */
class AttendanceDeviceEventController extends Controller
{
    public function __construct(
        private readonly AttendanceDeviceService $devices,
        private readonly DeviceAdapterManager $adapters,
        private readonly AttendanceEventService $events,
        private readonly SchoolContext $school,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->isSecure() && ! app()->environment(['local', 'testing'])) {
            return response()->json([
                'status' => 'rejected',
                'message' => __('This endpoint requires HTTPS.'),
            ], 403);
        }

        $device = $this->devices->authenticate(
            $request->header('X-Device-Id'),
            $request->header('X-Device-Secret'),
        );

        if ($device === null) {
            return response()->json([
                'status' => 'unauthenticated',
                'message' => __('Invalid device credentials.'),
            ], 401);
        }

        if (! $this->adapters->configured($device->kind)) {
            return response()->json([
                'status' => 'not_configured',
                'message' => __('No :kind integration is configured on this server, so events are not accepted.', [
                    'kind' => $device->kindLabel(),
                ]),
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'events' => ['required', 'array', 'min:1', 'max:'.(int) config('attendance.devices.ingest_max_batch', 100)],
            'events.*.external_ref' => ['required', 'string', 'max:191'],
            'events.*.user_id' => ['required', 'string', 'max:191'],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.meta' => ['nullable', 'array'],
            'events.*.meta.*' => ['nullable', 'scalar'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'invalid',
                'message' => __('The event payload was rejected.'),
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $validator->validated()['events'];
        $source = $device->kind;

        $results = $this->school->run((int) $device->school_id, function () use ($device, $rows, $source): array {
            $results = [];

            foreach ($rows as $row) {
                $occurredAt = Carbon::parse((string) $row['occurred_at']);
                $meta = $row['meta'] ?? [];
                $externalRef = (string) $row['external_ref'];
                $deviceUserId = (string) $row['user_id'];

                $student = $this->devices->studentFor($device, $deviceUserId);

                $event = $student === null
                    ? $this->events->reject($device, $source, $externalRef, $deviceUserId, $occurredAt, 'unknown_device_user', $meta)
                    : $this->events->ingest(
                        actor: null,
                        student: $student,
                        source: $source,
                        externalRef: $externalRef,
                        occurredAt: $occurredAt,
                        device: $device,
                        deviceUserId: $deviceUserId,
                        meta: $meta,
                    );

                $results[] = [
                    'external_ref' => $externalRef,
                    'status' => $event->status,
                    'reason' => $event->reason,
                    'message' => $event->reasonLabel($student?->full_name),
                    'student' => $student === null ? null : [
                        'student_id' => $student->student_id,
                        'name' => $student->full_name,
                    ],
                ];
            }

            return $results;
        });

        $device->forceFill([
            'status' => $device::STATUS_READY,
            'last_seen_at' => now(),
            'last_synced_at' => now(),
            'last_error' => null,
        ])->save();

        return response()->json([
            'status' => 'accepted',
            'device' => ['id' => $device->id, 'kind' => $device->kind],
            'received' => count($results),
            'results' => $results,
        ]);
    }
}
