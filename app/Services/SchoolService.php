<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SchoolService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly FileUploadService $uploads,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, School $school, array $data, ?UploadedFile $logo = null): School
    {
        if (! $actor->can('update', $school)) {
            throw new AuthorizationException(__('You are not allowed to update this school.'));
        }

        $oldValues = $school->only([
            'name', 'short_name', 'code', 'eiin', 'email', 'phone', 'phone_alt',
            'website', 'address', 'division', 'district', 'upazila', 'postcode',
            'motto', 'established_year', 'timezone', 'currency', 'locale', 'logo_path',
        ]);

        $oldLogo = $school->logo_path;
        $newLogo = $logo !== null
            ? $this->uploads->store($logo, 'school_logo', (string) $school->id)
            : null;

        try {
            DB::transaction(function () use ($actor, $school, $data, $newLogo, $oldValues): void {
                $school->fill(Arr::only($data, [
                    'name', 'short_name', 'code', 'eiin', 'email', 'phone', 'phone_alt',
                    'website', 'address', 'division', 'district', 'upazila', 'postcode',
                    'motto', 'established_year', 'timezone', 'currency', 'locale',
                ]));

                if ($newLogo !== null) {
                    $school->logo_path = $newLogo;
                }

                $school->save();

                $this->auditLogger->log(
                    event: 'school.updated',
                    auditable: $school,
                    description: __('School profile was updated'),
                    oldValues: $oldValues,
                    newValues: $school->only([
                        'name', 'short_name', 'code', 'eiin', 'email', 'phone', 'phone_alt',
                        'website', 'address', 'division', 'district', 'upazila', 'postcode',
                        'motto', 'established_year', 'timezone', 'currency', 'locale', 'logo_path',
                    ]),
                    actor: $actor,
                    schoolId: $school->id,
                );
            });
        } catch (\Throwable $exception) {
            if ($newLogo !== null) {
                $this->uploads->delete($newLogo, 'school_logo');
            }

            throw $exception;
        }

        if ($newLogo !== null) {
            $this->uploads->delete($oldLogo, 'school_logo');
        }

        return $school->refresh();
    }
}
