<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Doctor;

class SyncDoctorsFromUsers extends Command
{
    protected $signature = 'sync:doctors';
    protected $description = 'Sync users with role doctor into the doctors table';

    public function handle()
    {
        $users = User::where('role', 'doctor')->get();

        $this->info("Found {$users->count()} users with role 'doctor'");

        foreach ($users as $user) {
            // Check if this user is already linked
            if ($user->doctor_id && Doctor::find($user->doctor_id)) {
                $this->info("User {$user->name} already linked to Doctor ID {$user->doctor_id}");
                continue;
            }

            // Create a new doctor
            $specialities = ['Cardiology', 'Dentist', 'Dermatology', 'Pediatrics', 'General Practitioner'];
            $doctor = Doctor::create([
                'name' => $user->name,
                'email' => $user->email,
                'speciality' => $specialities[array_rand($specialities)],
                'phone' => $user->phone,
            ]);

            // Link user to doctor
            $user->doctor_id = $doctor->id;
            $user->save();

            $this->info("Linked user {$user->name} to new doctor ID {$doctor->id}");
        }

        $this->info("Done syncing doctors.");
    }
}
