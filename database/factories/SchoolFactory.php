<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        $name = fake()->unique()->city().' Model School';

        return [
            'name' => $name,
            'short_name' => Str::limit($name, 24, ''),
            'code' => Str::upper(fake()->unique()->bothify('SCH-####')),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'eiin' => fake()->unique()->numerify('######'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('01#########'),
            'phone_alt' => null,
            'website' => null,
            'address' => fake()->streetAddress(),
            'division' => fake()->randomElement([
                'Barishal', 'Chattogram', 'Dhaka', 'Khulna', 'Mymensingh', 'Rajshahi', 'Rangpur', 'Sylhet',
            ]),
            'district' => fake()->city(),
            'upazila' => fake()->citySuffix(),
            'postcode' => fake()->numerify('####'),
            'logo_path' => null,
            'motto' => 'Knowledge, Character, Service',
            'established_year' => fake()->numberBetween(1972, 2015),
            'timezone' => 'Asia/Dhaka',
            'currency' => 'BDT',
            'locale' => 'en',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
