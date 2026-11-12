<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'code' => $this->code,
            'eiin' => $this->eiin,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_alt' => $this->phone_alt,
            'website' => $this->website,
            'address' => $this->address,
            'division' => $this->division,
            'district' => $this->district,
            'upazila' => $this->upazila,
            'postcode' => $this->postcode,
            'motto' => $this->motto,
            'established_year' => $this->established_year,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'logo_url' => $this->logoUrl(),
            'counts' => $this->when(
                isset($this->users_count) || isset($this->students_count) || isset($this->teachers_count),
                fn (): array => [
                    'users' => (int) ($this->users_count ?? 0),
                    'students' => (int) ($this->students_count ?? 0),
                    'teachers' => (int) ($this->teachers_count ?? 0),
                ],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
