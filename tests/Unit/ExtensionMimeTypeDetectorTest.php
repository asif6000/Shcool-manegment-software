<?php

namespace Tests\Unit;

use App\Support\ExtensionMimeTypeDetector;
use PHPUnit\Framework\TestCase;

class ExtensionMimeTypeDetectorTest extends TestCase
{
    public function test_it_maps_known_extensions_to_their_mime_types(): void
    {
        $detector = new ExtensionMimeTypeDetector;

        $this->assertSame('image/jpeg', $detector->detectMimeTypeFromPath('photos/abc.JPG'));
        $this->assertSame('application/pdf', $detector->detectMimeTypeFromPath('documents/scan.pdf'));
        $this->assertNull($detector->detectMimeTypeFromPath('documents/unknown.bin'));
    }

    public function test_it_detects_image_headers_from_the_buffer(): void
    {
        $detector = new ExtensionMimeTypeDetector;

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8AAAwAB/AF+pQn4AAAAAElFTkSuQmCC');

        $this->assertSame('image/png', $detector->detectMimeTypeFromBuffer($png));
        $this->assertNull($detector->detectMimeTypeFromBuffer('not an image'));
    }

    public function test_it_falls_back_to_the_buffer_when_the_path_has_no_extension(): void
    {
        $detector = new ExtensionMimeTypeDetector;

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8AAAwAB/AF+pQn4AAAAAElFTkSuQmCC');

        $this->assertSame('image/png', $detector->detectMimeType('uploads/photo', $png));
    }
}
