<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoctorMigrationTest extends TestCase
{
    public function test_legacy_doctor_accounts_and_booking_references_survive_consolidation(): void
    {
        config(['database.connections.migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection('migration_test');
        try {
            $paths = collect(glob(database_path('migrations/*.php')))->reject(fn ($p) => str_contains($p, '2026_10_31'))->values()->all();
            $this->artisan('migrate', ['--database' => 'migration_test', '--path' => $paths, '--realpath' => true])->assertSuccessful();
            $hash = Hash::make('Existing-Password-2026!');
            $doctorId = DB::table('doctors')->insertGetId(['name' => 'Legacy Doctor', 'email' => 'legacy@example.test', 'password' => $hash, 'speciality' => 'Cardiology']);
            $patientId = DB::table('users')->insertGetId(['name' => 'Patient', 'email' => 'patient@example.test', 'password' => $hash, 'role' => 'user']);
            $userId = DB::table('users')->insertGetId(['name' => 'Doctor Account', 'email' => 'doctor-login@example.test', 'password' => $hash, 'role' => 'doctor', 'doctor_id' => $doctorId]);
            DB::table('appointments')->insert(['patient_id' => $patientId, 'doctor_id' => $doctorId, 'date' => '2027-01-01', 'time' => '10:00']);
            $migration = require database_path('migrations/2026_10_31_000001_unify_doctors_with_users.php');
            $migration->up();
            $this->assertFalse(Schema::hasTable('doctors'));
            $this->assertFalse(Schema::hasColumn('users', 'doctor_id'));
            $this->assertEquals($userId, DB::table('appointments')->value('doctor_id'));
            $this->assertEquals('Cardiology', DB::table('users')->where('id', $userId)->value('speciality'));
            $this->assertTrue(Hash::check('Existing-Password-2026!', DB::table('users')->where('id', $userId)->value('password')));
            $this->assertCount(0, DB::select('PRAGMA foreign_key_check'));
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('migration_test');
        }
    }
}
