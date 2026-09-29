<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Support\Facades\Http;

class DoctorVerificationAIController extends Controller
{
    public function analyzeCertificate($doctorId)
    {
        $doctor = Doctor::findOrFail($doctorId);

        if (!$doctor->certificate_path) {
            return redirect()->back()->with('error', 'No certificate found');
        }
$filePath = storage_path('app/public/' . $doctor->certificate_path);
$imageData = base64_encode(file_get_contents($filePath));

$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . config('services.openai.key'),
    'Content-Type'  => 'application/json',
])->post('https://api.openai.com/v1/responses', [

    'model' => 'gpt-4.1-mini',

    'input' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'input_text',
                    'text' => "
Analyze this certificate and return ONLY JSON:

{
  \"risk_score\": number (0-100),
  \"confidence\": number (0-100),
  \"status\": \"valid | suspicious | fake\",
  \"observations\": [\"point 1\", \"point 2\"]
}
"
                ],
                [
                    'type' => 'input_image',
                    'image_url' => 'data:image/png;base64,' . $imageData
                ]
            ]
        ]
    ]
]);
     
        $data = $response->json();

        // Extract AI message safely
$content = $data['output'][0]['content'][0]['text'] ?? null;
        $parsed = json_decode($content, true);

        if (!$parsed) {
           return redirect()->back()->with('error', 'AI failed to analyze');
        }

        // Save clean AI result only
        $doctor->update([
            'ai_result' => json_encode($parsed)
        ]);

        return redirect()->back()->with('success', 'AI analysis completed');
    }
}
