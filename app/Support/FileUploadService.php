<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileUploadService
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Documents accepted from the portal. The extension always comes from the
     * sniffed MIME type, never from the client-supplied filename, so an uploaded
     * script can only ever be stored with the extension of the bytes it holds.
     */
    private const DOCUMENT_MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    /**
     * A .docx or .xlsx is a ZIP container, and libmagic reports it as
     * application/zip on many systems. The container is accepted only when the
     * package really carries the OOXML entry points, and it is stored with the
     * extension matching those entries rather than the filename.
     */
    private const OOXML_EXTENSIONS = [
        'docx' => ['[Content_Types].xml', 'word/'],
        'xlsx' => ['[Content_Types].xml', 'xl/'],
    ];

    /**
     * Video clips an admin uploads for a class. The extension still comes from
     * the sniffed bytes, so a renamed script cannot be stored as a video.
     */
    private const VIDEO_MIME_EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
        'video/quicktime' => 'mov',
    ];

    public function store(UploadedFile $file, string $profile, string $ownerKey): string
    {
        return $this->storeWith($file, $profile, $ownerKey, self::MIME_EXTENSIONS, __('Only JPEG, PNG and WebP images are allowed.'));
    }

    /**
     * @param  UploadedFile  $file  a document already passing its validation rules
     */
    public function storeDocument(UploadedFile $file, string $profile, string $ownerKey): string
    {
        return $this->storeWith(
            $file,
            $profile,
            $ownerKey,
            self::DOCUMENT_MIME_EXTENSIONS,
            __('The file type is not allowed. Upload a PDF, document, spreadsheet, text file or image.'),
            $this->ooxmlExtension($file),
        );
    }

    /**
     * @param  UploadedFile  $file  a video already passing its validation rules
     */
    public function storeVideo(UploadedFile $file, string $profile, string $ownerKey): string
    {
        return $this->storeWith(
            $file,
            $profile,
            $ownerKey,
            self::VIDEO_MIME_EXTENSIONS,
            __('Upload an MP4, WebM, Ogg or QuickTime video file.')
        );
    }

    /**
     * @param  array<string, string>  $allowed
     */
    private function storeWith(UploadedFile $file, string $profile, string $ownerKey, array $allowed, string $rejection, ?string $ooxmlExtension = null): string
    {
        $config = $this->config($profile);
        $mime = $file->getMimeType();
        $extension = $allowed[$mime] ?? null;

        if ($extension === null && $ooxmlExtension !== null && $mime === 'application/zip') {
            $extension = $ooxmlExtension;
        }

        if ($extension === null) {
            throw ValidationException::withMessages([
                'file' => $rejection,
            ]);
        }

        $directory = trim($config['path'].'/'.$ownerKey, '/');
        $filename = Str::uuid()->toString().'.'.$extension;

        $path = $file->storeAs($directory, $filename, ['disk' => $config['disk']]);

        if (! is_string($path)) {
            throw new RuntimeException('Unable to store the uploaded file.');
        }

        return $path;
    }

    /**
     * Copy an already-validated image into another profile directory with a
     * fresh name. Returns null when the source is missing or lives outside the
     * expected directory, so callers never move arbitrary files.
     */
    public function copy(string $path, string $fromProfile, string $toProfile, string $ownerKey): ?string
    {
        $from = $this->config($fromProfile);
        $to = $this->config($toProfile);

        if ($from['disk'] !== $to['disk'] || ! Str::startsWith($path, $from['path'].'/')) {
            return null;
        }

        $disk = Storage::disk($to['disk']);

        if (! $disk->exists($path)) {
            return null;
        }

        $extension = Str::lower((string) pathinfo($path, PATHINFO_EXTENSION));
        $target = trim($to['path'].'/'.$ownerKey, '/').'/'.Str::uuid()->toString().'.'.$extension;

        return $disk->copy($path, $target) ? $target : null;
    }

    public function delete(?string $path, string $profile): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $config = $this->config($profile);

        if (! Str::startsWith($path, $config['path'].'/')) {
            return;
        }

        Storage::disk($config['disk'])->delete($path);
    }

    public function url(?string $path, string $profile): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk($this->config($profile)['disk'])->url($path);
    }

    /**
     * Same as url(), but returns null when the stored file is gone, so callers
     * can fall back to a default instead of printing a broken image.
     */
    public function urlIfExists(?string $path, string $profile): ?string
    {
        $url = $this->url($path, $profile);

        if ($url === null) {
            return null;
        }

        return Storage::disk($this->config($profile)['disk'])->exists($path) ? $url : null;
    }

    /**
     * A private download for one of our own stored files. The caller must have
     * already decided that this actor may reach the record the path belongs to.
     * A path outside the profile's own directory never leaves the disk, so a
     * stale or tampered column cannot be used to read elsewhere.
     */
    public function response(string $path, string $profile, ?string $label): StreamedResponse
    {
        $config = $this->config($profile);
        abort_unless(Str::startsWith($path, $config['path'].'/'), 404);

        $disk = Storage::disk($config['disk']);
        abort_unless($disk->exists($path), 404);

        // The download name is echoed into a response header, so it is reduced to
        // safe characters and the extension is taken from the stored path only.
        $filename = Str::of($label ?? '')
            ->ascii()
            ->replace(['"', "'", '\\', '/', "\n", "\r", ';'], '')
            ->squish()
            ->limit(60, '')
            ->trim()
            ->toString();
        $filename = ($filename === '' ? 'file' : $filename).'.'.pathinfo($path, PATHINFO_EXTENSION);

        return $disk->response($path, $filename, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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

    /**
     * The client may only steer between the two OOXML kinds, and only after the
     * bytes prove the container carries that kind's entry points.
     */
    private function ooxmlExtension(UploadedFile $file): ?string
    {
        $client = strtolower($file->getClientOriginalExtension());
        $markers = self::OOXML_EXTENSIONS[$client] ?? null;

        if ($markers === null) {
            return null;
        }

        $path = $file->getRealPath();
        $bytes = is_string($path) ? @file_get_contents($path) : false;

        if (! is_string($bytes)) {
            return null;
        }

        foreach ($markers as $marker) {
            if (! str_contains($bytes, $marker)) {
                return null;
            }
        }

        return $client;
    }
}
