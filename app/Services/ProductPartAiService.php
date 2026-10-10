<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ProductPartAiService
{
    protected string $apiKey;

    protected array $models = [
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.0-flash',
        'gemini-flash-latest',
    ];

    public function __construct()
    {
        $this->apiKey = (string) env('GEMINI_API_KEY', '');
    }

    public function identify(string $partNumber): array
    {
        $partNumber = trim($partNumber);

        if ($partNumber === '') {
            return ['success' => false, 'message' => 'Part Number is required.'];
        }

        if (trim($this->apiKey) === '') {
            return ['success' => false, 'message' => 'Gemini API is not configured.'];
        }

        $systemInstruction =
            "You are an automotive replacement-parts catalog researcher for W68 Auto Parts.\n" .
            "Use Google Search grounding to research the EXACT part number supplied by the user.\n" .
            "Return ONLY one valid JSON object, without markdown or commentary.\n" .
            "Required shape: {\"found\":true,\"description\":\"BALL JOINT\",\"applications\":[{\"car_brand\":\"TOYOTA\",\"car_model\":\"HILUX\",\"year_from\":\"2005\",\"year_to\":\"2015\",\"engine\":\"2KD / 1KD\"}],\"confidence\":\"high\",\"notes\":\"\"}.\n" .
            "Rules:\n" .
            "1. Match the exact part number; never silently substitute a similar number.\n" .
            "2. description is the SHORT GENERIC PART NAME ONLY and uppercase, e.g. BALL JOINT, TIE ROD END, RACK END, STABILIZER LINK, CONTROL ARM, ENGINE MOUNTING, WHEEL BEARING, BRAKE PAD, ABS SENSOR.\n" .
            "3. Return ALL clearly verified vehicle applications for the exact part number. Use a separate application object for each make/model/engine/year fitment.\n" .
            "4. car_brand and car_model must be uppercase.\n" .
            "5. year_from and year_to must be four-digit years when verified; otherwise use an empty string.\n" .
            "6. engine must be verified; otherwise use an empty string.\n" .
            "7. Deduplicate identical applications.\n" .
            "8. Never invent compatibility. If exact compatibility cannot be verified, return found=false with empty description/applications.\n";

        $body = [
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => 'Exact automotive part number: ' . $partNumber]],
            ]],
            'tools' => [['google_search' => (object) []]],
            'generationConfig' => ['temperature' => 0.1],
        ];

        $lastError = 'Gemini could not identify this part number.';

        foreach ($this->models as $model) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->apiKey;
                    $response = Http::timeout(45)
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->post($url, $body);

                    if (!$response->successful()) {
                        $lastError = (string) ($response->json('error.message') ?: $response->body());

                        if (in_array($response->status(), [429, 503], true) && $attempt < 2) {
                            usleep(1000000 * $attempt);
                            continue;
                        }

                        break;
                    }

                    $data = $response->json();
                    $text = '';

                    foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
                        if (isset($part['text'])) {
                            $text .= (string) $part['text'];
                        }
                    }

                    $text = trim($text);
                    $ticks = chr(96) . chr(96) . chr(96);
                    $text = trim(str_replace([$ticks . 'json', $ticks . 'JSON', $ticks], '', $text));

                    $decoded = json_decode($text, true);

                    if (!is_array($decoded)) {
                        $first = strpos($text, '{');
                        $last = strrpos($text, '}');

                        if ($first !== false && $last !== false && $last > $first) {
                            $decoded = json_decode(substr($text, $first, $last - $first + 1), true);
                        }
                    }

                    if (!is_array($decoded)) {
                        $lastError = 'Gemini returned an unreadable product result.';
                        break;
                    }

                    return $this->normalize($partNumber, $decoded);
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();

                    if ($attempt < 2) {
                        usleep(1000000 * $attempt);
                        continue;
                    }
                }
            }
        }

        return [
            'success' => false,
            'found' => false,
            'part_number' => $partNumber,
            'description' => '',
            'applications' => [],
            'confidence' => 'low',
            'message' => $lastError,
        ];
    }

    protected function normalize(string $partNumber, array $data): array
    {
        $found = (bool) ($data['found'] ?? false);
        $description = mb_strtoupper(trim((string) ($data['description'] ?? '')));
        $confidence = strtolower(trim((string) ($data['confidence'] ?? 'low')));

        if (!in_array($confidence, ['high', 'medium', 'low'], true)) {
            $confidence = 'low';
        }

        $applications = [];
        $seen = [];

        foreach (($data['applications'] ?? []) as $application) {
            if (!is_array($application)) {
                continue;
            }

            $brand = mb_strtoupper(trim((string) ($application['car_brand'] ?? '')));
            $carModel = mb_strtoupper(trim((string) ($application['car_model'] ?? '')));
            $yearFrom = trim((string) ($application['year_from'] ?? ''));
            $yearTo = trim((string) ($application['year_to'] ?? ''));
            $engine = mb_strtoupper(trim((string) ($application['engine'] ?? '')));

            if ($yearFrom !== '' && !preg_match('/^\d{4}$/', $yearFrom)) {
                $yearFrom = '';
            }

            if ($yearTo !== '' && !preg_match('/^\d{4}$/', $yearTo)) {
                $yearTo = '';
            }

            if ($brand === '' && $carModel === '') {
                continue;
            }

            $key = implode('|', [$brand, $carModel, $yearFrom, $yearTo, $engine]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $applications[] = [
                'car_brand' => $brand,
                'car_model' => $carModel,
                'year_from' => $yearFrom,
                'year_to' => $yearTo,
                'engine' => $engine,
            ];

            if (count($applications) >= 30) {
                break;
            }
        }

        $ok = $found && $description !== '' && !empty($applications);

        return [
            'success' => $ok,
            'found' => $ok,
            'part_number' => $partNumber,
            'description' => $description,
            'applications' => $applications,
            'confidence' => $confidence,
            'message' => trim((string) ($data['notes'] ?? ($ok ? '' : 'Exact part-number compatibility could not be verified.'))),
        ];
    }
}
