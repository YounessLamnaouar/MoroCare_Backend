<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Support\Facades\Hash;

class SyncUsersFromDoctors extends Command
{
    protected $signature = 'sync:users-from-doctors';
    protected $description = 'Sync doctors into the users table with role doctor';

    public function handle()
    {
        $doctors = Doctor::all();
        $this->info("Found {$doctors->count()} doctors to sync");

        foreach ($doctors as $doctor) {
            // Check if user already exists for this doctor
            $existingUser = User::where('email', $doctor->email)->first();
            
            if ($existingUser) {
                // Update existing user
                $existingUser->update([
                    'role' => 'doctor',
                    'doctor_id' => $doctor->id
                ]);
                $this->info("Updated existing user for doctor: {$doctor->name}");
            } else {
                // Create new user
                $user = User::create([
                    'name' => $doctor->name,
                    'email' => $doctor->email,
                    'password' => Hash::make('password'), // Default password
                    'role' => 'doctor',
                    'doctor_id' => $doctor->id,
                    'phone' => $doctor->phone
                ]);
                $this->info("Created new user for doctor: {$doctor->name}");
            }
        }

        $this->info("Done syncing doctors to users table.");
    }
} 