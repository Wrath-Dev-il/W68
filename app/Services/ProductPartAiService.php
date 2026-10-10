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

        // Prefer the lighter model first for Product Master lookups. The larger
        // models remain fallbacks when Gemini cannot answer reliably.
        $models = [
            'gemini-2.5-flash-lite',
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-flash-latest',
        ];

        $researchInstruction =
            "You are a product identification and automotive-fitment researcher for W68 Auto Parts in the PHILIPPINES.\n" .
            "Use Google Search to research the EXACT product/part number supplied by the user, with PHILIPPINE MARKET relevance as the priority.\n" .
            "Prioritize evidence from Philippine distributors, Philippine auto-parts sellers, official Philippine vehicle/model information, local catalogs, and Philippine e-commerce/local-market listings when useful.\n" .
            "You may use global manufacturer or technical catalogs to identify the exact part, engine code, dimensions or cross-reference, but VEHICLE APPLICATIONS returned to W68 must be models/variants sold, imported, or commonly used in the Philippine market.\n" .
            "Do not return a foreign-market-only vehicle application merely because the same engine/part exists overseas. If Philippine relevance cannot be supported, omit that application rather than guessing.\n" .
            "Use Philippine-market model names and common local naming when they differ from overseas names.\n" .
            "W68 may also stock GENERAL or UNIVERSAL products such as LED bulbs, lamps, tools, accessories, electrical items and shop supplies. These are valid products even when they have no vehicle fitment; identify them using naming commonly used in the Philippine market.\n" .
            "A part number may contain a size suffix such as 0.25, 0.50, STD, OS or US. Treat that as the bearing/part size variant, not as the vehicle application.\n" .
            "Collect the short generic part type, size/variant meaning, every verified engine code, and EVERY clearly supported PHILIPPINE-MARKET vehicle application.\n" .
            "For every application, explicitly state MAKE/CAR BRAND, CAR MODEL, YEAR FROM, YEAR TO, and ENGINE CODE or engine displacement when available.\n" .
            "Search multiple useful results when needed. Do not stop after the first engine code or first vehicle fitment.\n" .
            "Do not substitute a similar part number and do not invent compatibility.\n" .
            "If the exact number clearly identifies a general/universal non-vehicle-specific product, still mark it FOUND and identify its short generic description; vehicle applications may be empty.\n";

        $extractInstruction =
            "Convert the supplied product research into strict structured data for W68 Product Master.\n" .
            "Return JSON only.\n" .
            "description must be a SHORT GENERIC PART NAME ONLY in uppercase, for example CON ROD BEARING, MAIN BEARING, BALL JOINT, TIE ROD END, RACK END, STABILIZER LINK, CONTROL ARM, ENGINE MOUNTING, WHEEL BEARING, BRAKE PAD, ABS SENSOR.\n" .
            "Do not put size text such as 0.25MM UNDERSIZE, 0.50MM, STD, OS or US in description. Keep size information only in notes.\n" .
            "product_type must be AUTOMOTIVE when vehicle fitment exists, otherwise GENERAL for a verified general/universal product.\n" .
            "applications must contain every clearly verified vehicle fitment for AUTOMOTIVE products. For GENERAL products, applications must be an empty array.\n" .
            "For internal engine parts: if the exact part is verified for an engine code, and supplemental research verifies that engine in a Philippine-market vehicle model/year, you MAY bridge that engine fitment into an application row.\n" .
            "Only include vehicle applications relevant to the PHILIPPINE MARKET. Exclude foreign-market-only fitments unless the same model/variant is also sold, imported, or commonly used in the Philippines.\n" .
            "Never place the vehicle model inside car_brand. Never place the brand inside car_model.\n" .
            "year_from/year_to must be four-digit years when supported by the research. A single verified model year must be used for both year_from and year_to.\n" .
            "If a range such as 2005-2015 appears, split it into year_from=2005 and year_to=2015.\n" .
            "Do not invent missing fitment data.\n";

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'found' => ['type' => 'BOOLEAN'],
                'product_type' => ['type' => 'STRING', 'enum' => ['AUTOMOTIVE', 'GENERAL']],
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
            'required' => ['found', 'product_type', 'description', 'applications', 'confidence', 'notes'],
        ];

        $lastError = 'Gemini could not identify this part number.';

        foreach ($models as $model) {
            try {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->apiKey;

                // PASS 1: one grounded exact-part search.
                $researchBody = [
                    'systemInstruction' => ['parts' => [['text' => $researchInstruction]]],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => 'PHILIPPINES / PHILIPPINE MARKET lookup for exact product or automotive part number ' . $partNumber . '. If automotive, include only Philippine-market make/model/year/engine fitment details. If general/universal, identify the exact product type as sold/named in the Philippine market without inventing vehicle fitment.',
                        ]],
                    ]],
                    'tools' => [['google_search' => (object) []]],
                    'generationConfig' => ['temperature' => 0.1],
                ];

                $researchResponse = Http::timeout(25)
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

                // PASS 2: fast structured extraction. Most parts finish here.
                $firstExtract = $this->extractStructuredResult(
                    $url,
                    $partNumber,
                    $researchText,
                    $extractInstruction,
                    $schema
                );

                if (!is_array($firstExtract)) {
                    $lastError = 'Gemini returned an unreadable structured fitment result.';
                    continue;
                }

                $firstResult = $this->normalize($partNumber, $firstExtract);

                if (!$this->needsVehicleExpansion($firstResult)) {
                    return $firstResult;
                }

                // Only engine-only/incomplete results get this extra grounded pass.
                // This keeps common BALL JOINT / TIE ROD END lookups much faster.
                $vehicleResearchInstruction =
                    "You are an automotive engine-to-vehicle fitment researcher for the PHILIPPINE MARKET.\n" .
                    "Using Google Search, take the exact part-number research below and identify every ENGINE CODE mentioned.\n" .
                    "For each engine code, find verified vehicle MAKE, MODEL/SERIES, YEAR FROM, YEAR TO and engine code specifically for vehicles sold, imported, or commonly used in the Philippines.\n" .
                    "Prioritize Philippine sources and Philippine-market model names. Global engine catalogs may support technical identity, but do not return a foreign-market-only vehicle fitment as a Philippine application.\n" .
                    "This is especially important for engine-internal parts such as con-rod bearings and main bearings.\n" .
                    "Do not guess. If Philippine-market model/year support cannot be verified, say that it remains unresolved.\n";

                $vehicleResearchBody = [
                    'systemInstruction' => ['parts' => [['text' => $vehicleResearchInstruction]]],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => "MARKET: PHILIPPINES\nEXACT PART NUMBER: " . $partNumber . "\n\nPART RESEARCH:\n" . $researchText,
                        ]],
                    ]],
                    'tools' => [['google_search' => (object) []]],
                    'generationConfig' => ['temperature' => 0.1],
                ];

                $vehicleResearchResponse = Http::timeout(25)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, $vehicleResearchBody);

                if (!$vehicleResearchResponse->successful()) {
                    // Return the useful first result instead of making the user wait
                    // through additional model fallbacks.
                    return $firstResult;
                }

                $vehicleResearchData = $vehicleResearchResponse->json();
                $vehicleResearchText = '';

                foreach (($vehicleResearchData['candidates'][0]['content']['parts'] ?? []) as $part) {
                    if (isset($part['text'])) {
                        $vehicleResearchText .= (string) $part['text'];
                    }
                }

                $vehicleResearchText = trim($vehicleResearchText);

                if ($vehicleResearchText === '') {
                    return $firstResult;
                }

                $combinedResearch = $researchText
                    . "\n\n=== SUPPLEMENTAL ENGINE-TO-VEHICLE RESEARCH ===\n"
                    . $vehicleResearchText;

                $expandedExtract = $this->extractStructuredResult(
                    $url,
                    $partNumber,
                    $combinedResearch,
                    $extractInstruction,
                    $schema
                );

                if (!is_array($expandedExtract)) {
                    return $firstResult;
                }

                $expandedResult = $this->normalize($partNumber, $expandedExtract);

                return $this->resultCompletenessScore($expandedResult) >= $this->resultCompletenessScore($firstResult)
                    ? $expandedResult
                    : $firstResult;
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

    protected function extractStructuredResult(
        string $url,
        string $partNumber,
        string $researchText,
        string $extractInstruction,
        array $schema
    ): ?array {
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

        $response = Http::timeout(18)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $extractBody);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        $jsonText = '';

        foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (isset($part['text'])) {
                $jsonText .= (string) $part['text'];
            }
        }

        $decoded = json_decode(trim($jsonText), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function needsVehicleExpansion(array $result): bool
    {
        if (!($result['success'] ?? false)) {
            return false;
        }

        $applications = is_array($result['applications'] ?? null)
            ? $result['applications']
            : [];

        if (empty($applications)) {
            return false;
        }

        foreach ($applications as $application) {
            $model = trim((string) ($application['car_model'] ?? ''));
            $yearFrom = trim((string) ($application['year_from'] ?? ''));
            $yearTo = trim((string) ($application['year_to'] ?? ''));
            $engine = trim((string) ($application['engine'] ?? ''));

            if ($engine !== '' && ($model === '' || $yearFrom === '' || $yearTo === '')) {
                return true;
            }
        }

        return false;
    }

    protected function resultCompletenessScore(array $result): int
    {
        $score = trim((string) ($result['description'] ?? '')) !== '' ? 10 : 0;

        foreach (($result['applications'] ?? []) as $application) {
            if (!is_array($application)) continue;
            foreach (['car_brand', 'car_model', 'year_from', 'year_to', 'engine'] as $field) {
                if (trim((string) ($application[$field] ?? '')) !== '') {
                    $score++;
                }
            }
        }

        return $score;
    }

    protected function normalize(string $partNumber, array $data): array
    {
        $found = (bool) ($data['found'] ?? false);
        $description = mb_strtoupper(trim((string) ($data['description'] ?? '')));
        $productType = mb_strtoupper(trim((string) ($data['product_type'] ?? 'AUTOMOTIVE')));
        if (!in_array($productType, ['AUTOMOTIVE', 'GENERAL'], true)) {
            $productType = 'AUTOMOTIVE';
        }
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

        $ok = $found && $description !== '' && ($productType === 'GENERAL' || !empty($applications));

        return [
            'success' => $ok,
            'found' => $ok,
            'product_type' => $productType,
            'part_number' => $partNumber,
            'description' => $description,
            'applications' => $productType === 'GENERAL' ? [] : $applications,
            'confidence' => $confidence,
            'message' => trim((string) ($data['notes'] ?? ($ok ? '' : 'Exact part-number compatibility could not be verified.'))),
        ];
    }
}
