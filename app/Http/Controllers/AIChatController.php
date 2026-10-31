<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class AIChatController extends Controller
{
    public function ask(Request $request)
    {
        $data = $request->validate(['question' => 'required|string|max:2000', 'language' => 'required|in:en,fr,ar', 'consent' => 'accepted']);
        $key = config('services.aimlapi.key');
        if (blank($key)) return response()->json(['message' => 'The assistant is unavailable.'], 503);
        $language = ['en' => 'English', 'fr' => 'French', 'ar' => 'Arabic'][$data['language']];
        try {
            $response = Http::acceptJson()->withToken($key)->timeout(30)->post('https://api.aimlapi.com/v1/chat/completions', [
                'model' => config('services.aimlapi.model'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You are MoroCare’s general health information assistant. Reply in '.$language.'. Give cautious, concise general information; never claim a diagnosis, prescribe treatment, or replace a clinician. For potentially urgent symptoms encourage immediate in-person emergency care. Do not request identifying details. Available platform services: doctor directory, appointment requests, private consultation messages, optional video. Medication delivery is not available. Do not claim to book appointments or access records.'],
                    ['role' => 'user', 'content' => $data['question']],
                ], 'max_tokens' => 800,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI provider connection unavailable.');
            return response()->json(['message' => 'The assistant is unavailable.'], 503);
        }
        if (! $response->successful()) {
            Log::warning('AI provider rejected request.', ['status' => $response->status()]);
            return response()->json(['message' => 'The assistant is unavailable.'], $response->status() === 429 ? 429 : 502);
        }
        $reply = $response->json('choices.0.message.content');
        if (! is_string($reply) || trim($reply) === '') return response()->json(['message' => 'The assistant is unavailable.'], 502);
        return response()->json(['reply' => $reply]);
    }
}
