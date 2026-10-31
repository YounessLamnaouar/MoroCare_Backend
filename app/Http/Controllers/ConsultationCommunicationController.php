<?php
namespace App\Http\Controllers;

use App\Models\ConsultationMessage;
use App\Models\Teleconsultation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ConsultationCommunicationController extends Controller
{
    private function participant(Request $request, Teleconsultation $consultation, bool $write = false): void
    {
        // Administrators manage scheduling, not private clinical conversations.
        abort_unless(in_array($request->user()->id, [$consultation->patient_id, $consultation->doctor_id], true), 403);
        abort_unless(in_array($consultation->status, $write ? ['Confirmed'] : ['Confirmed', 'Completed']), 403);
    }
    public function messages(Request $request, Teleconsultation $teleconsultation)
    {
        $this->participant($request, $teleconsultation);
        $request->validate(['after' => 'nullable|integer|min:0']);
        return ConsultationMessage::where('teleconsultation_id', $teleconsultation->id)
            ->where('id', '>', $request->integer('after'))->orderBy('id')->limit(100)->get();
    }
    public function send(Request $request, Teleconsultation $teleconsultation)
    {
        $this->participant($request, $teleconsultation, true);
        $data = $request->validate(['body' => 'required|string|max:4000', 'client_id' => 'required|uuid']);
        $message = ConsultationMessage::firstOrCreate(['sender_id' => $request->user()->id, 'client_id' => $data['client_id']],
            ['teleconsultation_id' => $teleconsultation->id, 'body' => $data['body']]);
        abort_unless($message->teleconsultation_id === $teleconsultation->id, 409);
        return response()->json($message, 201);
    }
    public function video(Request $request, Teleconsultation $teleconsultation)
    {
        $this->participant($request, $teleconsultation, true);
        $start = $teleconsultation->date->copy()->setTimeFromTimeString($teleconsultation->time);
        $inWindow = now()->between($start->copy()->subMinutes(15), $start->copy()->addHours(2));
        $url = config('services.video.turn_url');
        $secret = config('services.video.turn_secret');
        // Direct connections are restricted to an explicitly enabled local environment.
        $local = app()->environment('local') && config('services.video.local_testing')
            && in_array($request->ip(), ['127.0.0.1', '::1'], true);
        $relay = filled($url) && filled($secret);
        $enabled = ($inWindow || $local) && ($relay || $local);
        $username = now()->addHours(2)->timestamp.':'.$request->user()->id;
        return response()->json([
            'enabled' => $enabled,
            'iceTransportPolicy' => $local && ! $relay ? 'all' : 'relay',
            'reason' => ! $inWindow && ! $local ? 'outside_window' : (! $enabled ? 'video_unconfigured' : null),
            'iceServers' => $enabled && $relay ? [['urls' => $url, 'username' => $username,
                'credential' => base64_encode(hash_hmac('sha1', $username, $secret, true))]] : [],
            'cursor' => DB::table('consultation_signals')->where('teleconsultation_id', $teleconsultation->id)->max('id') ?? 0,
        ]);
    }
    public function signals(Request $request, Teleconsultation $teleconsultation)
    {
        $this->participant($request, $teleconsultation, true);
        abort_unless($this->video($request, $teleconsultation)->getData()->enabled, 403);
        $request->validate(['after' => 'nullable|integer|min:0']);
        $rows = DB::table('consultation_signals')->where('teleconsultation_id', $teleconsultation->id)
            ->where('id', '>', $request->integer('after'))->where('created_at', '>', now()->subMinutes(2))
            ->orderBy('id')->limit(100)->get();
        return $rows->map(function ($row) {
            $row->payload = json_decode(Crypt::decryptString($row->payload), true);
            return $row;
        });
    }
    public function signal(Request $request, Teleconsultation $teleconsultation)
    {
        $this->participant($request, $teleconsultation, true);
        abort_unless($this->video($request, $teleconsultation)->getData()->enabled, 403);
        $data = $request->validate([
            'type' => 'required|in:ready,offer,answer,candidate,leave',
            'payload' => 'present|array',
        ]);
        abort_if(strlen(json_encode($data['payload'])) > 32000, 422);
        abort_if($data['type'] === 'offer' && $request->user()->id !== $teleconsultation->doctor_id, 403);
        DB::table('consultation_signals')->where('created_at', '<', now()->subMinutes(2))->delete();
        DB::table('consultation_signals')->insert([
            'teleconsultation_id' => $teleconsultation->id, 'sender_id' => $request->user()->id,
            'type' => $data['type'], 'payload' => Crypt::encryptString(json_encode($data['payload'])), 'created_at' => now(),
        ]);
        return response()->noContent();
    }
}
