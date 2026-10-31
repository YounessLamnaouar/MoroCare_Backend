<?php

namespace Tests\Feature;

use App\Models\Appointment;

use App\Models\Teleconsultation;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_can_login_and_logout_revokes_its_token(): void
    {
        $this->seed(DemoAccountSeeder::class);
        $this->seed(DemoAccountSeeder::class);
        $this->assertDatabaseCount('users', 3);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('doctors'));
        foreach (['admin', 'doctor', 'user'] as $role) {
            $response = $this->postJson('/api/login', ['email' => strtoupper($role).'@morocare.test', 'password' => 'MoroCare-Demo-2026!'])
                ->assertOk()->assertJsonPath('user.role', $role)->assertJsonStructure(['token']);
            $token = $response->json('token');
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('role', $role);
            $this->withToken($token)->postJson('/api/logout')->assertNoContent();
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_registration_cannot_create_privileged_accounts(): void
    {
        $data = ['name' => 'New Patient', 'email' => 'PATIENT@example.test', 'password' => 'New-Password-2026',
            'password_confirmation' => 'New-Password-2026', 'phone' => '0612345678', 'birthday' => '1990-01-01'];
        $this->postJson('/api/register', $data + ['role' => 'admin'])->assertUnprocessable();
        $this->postJson('/api/register', $data)->assertOk()->assertJsonPath('user.role', 'user');
        $this->assertDatabaseHas('users', ['email' => 'patient@example.test', 'role' => 'user']);
    }

    public function test_doctor_creation_creates_a_login_and_edits_stay_in_sync(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
        $data = ['name' => 'Doctor Test', 'email' => 'doctor@example.test', 'password' => 'Doctor-Password-2026', 'speciality' => 'General'];
        $doctor = $this->postJson('/api/doctors', $data)->assertCreated()->assertJsonMissingPath('password')->json();
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertEquals($doctor['id'], $user->id);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($data['password'], $user->password));
        $this->putJson('/api/doctors/'.$doctor['id'], array_replace($data, ['email' => 'changed@example.test']))->assertOk();
        $this->assertEquals('changed@example.test', $user->fresh()->email);
        $this->deleteJson('/api/doctors/'.$doctor['id'])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
    }

    public function test_bookings_are_private_and_patient_cannot_impersonate_or_confirm(): void
    {
        $patient = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $doctor = User::factory()->create(['role' => 'doctor', 'speciality' => 'General']);
        $booking = Appointment::factory()->create(['patient_id' => $other->id, 'doctor_id' => $doctor->id]);
        $this->getJson('/api/appointments')->assertUnauthorized();
        $this->actingAs($patient, 'sanctum');
        $this->getJson('/api/appointments')->assertOk()->assertExactJson([]);
        $this->getJson('/api/appointments/'.$booking->id)->assertForbidden();
        $data = ['patient_id' => $other->id, 'doctor_id' => $doctor->id, 'date' => now()->addDays(2)->toDateString(), 'time' => '10:00'];
        $this->postJson('/api/appointments', $data)->assertForbidden();
        $data['patient_id'] = $patient->id;
        $own = $this->postJson('/api/appointments', $data + ['status' => 'Confirmed'])->assertCreated()->json();
        $this->assertDatabaseHas('appointments', ['id' => $own['id'], 'status' => 'Pending']);
        $this->getJson('/api/appointments/'.$own['id'])->assertOk()->assertJsonPath('date', $data['date'])->assertJsonMissingPath('patient.birthday');
        $this->patchJson('/api/appointments/'.$own['id'].'/status', ['status' => 'Confirmed'])->assertForbidden();
        $this->postJson('/api/teleconsultations', $data)->assertUnprocessable()->assertJsonValidationErrors('time');
        $this->patchJson('/api/appointments/'.$own['id'].'/status', ['status' => 'Canceled'])->assertOk();
        $this->postJson('/api/teleconsultations', $data)->assertCreated()->assertJsonStructure(['room_id']);
        $this->postJson('/api/doctors', [])->assertForbidden();
    }

    public function test_doctors_only_manage_their_own_bookings(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor', 'speciality' => 'General']);
        $user = $doctor;
        $own = Teleconsultation::factory()->create(['doctor_id' => $doctor->id, 'status' => 'Pending', 'date' => now()->addDays(2)]);
        $other = Teleconsultation::factory()->create(['doctor_id' => User::factory()->create(['role' => 'doctor', 'speciality' => 'General'])->id]);
        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/teleconsultations')->assertOk()->assertJsonCount(1);
        $this->patchJson('/api/teleconsultations/'.$other->id.'/status', ['status' => 'Confirmed'])->assertForbidden();
        $this->patchJson('/api/teleconsultations/'.$own->id.'/status', ['status' => 'Confirmed'])->assertOk()->assertJsonPath('teleconsultation.status', 'Confirmed');
    }

    public function test_invalid_login_and_profile_password_updates(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->actingAs($user, 'sanctum');
        $this->putJson('/api/user/profile', ['name' => 'Updated', 'role' => 'admin'])->assertOk()->assertJsonPath('role', 'user');
        $this->putJson('/api/user/password', ['current_password' => 'password', 'password' => 'Changed-Password-2026', 'password_confirmation' => 'Changed-Password-2026'])->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Changed-Password-2026', $user->fresh()->password));
    }

    public function test_contact_messages_are_validated_persisted_and_only_admin_can_read(): void
    {
        $this->postJson('/api/contact', [])->assertUnprocessable();
        $this->postJson('/api/contact', ['name' => 'Test Sender', 'email' => 'sender@example.test', 'message' => 'Test message'])->assertCreated();
        $this->assertDatabaseHas('contact_messages', ['message' => 'Test message']);
        $this->getJson('/api/contact')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'user']), 'sanctum')->getJson('/api/contact')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')->getJson('/api/contact')->assertOk()->assertJsonPath('data.0.message', 'Test message');
    }

}
