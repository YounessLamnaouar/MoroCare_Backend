<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) throw new \RuntimeException('Demo accounts are local-only.');
        foreach (['admin', 'doctor', 'user'] as $role) {
            User::firstOrCreate(['email' => $role.'@morocare.test'], [
                'name' => ucfirst($role).' Demo', 'role' => $role,
                'password' => 'MoroCare-Demo-2026!', 'phone' => '0612345678', 'birthday' => '1990-01-01',
                'speciality' => $role === 'doctor' ? 'General' : null,
                'address' => $role === 'doctor' ? 'Casablanca' : null,
            ]);
        }
    }
}
