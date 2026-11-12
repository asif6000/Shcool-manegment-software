<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'user_id' => null,
            'employee_code' => 'TCH-'.fake()->unique()->numerify('####'),
            'first_name' => fake()->firstName($gender === 'male' ? 'male' : 'female'),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-24 years')->format('Y-m-d'),
            'phone' => fake()->numerify('01#########'),
            'email' => fake()->unique()->safeEmail(),
            'designation' => fake()->randomElement(['Assistant Teacher', 'Senior Teacher', 'Lecturer']),
            'joining_date' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
            'qualification' => fake()->randomElement(['B.Ed', 'M.Ed', 'B.Sc', 'M.A']),
            'specialization' => fake()->randomElement(['Mathematics', 'English', 'Science', 'Bangla', 'ICT']),
            'employment_type' => 'full_time',
            'status' => 'active',
            'photo_path' => null,
        ];
    }
}
