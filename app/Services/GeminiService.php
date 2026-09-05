<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeminiService
{
    protected string $apiKey;
    protected array $models = [
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.0-flash',
        'gemini-flash-latest',
    ];
    protected int $dailyLimit = 1500;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
    }

    public function sendMessage(string $message, ?string $imageBase64 = null, ?string $imageMimeType = null, array $history = []): array
    {
        if ($imageBase64 === null) {
            $localCommandResponse = $this->tryHandleLocalCommand($message);
            if ($localCommandResponse !== null) {
                return [
                    'success' => true,
                    'text' => $localCommandResponse,
                    'usage' => ['localCommand' => true],
                    'dailyRemaining' => $this->getDailyRemaining(),
                    'dailyLimit' => $this->dailyLimit,
                    'resetTime' => $this->getResetTime(),
                ];
            }
        }

        $schema = $this->getDatabaseSchema();
        $navContext = $this->getNavigationContext();
        $baseUrl = url('/');
        
        $systemInstruction = "You are an AI assistant for W68 Auto Parts enterprise management system.\n\n" .
            "=== DATABASE SCHEMA ===\n{$schema}\n\n" .
            "=== SYSTEM PAGES ===\n{$navContext}\n\n" .
            "=== GOOGLE SEARCH GROUNDING & LOCAL DB COMPARISON ===\n" .
            "When a user uploads or attaches an image of a product:\n" .
            "1. Identify the product brand, part number, product code, application, or specifications from any markings or visual cues in the image.\n" .
            "2. Compare the local database details (such as quantity on hand, local selling price, specifications, position) with the internet search findings provided in the context.\n" .
            "3. Show the user a clear comparison (such as stock status, spec matching, price comparison) and describe what matches or differs.\n" .
            "4. Finally, ALWAYS provide a redirect link at the very end of your response to the Product Master List filtered by the matching product (e.g. `[REDIRECT]({$baseUrl}/admin/masterlist/product?search[product_code]=VALUE` or `?search[part_number]=VALUE`).\n\n" .
            "========================================\n" .
            "ABSOLUTE MANDATORY NAVIGATION RULES:\n" .
            "========================================\n" .
            "1. NEVER show SQL queries. NEVER. Not even as an example. This is forbidden.\n" .
            "2. When a user asks to VIEW, SHOW, LIST, SEARCH, or FIND data (products, suppliers, customers, etc.), " .
            "   you MUST redirect them to the correct page by ending your response with exactly this format:\n" .
            "   [REDIRECT]({$baseUrl}/the_url_path_here)\n" .
            "   The [REDIRECT] text must be the VERY LAST thing in your response.\n" .
            "3. For ANY page (Masterlists, Sales, Purchasing, etc.), you can apply smart routing using URL parameters:\n" .
            "   - To search a specific column: Append ?search[COLUMN_NAME]=VALUE\n" .
            "   - To open a specific tab: Append ?tab=TAB_NAME (e.g., all, newly, low, admin, employee)\n" .
            "   - IMPORTANT: If your VALUE contains spaces or special characters (like slashes, ampersands), you MUST properly URL-encode the VALUE (e.g., use %20 for spaces, %2F for slashes). Example: 'Part A / B' becomes 'Part%20A%20%2F%20B'.\n" .
            "   CRITICAL MAPPING RULES (Products):\n" .
            "   - 'category' column holds the BRAND (e.g., Autostar, Toyota, NGK).\n" .
            "   - 'description' column holds the PART NAME (e.g., BALL JOINT, FULL SET GASKET, REGULATOR).\n" .
            "   - 'application' column holds the CAR MODEL (e.g., EXPLORER 02-05, FOCUS 1.8).\n" .
            "   - If user asks for 'product code', ALWAYS use 'product_code'. Do NOT use 'part_number'.\n" .
            "   - If user asks for 'part number' or 'part no', ALWAYS use 'part_number'. Do NOT use 'product_code'.\n" .
            "   - IMPORTANT: If a user provides an alphanumeric string (like 'FS-2633-S / 6D24') and simply says 'the product [CODE]', DEFAULT to using 'product_code'.\n" .
            "   Example for Ball Joint (Part Name): [REDIRECT]({$baseUrl}/admin/masterlist/product?search[description]=Ball+Joint)\n" .
            "   Example for Autostar (Brand): [REDIRECT]({$baseUrl}/admin/masterlist/product?search[category]=Autostar)\n" .
            "   Example for Employee Tab in USM: [REDIRECT]({$baseUrl}/admin/usm?tab=employee)\n" .
            "4. NEVER say you 'cannot access' or 'don't have access to' data. Instead, redirect to the page.\n" .
            "5. NEVER define data with SQL or code blocks. Only give a brief 1-sentence plain English summary then redirect.\n" .
            "6. If the user asks a general question (not about data), answer briefly WITHOUT a redirect.\n\n" .
            "REDIRECT FORMAT EXAMPLE:\n" .
            "User: show me all autostar ball joints\n" .
            "You: Taking you to the Product Master List filtered by Autostar Ball Joints. [REDIRECT]({$baseUrl}/admin/masterlist/product?search[category]=Autostar&search[description]=Ball+Joint)\n\n" .
            "Capabilities:\n" .
            "1. Search product details, stock, prices, reorder levels, and local availability\n" .
            "2. Suggest similar or substitute products from the master list\n" .
            "3. Search suppliers and show supplier purchase activity\n" .
            "4. Search customers and summarize customer purchase history\n" .
            "5. Show fast-moving products, low-stock products, data quality issues, and business summaries\n" .
            "6. Search audit trails and archived records\n" .
            "7. Analyze images (parts, documents, etc.)\n" .
            "8. Navigate users to the right page with pre-applied filters\n\n" .
            "ASSISTANT COMMAND STYLE:\n" .
            "- If the user asks for stock, availability, low stock, or reorder items, call `check_stock_and_reorder`.\n" .
            "- If the user asks for alternatives, substitutes, replacements, or similar items, call `find_product_alternatives`.\n" .
            "- If the user asks about suppliers, call `search_suppliers` or `get_supplier_purchase_activity`.\n" .
            "- If the user asks about customers or customer history, call `search_customers` or `get_customer_purchase_history`.\n" .
            "- If the user asks for best-selling, fast-moving, slow-moving, or product movement, call `get_product_movement_report`.\n" .
            "- If the user asks for duplicate/missing/import/data cleanup issues, call `get_data_quality_report`.\n" .
            "- If the user asks who changed/deleted/restored something, call `search_audit_trail` or `search_archived_records`.\n" .
            "- If the user asks where to go or how to open a page, call `get_page_navigation` and end with the redirect link when appropriate.\n\n" .
            "If the user asks for COMMANDS, HELP, or WHAT CAN YOU DO, show concise examples like:\n" .
            "- stock PRODUCT_NAME\n" .
            "- low stock products\n" .
            "- alternatives for PRODUCT_NAME\n" .
            "- supplier SUPPLIER_NAME\n" .
            "- supplier activity SUPPLIER_NAME\n" .
            "- customer CUSTOMER_NAME\n" .
            "- customer history CUSTOMER_NAME\n" .
            "- fast moving products\n" .
            "- slow moving products\n" .
            "- business summary\n" .
            "- data cleanup report\n" .
            "- audit PRODUCT_OR_USER_NAME\n" .
            "- archived PRODUCT_OR_CUSTOMER_NAME\n" .
            "- open PAGE_NAME\n" .
            "- price check PRODUCT_NAME\n" .
            "- margin PRODUCT_NAME\n" .
            "- no price products\n" .
            "- negative stock products\n" .
            "- new products\n" .
            "- product ledger PRODUCT_NAME\n" .
            "- stock movement PRODUCT_NAME\n" .
            "- sales for PRODUCT_NAME\n" .
            "- purchase for PRODUCT_NAME\n" .
            "- top customers\n" .
            "- inactive customers\n" .
            "- top suppliers\n" .
            "- inactive suppliers\n" .
            "- pending sales orders\n" .
            "- pending purchase orders\n" .
            "- rush sales notes\n" .
            "- overdue payments\n" .
            "- unpaid invoices CUSTOMER_NAME\n" .
            "- supplier balance SUPPLIER_NAME\n" .
            "- customer balance CUSTOMER_NAME\n" .
            "- products without image\n" .
            "- products missing application\n" .
            "- products missing part number\n" .
            "- duplicate products\n" .
            "- recent changes\n" .
            "- who edited PRODUCT_NAME\n" .
            "- who deleted PRODUCT_NAME\n" .
            "- restore guide PRODUCT_NAME\n" .
            "- daily sales summary\n" .
            "- monthly sales summary\n" .
            "- inventory health\n" .
            "- supplier reorder suggestion SUPPLIER_NAME\n" .
            "- sales order help\n" .
            "- purchase order help\n" .
            "- inventory adjustment help\n" .
            "- can sell PRODUCT_NAME QTY\n" .
            "- shortage check PRODUCT_NAME QTY\n" .
            "- find by part number PART_NUMBER\n" .
            "- find by vehicle MODEL_NAME\n" .
            "- customer last order CUSTOMER_NAME\n" .
            "- supplier price history PRODUCT_NAME\n" .
            "- dead stock products\n" .
            "- stock value report\n" .
            "- today activity\n" .
            "- backup status\n\n" .
            "When answering, be professional and concise. Use markdown formatting for readability.";

        // Determine if internet search is needed
        $needsInternetSearch = ($imageBase64 !== null) || 
            preg_match('/\b(compare|internet|web|google|search|market|online|price|external)\b/i', $message);

        $webFindings = null;
        if ($needsInternetSearch) {
            $webFindings = $this->runInternetSearch($message, $imageBase64, $imageMimeType);
        }

        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role' => $msg['role'],
                'parts' => [['text' => $msg['text']]]
            ];
        }

        $parts = [['text' => $message]];
        if ($imageBase64 && $imageMimeType) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $imageMimeType,
                    'data' => $imageBase64
                ]
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => $parts];

        // Enrich the user turn if we have web findings
        if ($webFindings) {
            $parts[] = [
                'text' => "\n\n=== INTERNET SEARCH FINDINGS ===\n" . $webFindings . "\n================================\n" .
                          "Now, please call the `search_local_products` tool to query the local database for this product, compare it, and show the results to the user."
            ];
            $contents[count($contents) - 1]['parts'] = $parts;
        }

        // Tools for the main conversation (only function calling, no google_search to avoid conflict)
        $tools = [
            [
                'function_declarations' => [
                    [
                        'name' => 'search_local_products',
                        'description' => 'Search for auto part products in the local masterlist database by product code, part number, description, brand, or application. Returns matching products with their specifications, quantity on hand, and price.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => [
                                    'type' => 'STRING',
                                    'description' => 'The search term (e.g. part number, product code, category/brand, description, etc.)'
                                ]
                            ],
                            'required' => ['query']
                        ]
                    ],
                    [
                        'name' => 'check_stock_and_reorder',
                        'description' => 'Check product stock availability, low-stock items, reorder levels, and suggested reorder quantity.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => [
                                    'type' => 'STRING',
                                    'description' => 'Optional product code, part number, brand, description, or application. Leave blank to list low-stock/reorder items.'
                                ],
                                'limit' => [
                                    'type' => 'INTEGER',
                                    'description' => 'Maximum number of records to return.'
                                ]
                            ]
                        ]
                    ],
                    [
                        'name' => 'find_product_alternatives',
                        'description' => 'Find similar or substitute products based on product code, part number, description, brand, application, or specification.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => [
                                    'type' => 'STRING',
                                    'description' => 'The product to find alternatives for.'
                                ],
                                'limit' => [
                                    'type' => 'INTEGER',
                                    'description' => 'Maximum number of alternatives to return.'
                                ]
                            ],
                            'required' => ['query']
                        ]
                    ],
                    [
                        'name' => 'search_suppliers',
                        'description' => 'Search suppliers by code, name, contact person, phone, email, address, payment terms, or status.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => ['type' => 'STRING', 'description' => 'Supplier search term.'],
                                'limit' => ['type' => 'INTEGER', 'description' => 'Maximum number of records to return.']
                            ],
                            'required' => ['query']
                        ]
                    ],
                    [
                        'name' => 'search_customers',
                        'description' => 'Search customers by name, contact number, contact person, address, TIN, pricing remarks, or terms.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => ['type' => 'STRING', 'description' => 'Customer search term.'],
                                'limit' => ['type' => 'INTEGER', 'description' => 'Maximum number of records to return.']
                            ],
                            'required' => ['query']
                        ]
                    ],
                    [
                        'name' => 'get_customer_purchase_history',
                        'description' => 'Summarize recent sales orders and top purchased products for a customer.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => ['type' => 'STRING', 'description' => 'Customer name, contact, or ID.'],
                                'limit' => ['type' => 'INTEGER', 'description' => 'Maximum number of recent orders/items to return.']
                            ],
                            'required' => ['query']
                        ]
                    ],
                    [
                        'name' => 'get_supplier_purchase_activity',
                        'description' => 'Summarize recent purchase orders and common purchased products for a supplier.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => ['type' => 'STRING', 'description' => 'Supplier name, code, contact, or ID.'],
                                'limit' => ['type' => 'INTEGER', 'description' => 'Maximum number of recent orders/items to return.']
                            ],
                            'required' => ['query']
                        ]
                    ],
                    [
                        'name' => 'get_product_movement_report',
                        'description' => 'Show fast-moving or slow-moving products using product ledger movement.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'mode' => ['type' => 'STRING', 'description' => 'Use fast for best-selling/fast-moving or slow for slow-moving.'],
                                'days' => ['type' => 'INTEGER', 'description' => 'Number of days to look back.'],
                                'limit' => ['type' => 'INTEGER', 'description' => 'Maximum number of products to return.']
                            ]
                        ]
                    ],
                    [
                        'name' => 'get_business_summary',
                        'description' => 'Get a short operational summary: counts, low stock, recent sales/purchases, archived records, and audit activity.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'days' => ['type' => 'INTEGER', 'description' => 'Number of days for recent activity.']
                            ]
                        ]
                    ],
                    [
                        'name' => 'get_data_quality_report',
                        'description' => 'Find duplicate or missing product, supplier, and customer data useful before imports or cleanup.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'limit' => ['type' => 'INTEGER', 'description' => 'Maximum number of duplicate examples to return.']
                            ]
                        ]
                    ],
                    [
                        'name' => 'search_audit_trail',
                        'description' => 'Search audit trail entries by module, action, record name, display ID, user, or keyword.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => ['type' => 'STRING', 'description' => 'Audit keyword or record name. Leave blank for latest audit entries.'],
                                'limit' => ['type' => 'INTEGER', 'description' => 'Maximum number of records to return.']
                            ]
                        ]
                    ],
                    [
                        'name' => 'search_archived_records',
                        'description' => 'Search archived/deleted records by module, display ID, record name, deleted by, or keyword.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => ['type' => 'STRING', 'description' => 'Archive keyword or record name. Leave blank for latest archived records.'],
                                'limit' => ['type' => 'INTEGER', 'description' => 'Maximum number of records to return.']
                            ]
                        ]
                    ],
                    [
                        'name' => 'get_page_navigation',
                        'description' => 'Return the best system page and redirect URL for a requested task or module.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'destination' => ['type' => 'STRING', 'description' => 'Requested page/task such as product, supplier, sales order, purchase order, audit trail, inventory adjustment, payments, reports.'],
                                'search' => ['type' => 'STRING', 'description' => 'Optional search/filter value for the page.']
                            ],
                            'required' => ['destination']
                        ]
                    ]
                ]
            ]
        ];

        $lastError = '';
        foreach ($this->models as $model) {
            $currentContents = $contents;
            $maxFunctionCalls = 5;
            $success = false;
            $text = '';
            $usage = [];

            for ($callCount = 0; $callCount < $maxFunctionCalls; $callCount++) {
                $requestBody = [
                    'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => $currentContents,
                    'tools' => $tools,
                    'safetySettings' => [
                        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
                    ]
                ];

                $maxRetries = 5;
                $retryDelay = 1;
                $response = null;
                $lastError = '';

                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    try {
                        $response = Http::timeout(60)->withHeaders([
                            'Content-Type' => 'application/json',
                        ])->post(
                            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}",
                            $requestBody
                        );

                        if ($response->successful()) {
                            break;
                        }

                        if ($response->status() == 429 || $response->status() == 503) {
                            $lastError = $response->json()['error']['message'] ?? $response->body();
                            throw new \Exception("API overloaded or rate-limited: " . $lastError);
                        }

                        $lastError = $response->json()['error']['message'] ?? $response->body();
                        break;
                    } catch (\Exception $e) {
                        $lastError = $e->getMessage();
                        
                        if ($attempt == $maxRetries) {
                            \Illuminate\Support\Facades\Log::warning("Gemini API failed after {$maxRetries} attempts for model {$model}: " . $e->getMessage());
                            break;
                        }

                        // Only retry on connection/timeout/429/503 errors
                        $isRetryable = $response !== null && in_array($response->status(), [429, 503]);
                        $isConnectionError = str_contains($e->getMessage(), 'cURL') || str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'could not connect');

                        if (!$isRetryable && !$isConnectionError) {
                            break;
                        }

                        $sleepTime = ($retryDelay * pow(2, $attempt)) + (rand(1, 1000) / 1000);
                        usleep($sleepTime * 1000000);
                    }
                }

                if (!$response || !$response->successful()) {
                    if (str_contains($lastError, 'quota') || str_contains($lastError, 'Quota')) {
                        break; // Try next model
                    }
                    break; // Non-quota error, abort this model
                }

                $data = $response->json();
                $candidate = $data['candidates'][0] ?? null;
                if (!$candidate) {
                    $lastError = "No candidate in response";
                    break;
                }

                $parts = $candidate['content']['parts'] ?? [];
                
                // Check if there is a functionCall
                $functionCall = null;
                foreach ($parts as $part) {
                    if (isset($part['functionCall'])) {
                        $functionCall = $part['functionCall'];
                        break;
                    }
                }

                if ($functionCall) {
                    $functionName = $functionCall['name'];
                    $functionArgs = $functionCall['args'] ?? [];

                    $functionResult = [];
                    if ($functionName === 'search_local_products') {
                        $functionResult = $this->searchLocalProducts($functionArgs['query'] ?? '');
                    } elseif ($functionName === 'check_stock_and_reorder') {
                        $functionResult = $this->checkStockAndReorder(
                            $functionArgs['query'] ?? '',
                            (int) ($functionArgs['limit'] ?? 10)
                        );
                    } elseif ($functionName === 'find_product_alternatives') {
                        $functionResult = $this->findProductAlternatives(
                            $functionArgs['query'] ?? '',
                            (int) ($functionArgs['limit'] ?? 10)
                        );
                    } elseif ($functionName === 'search_suppliers') {
                        $functionResult = $this->searchSuppliers(
                            $functionArgs['query'] ?? '',
                            (int) ($functionArgs['limit'] ?? 10)
                        );
                    } elseif ($functionName === 'search_customers') {
                        $functionResult = $this->searchCustomers(
                            $functionArgs['query'] ?? '',
                            (int) ($functionArgs['limit'] ?? 10)
                        );
                    } elseif ($functionName === 'get_customer_purchase_history') {
                        $functionResult = $this->getCustomerPurchaseHistory(
                            $functionArgs['query'] ?? '',
                            (int) ($functionArgs['limit'] ?? 8)
                        );
                    } elseif ($functionName === 'get_supplier_purchase_activity') {
                        $functionResult = $this->getSupplierPurchaseActivity(
                            $functionArgs['query'] ?? '',
                            (int) ($functionArgs['limit'] ?? 8)
                        );
                    } elseif ($functionName === 'get_product_movement_report') {
                        $functionResult = $this->getProductMovementReport(
                            $functionArgs['mode'] ?? 'fast',
                            (int) ($functionArgs['days'] ?? 30),
                            (int) ($functionArgs['limit'] ?? 10)
                        );
                    } elseif ($functionName === 'get_business_summary') {
                        $functionResult = $this->getBusinessSummary((int) ($functionArgs['days'] ?? 7));
                    } elseif ($functionName === 'get_data_quality_report') {
                        $functionResult = $this->getDataQualityReport((int) ($functionArgs['limit'] ?? 10));
                    } elseif ($functionName === 'search_audit_trail') {
                        $functionResult = $this->searchAuditTrail(
                            $functionArgs['query'] ?? '',
                            (int) ($functionArgs['limit'] ?? 10)
                        );
                    } elseif ($functionName === 'search_archived_records') {
                        $functionResult = $this->searchArchivedRecords(
                            $functionArgs['query'] ?? '',
                            (int) ($functionArgs['limit'] ?? 10)
                        );
                    } elseif ($functionName === 'get_page_navigation') {
                        $functionResult = $this->getPageNavigation(
                            $functionArgs['destination'] ?? '',
                            $functionArgs['search'] ?? ''
                        );
                    }

                    // Add the model's functionCall turn to currentContents
                    $currentContents[] = [
                        'role' => 'model',
                        'parts' => [
                            [
                                'functionCall' => $functionCall
                            ]
                        ]
                    ];

                    // Add the function response turn
                    $currentContents[] = [
                        'role' => 'function',
                        'parts' => [
                            [
                                'functionResponse' => [
                                    'name' => $functionName,
                                    'response' => [
                                        'results' => $functionResult
                                    ]
                                ]
                            ]
                        ]
                    ];

                    // Call generation again with new tool contents
                    continue;
                }

                // Final response
                foreach ($parts as $part) {
                    if (isset($part['text'])) {
                        $text .= $part['text'];
                    }
                }
                
                $usage = $data['usageMetadata'] ?? [];
                $success = true;
                break;
            }

            if ($success) {
                $this->incrementDailyCount();

                return [
                    'success' => true,
                    'text' => $text,
                    'usage' => $usage,
                    'dailyRemaining' => $this->getDailyRemaining(),
                    'dailyLimit' => $this->dailyLimit,
                    'resetTime' => $this->getResetTime(),
                ];
            }

            if (str_contains($lastError, 'quota') || str_contains($lastError, 'Quota')) {
                continue;
            }
            break;
        }

        return [
            'success' => false,
            'error' => $lastError,
            'dailyRemaining' => $this->getDailyRemaining(),
            'dailyLimit' => $this->dailyLimit,
            'resetTime' => $this->getResetTime(),
        ];
    }

    /**
     * Run an internet search using the Google Search grounding tool to gather market information.
     */
    protected function runInternetSearch(string $message, ?string $imageBase64 = null, ?string $imageMimeType = null): ?string
    {
        $systemInstruction = "You are a product identification assistant. Analyze the query/image, identify the product, and use Google Search to retrieve its specifications, brand, part numbers, applications, and average market price. Summarize these findings clearly.";
        
        $parts = [['text' => "Identify this product and find its market details: " . $message]];
        if ($imageBase64 && $imageMimeType) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $imageMimeType,
                    'data' => $imageBase64
                ]
            ];
        }

        $requestBody = [
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => [
                ['role' => 'user', 'parts' => $parts]
            ],
            'tools' => [
                ['google_search' => (object)[]]
            ]
        ];

        foreach ($this->models as $model) {
            $maxRetries = 3;
            $retryDelay = 1;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $response = Http::timeout(40)->withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}",
                        $requestBody
                    );

                    if ($response->successful()) {
                        $data = $response->json();
                        $text = '';
                        if (isset($data['candidates'][0]['content']['parts'])) {
                            foreach ($data['candidates'][0]['content']['parts'] as $part) {
                                if (isset($part['text'])) {
                                    $text .= $part['text'];
                                }
                            }
                        }
                        if (!empty($text)) {
                            return $text;
                        }
                        break;
                    }

                    if ($response->status() == 429 || $response->status() == 503) {
                        if ($attempt == $maxRetries) break;
                        $sleepTime = ($retryDelay * pow(2, $attempt)) + (rand(1, 1000) / 1000);
                        usleep($sleepTime * 1000000);
                        continue;
                    }
                    break;
                } catch (\Exception $e) {
                    if ($attempt == $maxRetries) break;
                    $sleepTime = ($retryDelay * pow(2, $attempt)) + (rand(1, 1000) / 1000);
                    usleep($sleepTime * 1000000);
                }
            }
        }

        return null;
    }

    /**
     * Search the local masterlist database for products matching the query.
     */
    public function searchLocalProducts(string $query): array
    {
        if (empty(trim($query))) {
            return [];
        }

        try {
            $products = \App\Models\Product::where(function ($q) use ($query) {
                $q->where('product_code', 'LIKE', "%{$query}%")
                  ->orWhere('part_number', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhere('category', 'LIKE', "%{$query}%")
                  ->orWhere('application', 'LIKE', "%{$query}%")
                  ->orWhere('specification', 'LIKE', "%{$query}%")
                  ->orWhere('position', 'LIKE', "%{$query}%");
            })
            ->limit(10)
            ->get([
                'product_code',
                'part_number',
                'category',
                'specification',
                'description',
                'application',
                'position',
                'on_hand',
                'selling_price',
                'cost',
                'price_online',
                'status'
            ])
            ->toArray();

            return $products;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    protected function tryHandleLocalCommand(string $message): ?string
    {
        $command = trim(preg_replace('/\s+/', ' ', $message));
        $lower = strtolower($command);

        if ($lower === 'commands' || $lower === 'help' || $lower === 'what can you do') {
            return $this->formatCommandList();
        }

        if ($lower === 'business summary' || $lower === 'summary') {
            return $this->formatBusinessSummary($this->getBusinessSummary(7));
        }

        if (preg_match('/^(can\s+sell|shortage\s+check)\s+(.+)\s+(\d+)$/i', $command, $matches)) {
            return $this->formatSellabilityCheck($this->checkSellability($matches[2], (int) $matches[3]));
        }

        if (preg_match('/^find\s+by\s+part\s+number\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Part number results', $this->findProductsByColumn('part_number', $matches[1], 10), ['product_code', 'part_number', 'description', 'application', 'on_hand', 'selling_price']);
        }

        if (preg_match('/^find\s+by\s+vehicle\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Vehicle application results', $this->findProductsByColumn('application', $matches[1], 10), ['product_code', 'part_number', 'description', 'application', 'on_hand', 'selling_price']);
        }

        if (preg_match('/^price\s+check\s+(.+)$/i', $command, $matches)) {
            return $this->formatPriceInfo($this->getProductPriceInfo($matches[1]));
        }

        if (preg_match('/^margin\s+(.+)$/i', $command, $matches)) {
            return $this->formatPriceInfo($this->getProductPriceInfo($matches[1]));
        }

        if ($lower === 'no price products') {
            return $this->formatSimpleRows('Products with missing or zero price', $this->getProductsByIssue('no_price', 10), ['product_code', 'description', 'selling_price', 'cost', 'on_hand']);
        }

        if ($lower === 'negative stock products') {
            return $this->formatSimpleRows('Negative stock products', $this->getProductsByIssue('negative_stock', 10), ['product_code', 'description', 'on_hand', 'selling_price']);
        }

        if ($lower === 'new products') {
            return $this->formatSimpleRows('New products', $this->getProductsByIssue('new_products', 10), ['product_code', 'description', 'category', 'on_hand', 'status']);
        }

        if ($lower === 'products without image') {
            return $this->formatSimpleRows('Products without image', $this->getProductsByIssue('without_image', 10), ['product_code', 'description', 'category', 'on_hand']);
        }

        if ($lower === 'products missing application') {
            return $this->formatSimpleRows('Products missing application', $this->getProductsByIssue('missing_application', 10), ['product_code', 'description', 'category', 'on_hand']);
        }

        if ($lower === 'products missing part number') {
            return $this->formatSimpleRows('Products missing part number', $this->getProductsByIssue('missing_part_number', 10), ['product_code', 'description', 'category', 'on_hand']);
        }

        if ($lower === 'duplicate products') {
            return $this->formatDuplicateProducts($this->getDuplicateProducts(10));
        }

        if ($lower === 'low stock products' || $lower === 'reorder products' || $lower === 'low stock' || $lower === 'reorder') {
            return $this->formatStockList($this->checkStockAndReorder('', 10));
        }

        if ($lower === 'stock value report') {
            return $this->formatStockValueReport($this->getStockValueReport(10));
        }

        if (preg_match('/^(product\s+ledger|stock\s+movement)\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Product ledger', $this->getProductLedger($matches[2], 10), ['date', 'transaction_type', 'transaction_number', 'entity_name', 'quantity_in', 'quantity_out', 'balance_stock']);
        }

        if (preg_match('/^stock\s+(.+)$/i', $command, $matches)) {
            return $this->formatStockList($this->checkStockAndReorder($matches[1], 10));
        }

        if (preg_match('/^sales\s+for\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Product sales activity', $this->getProductSalesActivity($matches[1], 10), ['order_number', 'customer_name', 'product_code', 'description', 'quantity', 'unit_price', 'subtotal', 'status']);
        }

        if (preg_match('/^purchase\s+for\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Product purchase activity', $this->getProductPurchaseActivity($matches[1], 10), ['po_number', 'supplier_name', 'product_code', 'description', 'quantity', 'unit_price', 'subtotal', 'status']);
        }

        if (preg_match('/^(cheapest\s+(?:supplier|buy)|best\s+supplier\s+price)\s+(.+)$/i', $command, $matches)) {
            return $this->formatCheapestSupplierList($this->getCheapestSuppliersForProduct($matches[2], 10));
        }

        if (preg_match('/^alternatives?\s+(?:for\s+)?(.+)$/i', $command, $matches)) {
            return $this->formatAlternatives($this->findProductAlternatives($matches[1], 10));
        }

        if ($lower === 'top suppliers') {
            return $this->formatSimpleRows('Top suppliers', $this->getSupplierRanking('top', 10), ['supplier_id', 'supplier_name', 'order_count', 'total_amount']);
        }

        if ($lower === 'inactive suppliers') {
            return $this->formatSimpleRows('Inactive suppliers', $this->getSupplierRanking('inactive', 10), ['supplier_code', 'name', 'contact_number', 'status']);
        }

        if (preg_match('/^supplier\s+activity\s+(.+)$/i', $command, $matches)) {
            return $this->formatSupplierActivity($this->getSupplierPurchaseActivity($matches[1], 8));
        }

        if (preg_match('/^supplier\s+price\s+history\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Supplier price history', $this->getSupplierPriceHistory($matches[1], 10), ['po_number', 'supplier_name', 'product_code', 'description', 'quantity', 'unit_price', 'created_at']);
        }

        if (preg_match('/^supplier\s+balance\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Supplier payable balance', $this->getSupplierBalance($matches[1], 10), ['supplier_name', 'voucher_no', 'invoice_no', 'amount_due', 'amount_paid', 'payment_status']);
        }

        if (preg_match('/^supplier\s+reorder\s+suggestion\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Supplier reorder suggestions', $this->getSupplierReorderSuggestion($matches[1], 10), ['product_code', 'description', 'on_hand', 'Re_order_level', 'suggested_reorder_qty']);
        }

        if (preg_match('/^supplier\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Supplier results', $this->searchSuppliers($matches[1], 10), ['supplier_code', 'name', 'contact_person', 'contact_number', 'status']);
        }

        if ($lower === 'top customers') {
            return $this->formatSimpleRows('Top customers', $this->getCustomerRanking('top', 10), ['customer_id', 'customer_name', 'order_count', 'total_amount']);
        }

        if ($lower === 'inactive customers') {
            return $this->formatSimpleRows('Inactive customers', $this->getCustomerRanking('inactive', 10), ['name', 'contact_person', 'contact_number', 'terms']);
        }

        if (preg_match('/^customer\s+history\s+(.+)$/i', $command, $matches)) {
            return $this->formatCustomerHistory($this->getCustomerPurchaseHistory($matches[1], 8));
        }

        if (preg_match('/^customer\s+last\s+order\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Customer last order', $this->getCustomerLastOrder($matches[1]), ['order_number', 'customer_name', 'total_amount', 'status', 'created_at']);
        }

        if (preg_match('/^customer\s+balance\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Customer balance', $this->getCustomerBalance($matches[1], 10), ['customer_name', 'invoice_no', 'invoice_amount', 'due_amount', 'paid_amount', 'payment_status']);
        }

        if (preg_match('/^customer\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Customer results', $this->searchCustomers($matches[1], 10), ['name', 'contact_person', 'contact_number', 'terms']);
        }

        if ($lower === 'pending sales orders') {
            return $this->formatSimpleRows('Pending sales orders', $this->getPendingOrders('sales', 10), ['order_number', 'customer_name', 'total_amount', 'status', 'created_at']);
        }

        if ($lower === 'pending purchase orders') {
            return $this->formatSimpleRows('Pending purchase orders', $this->getPendingOrders('purchase', 10), ['po_number', 'supplier_id', 'total_amount', 'status', 'created_at']);
        }

        if ($lower === 'rush sales notes') {
            return $this->formatSimpleRows('Rush sales notes', $this->getRushSalesNotes(10), ['sales_number', 'customer_name', 'order_date', 'net_total', 'status']);
        }

        if ($lower === 'overdue payments') {
            return $this->formatSimpleRows('Overdue or unpaid payments', $this->getOverduePayments(10), ['customer_name', 'invoice_no', 'due_amount', 'paid_amount', 'payment_status']);
        }

        if (preg_match('/^unpaid\s+invoices\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Unpaid invoices', $this->getUnpaidInvoices($matches[1], 10), ['customer_name', 'invoice_no', 'invoice_amount', 'due_amount', 'paid_amount', 'payment_status']);
        }

        if ($lower === 'fast moving products' || $lower === 'fast moving') {
            return $this->formatMovementReport($this->getProductMovementReport('fast', 30, 10));
        }

        if ($lower === 'slow moving products' || $lower === 'slow moving') {
            return $this->formatMovementReport($this->getProductMovementReport('slow', 30, 10));
        }

        if ($lower === 'dead stock products') {
            return $this->formatSimpleRows('Dead stock products', $this->getDeadStockProducts(10), ['product_code', 'description', 'on_hand', 'last_movement_at', 'stock_value']);
        }

        if ($lower === 'data cleanup report' || $lower === 'data quality report') {
            return $this->formatDataQualityReport($this->getDataQualityReport(10));
        }

        if ($lower === 'inventory health') {
            return $this->formatInventoryHealth($this->getInventoryHealth());
        }

        if ($lower === 'daily sales summary') {
            return $this->formatSalesSummary($this->getSalesSummary('daily'));
        }

        if ($lower === 'today activity') {
            return $this->formatTodayActivity($this->getTodayActivity());
        }

        if ($lower === 'backup status') {
            return $this->formatBackupStatus($this->getBackupStatus());
        }

        if ($lower === 'monthly sales summary') {
            return $this->formatSalesSummary($this->getSalesSummary('monthly'));
        }

        if ($lower === 'recent changes') {
            return $this->formatSimpleRows('Recent changes', $this->searchAuditTrail('', 10), ['module', 'action', 'display_id', 'record_name', 'user_name', 'created_at']);
        }

        if (preg_match('/^who\s+edited\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Edit history', $this->searchAuditTrail($matches[1], 10), ['module', 'action', 'display_id', 'record_name', 'user_name', 'created_at']);
        }

        if (preg_match('/^who\s+deleted\s+(.+)$/i', $command, $matches)) {
            return $this->formatSimpleRows('Delete history', $this->searchArchivedRecords($matches[1], 10), ['module', 'display_id', 'data_name', 'deleted_by', 'deleted_at', 'restored_at']);
        }

        if (preg_match('/^restore\s+guide\s+(.+)$/i', $command, $matches)) {
            return $this->formatRestoreGuide($matches[1]);
        }

        if (preg_match('/^audit(?:\s+(.+))?$/i', $command, $matches)) {
            return $this->formatSimpleRows('Audit results', $this->searchAuditTrail($matches[1] ?? '', 10), ['module', 'action', 'display_id', 'record_name', 'user_name', 'created_at']);
        }

        if (preg_match('/^archived?(?:\s+(.+))?$/i', $command, $matches)) {
            return $this->formatSimpleRows('Archived records', $this->searchArchivedRecords($matches[1] ?? '', 10), ['module', 'display_id', 'data_name', 'deleted_by', 'deleted_at', 'restored_at']);
        }

        if (preg_match('/^open\s+(.+)$/i', $command, $matches)) {
            $navigation = $this->getPageNavigation($matches[1], '');
            return "Opening {$navigation['label']}.\n\n{$navigation['redirect']}";
        }

        if ($lower === 'sales order help') {
            return $this->formatWorkflowHelp('sales_order');
        }

        if ($lower === 'purchase order help') {
            return $this->formatWorkflowHelp('purchase_order');
        }

        if ($lower === 'inventory adjustment help') {
            return $this->formatWorkflowHelp('inventory_adjustment');
        }

        return null;
    }

    protected function formatCommandList(): string
    {
        return "Commands you can use:\n\n" .
            "stock PRODUCT_NAME - Checks stock, price, status, and availability.\n" .
            "stock PRODUCT_CODE - Finds a specific product by product code and shows quantity on hand.\n" .
            "low stock products - Shows products that are low stock or need reorder.\n" .
            "reorder products - Shows items below reorder level and suggested reorder quantity.\n" .
            "alternatives for PRODUCT_NAME - Finds similar/substitute products from the master list.\n" .
            "supplier SUPPLIER_NAME - Searches supplier contact, address, terms, and status.\n" .
            "supplier activity SUPPLIER_NAME - Shows recent purchase orders and common products.\n" .
            "customer CUSTOMER_NAME - Searches customer contact, address, TIN, terms, and remarks.\n" .
            "customer history CUSTOMER_NAME - Shows recent sales orders and top products bought.\n" .
            "fast moving products - Shows products with highest outgoing movement.\n" .
            "slow moving products - Shows products with little/no outgoing movement.\n" .
            "business summary - Shows product, supplier, customer, sales, purchase, low stock, archive, and audit counts.\n" .
            "data cleanup report - Finds missing/duplicate master list data.\n" .
            "audit PRODUCT_OR_USER_NAME - Searches audit trail changes.\n" .
            "archived PRODUCT_OR_CUSTOMER_NAME - Searches deleted/archived records.\n" .
            "open PAGE_NAME - Navigates to the requested system page.\n" .
            "price check PRODUCT_NAME - Shows selling price, cost, online price, and estimated margin.\n" .
            "margin PRODUCT_NAME - Calculates estimated profit and margin percentage.\n" .
            "no price products - Lists products with missing or zero selling price.\n" .
            "negative stock products - Lists products with stock below zero.\n" .
            "new products - Shows newly added products.\n" .
            "product ledger PRODUCT_NAME - Shows product stock ledger movement.\n" .
            "stock movement PRODUCT_NAME - Shows quantity in/out and balance history.\n" .
            "sales for PRODUCT_NAME - Shows recent sales order activity for a product.\n" .
            "purchase for PRODUCT_NAME - Shows recent purchase order activity for a product.\n" .
            "top customers - Shows customers with highest sales activity.\n" .
            "inactive customers - Shows customers with no recent sales orders.\n" .
            "top suppliers - Shows suppliers with highest purchase activity.\n" .
            "inactive suppliers - Shows suppliers with no recent purchase orders.\n" .
            "pending sales orders - Shows open/pending sales orders.\n" .
            "pending purchase orders - Shows pending purchase orders.\n" .
            "rush sales notes - Shows rush sales notes.\n" .
            "overdue payments - Shows unpaid/partial customer invoice payments.\n" .
            "unpaid invoices CUSTOMER_NAME - Shows unpaid invoices for a customer.\n" .
            "supplier balance SUPPLIER_NAME - Shows payable invoice balance for a supplier.\n" .
            "customer balance CUSTOMER_NAME - Shows receivable/payment balance for a customer.\n" .
            "products without image - Lists products missing product photos.\n" .
            "products missing application - Lists products missing vehicle application.\n" .
            "products missing part number - Lists products missing part number.\n" .
            "duplicate products - Finds duplicate product codes/part numbers.\n" .
            "recent changes - Shows latest audit trail changes.\n" .
            "who edited PRODUCT_NAME - Shows update history for a record.\n" .
            "who deleted PRODUCT_NAME - Shows archive/delete history for a record.\n" .
            "restore guide PRODUCT_NAME - Shows where to restore deleted records.\n" .
            "daily sales summary - Shows today's sales order summary.\n" .
            "monthly sales summary - Shows this month's sales order summary.\n" .
            "inventory health - Shows low stock, negative stock, missing price/image/application/part number counts.\n" .
            "supplier reorder suggestion SUPPLIER_NAME - Suggests low-stock items based on supplier purchase history.\n" .
            "sales order help - Shows basic sales order workflow guidance.\n" .
            "purchase order help - Shows basic purchase order workflow guidance.\n" .
            "inventory adjustment help - Shows basic stock adjustment workflow guidance.\n" .
            "can sell PRODUCT_NAME QTY - Checks if stock can cover the requested sale quantity.\n" .
            "shortage check PRODUCT_NAME QTY - Shows shortage if requested quantity is above stock.\n" .
            "find by part number PART_NUMBER - Searches products by exact/near part number.\n" .
            "find by vehicle MODEL_NAME - Searches products by vehicle application/model.\n" .
            "customer last order CUSTOMER_NAME - Shows the latest sales order for a customer.\n" .
            "supplier price history PRODUCT_NAME - Shows recent purchase prices for a product.\n" .
            "cheapest supplier PRODUCT_NAME - Lists suppliers ranked by cheapest recorded purchase cost for the product.\n" .
            "cheapest buy PRODUCT_NAME - Same as cheapest supplier; finds where to buy the product cheapest.\n" .
            "best supplier price PRODUCT_NAME - Compares supplier costs for the product.\n" .
            "dead stock products - Shows products with stock but no recent movement.\n" .
            "stock value report - Estimates total inventory value and highest-value stock.\n" .
            "today activity - Shows today's sales, purchases, audit, low stock, and rush activity.\n" .
            "backup status - Shows latest backup/data-up file detected in the project.";
    }

    protected function formatBusinessSummary(array $summary): string
    {
        if (isset($summary['error'])) {
            return "Business summary failed: {$summary['error']}";
        }

        return "Business summary for the last {$summary['days']} days:\n\n" .
            "- Products: " . number_format((int) ($summary['products'] ?? 0)) . "\n" .
            "- Suppliers: " . number_format((int) ($summary['suppliers'] ?? 0)) . "\n" .
            "- Customers: " . number_format((int) ($summary['customers'] ?? 0)) . "\n" .
            "- Sales orders: " . number_format((int) ($summary['sales_orders'] ?? 0)) . "\n" .
            "- Purchase orders: " . number_format((int) ($summary['purchase_orders'] ?? 0)) . "\n" .
            "- Low-stock products: " . number_format((int) ($summary['low_stock_products'] ?? 0)) . "\n" .
            "- Recent sales total: " . number_format((float) ($summary['recent_sales_total'] ?? 0), 2) . "\n" .
            "- Recent purchase total: " . number_format((float) ($summary['recent_purchase_total'] ?? 0), 2) . "\n" .
            "- Archived records: " . number_format((int) ($summary['archived_records'] ?? 0)) . "\n" .
            "- Recent audit entries: " . number_format((int) ($summary['recent_audit_entries'] ?? 0));
    }

    protected function formatStockList(array $result): string
    {
        if (isset($result['error'])) {
            return "Stock check failed: {$result['error']}";
        }

        $products = $result['products'] ?? $result;
        if (empty($products)) {
            return "No matching stock records found.";
        }

        $lines = ["Stock results:"];
        foreach (array_slice($products, 0, 10) as $product) {
            $lines[] = "- " . trim((string) ($product['product_code'] ?? 'No code')) .
                " | " . trim((string) ($product['description'] ?? 'No description')) .
                " | On hand: " . ($product['on_hand'] ?? '0') .
                " | Status: " . ($product['stock_status'] ?? ($product['status'] ?? 'N/A')) .
                " | Price: " . ($product['selling_price'] ?? 'N/A');
        }

        return implode("\n", $lines);
    }

    protected function formatAlternatives(array $result): string
    {
        if (isset($result['error'])) {
            return "Alternative search failed: {$result['error']}";
        }

        $alternatives = $result['alternatives'] ?? [];
        if (empty($alternatives)) {
            return "No alternatives found for that product.";
        }

        return $this->formatSimpleRows('Alternative products', $alternatives, ['product_code', 'description', 'application', 'on_hand', 'selling_price']);
    }

    protected function formatSupplierActivity(array $result): string
    {
        if (isset($result['error'])) {
            return "Supplier activity failed: {$result['error']}";
        }

        $lines = ["Supplier activity:"];
        foreach (array_slice($result['suppliers'] ?? [], 0, 3) as $supplier) {
            $lines[] = "- Supplier: " . (($supplier['supplier_code'] ?? '') . ' ' . ($supplier['name'] ?? ''));
        }
        foreach (array_slice($result['recent_purchase_orders'] ?? [], 0, 5) as $order) {
            $lines[] = "- PO: " . ($order['po_number'] ?? $order['receiving_number'] ?? 'N/A') .
                " | Status: " . ($order['status'] ?? 'N/A') .
                " | Total: " . ($order['total_amount'] ?? '0');
        }
        foreach (array_slice($result['common_products'] ?? [], 0, 5) as $product) {
            $lines[] = "- Common product: " . ($product['product_code'] ?? 'No code') .
                " | " . ($product['description'] ?? 'No description') .
                " | Qty: " . ($product['total_qty'] ?? '0');
        }

        return implode("\n", $lines);
    }

    protected function formatCheapestSupplierList(array $result): string
    {
        if (isset($result['error'])) {
            return "Cheapest supplier search failed: {$result['error']}";
        }

        if (empty($result['suppliers'] ?? [])) {
            return "No supplier purchase cost history found for that product.";
        }

        $target = $result['matched_product'] ?? null;
        $lines = ['Cheapest supplier options:'];
        if ($target) {
            $lines[] = 'Product match: ' . trim((string) ($target['product_code'] ?? 'No code')) .
                ' | ' . trim((string) ($target['description'] ?? 'No description'));
        }

        foreach (array_slice($result['suppliers'], 0, 10) as $row) {
            $lines[] = "- " . ($row['supplier_name'] ?? 'Unknown supplier') .
                " | Lowest cost: " . number_format((float) ($row['lowest_unit_price'] ?? 0), 2) .
                " | Avg cost: " . number_format((float) ($row['average_unit_price'] ?? 0), 2) .
                " | Last cost: " . number_format((float) ($row['last_unit_price'] ?? 0), 2) .
                " | Purchases: " . (int) ($row['purchase_count'] ?? 0) .
                " | Last PO: " . ($row['last_po_number'] ?? 'N/A') .
                " | Last date: " . ($row['last_purchase_date'] ?? 'N/A');
        }

        return implode("\n", $lines);
    }

    protected function formatCustomerHistory(array $result): string
    {
        if (isset($result['error'])) {
            return "Customer history failed: {$result['error']}";
        }

        $lines = ["Customer history:"];
        foreach (array_slice($result['customers'] ?? [], 0, 3) as $customer) {
            $lines[] = "- Customer: " . ($customer['name'] ?? 'N/A') . " | Contact: " . ($customer['contact_number'] ?? 'N/A');
        }
        foreach (array_slice($result['recent_orders'] ?? [], 0, 5) as $order) {
            $lines[] = "- SO: " . ($order['order_number'] ?? 'N/A') .
                " | Status: " . ($order['status'] ?? 'N/A') .
                " | Total: " . ($order['total_amount'] ?? '0');
        }
        foreach (array_slice($result['top_products'] ?? [], 0, 5) as $product) {
            $lines[] = "- Top product: " . ($product['product_code'] ?? 'No code') .
                " | " . ($product['description'] ?? 'No description') .
                " | Qty: " . ($product['total_qty'] ?? '0');
        }

        return implode("\n", $lines);
    }

    protected function formatMovementReport(array $result): string
    {
        if (isset($result['error'])) {
            return "Movement report failed: {$result['error']}";
        }

        $title = ($result['mode'] ?? 'fast') === 'slow' ? 'Slow-moving products' : 'Fast-moving products';
        return $this->formatSimpleRows($title, $result['products'] ?? [], ['product_code', 'description', 'total_out', 'total_in', 'on_hand']);
    }

    protected function formatDataQualityReport(array $report): string
    {
        if (isset($report['error'])) {
            return "Data cleanup report failed: {$report['error']}";
        }

        return "Data cleanup report:\n\n" .
            "- Products missing product code: " . number_format((int) ($report['products']['missing_product_code'] ?? 0)) . "\n" .
            "- Products missing part number: " . number_format((int) ($report['products']['missing_part_number'] ?? 0)) . "\n" .
            "- Products missing price: " . number_format((int) ($report['products']['missing_price'] ?? 0)) . "\n" .
            "- Duplicate product codes found: " . count($report['products']['duplicate_product_codes'] ?? []) . "\n" .
            "- Duplicate part numbers found: " . count($report['products']['duplicate_part_numbers'] ?? []) . "\n" .
            "- Suppliers missing contact number: " . number_format((int) ($report['suppliers']['missing_contact_number'] ?? 0)) . "\n" .
            "- Suppliers missing email: " . number_format((int) ($report['suppliers']['missing_email'] ?? 0)) . "\n" .
            "- Customers missing contact number: " . number_format((int) ($report['customers']['missing_contact_number'] ?? 0)) . "\n" .
            "- Customers missing terms: " . number_format((int) ($report['customers']['missing_terms'] ?? 0)) . "\n" .
            "- Duplicate customer names found: " . count($report['customers']['duplicate_names'] ?? []);
    }

    protected function formatPriceInfo(array $result): string
    {
        if (isset($result['error'])) {
            return "Price check failed: {$result['error']}";
        }

        if (empty($result)) {
            return "No matching product price found.";
        }

        $lines = ['Price and margin results:'];
        foreach (array_slice($result, 0, 10) as $product) {
            $lines[] = "- " . trim((string) ($product['product_code'] ?? 'No code')) .
                " | " . trim((string) ($product['description'] ?? 'No description')) .
                " | Cost: " . number_format((float) ($product['cost'] ?? 0), 2) .
                " | Selling: " . number_format((float) ($product['selling_price'] ?? 0), 2) .
                " | Online: " . number_format((float) ($product['price_online'] ?? 0), 2) .
                " | Profit: " . number_format((float) ($product['estimated_profit'] ?? 0), 2) .
                " | Margin: " . number_format((float) ($product['estimated_margin_percent'] ?? 0), 2) . "%";
        }

        return implode("\n", $lines);
    }

    protected function formatDuplicateProducts(array $result): string
    {
        if (isset($result['error'])) {
            return "Duplicate product report failed: {$result['error']}";
        }

        return "Duplicate products:\n\n" .
            "- Duplicate product codes: " . count($result['duplicate_product_codes'] ?? []) . "\n" .
            "- Duplicate part numbers: " . count($result['duplicate_part_numbers'] ?? []) . "\n" .
            "- Duplicate descriptions: " . count($result['duplicate_descriptions'] ?? []);
    }

    protected function formatInventoryHealth(array $result): string
    {
        if (isset($result['error'])) {
            return "Inventory health failed: {$result['error']}";
        }

        return "Inventory health:\n\n" .
            "- Total products: " . number_format((int) ($result['total_products'] ?? 0)) . "\n" .
            "- Low stock: " . number_format((int) ($result['low_stock'] ?? 0)) . "\n" .
            "- Negative stock: " . number_format((int) ($result['negative_stock'] ?? 0)) . "\n" .
            "- Missing/zero price: " . number_format((int) ($result['missing_price'] ?? 0)) . "\n" .
            "- Missing image: " . number_format((int) ($result['missing_image'] ?? 0)) . "\n" .
            "- Missing application: " . number_format((int) ($result['missing_application'] ?? 0)) . "\n" .
            "- Missing part number: " . number_format((int) ($result['missing_part_number'] ?? 0));
    }

    protected function formatSalesSummary(array $result): string
    {
        if (isset($result['error'])) {
            return "Sales summary failed: {$result['error']}";
        }

        return ucfirst($result['period'] ?? 'sales') . " sales summary:\n\n" .
            "- Orders: " . number_format((int) ($result['order_count'] ?? 0)) . "\n" .
            "- Total amount: " . number_format((float) ($result['total_amount'] ?? 0), 2) . "\n" .
            "- Top customers:\n" . implode("\n", array_map(
                fn ($row) => "  - " . ($row['customer_name'] ?? 'N/A') . ": " . number_format((float) ($row['total_amount'] ?? 0), 2),
                array_slice($result['top_customers'] ?? [], 0, 5)
            ));
    }

    protected function formatSellabilityCheck(array $result): string
    {
        if (isset($result['error'])) {
            return "Sellability check failed: {$result['error']}";
        }

        if (empty($result)) {
            return "No matching product found for that sellability check.";
        }

        $lines = ['Sellability check:'];
        foreach (array_slice($result, 0, 5) as $row) {
            $lines[] = "- " . ($row['product_code'] ?? 'No code') .
                " | " . ($row['description'] ?? 'No description') .
                " | Requested: " . ($row['requested_qty'] ?? 0) .
                " | On hand: " . ($row['on_hand'] ?? 0) .
                " | Can sell: " . (($row['can_sell'] ?? false) ? 'Yes' : 'No') .
                " | Shortage: " . ($row['shortage_qty'] ?? 0);
        }

        return implode("\n", $lines);
    }

    protected function formatStockValueReport(array $result): string
    {
        if (isset($result['error'])) {
            return "Stock value report failed: {$result['error']}";
        }

        $lines = [
            "Stock value report:",
            "",
            "- Total stock value by cost: " . number_format((float) ($result['total_cost_value'] ?? 0), 2),
            "- Total stock value by selling price: " . number_format((float) ($result['total_selling_value'] ?? 0), 2),
            "- Products with positive stock: " . number_format((int) ($result['positive_stock_products'] ?? 0)),
            "- Highest-value products:",
        ];

        foreach (array_slice($result['top_products'] ?? [], 0, 10) as $row) {
            $lines[] = "  - " . ($row['product_code'] ?? 'No code') .
                " | " . ($row['description'] ?? 'No description') .
                " | Qty: " . ($row['on_hand'] ?? 0) .
                " | Value: " . number_format((float) ($row['stock_value'] ?? 0), 2);
        }

        return implode("\n", $lines);
    }

    protected function formatTodayActivity(array $result): string
    {
        if (isset($result['error'])) {
            return "Today activity failed: {$result['error']}";
        }

        return "Today activity:\n\n" .
            "- Sales orders today: " . number_format((int) ($result['sales_orders_today'] ?? 0)) . "\n" .
            "- Sales total today: " . number_format((float) ($result['sales_total_today'] ?? 0), 2) . "\n" .
            "- Purchase orders today: " . number_format((int) ($result['purchase_orders_today'] ?? 0)) . "\n" .
            "- Purchase total today: " . number_format((float) ($result['purchase_total_today'] ?? 0), 2) . "\n" .
            "- Rush sales notes today: " . number_format((int) ($result['rush_sales_notes_today'] ?? 0)) . "\n" .
            "- Audit entries today: " . number_format((int) ($result['audit_entries_today'] ?? 0)) . "\n" .
            "- Current low-stock products: " . number_format((int) ($result['current_low_stock_products'] ?? 0));
    }

    protected function formatBackupStatus(array $result): string
    {
        if (isset($result['error'])) {
            return "Backup status failed: {$result['error']}";
        }

        if (empty($result['latest'])) {
            return "Backup status: no backup/data-up files were found in the checked folders.";
        }

        $latest = $result['latest'];
        return "Backup status:\n\n" .
            "- Latest file: " . ($latest['name'] ?? 'N/A') . "\n" .
            "- Folder: " . ($latest['folder'] ?? 'N/A') . "\n" .
            "- Modified: " . ($latest['modified_at'] ?? 'N/A') . "\n" .
            "- Size: " . number_format((float) ($latest['size_mb'] ?? 0), 2) . " MB\n" .
            "- Files found: " . number_format((int) ($result['count'] ?? 0));
    }

    protected function formatRestoreGuide(string $query): string
    {
        $navigation = $this->getPageNavigation('archive', $query);

        return "Restore guide for {$query}:\n\n" .
            "1. Open Archived Records.\n" .
            "2. Search the record name/code.\n" .
            "3. Confirm it is the correct archived item.\n" .
            "4. Use Restore from the archive page.\n\n" .
            $navigation['redirect'];
    }

    protected function formatWorkflowHelp(string $workflow): string
    {
        $helps = [
            'sales_order' => [
                'title' => 'Sales order help',
                'steps' => ['Open Sales Order.', 'Select or search the customer.', 'Add products and quantities.', 'Check stock before proceeding.', 'Review totals and finalize.'],
                'page' => 'sales order',
            ],
            'purchase_order' => [
                'title' => 'Purchase order help',
                'steps' => ['Open Purchase Order.', 'Select the supplier.', 'Add products, quantity, and unit price.', 'Review expected delivery and totals.', 'Save or receive when ready.'],
                'page' => 'purchase order',
            ],
            'inventory_adjustment' => [
                'title' => 'Inventory adjustment help',
                'steps' => ['Open Inventory Adjustment.', 'Search the product.', 'Enter the corrected physical quantity.', 'Add a clear adjustment reason.', 'Save to record the stock ledger change.'],
                'page' => 'inventory adjustment',
            ],
        ];

        $help = $helps[$workflow] ?? $helps['sales_order'];
        $navigation = $this->getPageNavigation($help['page'], '');

        return $help['title'] . ":\n\n" .
            implode("\n", array_map(fn ($step, $index) => ($index + 1) . ". {$step}", $help['steps'], array_keys($help['steps']))) .
            "\n\n" . $navigation['redirect'];
    }

    protected function formatSimpleRows(string $title, array $rows, array $columns): string
    {
        if (isset($rows['error'])) {
            return "{$title} failed: {$rows['error']}";
        }

        if (empty($rows)) {
            return "{$title}: no records found.";
        }

        $lines = [$title . ':'];
        foreach (array_slice($rows, 0, 10) as $row) {
            $parts = [];
            foreach ($columns as $column) {
                if (array_key_exists($column, $row) && trim((string) $row[$column]) !== '') {
                    $parts[] = "{$column}: " . trim((string) $row[$column]);
                }
            }
            $lines[] = '- ' . implode(' | ', $parts);
        }

        return implode("\n", $lines);
    }

    public function checkStockAndReorder(string $query = '', int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($query, $limit) {
            if (!$this->hasTable('masterlist', 'products')) {
                return ['error' => 'Products table is not available.'];
            }

            $products = DB::connection('masterlist')->table('products')
                ->when(trim($query) !== '', function ($builder) use ($query) {
                    $builder->where(function ($q) use ($query) {
                        $this->applyLikeSearch($q, [
                            'product_code',
                            'part_number',
                            'description',
                            'category',
                            'application',
                            'specification',
                            'position',
                        ], $query);
                    });
                })
                ->when(trim($query) === '', function ($builder) {
                    $builder->where(function ($q) {
                        if ($this->hasColumn('masterlist', 'products', 'Re_order_level')) {
                            $q->where(function ($nested) {
                                $nested->whereNotNull('Re_order_level')
                                    ->whereColumn('on_hand', '<=', 'Re_order_level');
                            })->orWhere('on_hand', '<', 20);
                        } else {
                            $q->where('on_hand', '<', 20);
                        }
                    });
                })
                ->orderBy('on_hand')
                ->limit($limit)
                ->get($this->existingColumns('masterlist', 'products', [
                    'id',
                    'product_code',
                    'part_number',
                    'category',
                    'description',
                    'application',
                    'on_hand',
                    'actual_qty',
                    'Re_order_level',
                    'selling_price',
                    'cost',
                    'status',
                ]))
                ->map(function ($product) {
                    $row = (array) $product;
                    $onHand = (int) ($row['on_hand'] ?? 0);
                    $reorderLevel = $row['Re_order_level'] ?? null;
                    $targetLevel = $reorderLevel !== null ? max((int) $reorderLevel, 0) : 20;
                    $row['stock_status'] = $onHand <= 0
                        ? 'Out of stock'
                        : ($onHand <= $targetLevel ? 'Low stock' : 'Available');
                    $row['suggested_reorder_qty'] = max($targetLevel - $onHand, 0);

                    return $row;
                })
                ->values()
                ->all();

            return [
                'query' => trim($query),
                'count' => count($products),
                'products' => $products,
            ];
        });
    }

    public function findProductAlternatives(string $query, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($query, $limit) {
            $matches = $this->searchLocalProducts($query);
            $target = $matches[0] ?? null;

            if (!$target || isset($target['error'])) {
                return [
                    'target' => null,
                    'alternatives' => [],
                    'message' => 'No matching product was found for alternatives.',
                ];
            }

            $builder = DB::connection('masterlist')->table('products')
                ->where(function ($q) use ($target) {
                    foreach (['description', 'application', 'category', 'specification'] as $column) {
                        $value = trim((string) ($target[$column] ?? ''));
                        if ($value !== '' && $this->hasColumn('masterlist', 'products', $column)) {
                            $q->orWhere($column, 'LIKE', '%' . $value . '%');
                        }
                    }
                });

            if (!empty($target['product_code'])) {
                $builder->where('product_code', '!=', $target['product_code']);
            }

            $alternatives = $builder
                ->orderByDesc('on_hand')
                ->limit($limit)
                ->get($this->existingColumns('masterlist', 'products', [
                    'product_code',
                    'part_number',
                    'category',
                    'specification',
                    'description',
                    'application',
                    'position',
                    'on_hand',
                    'selling_price',
                    'status',
                ]))
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();

            return [
                'target' => $target,
                'alternatives' => $alternatives,
            ];
        });
    }

    public function searchSuppliers(string $query, int $limit = 10): array
    {
        return $this->searchMasterlistTable('suppliers', $query, $limit, [
            'id',
            'supplier_code',
            'name',
            'contact_person',
            'contact_number',
            'email',
            'address',
            'payment_terms',
            'status',
            'pricing_remarks',
        ], [
            'supplier_code',
            'name',
            'contact_person',
            'contact_number',
            'email',
            'address',
            'payment_terms',
            'status',
            'pricing_remarks',
        ]);
    }

    public function searchCustomers(string $query, int $limit = 10): array
    {
        return $this->searchMasterlistTable('customers', $query, $limit, [
            'id',
            'name',
            'contact_person',
            'contact_number',
            'address',
            'tin',
            'pricing_remarks',
            'terms',
            'customer_type_id',
        ], [
            'name',
            'contact_person',
            'contact_number',
            'address',
            'tin',
            'pricing_remarks',
            'terms',
        ]);
    }

    public function checkSellability(string $query, int $requestedQty, int $limit = 5): array
    {
        $limit = $this->safeLimit($limit);
        $requestedQty = max(1, $requestedQty);

        return $this->guardedDataResponse(function () use ($query, $requestedQty, $limit) {
            return $this->findProductRows($query, $limit)
                ->map(function ($product) use ($requestedQty) {
                    $row = (array) $product;
                    $onHand = (int) ($row['on_hand'] ?? 0);
                    $row['requested_qty'] = $requestedQty;
                    $row['can_sell'] = $onHand >= $requestedQty;
                    $row['shortage_qty'] = max($requestedQty - $onHand, 0);

                    return $row;
                })
                ->values()
                ->all();
        });
    }

    public function findProductsByColumn(string $column, string $query, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);
        $allowedColumns = ['part_number', 'application', 'category', 'description', 'product_code'];

        return $this->guardedDataResponse(function () use ($column, $query, $limit, $allowedColumns) {
            if (!$this->hasTable('masterlist', 'products')) {
                return ['error' => 'Products table is not available.'];
            }

            if (!in_array($column, $allowedColumns, true) || !$this->hasColumn('masterlist', 'products', $column)) {
                return ['error' => 'Search column is not available.'];
            }

            return DB::connection('masterlist')->table('products')
                ->where($column, 'LIKE', "%{$query}%")
                ->orderByDesc('on_hand')
                ->limit($limit)
                ->get($this->existingColumns('masterlist', 'products', $this->productColumns()))
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }

    public function getProductPriceInfo(string $query, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($query, $limit) {
            return $this->findProductRows($query, $limit)
                ->map(function ($product) {
                    $row = (array) $product;
                    $selling = (float) ($row['selling_price'] ?? 0);
                    $cost = (float) ($row['cost'] ?? 0);
                    $row['estimated_profit'] = $selling - $cost;
                    $row['estimated_margin_percent'] = $selling > 0 ? (($selling - $cost) / $selling) * 100 : 0;

                    return $row;
                })
                ->values()
                ->all();
        });
    }

    public function getProductsByIssue(string $issue, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($issue, $limit) {
            if (!$this->hasTable('masterlist', 'products')) {
                return ['error' => 'Products table is not available.'];
            }

            $builder = DB::connection('masterlist')->table('products');
            if ($issue === 'no_price') {
                $builder->where(function ($q) {
                    $q->whereNull('selling_price')->orWhere('selling_price', '<=', 0);
                });
            } elseif ($issue === 'negative_stock') {
                $builder->where('on_hand', '<', 0);
            } elseif ($issue === 'new_products') {
                $builder->where('status', 'LIKE', '%New%');
            } elseif ($issue === 'without_image') {
                $imageColumn = $this->hasColumn('masterlist', 'products', 'Product_Picture') ? 'Product_Picture' : null;
                if ($imageColumn) {
                    $builder->where(function ($q) use ($imageColumn) {
                        $q->whereNull($imageColumn)->orWhere($imageColumn, '');
                    });
                } else {
                    return [];
                }
            } elseif ($issue === 'missing_application') {
                $builder->where(function ($q) {
                    $q->whereNull('application')->orWhere('application', '');
                });
            } elseif ($issue === 'missing_part_number') {
                $builder->where(function ($q) {
                    $q->whereNull('part_number')->orWhere('part_number', '');
                });
            }

            return $builder
                ->orderByDesc($this->hasColumn('masterlist', 'products', 'created_at') ? 'created_at' : 'id')
                ->limit($limit)
                ->get($this->existingColumns('masterlist', 'products', $this->productColumns()))
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }

    public function getDuplicateProducts(int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return [
            'duplicate_product_codes' => $this->duplicateValues('masterlist', 'products', 'product_code', $limit),
            'duplicate_part_numbers' => $this->duplicateValues('masterlist', 'products', 'part_number', $limit),
            'duplicate_descriptions' => $this->duplicateValues('masterlist', 'products', 'description', $limit),
        ];
    }

    public function getProductLedger(string $query, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($query, $limit) {
            if (!$this->hasTable('ledger', 'product_ledgers')) {
                return ['error' => 'Product ledger table is not available.'];
            }

            $products = $this->findProductRows($query, 10);
            $ids = $products->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();

            $builder = DB::connection('ledger')->table('product_ledgers as pl')
                ->select(
                    'pl.product_id',
                    'pl.date',
                    'pl.transaction_type',
                    'pl.transaction_number',
                    'pl.reference_number',
                    'pl.entity_name',
                    'pl.quantity_in',
                    'pl.quantity_out',
                    'pl.balance_stock'
                );

            if (!empty($ids)) {
                $builder->whereIn('pl.product_id', $ids);
            } else {
                // Product code/description searching belongs to masterlist.  If
                // no product matched there, retain the old fallback of finding
                // ledger rows by transaction number.
                $builder->where('pl.transaction_number', 'LIKE', "%{$query}%");
            }

            $rows = $builder
                ->orderByDesc('pl.date')
                ->orderByDesc('pl.id')
                ->limit($limit)
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            $productMap = DB::connection('masterlist')->table('products')
                ->whereIn('id', $rows->pluck('product_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all())
                ->get(['id', 'product_code', 'description'])
                ->keyBy('id');

            return $rows->map(function ($row) use ($productMap) {
                $product = $productMap->get((int) $row->product_id);
                return [
                    'date' => $row->date,
                    'transaction_type' => $row->transaction_type,
                    'transaction_number' => $row->transaction_number,
                    'reference_number' => $row->reference_number,
                    'entity_name' => $row->entity_name,
                    'quantity_in' => $row->quantity_in,
                    'quantity_out' => $row->quantity_out,
                    'balance_stock' => $row->balance_stock,
                    'product_code' => $product->product_code ?? null,
                    'description' => $product->description ?? null,
                ];
            })->values()->all();
        });
    }

    public function getProductSalesActivity(string $query, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($query, $limit) {
            if (!$this->hasTable('sales', 'sales_order_items') || !$this->hasTable('sales', 'sales_orders')) {
                return ['error' => 'Sales order tables are not available.'];
            }

            $codes = $this->findProductRows($query, 10)->pluck('product_code')->filter()->map(fn ($code) => trim((string) $code))->values()->all();

            return DB::connection('sales')->table('sales_order_items as soi')
                ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
                ->where(function ($q) use ($query, $codes) {
                    if (!empty($codes)) {
                        $q->orWhereIn('soi.product_code', $codes);
                    }
                    $q->orWhere('soi.product_code', 'LIKE', "%{$query}%")
                        ->orWhere('soi.description', 'LIKE', "%{$query}%");
                })
                ->select('so.order_number', 'so.customer_name', 'so.status', 'so.created_at', 'soi.product_code', 'soi.description', 'soi.quantity', 'soi.unit_price', 'soi.subtotal')
                ->orderByDesc('so.created_at')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }

    public function getProductPurchaseActivity(string $query, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($query, $limit) {
            if (!$this->hasTable('purchase', 'purchase_order_items') || !$this->hasTable('purchase', 'purchase_orders')) {
                return ['error' => 'Purchase order tables are not available.'];
            }

            $codes = $this->findProductRows($query, 10)->pluck('product_code')->filter()->map(fn ($code) => trim((string) $code))->values()->all();
            $rows = DB::connection('purchase')->table('purchase_order_items as poi')
                ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                ->where(function ($q) use ($query, $codes) {
                    if (!empty($codes)) {
                        $q->orWhereIn('poi.product_code', $codes);
                    }
                    $q->orWhere('poi.product_code', 'LIKE', "%{$query}%")
                        ->orWhere('poi.description', 'LIKE', "%{$query}%");
                })
                ->select('po.po_number', 'po.supplier_id', 'po.status', 'po.created_at', 'poi.product_code', 'poi.description', 'poi.quantity', 'poi.unit_price', 'poi.subtotal')
                ->orderByDesc('po.created_at')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();

            return $this->attachSupplierNames($rows);
        });
    }

    public function getSupplierPriceHistory(string $query, int $limit = 10): array
    {
        return $this->getProductPurchaseActivity($query, $limit);
    }

    public function getCheapestSuppliersForProduct(string $query, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($query, $limit) {
            if (!$this->hasTable('purchase', 'purchase_order_items') || !$this->hasTable('purchase', 'purchase_orders')) {
                return ['error' => 'Purchase order tables are not available.'];
            }

            $products = $this->findProductRows($query, 10);
            $codes = $products->pluck('product_code')
                ->filter()
                ->map(fn ($code) => trim((string) $code))
                ->values()
                ->all();

            $rows = DB::connection('purchase')->table('purchase_order_items as poi')
                ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                ->where(function ($q) use ($query, $codes) {
                    if (!empty($codes)) {
                        $q->orWhereIn('poi.product_code', $codes);
                    }
                    $q->orWhere('poi.product_code', 'LIKE', "%{$query}%")
                        ->orWhere('poi.description', 'LIKE', "%{$query}%");
                })
                ->where('poi.unit_price', '>', 0)
                ->select(
                    'po.supplier_id',
                    DB::raw('MIN(poi.unit_price) as lowest_unit_price'),
                    DB::raw('AVG(poi.unit_price) as average_unit_price'),
                    DB::raw('COUNT(*) as purchase_count'),
                    DB::raw('MAX(po.created_at) as last_purchase_date')
                )
                ->groupBy('po.supplier_id')
                ->orderBy('lowest_unit_price')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();

            $rows = $this->attachSupplierNames($rows);
            $rows = collect($rows)
                ->map(function ($row) use ($query, $codes) {
                    $latest = DB::connection('purchase')->table('purchase_order_items as poi')
                        ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                        ->where('po.supplier_id', $row['supplier_id'] ?? null)
                        ->where(function ($q) use ($query, $codes) {
                            if (!empty($codes)) {
                                $q->orWhereIn('poi.product_code', $codes);
                            }
                            $q->orWhere('poi.product_code', 'LIKE', "%{$query}%")
                                ->orWhere('poi.description', 'LIKE', "%{$query}%");
                        })
                        ->where('poi.unit_price', '>', 0)
                        ->orderByDesc('po.created_at')
                        ->select('po.po_number', 'poi.unit_price')
                        ->first();

                    $row['last_po_number'] = $latest->po_number ?? null;
                    $row['last_unit_price'] = $latest->unit_price ?? null;

                    return $row;
                })
                ->values()
                ->all();

            return [
                'query' => $query,
                'matched_product' => $products->first() ? (array) $products->first() : null,
                'suppliers' => $rows,
            ];
        });
    }

    public function getCustomerLastOrder(string $query): array
    {
        return $this->guardedDataResponse(function () use ($query) {
            if (!$this->hasTable('sales', 'sales_orders')) {
                return ['error' => 'Sales orders table is not available.'];
            }

            $customers = $this->searchCustomers($query, 5);
            $customerIds = collect($customers)->pluck('id')->filter()->values()->all();
            $customerNames = collect($customers)->pluck('name')->filter()->values()->all();

            return DB::connection('sales')->table('sales_orders')
                ->where(function ($q) use ($query, $customerIds, $customerNames) {
                    if (!empty($customerIds) && $this->hasColumn('sales', 'sales_orders', 'customer_id')) {
                        $q->orWhereIn('customer_id', $customerIds);
                    }
                    foreach ($customerNames as $name) {
                        $q->orWhere('customer_name', 'LIKE', '%' . $name . '%');
                    }
                    $q->orWhere('customer_name', 'LIKE', '%' . $query . '%');
                })
                ->orderByDesc($this->hasColumn('sales', 'sales_orders', 'created_at') ? 'created_at' : 'id')
                ->limit(1)
                ->get($this->existingColumns('sales', 'sales_orders', ['id', 'order_number', 'customer_name', 'total_amount', 'status', 'created_at']))
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }

    public function getDeadStockProducts(int $limit = 10, int $days = 180): array
    {
        $limit = $this->safeLimit($limit);
        $days = max(30, min($days, 3650));

        return $this->guardedDataResponse(function () use ($limit, $days) {
            if (!$this->hasTable('masterlist', 'products')) {
                return ['error' => 'Products table is not available.'];
            }

            $recentProductIds = $this->hasTable('ledger', 'product_ledgers')
                ? DB::connection('ledger')->table('product_ledgers')
                    ->where('date', '>=', now()->subDays($days)->toDateString())
                    ->pluck('product_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all()
                : [];

            $builder = DB::connection('masterlist')->table('products as p')
                ->where('p.on_hand', '>', 0);

            if (!empty($recentProductIds)) {
                $builder->whereNotIn('p.id', $recentProductIds);
            }

            $rows = $builder
                ->select('p.id', 'p.product_code', 'p.description', 'p.on_hand', 'p.cost', 'p.selling_price')
                ->orderByDesc(DB::raw('COALESCE(p.on_hand, 0) * COALESCE(NULLIF(p.cost, 0), p.selling_price, 0)'))
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();

            $lastMovements = [];
            if ($this->hasTable('ledger', 'product_ledgers') && !empty($rows)) {
                $ids = collect($rows)->pluck('id')->filter()->values()->all();
                $lastMovements = DB::connection('ledger')->table('product_ledgers')
                    ->whereIn('product_id', $ids)
                    ->select('product_id', DB::raw('MAX(date) as last_movement_at'))
                    ->groupBy('product_id')
                    ->pluck('last_movement_at', 'product_id')
                    ->all();
            }

            return collect($rows)
                ->map(function ($row) use ($lastMovements) {
                    $unitValue = (float) ($row['cost'] ?? 0);
                    if ($unitValue <= 0) {
                        $unitValue = (float) ($row['selling_price'] ?? 0);
                    }
                    $row['stock_value'] = (int) ($row['on_hand'] ?? 0) * $unitValue;
                    $row['last_movement_at'] = $lastMovements[$row['id'] ?? null] ?? 'No ledger movement';

                    return $row;
                })
                ->values()
                ->all();
        });
    }

    public function getStockValueReport(int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($limit) {
            if (!$this->hasTable('masterlist', 'products')) {
                return ['error' => 'Products table is not available.'];
            }

            $base = DB::connection('masterlist')->table('products')->where('on_hand', '>', 0);
            $topRows = (clone $base)
                ->select(
                    'product_code',
                    'description',
                    'on_hand',
                    'cost',
                    'selling_price',
                    DB::raw('COALESCE(on_hand, 0) * COALESCE(NULLIF(cost, 0), selling_price, 0) as stock_value')
                )
                ->orderByDesc('stock_value')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();

            return [
                'positive_stock_products' => (clone $base)->count(),
                'total_cost_value' => (float) (clone $base)->sum(DB::raw('COALESCE(on_hand, 0) * COALESCE(cost, 0)')),
                'total_selling_value' => (float) (clone $base)->sum(DB::raw('COALESCE(on_hand, 0) * COALESCE(selling_price, 0)')),
                'top_products' => $topRows,
            ];
        });
    }

    public function getCustomerRanking(string $mode = 'top', int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);
        $mode = strtolower($mode) === 'inactive' ? 'inactive' : 'top';

        return $this->guardedDataResponse(function () use ($mode, $limit) {
            if ($mode === 'inactive') {
                if (!$this->hasTable('masterlist', 'customers')) {
                    return ['error' => 'Customers table is not available.'];
                }

                $activeIds = $this->hasTable('sales', 'sales_orders')
                    ? DB::connection('sales')->table('sales_orders')->where('created_at', '>=', now()->subDays(365))->pluck('customer_id')->filter()->unique()->values()->all()
                    : [];

                return DB::connection('masterlist')->table('customers')
                    ->when(!empty($activeIds), fn ($builder) => $builder->whereNotIn('id', $activeIds))
                    ->limit($limit)
                    ->get($this->existingColumns('masterlist', 'customers', ['id', 'name', 'contact_person', 'contact_number', 'terms']))
                    ->map(fn ($row) => (array) $row)
                    ->values()
                    ->all();
            }

            if (!$this->hasTable('sales', 'sales_orders')) {
                return ['error' => 'Sales orders table is not available.'];
            }

            return DB::connection('sales')->table('sales_orders')
                ->select('customer_id', 'customer_name', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(COALESCE(total_amount, 0)) as total_amount'))
                ->groupBy('customer_id', 'customer_name')
                ->orderByDesc('total_amount')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }

    public function getSupplierRanking(string $mode = 'top', int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);
        $mode = strtolower($mode) === 'inactive' ? 'inactive' : 'top';

        return $this->guardedDataResponse(function () use ($mode, $limit) {
            if ($mode === 'inactive') {
                if (!$this->hasTable('masterlist', 'suppliers')) {
                    return ['error' => 'Suppliers table is not available.'];
                }

                $activeIds = $this->hasTable('purchase', 'purchase_orders')
                    ? DB::connection('purchase')->table('purchase_orders')->where('created_at', '>=', now()->subDays(365))->pluck('supplier_id')->filter()->unique()->values()->all()
                    : [];

                return DB::connection('masterlist')->table('suppliers')
                    ->when(!empty($activeIds), fn ($builder) => $builder->whereNotIn('id', $activeIds))
                    ->limit($limit)
                    ->get($this->existingColumns('masterlist', 'suppliers', ['id', 'supplier_code', 'name', 'contact_number', 'status']))
                    ->map(fn ($row) => (array) $row)
                    ->values()
                    ->all();
            }

            if (!$this->hasTable('purchase', 'purchase_orders')) {
                return ['error' => 'Purchase orders table is not available.'];
            }

            $rows = DB::connection('purchase')->table('purchase_orders')
                ->select('supplier_id', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(COALESCE(total_amount, 0)) as total_amount'))
                ->groupBy('supplier_id')
                ->orderByDesc('total_amount')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();

            return $this->attachSupplierNames($rows);
        });
    }

    public function getPendingOrders(string $type, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($type, $limit) {
            if ($type === 'purchase') {
                if (!$this->hasTable('purchase', 'purchase_orders')) {
                    return ['error' => 'Purchase orders table is not available.'];
                }

                return DB::connection('purchase')->table('purchase_orders')
                    ->whereNotIn('status', ['Received', 'Closed', 'Cancelled', 'Canceled'])
                    ->orderByDesc($this->hasColumn('purchase', 'purchase_orders', 'created_at') ? 'created_at' : 'id')
                    ->limit($limit)
                    ->get($this->existingColumns('purchase', 'purchase_orders', ['id', 'po_number', 'supplier_id', 'total_amount', 'status', 'created_at']))
                    ->map(fn ($row) => (array) $row)
                    ->values()
                    ->all();
            }

            if (!$this->hasTable('sales', 'sales_orders')) {
                return ['error' => 'Sales orders table is not available.'];
            }

            return DB::connection('sales')->table('sales_orders')
                ->whereNotIn('status', ['Closed', 'Cancelled', 'Canceled', 'Completed'])
                ->orderByDesc($this->hasColumn('sales', 'sales_orders', 'created_at') ? 'created_at' : 'id')
                ->limit($limit)
                ->get($this->existingColumns('sales', 'sales_orders', ['id', 'order_number', 'customer_name', 'total_amount', 'status', 'created_at']))
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }

    public function getRushSalesNotes(int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($limit) {
            if (!$this->hasTable('sales', 'sales_notes')) {
                return ['error' => 'Sales notes table is not available.'];
            }

            return DB::connection('sales')->table('sales_notes')
                ->where('is_rush', 1)
                ->orderByDesc($this->hasColumn('sales', 'sales_notes', 'created_at') ? 'created_at' : 'id')
                ->limit($limit)
                ->get($this->existingColumns('sales', 'sales_notes', ['id', 'sales_number', 'customer_name', 'order_date', 'net_total', 'status']))
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }

    public function getOverduePayments(int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($limit) {
            if (!$this->hasTable('accounting', 'process_payment_invoices') || !$this->hasTable('accounting', 'process_payments')) {
                return ['error' => 'Payment tables are not available.'];
            }

            return DB::connection('accounting')->table('process_payment_invoices as ppi')
                ->join('process_payments as pp', 'pp.id', '=', 'ppi.process_payment_id')
                ->where(function ($q) {
                    $q->whereColumn('ppi.due_amount', '>', 'ppi.paid_amount')
                        ->orWhere('ppi.payment_status', '!=', 'Paid');
                })
                ->select('pp.customer_name', 'ppi.invoice_no', 'ppi.invoice_amount', 'ppi.due_amount', 'ppi.paid_amount', 'ppi.payment_status')
                ->orderByDesc('ppi.due_amount')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }

    public function getUnpaidInvoices(string $customerQuery, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($customerQuery, $limit) {
            $rows = $this->getOverduePayments(25);
            if (isset($rows['error'])) {
                return $rows;
            }

            return collect($rows)
                ->filter(fn ($row) => str_contains(strtolower((string) ($row['customer_name'] ?? '')), strtolower($customerQuery)))
                ->take($limit)
                ->values()
                ->all();
        });
    }

    public function getCustomerBalance(string $customerQuery, int $limit = 10): array
    {
        return $this->getUnpaidInvoices($customerQuery, $limit);
    }

    public function getSupplierBalance(string $supplierQuery, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($supplierQuery, $limit) {
            if (!$this->hasTable('accounting', 'payable_cheque_voucher_invoices') || !$this->hasTable('accounting', 'payable_cheque_vouchers')) {
                return ['error' => 'Payable voucher tables are not available.'];
            }

            return DB::connection('accounting')->table('payable_cheque_voucher_invoices as pcvi')
                ->join('payable_cheque_vouchers as pcv', 'pcv.id', '=', 'pcvi.payable_cheque_voucher_id')
                ->where('pcv.supplier_name', 'LIKE', "%{$supplierQuery}%")
                ->where(function ($q) {
                    $q->whereColumn('pcvi.amount_due', '>', 'pcvi.amount_paid')
                        ->orWhere('pcvi.payment_status', '!=', 'Paid');
                })
                ->select('pcv.supplier_name', 'pcv.voucher_no', 'pcvi.invoice_no', 'pcvi.invoice_amount', 'pcvi.amount_due', 'pcvi.amount_paid', 'pcvi.payment_status')
                ->orderByDesc('pcvi.amount_due')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }

    public function getInventoryHealth(): array
    {
        return $this->guardedDataResponse(function () {
            if (!$this->hasTable('masterlist', 'products')) {
                return ['error' => 'Products table is not available.'];
            }

            $products = DB::connection('masterlist')->table('products');
            $imageColumn = $this->hasColumn('masterlist', 'products', 'Product_Picture') ? 'Product_Picture' : null;

            return [
                'total_products' => (clone $products)->count(),
                'low_stock' => (clone $products)->where(function ($q) {
                    if ($this->hasColumn('masterlist', 'products', 'Re_order_level')) {
                        $q->where(function ($nested) {
                            $nested->whereNotNull('Re_order_level')
                                ->whereColumn('on_hand', '<=', 'Re_order_level');
                        })->orWhere('on_hand', '<', 20);
                    } else {
                        $q->where('on_hand', '<', 20);
                    }
                })->count(),
                'negative_stock' => (clone $products)->where('on_hand', '<', 0)->count(),
                'missing_price' => (clone $products)->where(function ($q) {
                    $q->whereNull('selling_price')->orWhere('selling_price', '<=', 0);
                })->count(),
                'missing_image' => $imageColumn ? (clone $products)->where(function ($q) use ($imageColumn) {
                    $q->whereNull($imageColumn)->orWhere($imageColumn, '');
                })->count() : 0,
                'missing_application' => (clone $products)->where(function ($q) {
                    $q->whereNull('application')->orWhere('application', '');
                })->count(),
                'missing_part_number' => (clone $products)->where(function ($q) {
                    $q->whereNull('part_number')->orWhere('part_number', '');
                })->count(),
            ];
        });
    }

    public function getSalesSummary(string $period = 'daily'): array
    {
        $period = strtolower($period) === 'monthly' ? 'monthly' : 'daily';

        return $this->guardedDataResponse(function () use ($period) {
            if (!$this->hasTable('sales', 'sales_orders')) {
                return ['error' => 'Sales orders table is not available.'];
            }

            $start = $period === 'monthly' ? now()->startOfMonth() : now()->startOfDay();
            $end = $period === 'monthly' ? now()->endOfMonth() : now()->endOfDay();
            $base = DB::connection('sales')->table('sales_orders')
                ->whereBetween('created_at', [$start, $end]);

            return [
                'period' => $period,
                'order_count' => (clone $base)->count(),
                'total_amount' => (float) (clone $base)->sum('total_amount'),
                'top_customers' => (clone $base)
                    ->select('customer_name', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(COALESCE(total_amount, 0)) as total_amount'))
                    ->groupBy('customer_name')
                    ->orderByDesc('total_amount')
                    ->limit(5)
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->values()
                    ->all(),
            ];
        });
    }

    public function getTodayActivity(): array
    {
        return $this->guardedDataResponse(function () {
            $start = now()->startOfDay();
            $end = now()->endOfDay();

            return [
                'sales_orders_today' => $this->hasTable('sales', 'sales_orders')
                    ? DB::connection('sales')->table('sales_orders')->whereBetween('created_at', [$start, $end])->count()
                    : 0,
                'sales_total_today' => $this->hasTable('sales', 'sales_orders')
                    ? (float) DB::connection('sales')->table('sales_orders')->whereBetween('created_at', [$start, $end])->sum('total_amount')
                    : 0,
                'purchase_orders_today' => $this->hasTable('purchase', 'purchase_orders')
                    ? DB::connection('purchase')->table('purchase_orders')->whereBetween('created_at', [$start, $end])->count()
                    : 0,
                'purchase_total_today' => $this->hasTable('purchase', 'purchase_orders')
                    ? (float) DB::connection('purchase')->table('purchase_orders')->whereBetween('created_at', [$start, $end])->sum('total_amount')
                    : 0,
                'rush_sales_notes_today' => $this->hasTable('sales', 'sales_notes')
                    ? DB::connection('sales')->table('sales_notes')->where('is_rush', 1)->whereBetween('created_at', [$start, $end])->count()
                    : 0,
                'audit_entries_today' => $this->hasTable('ledger', 'audit_trails')
                    ? DB::connection('ledger')->table('audit_trails')->whereBetween('created_at', [$start, $end])->count()
                    : 0,
                'current_low_stock_products' => $this->getInventoryHealth()['low_stock'] ?? 0,
            ];
        });
    }

    public function getBackupStatus(): array
    {
        return $this->guardedDataResponse(function () {
            $candidateFolders = [
                storage_path('app'),
                storage_path('app/backups'),
                storage_path('app/data-ups'),
                storage_path('app/private'),
                base_path('storage'),
                base_path(),
            ];

            $files = [];
            foreach ($candidateFolders as $folder) {
                if (!is_dir($folder)) {
                    continue;
                }

                foreach (glob($folder . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                    if (!is_file($path)) {
                        continue;
                    }

                    $name = basename($path);
                    $lower = strtolower($name);
                    $looksLikeBackup = str_contains($lower, 'backup')
                        || str_contains($lower, 'data-up')
                        || str_contains($lower, 'dataup')
                        || str_contains($lower, 'masterlist')
                        || str_ends_with($lower, '.sql')
                        || str_ends_with($lower, '.zip');

                    if (!$looksLikeBackup) {
                        continue;
                    }

                    $modifiedAt = filemtime($path) ?: time();
                    $files[] = [
                        'name' => $name,
                        'folder' => dirname($path),
                        'path' => $path,
                        'modified_ts' => $modifiedAt,
                        'modified_at' => date('Y-m-d H:i:s', $modifiedAt),
                        'size_mb' => (filesize($path) ?: 0) / 1048576,
                    ];
                }
            }

            usort($files, fn ($a, $b) => ($b['modified_ts'] ?? 0) <=> ($a['modified_ts'] ?? 0));

            return [
                'count' => count($files),
                'latest' => $files[0] ?? null,
                'files' => array_slice($files, 0, 10),
            ];
        });
    }

    public function getSupplierReorderSuggestion(string $supplierQuery, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($supplierQuery, $limit) {
            $suppliers = $this->searchSuppliers($supplierQuery, 5);
            $supplierIds = collect($suppliers)->pluck('id')->filter()->values()->all();
            $codes = [];

            if (!empty($supplierIds) && $this->hasTable('purchase', 'purchase_order_items') && $this->hasTable('purchase', 'purchase_orders')) {
                $codes = DB::connection('purchase')->table('purchase_order_items as poi')
                    ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                    ->whereIn('po.supplier_id', $supplierIds)
                    ->pluck('poi.product_code')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }

            $builder = DB::connection('masterlist')->table('products')
                ->where(function ($q) {
                    if ($this->hasColumn('masterlist', 'products', 'Re_order_level')) {
                        $q->where(function ($nested) {
                            $nested->whereNotNull('Re_order_level')
                                ->whereColumn('on_hand', '<=', 'Re_order_level');
                        })->orWhere('on_hand', '<', 20);
                    } else {
                        $q->where('on_hand', '<', 20);
                    }
                });

            if (!empty($codes)) {
                $builder->whereIn('product_code', $codes);
            }

            return $builder
                ->orderBy('on_hand')
                ->limit($limit)
                ->get($this->existingColumns('masterlist', 'products', $this->productColumns()))
                ->map(function ($product) {
                    $row = (array) $product;
                    $onHand = (int) ($row['on_hand'] ?? 0);
                    $reorderLevel = max((int) ($row['Re_order_level'] ?? 20), 0);
                    $row['suggested_reorder_qty'] = max($reorderLevel - $onHand, 0);

                    return $row;
                })
                ->values()
                ->all();
        });
    }

    public function getCustomerPurchaseHistory(string $query, int $limit = 8): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($query, $limit) {
            $customers = $this->searchCustomers($query, 5);
            $customerIds = collect($customers)->pluck('id')->filter()->values()->all();
            $customerNames = collect($customers)->pluck('name')->filter()->values()->all();

            $orders = [];
            $topProducts = [];
            $hasCustomerMatch = !empty($customerIds) || !empty($customerNames);

            if ($hasCustomerMatch && $this->hasTable('sales', 'sales_orders')) {
                $ordersQuery = DB::connection('sales')->table('sales_orders')
                    ->where(function ($q) use ($customerIds, $customerNames, $query) {
                        if (!empty($customerIds) && $this->hasColumn('sales', 'sales_orders', 'customer_id')) {
                            $q->orWhereIn('customer_id', $customerIds);
                        }
                        foreach ($customerNames as $name) {
                            $q->orWhere('customer_name', 'LIKE', '%' . $name . '%');
                        }
                        $q->orWhere('customer_name', 'LIKE', '%' . $query . '%');
                    })
                    ->orderByDesc($this->hasColumn('sales', 'sales_orders', 'created_at') ? 'created_at' : 'id')
                    ->limit($limit);

                $orders = $ordersQuery
                    ->get($this->existingColumns('sales', 'sales_orders', [
                        'id',
                        'order_number',
                        'customer_name',
                        'invoice_numbers',
                        'total_amount',
                        'status',
                        'remarks',
                        'created_at',
                    ]))
                    ->map(fn ($row) => (array) $row)
                    ->values()
                    ->all();
            }

            if ($hasCustomerMatch && $this->hasTable('sales', 'sales_order_items') && $this->hasTable('sales', 'sales_orders')) {
                $topProducts = DB::connection('sales')->table('sales_order_items as soi')
                    ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
                    ->where(function ($q) use ($customerIds, $customerNames, $query) {
                        if (!empty($customerIds) && $this->hasColumn('sales', 'sales_orders', 'customer_id')) {
                            $q->orWhereIn('so.customer_id', $customerIds);
                        }
                        foreach ($customerNames as $name) {
                            $q->orWhere('so.customer_name', 'LIKE', '%' . $name . '%');
                        }
                        $q->orWhere('so.customer_name', 'LIKE', '%' . $query . '%');
                    })
                    ->select('soi.product_code', 'soi.description', DB::raw('SUM(COALESCE(soi.quantity, 0)) as total_qty'), DB::raw('COUNT(DISTINCT so.id) as order_count'))
                    ->groupBy('soi.product_code', 'soi.description')
                    ->orderByDesc('total_qty')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->values()
                    ->all();
            }

            return [
                'customers' => $customers,
                'recent_orders' => $orders,
                'top_products' => $topProducts,
            ];
        });
    }

    public function getSupplierPurchaseActivity(string $query, int $limit = 8): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($query, $limit) {
            $suppliers = $this->searchSuppliers($query, 5);
            $supplierIds = collect($suppliers)->pluck('id')->filter()->values()->all();

            $orders = [];
            $topProducts = [];

            if (!empty($supplierIds) && $this->hasTable('purchase', 'purchase_orders')) {
                $orders = DB::connection('purchase')->table('purchase_orders')
                    ->whereIn('supplier_id', $supplierIds)
                    ->orderByDesc($this->hasColumn('purchase', 'purchase_orders', 'created_at') ? 'created_at' : 'id')
                    ->limit($limit)
                    ->get($this->existingColumns('purchase', 'purchase_orders', [
                        'id',
                        'po_number',
                        'receiving_number',
                        'supplier_invoice_number',
                        'supplier_id',
                        'date',
                        'expected_delivery_date',
                        'total_amount',
                        'actual_total_amount',
                        'status',
                        'currency',
                        'remarks',
                    ]))
                    ->map(fn ($row) => (array) $row)
                    ->values()
                    ->all();
            }

            if (!empty($supplierIds) && $this->hasTable('purchase', 'purchase_order_items') && $this->hasTable('purchase', 'purchase_orders')) {
                $topProducts = DB::connection('purchase')->table('purchase_order_items as poi')
                    ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                    ->whereIn('po.supplier_id', $supplierIds)
                    ->select('poi.product_code', 'poi.description', DB::raw('SUM(COALESCE(poi.quantity, 0)) as total_qty'), DB::raw('AVG(COALESCE(poi.unit_price, 0)) as average_unit_price'))
                    ->groupBy('poi.product_code', 'poi.description')
                    ->orderByDesc('total_qty')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->values()
                    ->all();
            }

            return [
                'suppliers' => $suppliers,
                'recent_purchase_orders' => $orders,
                'common_products' => $topProducts,
            ];
        });
    }

    public function getProductMovementReport(string $mode = 'fast', int $days = 30, int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);
        $days = max(1, min($days, 365));
        $mode = strtolower(trim($mode)) === 'slow' ? 'slow' : 'fast';

        return $this->guardedDataResponse(function () use ($mode, $days, $limit) {
            if (!$this->hasTable('ledger', 'product_ledgers')) {
                return ['error' => 'Product ledger table is not available.'];
            }

            $ledgerRows = DB::connection('ledger')->table('product_ledgers as pl')
                ->where('pl.date', '>=', now()->subDays($days)->toDateString())
                ->select(
                    'pl.product_id',
                    DB::raw('SUM(COALESCE(pl.quantity_out, 0)) as total_out'),
                    DB::raw('SUM(COALESCE(pl.quantity_in, 0)) as total_in'),
                    DB::raw('COUNT(*) as ledger_entries')
                )
                ->groupBy('pl.product_id')
                ->orderBy('total_out', $mode === 'slow' ? 'asc' : 'desc')
                ->limit($limit)
                ->get();

            $productMap = collect();
            if ($ledgerRows->isNotEmpty() && $this->hasTable('masterlist', 'products')) {
                $productMap = DB::connection('masterlist')->table('products')
                    ->whereIn('id', $ledgerRows->pluck('product_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all())
                    ->get(['id', 'product_code', 'description', 'category', 'application', 'on_hand'])
                    ->keyBy('id');
            }

            $rows = $ledgerRows->map(function ($row) use ($productMap) {
                $product = $productMap->get((int) $row->product_id);
                return [
                    'product_id' => (int) $row->product_id,
                    'product_code' => $product->product_code ?? null,
                    'description' => $product->description ?? null,
                    'category' => $product->category ?? null,
                    'application' => $product->application ?? null,
                    'on_hand' => $product->on_hand ?? null,
                    'total_out' => $row->total_out,
                    'total_in' => $row->total_in,
                    'ledger_entries' => $row->ledger_entries,
                ];
            })->values()->all();

            return [
                'mode' => $mode,
                'days' => $days,
                'products' => $rows,
            ];
        });
    }

    public function getBusinessSummary(int $days = 7): array
    {
        $days = max(1, min($days, 365));

        return $this->guardedDataResponse(function () use ($days) {
            $summary = [
                'days' => $days,
                'products' => $this->tableCount('masterlist', 'products'),
                'suppliers' => $this->tableCount('masterlist', 'suppliers'),
                'customers' => $this->tableCount('masterlist', 'customers'),
                'sales_orders' => $this->tableCount('sales', 'sales_orders'),
                'purchase_orders' => $this->tableCount('purchase', 'purchase_orders'),
                'archived_records' => $this->tableCount('ledger', 'archived_records'),
                'recent_audit_entries' => $this->recentCount('ledger', 'audit_trails', 'created_at', $days),
            ];

            if ($this->hasTable('masterlist', 'products')) {
                $summary['low_stock_products'] = DB::connection('masterlist')->table('products')
                    ->where(function ($q) {
                        if ($this->hasColumn('masterlist', 'products', 'Re_order_level')) {
                            $q->where(function ($nested) {
                                $nested->whereNotNull('Re_order_level')
                                    ->whereColumn('on_hand', '<=', 'Re_order_level');
                            })->orWhere('on_hand', '<', 20);
                        } else {
                            $q->where('on_hand', '<', 20);
                        }
                    })
                    ->count();
            }

            if ($this->hasTable('sales', 'sales_orders')) {
                $summary['recent_sales_orders'] = $this->recentCount('sales', 'sales_orders', 'created_at', $days);
                $summary['recent_sales_total'] = (float) DB::connection('sales')->table('sales_orders')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->sum('total_amount');
            }

            if ($this->hasTable('purchase', 'purchase_orders')) {
                $summary['recent_purchase_orders'] = $this->recentCount('purchase', 'purchase_orders', 'created_at', $days);
                $summary['recent_purchase_total'] = (float) DB::connection('purchase')->table('purchase_orders')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->sum('total_amount');
            }

            return $summary;
        });
    }

    public function getDataQualityReport(int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($limit) {
            $report = [];

            if ($this->hasTable('masterlist', 'products')) {
                $products = DB::connection('masterlist')->table('products');
                $report['products'] = [
                    'missing_product_code' => (clone $products)->where(function ($q) {
                        $q->whereNull('product_code')->orWhere('product_code', '');
                    })->count(),
                    'missing_part_number' => (clone $products)->where(function ($q) {
                        $q->whereNull('part_number')->orWhere('part_number', '');
                    })->count(),
                    'missing_price' => (clone $products)->where(function ($q) {
                        $q->whereNull('selling_price')->orWhere('selling_price', '<=', 0);
                    })->count(),
                    'duplicate_product_codes' => $this->duplicateValues('masterlist', 'products', 'product_code', $limit),
                    'duplicate_part_numbers' => $this->duplicateValues('masterlist', 'products', 'part_number', $limit),
                ];
            }

            if ($this->hasTable('masterlist', 'suppliers')) {
                $suppliers = DB::connection('masterlist')->table('suppliers');
                $report['suppliers'] = [
                    'missing_contact_number' => (clone $suppliers)->where(function ($q) {
                        $q->whereNull('contact_number')->orWhere('contact_number', '');
                    })->count(),
                    'missing_email' => (clone $suppliers)->where(function ($q) {
                        $q->whereNull('email')->orWhere('email', '');
                    })->count(),
                ];
            }

            if ($this->hasTable('masterlist', 'customers')) {
                $customers = DB::connection('masterlist')->table('customers');
                $report['customers'] = [
                    'missing_contact_number' => (clone $customers)->where(function ($q) {
                        $q->whereNull('contact_number')->orWhere('contact_number', '');
                    })->count(),
                    'missing_terms' => (clone $customers)->where(function ($q) {
                        $q->whereNull('terms')->orWhere('terms', '');
                    })->count(),
                    'duplicate_names' => $this->duplicateValues('masterlist', 'customers', 'name', $limit),
                ];
            }

            return $report;
        });
    }

    public function searchAuditTrail(string $query = '', int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($query, $limit) {
            if (!$this->hasTable('ledger', 'audit_trails')) {
                return ['error' => 'Audit trail table is not available.'];
            }

            return DB::connection('ledger')->table('audit_trails')
                ->when(trim($query) !== '', function ($builder) use ($query) {
                    $builder->where(function ($q) use ($query) {
                        $this->applyLikeSearch($q, [
                            'module',
                            'action',
                            'display_id',
                            'record_name',
                            'user_name',
                            'user_identifier',
                            'audit_data',
                        ], $query);
                    });
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get($this->existingColumns('ledger', 'audit_trails', [
                    'id',
                    'module',
                    'action',
                    'display_id',
                    'record_name',
                    'user_name',
                    'user_identifier',
                    'created_at',
                ]))
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }

    public function searchArchivedRecords(string $query = '', int $limit = 10): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($query, $limit) {
            if (!$this->hasTable('ledger', 'archived_records')) {
                return ['error' => 'Archived records table is not available.'];
            }

            return DB::connection('ledger')->table('archived_records')
                ->when(trim($query) !== '', function ($builder) use ($query) {
                    $builder->where(function ($q) use ($query) {
                        $this->applyLikeSearch($q, [
                            'module',
                            'display_id',
                            'data_name',
                            'deleted_by',
                            'restored_by',
                            'archived_data',
                        ], $query);
                    });
                })
                ->orderByDesc('deleted_at')
                ->limit($limit)
                ->get($this->existingColumns('ledger', 'archived_records', [
                    'id',
                    'module',
                    'display_id',
                    'data_name',
                    'deleted_by',
                    'deleted_at',
                    'expires_at',
                    'restored_at',
                    'restored_by',
                ]))
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }

    public function getPageNavigation(string $destination, string $search = ''): array
    {
        $baseUrl = url('/');
        $needle = strtolower(trim($destination));
        $search = trim($search);

        $pages = [
            'product' => ['/admin/masterlist/product', 'Product Master List', 'description'],
            'supplier' => ['/admin/masterlist/supplier', 'Supplier Master List', 'name'],
            'customer' => ['/admin/masterlist/customer', 'Customer Master List', 'name'],
            'forwarder' => ['/admin/masterlist/forwarder', 'Forwarder Master List', 'name'],
            'inventory adjustment' => ['/admin/inventory/adjustment', 'Inventory Adjustment', null],
            'junk' => ['/admin/inventory/sales-junk', 'Junk Items', null],
            'convert' => ['/admin/inventory/item-convert', 'Convert Products', null],
            'purchase note' => ['/admin/purchase/purchase-note', 'Purchase Note', null],
            'purchase order' => ['/admin/purchase/purchase-order', 'Purchase Order', null],
            'purchase return' => ['/admin/purchase/purchase-return', 'Purchase Return', null],
            'sales note' => ['/admin/sales/sales-note', 'Sales Note', null],
            'sales order' => ['/admin/sales/sales-order', 'Sales Order', null],
            'sales return' => ['/admin/sales/sales-return', 'Sales Return', null],
            'consignment' => ['/admin/sales/consignment-invoice', 'Consignment Invoice', null],
            'waybill' => ['/admin/sales/waybill', 'Waybill', null],
            'payment' => ['/admin/payments', 'Payments', null],
            'audit' => ['/admin/system-security/audit-trail', 'Audit Trail', 'name'],
            'archive' => ['/admin/system-security/archived', 'Archived Records', 'name'],
            'sales report' => ['/admin/reports/sales-report', 'Sales Reports', null],
            'inventory report' => ['/admin/reports/inventory-reports', 'Inventory Reports', null],
            'accounts receivable' => ['/admin/reports/accounts-receivable', 'Accounts Receivable Reports', null],
            'cost report' => ['/admin/reports/cost-report', 'Cost Reports', null],
        ];

        $selected = $pages['product'];
        foreach ($pages as $key => $page) {
            if (str_contains($needle, $key)) {
                $selected = $page;
                break;
            }
        }

        [$path, $label, $searchColumn] = $selected;
        $url = $baseUrl . $path;
        if ($search !== '' && $searchColumn) {
            $url .= '?search[' . rawurlencode($searchColumn) . ']=' . rawurlencode($search);
        }

        return [
            'label' => $label,
            'url' => $url,
            'redirect' => "[REDIRECT]({$url})",
        ];
    }

    protected function productColumns(): array
    {
        return [
            'id',
            'product_code',
            'part_number',
            'category',
            'specification',
            'description',
            'application',
            'position',
            'on_hand',
            'actual_qty',
            'Re_order_level',
            'selling_price',
            'cost',
            'price_online',
            'status',
            'Product_Picture',
            'created_at',
        ];
    }

    protected function findProductRows(string $query, int $limit = 10)
    {
        $limit = $this->safeLimit($limit);

        if (!$this->hasTable('masterlist', 'products')) {
            return collect();
        }

        return DB::connection('masterlist')->table('products')
            ->where(function ($q) use ($query) {
                $this->applyLikeSearch($q, [
                    'product_code',
                    'part_number',
                    'description',
                    'category',
                    'application',
                    'specification',
                    'position',
                ], $query);
            })
            ->limit($limit)
            ->get($this->existingColumns('masterlist', 'products', $this->productColumns()));
    }

    protected function attachSupplierNames(array $rows): array
    {
        $supplierIds = collect($rows)->pluck('supplier_id')->filter()->unique()->values()->all();
        if (empty($supplierIds) || !$this->hasTable('masterlist', 'suppliers')) {
            return $rows;
        }

        $suppliers = DB::connection('masterlist')->table('suppliers')
            ->whereIn('id', $supplierIds)
            ->pluck('name', 'id');

        return collect($rows)
            ->map(function ($row) use ($suppliers) {
                $row['supplier_name'] = $suppliers[$row['supplier_id'] ?? null] ?? ($row['supplier_name'] ?? null);
                return $row;
            })
            ->values()
            ->all();
    }

    protected function searchMasterlistTable(string $table, string $query, int $limit, array $selectColumns, array $searchColumns): array
    {
        $limit = $this->safeLimit($limit);

        return $this->guardedDataResponse(function () use ($table, $query, $limit, $selectColumns, $searchColumns) {
            if (!$this->hasTable('masterlist', $table)) {
                return ['error' => "{$table} table is not available."];
            }

            $builder = DB::connection('masterlist')->table($table);
            if (trim($query) !== '') {
                $builder->where(function ($q) use ($table, $searchColumns, $query) {
                    $this->applyLikeSearch($q, array_filter($searchColumns, fn ($column) => $this->hasColumn('masterlist', $table, $column)), $query);
                });
            }

            return $builder
                ->limit($limit)
                ->get($this->existingColumns('masterlist', $table, $selectColumns))
                ->map(fn ($row) => (array) $row)
                ->values()
                ->all();
        });
    }
    protected function guardedDataResponse(callable $callback): array
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    protected function applyLikeSearch($queryBuilder, array $columns, string $query): void
    {
        $query = trim($query);
        if ($query === '') {
            return;
        }

        foreach ($columns as $column) {
            $queryBuilder->orWhere($column, 'LIKE', '%' . $query . '%');
        }
    }

    protected function existingColumns(string $connection, string $table, array $columns): array
    {
        $existing = array_values(array_filter($columns, fn ($column) => $this->hasColumn($connection, $table, $column)));
        return $existing ?: ['*'];
    }

    protected function hasTable(string $connection, string $table): bool
    {
        try {
            return Schema::connection($connection)->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function hasColumn(string $connection, string $table, string $column): bool
    {
        try {
            return Schema::connection($connection)->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function safeLimit(int $limit): int
    {
        return max(1, min($limit, 25));
    }

    protected function tableCount(string $connection, string $table): int
    {
        if (!$this->hasTable($connection, $table)) {
            return 0;
        }

        return (int) DB::connection($connection)->table($table)->count();
    }

    protected function recentCount(string $connection, string $table, string $column, int $days): int
    {
        if (!$this->hasTable($connection, $table) || !$this->hasColumn($connection, $table, $column)) {
            return 0;
        }

        return (int) DB::connection($connection)->table($table)
            ->where($column, '>=', now()->subDays($days))
            ->count();
    }

    protected function duplicateValues(string $connection, string $table, string $column, int $limit): array
    {
        if (!$this->hasTable($connection, $table) || !$this->hasColumn($connection, $table, $column)) {
            return [];
        }

        return DB::connection($connection)->table($table)
            ->select($column, DB::raw('COUNT(*) as total'))
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->having('total', '>', 1)
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->values()
            ->all();
    }

    protected function getDatabaseSchema(): string
    {
        return Cache::remember('gemini_db_schema', 3600, function () {
            $databases = [
                'core4_system_proposal' => 'mysql',
                'core4_masterlist' => 'masterlist',
                'core4_ledger' => 'ledger',
                'core4_sales_order' => 'sales',
                'core4_purchase' => 'purchase',
                'core4_accounting' => 'accounting',
            ];
            $output = '';

            foreach ($databases as $dbName => $connection) {
                $output .= "=== Database: {$dbName} ===\n";
                try {
                    $tables = DB::connection($connection)->select(
                        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME",
                        [$dbName]
                    );
                } catch (\Exception $e) {
                    try {
                        $tables = DB::connection('mysql')->select(
                            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME",
                            [$dbName]
                        );
                    } catch (\Exception $e2) {
                        $output .= "  (unavailable: {$e2->getMessage()})\n\n";
                        continue;
                    }
                }

                foreach ($tables as $table) {
                    $tableName = $table->TABLE_NAME;
                    $output .= "Table: {$tableName}\n";
                    try {
                        $conn = $connection;
                        try {
                            $columns = DB::connection($conn)->select(
                                "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
                                [$dbName, $tableName]
                            );
                        } catch (\Exception $e) {
                            $columns = DB::connection('mysql')->select(
                                "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
                                [$dbName, $tableName]
                            );
                        }
                        foreach ($columns as $col) {
                            $pk = ($col->COLUMN_KEY === 'PRI') ? ' PK' : '';
                            $auto = str_contains($col->EXTRA ?? '', 'auto_increment') ? ' AUTO' : '';
                            $nullable = ($col->IS_NULLABLE === 'YES') ? '' : ' NOT NULL';
                            $output .= "  - {$col->COLUMN_NAME}: {$col->COLUMN_TYPE}{$nullable}{$pk}{$auto}\n";
                        }
                    } catch (\Exception $e) {
                        $output .= "  (columns unavailable)\n";
                    }
                }
                $output .= "\n";
            }

            return $output;
        });
    }

    protected function getNavigationContext(): string
    {
        $baseUrl = url('/');
        return
"**Dashboard** - [Go to Dashboard]({$baseUrl}/admin/dashboard)

**System Security:**
- User Management (USM) - [Manage Users]({$baseUrl}/admin/usm)
- Archived Users - [View Archived]({$baseUrl}/admin/system-security/archived)
- Data Sync - [Data Sync]({$baseUrl}/admin/system-security/datasync)
- Audit Trail - [Audit Trail]({$baseUrl}/admin/system-security/audit-trail)

**Master List:**
- Product Master List - [Products]({$baseUrl}/admin/masterlist/product)
- Supplier Master List - [Suppliers]({$baseUrl}/admin/masterlist/supplier)
- Customer Master List - [Customers]({$baseUrl}/admin/masterlist/customer)
- Forwarder Master List - [Forwarders]({$baseUrl}/admin/masterlist/forwarder)

**Inventory Management:**
- Inventory Adjustment - [Adjust Inventory]({$baseUrl}/admin/inventory/adjustment)
- Junk Items - [Junk Items]({$baseUrl}/admin/inventory/sales-junk)
- Convert Items - [Convert Products]({$baseUrl}/admin/inventory/item-convert)

**Purchasing:**
- Purchasing Note - [Purchase Notes]({$baseUrl}/admin/purchase/purchase-note)
- Purchase Order - [Purchase Orders]({$baseUrl}/admin/purchase/purchase-order)
- Purchase Return - [Purchase Returns]({$baseUrl}/admin/purchase/purchase-return)

**Sales:**
- Sales Note - [Sales Notes]({$baseUrl}/admin/sales/sales-note)
- Sales Order - [Sales Orders]({$baseUrl}/admin/sales/sales-order)
- Consignment Invoice - [Consignment Invoices]({$baseUrl}/admin/sales/consignment-invoice)
- Waybill - [Waybills]({$baseUrl}/admin/sales/waybill)
- Sales Return - [Sales Returns]({$baseUrl}/admin/sales/sales-return)

**Accounting:**
- Payments - [Payments]({$baseUrl}/admin/payments)
- Payable Cheque Voucher - [Payable Cheques]({$baseUrl}/admin/accounting/payable-cheque-voucher)
- Expense Cheque Voucher - [Expense Cheques]({$baseUrl}/admin/accounting/expense-cheque-voucher)

**Reports:**
- Sales Reports - [Sales Reports]({$baseUrl}/admin/reports/sales-report)
- Inventory Reports - [Inventory Reports]({$baseUrl}/admin/reports/inventory-reports)
- Accounts Receivable Reports - [AR Reports]({$baseUrl}/admin/reports/accounts-receivable)
- Cost Report - [Cost Reports]({$baseUrl}/admin/reports/cost-report)

**User:**
- Profile - [My Profile]({$baseUrl}/admin/profile)";
    }

    protected function incrementDailyCount(): void
    {
        $key = 'gemini_daily_count_' . date('Ymd');
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->endOfDay());
    }

    public function getDailyRemaining(): int
    {
        $key = 'gemini_daily_count_' . date('Ymd');
        $count = (int) Cache::get($key, 0);
        return max(0, $this->dailyLimit - $count);
    }

    public function getDailyLimit(): int
    {
        return $this->dailyLimit;
    }

    public function getResetTime(): string
    {
        return now()->endOfDay()->format('h:i A');
    }
}
