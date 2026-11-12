<?php

namespace App\Models;

use App\Support\FileUploadService;
use App\Support\RoleHierarchy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'mobile_number',
        'avatar_path',
        'password',
        'is_active',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    /**
     * A student-role account without a student record is an admission applicant:
     * it signed itself up and is waiting for the school office to decide.
     */
    public function hasStudentRecord(): bool
    {
        return $this->relationLoaded('student')
            ? $this->student !== null
            : $this->student()->exists();
    }

    public function isApplicant(): bool
    {
        return $this->hasRole('student') && ! $this->hasStudentRecord();
    }

    public function homeRouteName(): string
    {
        if ($this->hasRole('student')) {
            return $this->hasStudentRecord() ? 'student.dashboard' : 'applicant.dashboard';
        }

        return 'dashboard';
    }

    public function passwordChangeRouteName(): string
    {
        if ($this->hasRole('student')) {
            return $this->hasStudentRecord()
                ? 'student.password.change'
                : 'applicant.password.change';
        }

        return 'profile.password.change';
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function devicePushTokens(): HasMany
    {
        return $this->hasMany(DevicePushToken::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        return $query->where($query->qualifyColumn('school_id'), $actor->school_id);
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

    public function avatarUrl(): ?string
    {
        return app(FileUploadService::class)->url($this->avatar_path, 'user_avatar');
    }

    public function primaryRoleLabel(): string
    {
        $role = $this->getRoleNames()->first();

        if ($role === null) {
            return __('No role');
        }

        return __(config("school.roles.{$role}.label", $role));
    }

    public function roleLevel(): int
    {
        return RoleHierarchy::actorLevel($this);
    }
}
