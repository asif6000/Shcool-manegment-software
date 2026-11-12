<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file a teacher attaches to a lesson: the worksheet they uploaded, or the
 * picture the editor inserted. The path is never served directly; downloads go
 * through the permission-checked lesson route.
 */
class LessonMaterial extends Model
{
    use BelongsToSchool;

    public const DOCUMENT = 'document';

    public const IMAGE = 'image';

    public const KINDS = [self::DOCUMENT, self::IMAGE];

    protected $fillable = [
        'lesson_id',
        'kind',
        'title',
        'file_path',
        'file_name',
        'file_size',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isImage(): bool
    {
        return $this->kind === self::IMAGE;
    }

    /**
     * FileUploadService adds the extension of the stored bytes itself, so the
     * label has to arrive without one; otherwise the class is offered
     * "fraction-strips.pdf.pdf".
     */
    public function downloadLabel(): string
    {
        $name = $this->file_name ?: (string) $this->title;
        $extension = pathinfo((string) $this->file_path, PATHINFO_EXTENSION);

        if ($extension === '') {
            return $name;
        }

        return preg_replace('/\.'.preg_quote($extension, '/').'\z/i', '', $name);
    }

    public function sizeLabel(): ?string
    {
        return $this->file_size === null ? null : number_format($this->file_size / 1024, 0).' KB';
    }
}
