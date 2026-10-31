<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Teleconsultation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsultationSecurityTest extends TestCase
{
    use RefreshDatabase;
    private function consultation(): Teleconsultation
    {
        return Teleconsultation::factory()->create([
            'patient_id' => User::factory()->create(['role' => 'user'])->id,
            'doctor_id' => User::factory()->create(['role' => 'doctor'])->id,
            'status' => 'Confirmed', 'date' => now()->toDateString(), 'time' => now()->format('H:i'),
        ]);
    }
    public function test_messages_are_encrypted_idempotent_and_private_even_from_admins(): void
    {
        $session = $this->consultation();
        $url = '/api/teleconsultations/'.$session->id.'/messages';
        $this->getJson($url)->assertUnauthorized();
        foreach (['user', 'doctor', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum')->getJson($url)->assertForbidden();
        }
        $this->actingAs(User::find($session->patient_id), 'sanctum');
        $message = ['body' => 'Private clinical conversation', 'client_id' => (string) Str::uuid()];
        $this->postJson($url, $message)->assertCreated();
        $this->postJson($url, $message)->assertCreated();
        $this->assertDatabaseCount('consultation_messages', 1);
        $this->assertStringNotContainsString('Private clinical', DB::table('consultation_messages')->value('body'));
        $this->actingAs(User::find($session->doctor_id), 'sanctum')->getJson($url)->assertOk()->assertJsonPath('0.body', $message['body']);
        $session->update(['status' => 'Completed']);
        $this->getJson($url)->assertOk();
        $this->postJson($url, ['body' => 'New message', 'client_id' => (string) Str::uuid()])->assertForbidden();
    }
    public function test_video_fails_closed_and_signaling_is_participant_only(): void
    {
        $session = $this->consultation();
        $url = '/api/teleconsultations/'.$session->id;
        $this->actingAs(User::find($session->patient_id), 'sanctum');
        $this->getJson($url.'/video')->assertOk()->assertJsonPath('enabled', false);
        config(['services.video.turn_url' => 'turns:relay.example.test:5349', 'services.video.turn_secret' => 'test-secret']);
        $this->getJson($url.'/video')->assertOk()->assertJsonPath('enabled', true)->assertJsonStructure(['iceServers' => [['urls', 'username', 'credential']]]);
        $this->postJson($url.'/signals', ['type' => 'offer', 'payload' => ['sdp' => 'fake']])->assertForbidden();
        $this->postJson($url.'/signals', ['type' => 'ready', 'payload' => []])->assertNoContent();
        $this->actingAs(User::find($session->doctor_id), 'sanctum')->getJson($url.'/signals')->assertOk()->assertJsonCount(1);
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')->getJson($url.'/signals')->assertForbidden();
        $session->update(['date' => now()->addDays(3)]);
        $this->actingAs(User::find($session->patient_id), 'sanctum')->getJson($url.'/video')->assertOk()->assertJsonPath('enabled', false);
    }
    public function test_temporary_password_must_be_changed_and_inactive_accounts_cannot_login(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor', 'must_change_password' => true]);
        $this->actingAs($doctor, 'sanctum')->getJson('/api/appointments')->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->getJson('/api/user')->assertOk();
        $this->putJson('/api/user/password', ['current_password' => 'password', 'password' => 'Updated-Password-2026!', 'password_confirmation' => 'Updated-Password-2026!'])->assertOk();
        $this->assertFalse($doctor->fresh()->must_change_password);
        $doctor->update(['is_active' => false]);
        $this->getJson('/api/appointments')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $doctor->email, 'password' => 'Updated-Password-2026!'])->assertUnprocessable();
    }
}
