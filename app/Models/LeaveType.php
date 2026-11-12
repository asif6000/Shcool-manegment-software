<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'name',
        'code',
        'max_days_per_year',
        'is_paid',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_days_per_year' => 'integer',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class);
    }
}
