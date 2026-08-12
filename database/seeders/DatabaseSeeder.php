<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Doctor;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create doctors first
        $this->call([
            UserSeeder::class,
            DoctorSeeder::class,
            AppointmentSeeder::class,
            TeleconsultationSeeder::class,
        ]);

        // Create test doctor user
        $doctor = Doctor::first();
        User::factory()->create([
            'name' => 'Test Doctor',
            'email' => 'doctor@example.com',
            'role' => 'doctor',
            'doctor_id' => $doctor->id,
        ]);

        // Create test patient user
        User::factory()->create([
            'name' => 'Test Patient',
            'email' => 'patient@example.com',
            'role' => 'user',
        ]);

        // Create additional regular users
        User::factory(10)->create([
            'role' => 'user'
        ]);
    }
}
