<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);
        $admissionDate = fake()->dateTimeBetween('-2 years', 'now');

        return [
            'student_id' => 'STU-'.fake()->unique()->numerify('######'),
            'admission_no' => 'ADM-'.fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName($gender === 'male' ? 'male' : 'female'),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-16 years', '-4 years')->format('Y-m-d'),
            'blood_group' => fake()->randomElement(['A+', 'B+', 'O+', 'AB+', 'A-', 'B-', 'O-', 'AB-']),
            'religion' => fake()->randomElement(['Islam', 'Hinduism', 'Christianity', 'Buddhism']),
            'nationality' => 'Bangladeshi',
            'photo_path' => null,
            'email' => null,
            'phone' => null,
            'present_address' => fake()->address(),
            'permanent_address' => null,
            'status' => 'active',
            'admission_date' => $admissionDate->format('Y-m-d'),
            'created_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
