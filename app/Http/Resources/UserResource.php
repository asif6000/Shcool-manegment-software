<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'must_change_password' => $this->must_change_password,
            'locale' => $this->locale,
            'avatar_url' => $this->avatarUrl(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'roles' => $this->whenLoaded('roles', fn (): array => $this->getRoleNames()->all()),
            'school' => $this->whenLoaded(
                'school',
                fn () => $this->school !== null ? new SchoolResource($this->school) : null,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
