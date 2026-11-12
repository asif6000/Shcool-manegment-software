<?php

namespace App\Http\Requests\Students;

use App\Services\AuthenticationService;
use App\Support\SchoolContext;
use App\Support\StudentRules;
use Illuminate\Foundation\Http\FormRequest;

class SaveStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('student') === null ? 'students.create' : 'students.update';

        return (bool) $this->user()?->can($permission);
    }

    /**
     * The sign-in mobile is stored in the reduced form the login lookup uses, so
     * "017-123 45678" and "01712345678" cannot become two different accounts.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => AuthenticationService::normaliseMobile(trim((string) $this->input('phone'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return StudentRules::save(
            app(SchoolContext::class)->id(),
            $this->route('student')?->getKey(),
        );
    }
}
