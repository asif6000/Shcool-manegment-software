<?php

namespace App\Models;

use App\Support\FileUploadService;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'short_name',
        'code',
        'slug',
        'eiin',
        'email',
        'phone',
        'phone_alt',
        'website',
        'address',
        'division',
        'district',
        'upazila',
        'postcode',
        'logo_path',
        'motto',
        'established_year',
        'timezone',
        'currency',
        'locale',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'established_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function academicSessions(): HasMany
    {
        return $this->hasMany(AcademicSession::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function logoUrl(): ?string
    {
        $url = app(FileUploadService::class)->urlIfExists($this->logo_path, 'school_logo');

        if ($url === null) {
            return asset('branding/logo.png').'?v=2';
        }

        // A replaced logo can keep its old path on some hosts, so the version
        // suffix forces browsers and the service worker to fetch the new file.
        return $url.'?v='.substr(md5($this->logo_path), 0, 8);
    }

    public function initials(): string
    {
        return Str::upper((string) Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => Str::substr($word, 0, 1))
            ->implode(''));
    }
}
