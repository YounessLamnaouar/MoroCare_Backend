<?php

namespace Database\Factories;

use App\Models\Teleconsultation;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teleconsultation>
 */
class TeleconsultationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $doctor = User::where('role', 'doctor')->inRandomOrder()->first() ?? User::factory()->create(['role' => 'doctor', 'speciality' => 'General']);
        $patient = \App\Models\User::where('role', 'user')->inRandomOrder()->first() ?? \App\Models\User::factory()->create(['role' => 'user']);
        return [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'time' => $this->faker->time('H:i'),
            'status' => $this->faker->randomElement(['Pending', 'Confirmed', 'Canceled', 'Completed']),
            'notes' => $this->faker->optional()->sentence(),
            'meeting_link' => $this->faker->optional()->url(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
} 