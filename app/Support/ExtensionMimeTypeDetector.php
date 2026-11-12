<?php

namespace App\Support;

use League\MimeTypeDetection\MimeTypeDetector;

/**
 * Flysystem's default detector constructs the ext-fileinfo `finfo` class while
 * the local adapter is being built. Some shared hosts disable that extension,
 * which makes every upload fail with "Class \"finfo\" not found" before any
 * file is written. Uploaded bytes are already validated by the request rules,
 * so the extension and the image header are enough for storage metadata.
 */
class ExtensionMimeTypeDetector implements MimeTypeDetector
{
    private const EXTENSIONS = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function detectMimeType(string $path, $contents): ?string
    {
        return $this->detectMimeTypeFromPath($path)
            ?? (is_string($contents) ? $this->detectMimeTypeFromBuffer($contents) : null);
    }

    public function detectMimeTypeFromBuffer(string $contents): ?string
    {
        $info = @getimagesizefromstring($contents);

        return is_array($info) && isset($info['mime']) ? $info['mime'] : null;
    }

    public function detectMimeTypeFromPath(string $path): ?string
    {
        return self::EXTENSIONS[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
    }

    public function detectMimeTypeFromFile(string $path): ?string
    {
        return $this->detectMimeTypeFromPath($path);
    }
}
