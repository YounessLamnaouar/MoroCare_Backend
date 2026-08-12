<?php

namespace Database\Seeders;

use App\Models\Teleconsultation;
use Illuminate\Database\Seeder;

class TeleconsultationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Teleconsultation::factory()->count(20)->create();
    }
} 