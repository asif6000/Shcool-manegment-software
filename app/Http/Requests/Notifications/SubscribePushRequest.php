<?php

namespace App\Http\Requests\Notifications;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SubscribePushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:2048', 'url', $this->secureEndpointRule()],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+={0,2}$/'],
            'keys.auth' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+={0,2}$/'],
            'content_encoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
        ];
    }

    /**
     * Web Push requires HTTPS. Local development may run over plain HTTP.
     */
    private function secureEndpointRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $scheme = parse_url($value, PHP_URL_SCHEME);

            if ($scheme === 'https') {
                return;
            }

            if ($scheme === 'http' && app()->environment(['local', 'testing'])) {
                return;
            }

            $fail(__('Push subscriptions must use an HTTPS endpoint.'));
        };
    }
}
