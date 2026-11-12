<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeType extends Model
{
    use BelongsToSchool, SoftDeletes;

    public const FREQUENCIES = ['monthly', 'quarterly', 'half_yearly', 'yearly', 'one_time', 'exam_based', 'custom'];

    public const CATEGORIES = ['tuition', 'examination', 'admission', 'transport', 'library', 'activity', 'other'];

    protected $fillable = [
        'name',
        'code',
        'description',
        'category',
        'frequency',
        'starts_on',
        'ends_on',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(FeeStudentAssignment::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(FeeInvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    public function discountRules(): HasMany
    {
        return $this->hasMany(FeeDiscount::class, 'applies_to_fee_type_id');
    }

    public function invoiceDiscounts(): HasMany
    {
        return $this->hasMany(FeeInvoiceDiscount::class);
    }

    /** Only a monthly fee is tied to a calendar month. */
    public function isMonthly(): bool
    {
        return $this->frequency === 'monthly';
    }

    /**
     * Which report group a fee belongs to, read off the name the admin typed.
     *
     * The add-fee screen asks for a name and nothing else, so this is the only
     * place a category can come from. The collection report groups tuition,
     * admission and examination money under their own totals, and a fee created
     * before any category was stored still lands in the right group.
     */
    public static function categoryForName(?string $name): string
    {
        $needle = mb_strtolower(str_replace(['-', '_'], ' ', trim((string) $name)));

        foreach (self::NAME_CATEGORY_WORDS as $category => $words) {
            foreach ($words as $word) {
                if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $needle) === 1) {
                    return $category;
                }
            }
        }

        return 'other';
    }

    /** @var array<string, list<string>> */
    public const NAME_CATEGORY_WORDS = [
        'admission' => ['admission', 'enrolment', 'enrollment', 'registration', 'joining'],
        'examination' => ['exam', 'examination', 'test', 'practical'],
        'transport' => ['transport', 'bus', 'vehicle', 'pick and drop'],
        'library' => ['library', 'book'],
        'activity' => ['activity', 'annual', 'sports', 'picnic', 'tour', 'event', 'culture'],
        'tuition' => ['tuition', 'month', 'class fee', 'school fee', 'stipend'],
    ];

    /** True when the fee type applies on the given date, if it is dated. */
    public function coversDate(CarbonInterface $date): bool
    {
        if ($this->starts_on !== null && $date->lt($this->starts_on)) {
            return false;
        }

        return $this->ends_on === null || $date->lte($this->ends_on);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
