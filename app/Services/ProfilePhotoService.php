<?php

namespace App\Services;

use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

/**
 * The profile photo a student or teacher manages for themselves in their own
 * portal.
 *
 * The row being written is always the record belonging to the signed-in
 * account, so no value supplied by the browser can point at somebody else's
 * photo. Nothing here resizes, crops or re-encodes: the bytes that arrive are
 * stored as they are, which keeps the original aspect ratio whatever the
 * camera produced.
 */
class ProfilePhotoService
{
    public function __construct(
        private readonly FileUploadService $uploads,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Rules for one upload profile. The accepted formats and the size ceiling
     * come from config, and there is deliberately no dimension rule: any
     * resolution and any ratio is a valid profile picture.
     *
     * @return array<string, list<string>>
     */
    public function rules(string $profile): array
    {
        $config = $this->config($profile);

        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', $config['mimes']),
                'max:'.(int) $config['max_kb'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(string $profile): array
    {
        $config = $this->config($profile);

        return [
            'image.required' => __('Choose an image first.'),
            'image.file' => __('The file must be an image.'),
            'image.image' => __('The file must be an image.'),
            'image.mimes' => __('Only JPEG, PNG and WebP images are allowed.'),
            'image.max' => __('The image must be :size MB or smaller. Crop it to a smaller size and try again.', [
                'size' => self::formatMb((int) $config['max_kb']),
            ]),
        ];
    }

    public function maxKb(string $profile): int
    {
        return (int) $this->config($profile)['max_kb'];
    }

    /**
     * @return list<string>
     */
    public function acceptedFormats(string $profile): array
    {
        return $this->config($profile)['mimes'];
    }

    public function replace(User $actor, Model $owner, UploadedFile $file, string $profile): void
    {
        $oldPhoto = $owner->photo_path;
        $newPhoto = $this->uploads->store($file, $profile, (string) $owner->getAttribute('school_id'));

        try {
            $owner->forceFill(['photo_path' => $newPhoto])->save();
        } catch (Throwable $exception) {
            $this->uploads->delete($newPhoto, $profile);

            throw $exception;
        }

        $this->uploads->delete($oldPhoto, $profile);

        $this->audit($actor, $owner, $oldPhoto, $newPhoto);
    }

    public function remove(User $actor, Model $owner, string $profile): void
    {
        $oldPhoto = $owner->photo_path;

        $owner->forceFill(['photo_path' => null])->save();

        $this->uploads->delete($oldPhoto, $profile);

        $this->audit($actor, $owner, $oldPhoto, null);
    }

    public static function formatMb(int $kb): string
    {
        return rtrim(rtrim(number_format($kb / 1024, 1, '.', ''), '0'), '.');
    }

    private function audit(User $actor, Model $owner, ?string $oldPhoto, ?string $newPhoto): void
    {
        $removed = $newPhoto === null;
        $name = (string) ($owner->full_name ?? $actor->name);

        $this->audit->log(
            event: $removed ? 'profile.photo.removed' : 'profile.photo.updated',
            auditable: $owner,
            description: $removed
                ? __(':name removed their profile photo.', ['name' => $name])
                : __(':name updated their profile photo.', ['name' => $name]),
            oldValues: ['photo' => $oldPhoto === null ? 'none' : 'set'],
            newValues: ['photo' => $newPhoto === null ? 'none' : 'set'],
            actor: $actor,
            schoolId: (int) $owner->getAttribute('school_id'),
        );
    }

    /**
     * @return array{disk: string, path: string, max_kb: int, mimes: list<string>}
     */
    private function config(string $profile): array
    {
        $config = config("school.uploads.{$profile}");

        if (! is_array($config)) {
            throw new RuntimeException("Unknown upload profile [{$profile}].");
        }

        return $config;
    }
}
