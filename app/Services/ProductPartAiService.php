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
            "A part number may contain a size suffix such as 0.25, 0.50, STD, OS or US. Treat that as the bearing/part size variant, not as the vehicle application.\n" .
            "Collect the short generic part type, size/variant meaning, every verified engine code, and EVERY clearly supported vehicle application.\n" .
            "For every application, explicitly state MAKE/CAR BRAND, CAR MODEL, YEAR FROM, YEAR TO, and ENGINE CODE or engine displacement when available.\n" .
            "Search multiple useful results when needed. Do not stop after the first engine code or first vehicle fitment.\n" .
            "Do not substitute a similar part number and do not invent compatibility.\n";

        $extractInstruction =
            "Convert the supplied automotive research into strict structured data for W68 Product Master.\n" .
            "Return JSON only.\n" .
            "description must be a SHORT GENERIC PART NAME ONLY in uppercase, for example CON ROD BEARING, MAIN BEARING, BALL JOINT, TIE ROD END, RACK END, STABILIZER LINK, CONTROL ARM, ENGINE MOUNTING, WHEEL BEARING, BRAKE PAD, ABS SENSOR.\n" .
            "Do not put size text such as 0.25MM UNDERSIZE, 0.50MM, STD, OS or US in description. Keep size information only in notes.\n" .
            "applications must contain every clearly verified vehicle fitment. Each application must have car_brand, car_model, year_from, year_to, engine.\n" .
            "For internal engine parts: if the exact part is verified for an engine code, and the supplemental vehicle research verifies that engine in a vehicle model/year, you MAY bridge that engine fitment into an application row.\n" .
            "Never place the vehicle model inside car_brand. Never place the brand inside car_model.\n" .
            "year_from/year_to must be four-digit years when supported by the research. A single verified model year must be used for both year_from and year_to.\n" .
            "If a range such as 2005-2015 appears, split it into year_from=2005 and year_to=2015.\n" .
            "Prefer specific vehicle rows such as ISUZU / ELF NPR / 1984 / 1993 / 4BD1 instead of returning only an engine code.\n" .
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

                // Second grounded pass: many engine-internal part catalogs only
                // identify engine codes. Resolve those engines to real vehicle
                // make/model/year applications before structured extraction.
                $vehicleResearchInstruction =
                    "You are an automotive engine-to-vehicle fitment researcher.\n" .
                    "Using Google Search, take the exact part-number research below and identify every ENGINE CODE mentioned.\n" .
                    "For each engine code, find verified vehicle MAKE, MODEL/SERIES, YEAR FROM, YEAR TO and engine code.\n" .
                    "This is especially important for engine-internal parts such as con-rod bearings and main bearings.\n" .
                    "Search engine-specific vehicle catalogs and reputable application references.\n" .
                    "Do not guess. If an engine has no verified model/year source, say that it remains unresolved.\n";

                $vehicleResearchBody = [
                    'systemInstruction' => ['parts' => [['text' => $vehicleResearchInstruction]]],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => "EXACT PART NUMBER: " . $partNumber . "\n\nPART RESEARCH:\n" . $researchText,
                        ]],
                    ]],
                    'tools' => [['google_search' => (object) []]],
                    'generationConfig' => ['temperature' => 0.1],
                ];

                $vehicleResearchText = '';
                try {
                    $vehicleResearchResponse = Http::timeout(45)
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->post($url, $vehicleResearchBody);

                    if ($vehicleResearchResponse->successful()) {
                        $vehicleResearchData = $vehicleResearchResponse->json();

                        foreach (($vehicleResearchData['candidates'][0]['content']['parts'] ?? []) as $part) {
                            if (isset($part['text'])) {
                                $vehicleResearchText .= (string) $part['text'];
                            }
                        }

                        $vehicleResearchText = trim($vehicleResearchText);
                    }
                } catch (\Throwable $vehicleResearchError) {
                    // Keep the original part research usable even if this
                    // supplemental fitment pass times out.
                    $vehicleResearchText = '';
                }

                $combinedResearch = $researchText;
                if ($vehicleResearchText !== '') {
                    $combinedResearch .= "\n\n=== SUPPLEMENTAL ENGINE-TO-VEHICLE RESEARCH ===\n" . $vehicleResearchText;
                }

                $extractBody = [
                    'systemInstruction' => ['parts' => [['text' => $extractInstruction]]],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => "PART NUMBER: " . $partNumber . "\n\nSEARCH RESEARCH:\n" . $combinedResearch,
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
