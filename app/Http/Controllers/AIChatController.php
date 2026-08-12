<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AIChatController extends Controller
{
    public function ask(Request $request)
    {
        $userPrompt = $request->input('question');

        // Simple spelling corrections
        $correctedPrompt = $this->correctSpelling($userPrompt);
        $ageGroup = $this->detectAgeGroup($correctedPrompt);

        $prompt = "
            Tu es l'assistant médical virtuel de MoroCare, une plateforme de santé marocaine dédiée à améliorer l'accès aux soins, notamment dans les zones rurales et mal desservies.

            Règles de fonctionnement :
            - Tu réponds toujours dans la langue utilisée par l’utilisateur : arabe, français ou anglais.
            - Tu fournis des réponses **claires, précises et bienveillantes**.
            - Tu donnes des conseils généraux de santé, mais tu **n’établis jamais de diagnostic médical précis**. Tu encourages les utilisateurs à consulter un professionnel.
            - Tu proposes des **informations sur les symptômes, la prévention, les médicaments courants** et la marche à suivre.
            - Si la question concerne un service (pharmacie, livraison, téléconsultation...), tu expliques comment utiliser ces services via MoroCare.
            - Tu restes neutre, respectueux, et tu **évites les sujets sensibles ou non médicaux**.
            - En cas de question inappropriée, tu déclines poliment et rediriges vers des sujets utiles liés à la santé ou au bien-être.

            Exemples de réponses :
            - \"Je comprends que vous ayez mal à la tête. Cela peut avoir plusieurs causes comme la fatigue, le stress ou la déshydratation. Si cela persiste, il est préférable de consulter un médecin.\"
            - \"Vous pouvez utiliser la fonctionnalité de téléconsultation de MoroCare pour discuter avec un professionnel de santé.\"
            - \"Pour la livraison de médicaments, veuillez rechercher la pharmacie la plus proche sur notre plateforme et passer commande.\"
            ...
            Question corrigée : \"$correctedPrompt\"";

        // Build message array
        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $correctedPrompt],
        ];

        // Call external AI API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer 339e81c2982c4a108add49fdd387ac88',
        ])->post('https://api.aimlapi.com/v1/chat/completions', [
            'model' => 'mistralai/Mistral-7B-Instruct-v0.2',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 3000,
        ]);

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->body();

            Log::error('AI API error:', [
                'status' => $status,
                'body' => $body,
            ]);

            // If rate limit or usage limit is reached
            if ($status === 429 || str_contains($body, 'limit')) {
                return response()->json([
                    'reply' => 'You have reached the usage limit for the AI assistant. Please try again later.'
                ], 429);
            }

            // General fallback error
            return response()->json([
                'reply' => 'An error occurred while generating the response. Please try again later.'
            ], 500);
        }

        $aiReply = $response['choices'][0]['message']['content'] ?? 'Aucune réponse générée.';

        return response()->json(['reply' => $aiReply]);
    }

    private function correctSpelling($text)
    {
        return str_ireplace(
            ['paas', 'merçi', 'keske', 'pk'],
            ['passe', 'merci', "qu'est-ce que", 'pourquoi'],
            $text
        );
    }

    private function detectAgeGroup($text)
    {
        $scores = [
            'enfant' => preg_match_all('/\b(papa|maman|jouer|rigolo|super|magique|bizarre)\b/i', $text),
            'adolescent' => preg_match_all('/\b(wesh|mdr|lol|bg|swag|game|daron)\b/i', $text),
            'expert' => preg_match_all('/\b(analyse|synthèse|hypothèse|scientifique|théorie|philosophie)\b/i', $text),
        ];

        $max = max($scores);
        return array_search($max, $scores) ?: 'adulte';
    }
}
