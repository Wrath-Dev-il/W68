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

        $researchInstruction =
            "You are an automotive replacement-parts fitment researcher for W68 Auto Parts.\n" .
            "Use Google Search to research the EXACT part number supplied by the user.\n" .
            "Collect the short generic part type and EVERY clearly supported vehicle application.\n" .
            "For every application, explicitly state MAKE/CAR BRAND, CAR MODEL, YEAR FROM, YEAR TO, and ENGINE CODE or engine displacement when available.\n" .
            "Search multiple useful results when needed. Do not stop after the first vehicle fitment.\n" .
            "Do not substitute a similar part number and do not invent compatibility.\n";

        $extractInstruction =
            "Convert the supplied automotive research into strict structured data for W68 Product Master.\n" .
            "Return JSON only.\n" .
            "description must be a SHORT GENERIC PART NAME ONLY in uppercase, for example BALL JOINT, TIE ROD END, RACK END, STABILIZER LINK, CONTROL ARM, ENGINE MOUNTING, WHEEL BEARING, BRAKE PAD, ABS SENSOR.\n" .
            "applications must contain every clearly verified vehicle fitment. Each application must have car_brand, car_model, year_from, year_to, engine.\n" .
            "Never place the vehicle model inside car_brand. Never place the brand inside car_model.\n" .
            "year_from/year_to must be four-digit years when supported by the research. A single verified model year must be used for both year_from and year_to.\n" .
            "If a range such as 2005-2015 appears, split it into year_from=2005 and year_to=2015.\n" .
            "Do not invent missing fitment data.\n";

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'found' => ['type' => 'BOOLEAN'],
                'description' => ['type' => 'STRING'],
                'applications' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'car_brand' => ['type' => 'STRING'],
                            'car_model' => ['type' => 'STRING'],
                            'year_from' => ['type' => 'STRING'],
                            'year_to' => ['type' => 'STRING'],
                            'engine' => ['type' => 'STRING'],
                        ],
                        'required' => ['car_brand', 'car_model', 'year_from', 'year_to', 'engine'],
                    ],
                ],
                'confidence' => ['type' => 'STRING'],
                'notes' => ['type' => 'STRING'],
            ],
            'required' => ['found', 'description', 'applications', 'confidence', 'notes'],
        ];

        $lastError = 'Gemini could not identify this part number.';

        foreach ($this->models as $model) {
            try {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->apiKey;

                $researchBody = [
                    'systemInstruction' => ['parts' => [['text' => $researchInstruction]]],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => 'Research exact automotive part number ' . $partNumber . '. Include full make/model/year/engine fitment details.',
                        ]],
                    ]],
                    'tools' => [['google_search' => (object) []]],
                    'generationConfig' => ['temperature' => 0.1],
                ];

                $researchResponse = Http::timeout(45)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, $researchBody);

                if (!$researchResponse->successful()) {
                    $lastError = (string) ($researchResponse->json('error.message') ?: $researchResponse->body());
                    continue;
                }

                $researchData = $researchResponse->json();
                $researchText = '';

                foreach (($researchData['candidates'][0]['content']['parts'] ?? []) as $part) {
                    if (isset($part['text'])) {
                        $researchText .= (string) $part['text'];
                    }
                }

                $researchText = trim($researchText);

                if ($researchText === '') {
                    $lastError = 'Gemini search returned no fitment details.';
                    continue;
                }

                $extractBody = [
                    'systemInstruction' => ['parts' => [['text' => $extractInstruction]]],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => "PART NUMBER: " . $partNumber . "\n\nSEARCH RESEARCH:\n" . $researchText,
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $schema,
                    ],
                ];

                $extractResponse = Http::timeout(45)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, $extractBody);

                if (!$extractResponse->successful()) {
                    $lastError = (string) ($extractResponse->json('error.message') ?: $extractResponse->body());
                    continue;
                }

                $extractData = $extractResponse->json();
                $jsonText = '';

                foreach (($extractData['candidates'][0]['content']['parts'] ?? []) as $part) {
                    if (isset($part['text'])) {
                        $jsonText .= (string) $part['text'];
                    }
                }

                $decoded = json_decode(trim($jsonText), true);

                if (!is_array($decoded)) {
                    $lastError = 'Gemini returned an unreadable structured fitment result.';
                    continue;
                }

                return $this->normalize($partNumber, $decoded);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
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

            $brand = mb_strtoupper(trim((string) (
                $application['car_brand']
                ?? $application['brand']
                ?? $application['make']
                ?? $application['vehicle_make']
                ?? ''
            )));

            $carModel = mb_strtoupper(trim((string) (
                $application['car_model']
                ?? $application['model']
                ?? $application['vehicle_model']
                ?? ''
            )));

            $yearFrom = trim((string) (
                $application['year_from']
                ?? $application['from_year']
                ?? $application['start_year']
                ?? ''
            ));

            $yearTo = trim((string) (
                $application['year_to']
                ?? $application['to_year']
                ?? $application['end_year']
                ?? ''
            ));

            $engine = mb_strtoupper(trim((string) (
                $application['engine']
                ?? $application['engine_code']
                ?? $application['engine_codes']
                ?? $application['engine_type']
                ?? ''
            )));

            $combinedYear = trim((string) (
                $application['year_range']
                ?? $application['years']
                ?? $application['year']
                ?? ''
            ));

            if (($yearFrom === '' || $yearTo === '') && $combinedYear !== '') {
                preg_match_all('/\b(19|20)\d{2}\b/', $combinedYear, $yearMatches);
                $years = $yearMatches[0] ?? [];

                if ($yearFrom === '' && isset($years[0])) {
                    $yearFrom = (string) $years[0];
                }

                if ($yearTo === '' && isset($years[1])) {
                    $yearTo = (string) $years[1];
                } elseif ($yearTo === '' && isset($years[0]) && count($years) === 1) {
                    $yearTo = (string) $years[0];
                }
            }

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
