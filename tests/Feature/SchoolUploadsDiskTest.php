<?php

namespace Tests\Feature;

use App\Support\ExtensionMimeTypeDetector;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use ReflectionProperty;
use Tests\TestCase;

class SchoolUploadsDiskTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/school_uploads');
        config(['filesystems.disks.school_uploads.root' => $this->root]);
        Storage::forgetDisk('school_uploads');
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('school_uploads');
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_the_school_uploads_disk_uses_the_extension_detector(): void
    {
        $this->assertSame('school_local', config('filesystems.disks.school_uploads.driver'));

        $disk = Storage::disk('school_uploads');
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);

        $adapter = $this->readProperty($disk, 'adapter');
        $this->assertInstanceOf(LocalFilesystemAdapter::class, $adapter);
        $this->assertInstanceOf(ExtensionMimeTypeDetector::class, $this->readProperty($adapter, 'mimeTypeDetector'));
    }

    public function test_the_school_uploads_disk_writes_reads_and_serves_files(): void
    {
        $disk = Storage::disk('school_uploads');

        $this->assertTrue($disk->put('photos/example.txt', 'hello'));
        $this->assertTrue($disk->exists('photos/example.txt'));
        $this->assertSame('hello', $disk->get('photos/example.txt'));
        $this->assertSame('/uploads/photos/example.txt', $disk->url('photos/example.txt'));
        $this->assertTrue($disk->delete('photos/example.txt'));

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8AAAwAB/AF+pQn4AAAAAElFTkSuQmCC');

        $this->assertTrue($disk->put('photos/example.png', $png));
        $this->assertSame('image/png', $disk->mimeType('photos/example.png'));
    }

    private function readProperty(object $object, string $property): mixed
    {
        return (new ReflectionProperty($object, $property))->getValue($object);
    }
}
